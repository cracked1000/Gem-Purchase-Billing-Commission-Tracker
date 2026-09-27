<?php

namespace App\Services;

use App\Models\InvoiceItem;
use Illuminate\Support\Collection;

class GemRegisterService
{
    public const DEFAULT_COLUMNS = [
        'serial', 'description', 'receipt', 'purchase_date', 'seller',
        'pieces', 'weight', 'rate', 'rate_basis', 'bought_price',
        'verification', 'stone_note',
    ];

    public function availableColumns(): array
    {
        return [
            'serial' => ['label' => 'Serial', 'type' => 'text'],
            'description' => ['label' => 'Gem / Lot', 'type' => 'text'],
            'receipt' => ['label' => 'Receipt #', 'type' => 'text'],
            'purchase_date' => ['label' => 'Purchase Date', 'type' => 'date'],
            'recorded_time' => ['label' => 'Recorded Time', 'type' => 'text'],
            'seller' => ['label' => 'Seller', 'type' => 'text'],
            'seller_phone' => ['label' => 'Seller Phone', 'type' => 'text'],
            'pieces' => ['label' => 'Pieces', 'type' => 'integer'],
            'weight' => ['label' => 'Weight (ct)', 'type' => 'decimal'],
            'rate' => ['label' => 'Rate (LKR)', 'type' => 'money'],
            'rate_basis' => ['label' => 'Rate Basis', 'type' => 'text'],
            'bought_price' => ['label' => 'Bought Price (LKR)', 'type' => 'money'],
            'verification' => ['label' => 'Verification', 'type' => 'text'],
            'stone_note' => ['label' => 'Stone Note', 'type' => 'text'],
            'bill_note' => ['label' => 'Bill Note', 'type' => 'text'],
            'payout_status' => ['label' => 'Payout Status', 'type' => 'text'],
        ];
    }

    public function normalizeColumns(mixed $requested): array
    {
        $available = array_keys($this->availableColumns());
        $requested = is_array($requested) ? $requested : self::DEFAULT_COLUMNS;
        $selected = array_values(array_unique(array_intersect($available, $requested)));

        return $selected ?: self::DEFAULT_COLUMNS;
    }

    /** @return array{columns: array, rows: Collection, totals: object} */
    public function data(array $selected): array
    {
        $definitions = $this->availableColumns();
        $columns = collect($selected)->mapWithKeys(fn ($key) => [$key => $definitions[$key]])->all();

        $items = InvoiceItem::with(['invoice.seller'])
            ->whereHas('invoice')
            ->get()
            ->sort(function (InvoiceItem $a, InvoiceItem $b): int {
                $aSerial = $a->serial_number ?: '~~~~~~~~';
                $bSerial = $b->serial_number ?: '~~~~~~~~';
                $serial = strnatcasecmp($aSerial, $bSerial);

                return $serial !== 0 ? $serial : ($a->id <=> $b->id);
            })->values();

        $rows = $items->map(function (InvoiceItem $item) use ($selected) {
            $invoice = $item->invoice;
            $values = [
                'serial' => $item->serial_number ?: '—',
                'description' => $item->description,
                'receipt' => $invoice->invoice_number,
                'purchase_date' => $invoice->sold_at->format('Y-m-d'),
                'recorded_time' => $invoice->created_at->format('Y-m-d H:i:s'),
                'seller' => $invoice->seller->name ?? '—',
                'seller_phone' => $invoice->seller->phone ?? '—',
                'pieces' => (int) $item->pieces,
                'weight' => $item->carats !== null ? (float) $item->carats : null,
                'rate' => (float) $item->unit_price,
                'rate_basis' => $item->carats !== null && (float) $item->carats > 0 ? 'PER CT' : 'PER PC',
                'bought_price' => (float) $item->amount,
                'verification' => ! empty($item->verification_actions)
                    ? strtoupper(implode(' + ', $item->verification_actions))
                    : 'NONE',
                'stone_note' => $item->stone_notes ?: '—',
                'bill_note' => $invoice->notes ?: '—',
                'payout_status' => strtoupper($invoice->status),
            ];

            return collect($selected)->mapWithKeys(fn ($key) => [$key => $values[$key]])->all();
        });

        $totals = (object) [
            'gems' => $items->count(),
            'pieces' => (int) $items->sum('pieces'),
            'weight' => (float) $items->sum('carats'),
            'bought_price' => (float) $items->sum('amount'),
        ];

        return compact('columns', 'rows', 'totals');
    }
}
