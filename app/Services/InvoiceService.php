<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /**
     * Create an invoice together with its items in one transaction.
     * All totals are computed server-side (never trusted from the browser).
     */
    public function create(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::create([
                'invoice_number'     => null, // assigned right after we have the id
                'seller_id'          => $data['seller_id'],
                'sold_at'            => $data['sold_at'],
                'commission_percent' => $data['commission_percent'],
                'status'             => $data['status'] ?? Invoice::STATUS_PENDING,
                'notes'              => $data['notes'] ?? null,
            ]);

            $this->syncItems($invoice, $data['items']);
            $this->recomputeTotals($invoice);

            // Allocate a concurrency-safe daily sequence. Counting invoices is
            // unsafe because simultaneous requests can receive the same number,
            // and deleted invoices must never cause old numbers to be reused.
            $soldOn = \Carbon\Carbon::parse($data['sold_at'])->format('Y-m-d');
            $datePrefix = \Carbon\Carbon::parse($data['sold_at'])->format('dmY');

            DB::table('invoice_sequences')->insertOrIgnore([
                'sold_on' => $soldOn,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $counter = DB::table('invoice_sequences')
                ->where('sold_on', $soldOn)
                ->lockForUpdate()
                ->first();

            // This also makes upgrades safe when older bills already exist but
            // the sequence table has only just been introduced.
            $existingMax = Invoice::withTrashed()
                ->where('invoice_number', 'like', $datePrefix.'%')
                ->pluck('invoice_number')
                ->map(fn ($number) => (int) substr((string) $number, strlen($datePrefix)))
                ->max() ?? 0;
            $next = max((int) $counter->last_number, (int) $existingMax) + 1;

            DB::table('invoice_sequences')->where('sold_on', $soldOn)->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

            $invoice->invoice_number = $datePrefix.str_pad((string) $next, 2, '0', STR_PAD_LEFT);
            $invoice->save();

            return $invoice->fresh(['items', 'seller']);
        }, 3);
    }

    /** Update an invoice and fully re-sync its items. */
    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice->fill([
                'seller_id'          => $data['seller_id'],
                'sold_at'            => $data['sold_at'],
                'commission_percent' => $data['commission_percent'],
                'notes'              => $data['notes'] ?? null,
            ]);
            $invoice->save();

            $this->syncItems($invoice, $data['items']);
            $this->recomputeTotals($invoice);

            return $invoice->fresh(['items', 'seller']);
        }, 3);
    }

    /** Replace all items of an invoice with the given rows. */
    private function syncItems(Invoice $invoice, array $items): void
    {
        $invoice->items()->delete();

        $rows = [];
        foreach ($items as $item) {
            $pieces = (int) $item['pieces'];
            $carats = isset($item['carats']) && $item['carats'] !== '' ? (string) $item['carats'] : null;
            $hasCarats = $carats !== null && (float) $carats > 0;
            $basis = $hasCarats ? $carats : (string) $pieces;
            $mode = ($item['price_mode'] ?? 'rate') === 'total' ? 'total' : 'rate';
            $rateProvided = isset($item['unit_price']) && $item['unit_price'] !== '';
            $totalProvided = isset($item['total_price']) && $item['total_price'] !== '';

            // Either field can drive the calculation. The last field edited in
            // the browser sets price_mode; the server recalculates the other
            // value and never trusts a browser-supplied line amount.
            if ($mode === 'total' && $totalProvided) {
                $amount = $this->normalizeMoney((string) $item['total_price']);
                $unitPrice = $this->divideMoney($amount, $basis);
            } elseif ($rateProvided) {
                $mode = 'rate';
                $unitPrice = $this->normalizeMoney((string) $item['unit_price']);
                $amount = $this->multiplyMoney($basis, $unitPrice);
            } else {
                $mode = 'total';
                $amount = $this->normalizeMoney((string) ($item['total_price'] ?? 0));
                $unitPrice = $this->divideMoney($amount, $basis);
            }

            $actions = collect($item['verification_actions'] ?? [])
                ->filter(fn ($action) => in_array($action, ['recheck', 'repolish', 'recut'], true))
                ->unique()->values()->all();
            $stoneNotes = mb_strtoupper(trim((string) ($item['stone_notes'] ?? '')));

            $rows[] = [
                'serial_number' => ($serial = mb_strtoupper(trim((string) ($item['serial_number'] ?? '')))) !== '' ? $serial : null,
                'price_mode' => $mode,
                'verification_actions' => $actions ?: null,
                'stone_notes' => $stoneNotes !== '' ? $stoneNotes : null,
                'description' => mb_strtoupper(trim($item['description'])),
                'carats'      => $carats,
                'pieces'      => $pieces,
                'unit_price'  => $unitPrice,
                'amount'      => $amount,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }

        $invoice->items()->createMany($rows);
    }

    /** Recalculate subtotal / commission / net payable / piece count. */
    public function recomputeTotals(Invoice $invoice): void
    {
        $items = $invoice->items()->get(['pieces', 'amount']);

        $subtotal = '0.00';
        foreach ($items as $item) {
            $subtotal = function_exists('bcadd')
                ? bcadd($subtotal, (string) $item->amount, 2)
                : number_format((float) $subtotal + (float) $item->amount, 2, '.', '');
        }
        $pieces = (int) $items->sum('pieces');
        $commission = $this->multiplyMoney($subtotal, (string) $invoice->commission_percent, 100);
        $net = function_exists('bcsub')
            ? bcsub($subtotal, $commission, 2)
            : number_format((float) $subtotal - (float) $commission, 2, '.', '');

        $invoice->forceFill([
            'subtotal'          => $subtotal,
            'commission_amount' => $commission,
            'net_payable'       => $net,
            'total_pieces'      => $pieces,
        ])->save();
    }

    private function normalizeMoney(string $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }

    private function divideMoney(string $amount, string $basis): string
    {
        if ((float) $basis <= 0) {
            return '0.00';
        }

        if (function_exists('bcdiv')) {
            $value = bcdiv($amount, $basis, 4);

            return bcdiv(bcadd($value, '0.005', 3), '1', 2);
        }

        return number_format(round((float) $amount / (float) $basis, 2), 2, '.', '');
    }

    private function multiplyMoney(string $left, string $right, int $divisor = 1): string
    {
        if (function_exists('bcmul')) {
            $value = bcmul($left, $right, 4);
            if ($divisor !== 1) {
                $value = bcdiv($value, (string) $divisor, 4);
            }

            // All validated business amounts are non-negative.
            return bcdiv(bcadd($value, '0.005', 3), '1', 2);
        }

        return number_format(round(((float) $left * (float) $right) / $divisor, 2), 2, '.', '');
    }
}
