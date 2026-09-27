<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\WipeMode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class FinalizingController extends Controller
{
    public function __construct(private WipeMode $wipe)
    {
    }

    public function index(Request $request): View
    {
        $version = ($this->wipe->enabled() || $request->input('version') === 'buyer') ? 'buyer' : 'seller';
        $sort = $this->sort($request);
        [$days, $grand] = $this->finalizedData($sort);

        return view('finalizing.index', compact('days', 'grand', 'version', 'sort'));
    }

    public function pdf(Request $request): Response
    {
        $request->validate(['buyer' => ['nullable', 'boolean']]);
        $buyerVersion = $this->wipe->enabled() || $request->boolean('buyer');
        $sort = $this->sort($request);

        // The Finalizing report renders EVERY transaction (and every gem item)
        // into a single dompdf table. That needs far more than the default 128M
        // CLI memory limit once your data grows, so raise it just for this
        // request. Safe: only ever increases the limit, never lowers it.
        $current = $this->memoryLimitBytes();
        if ($current >= 0 && $current < 1024 * 1024 * 1024) {
            @ini_set('memory_limit', '1024M');
        }

        [$days, $grand] = $this->finalizedData($sort);

        $pdf = Pdf::loadView('finalizing.pdf', compact('days', 'grand', 'buyerVersion', 'sort'))
            ->setPaper('a4', 'landscape');

        $copy = $buyerVersion ? 'purchase' : 'settlement';
        $order = $sort === 'serial' ? 'serial' : 'day';

        return $pdf->download('finalized-transactions-'.$copy.'-'.$order.'-'.now()->format('Ymd-His').'.pdf');
    }

    private function sort(Request $request): string
    {
        return $request->input('sort') === 'serial' ? 'serial' : 'day';
    }

    /** Parse PHP's human-readable memory_limit into bytes (-1 = unlimited). */
    private function memoryLimitBytes(): int
    {
        $raw = (string) ini_get('memory_limit');
        if ($raw === '' || $raw === '-1') {
            return -1;
        }
        $value = (int) $raw;
        switch (strtoupper(substr($raw, -1))) {
            case 'G': $value *= 1024; // no break
            case 'M': $value *= 1024; // no break
            case 'K': $value *= 1024; // no break
        }
        return $value;
    }

    /** All active transactions, oldest day first. */
    private function finalizedData(string $sort = 'day'): array
    {
        $invoices = Invoice::with(['seller', 'items'])
            ->orderBy('sold_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $days = $invoices
            ->groupBy(fn (Invoice $invoice) => $invoice->sold_at->format('Y-m-d'))
            ->map(function ($invoices, string $date) use ($sort) {
                return (object) [
                    'date' => \Carbon\Carbon::parse($date),
                    'invoices' => $invoices,
                    'lines' => $this->dayLines($invoices, $sort),
                    'receipts' => $invoices->count(),
                    'pieces' => (int) $invoices->sum('total_pieces'),
                    'carats' => (float) $invoices->flatMap(fn (Invoice $invoice) => $invoice->items)->sum('carats'),
                    'purchased' => (float) $invoices->sum('subtotal'),
                    'commission' => (float) $invoices->sum('commission_amount'),
                    'net' => (float) $invoices->sum('net_payable'),
                ];
            });

        $grand = (object) [
            'days' => $days->count(),
            'receipts' => $invoices->count(),
            'items' => $invoices->sum(fn (Invoice $invoice) => $invoice->items->count()),
            'pieces' => (int) $invoices->sum('total_pieces'),
            'carats' => (float) $invoices->flatMap(fn (Invoice $invoice) => $invoice->items)->sum('carats'),
            'purchased' => (float) $invoices->sum('subtotal'),
            'commission' => (float) $invoices->sum('commission_amount'),
            'net' => (float) $invoices->sum('net_payable'),
        ];

        return [$days, $grand];
    }

    /**
     * One visual row per gem.
     *
     * sort=day    — keep gems under their bill, in recorded order.
     * sort=serial — flatten the day and list 1, 2, 3… by serial.
     *
     * @param  Collection<int, Invoice>  $invoices
     * @return Collection<int, object>
     */
    private function dayLines(Collection $invoices, string $sort): Collection
    {
        $lines = collect();

        foreach ($invoices as $invoice) {
            $isFirst = true;
            foreach ($invoice->items as $item) {
                $lines->push((object) [
                    'invoice' => $invoice,
                    'item' => $item,
                    'first' => $isFirst,
                ]);
                $isFirst = false;
            }
        }

        if ($sort !== 'serial') {
            return $lines->values();
        }

        return $lines
            ->map(function (object $line) {
                $line->first = true; // seller/time on every row once bills are ungrouped

                return $line;
            })
            ->sort(function (object $a, object $b): int {
                return $this->compareSerial(
                    $a->item->serial_number,
                    $b->item->serial_number,
                    (int) $a->item->id,
                    (int) $b->item->id
                );
            })
            ->values();
    }

    /** Natural ascending compare: 1, 2, 10. Empty serials go last. */
    private function compareSerial(?string $a, ?string $b, int $aid, int $bid): int
    {
        $as = trim((string) ($a ?? ''));
        $bs = trim((string) ($b ?? ''));
        $aEmpty = $as === '';
        $bEmpty = $bs === '';

        if ($aEmpty || $bEmpty) {
            return $aEmpty === $bEmpty ? ($aid <=> $bid) : ($aEmpty ? 1 : -1);
        }

        $cmp = strnatcasecmp($as, $bs);

        return $cmp !== 0 ? $cmp : ($aid <=> $bid);
    }
}
