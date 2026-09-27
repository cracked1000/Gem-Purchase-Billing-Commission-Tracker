<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Seller;
use App\Services\WipeMode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private WipeMode $wipe)
    {
    }

    /** Simple searchable on-screen ledger; PDF reports have dedicated actions. */
    public function index(Request $request): View
    {
        [$invoices, $totals, $filters] = $this->reportData($request);
        $sellers = Seller::orderBy('name')->get(['id', 'name']);

        return view('reports.index', compact('invoices', 'totals', 'filters', 'sellers'));
    }

    /** Backwards-compatible filtered/range report. */
    public function pdf(Request $request): Response
    {
        [$invoices, $totals, $filters] = $this->reportData($request, true);

        return $this->renderPdf(
            $request,
            $invoices,
            $totals,
            $filters,
            'Purchase Report',
            $this->periodLabel($filters),
            'filtered'
        );
    }

    /** Every transaction completed on one selected calendar day. */
    public function dailyPdf(Request $request): Response
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'order' => ['nullable', 'in:seller,fifo'],
        ]);
        $filters = ['from' => $validated['date'], 'to' => $validated['date']];
        $orderMode = $this->orderMode($request);
        [$invoices, $totals] = $this->dataForFilters($filters, true, $orderMode);

        return $this->renderPdf(
            $request,
            $invoices,
            $totals,
            $filters,
            'Daily Purchase Report',
            \Carbon\Carbon::parse($validated['date'])->format('d F Y'),
            'daily-'.$validated['date'],
            $orderMode
        );
    }

    /** Complete register containing every active bill in the system. */
    public function allPdf(Request $request): Response
    {
        $request->validate(['order' => ['nullable', 'in:seller,fifo']]);
        $orderMode = $this->orderMode($request);
        [$invoices, $totals] = $this->dataForFilters([], true, $orderMode);

        return $this->renderPdf(
            $request,
            $invoices,
            $totals,
            [],
            'Complete Purchase Register',
            'All recorded transactions',
            'all-bills',
            $orderMode
        );
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        [$invoices] = $this->reportData($request);

        $restricted = $this->wipe->enabled();
        $headers = $restricted
            ? ['Receipt number', 'Date (SLT)', 'Seller', 'Phone', 'Pieces', 'Purchased (LKR)', 'Status']
            : ['Receipt number', 'Date (SLT)', 'Seller', 'Phone', 'Pieces', 'Purchased (LKR)', 'Commission %', 'Commission (LKR)', 'Net payable (LKR)', 'Status'];

        return response()->streamDownload(function () use ($invoices, $headers, $restricted) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($invoices as $invoice) {
                $row = [
                    $invoice->invoice_number,
                    $invoice->sold_at->format('Y-m-d'),
                    $invoice->seller->name ?? '',
                    $invoice->seller->phone ?? '',
                    $invoice->total_pieces,
                    number_format((float) $invoice->subtotal, 2, '.', ''),
                ];
                if (! $restricted) {
                    $row[] = number_format((float) $invoice->commission_percent, 2, '.', '');
                    $row[] = number_format((float) $invoice->commission_amount, 2, '.', '');
                    $row[] = number_format((float) $invoice->net_payable, 2, '.', '');
                }
                $row[] = $invoice->status;
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'gemdesk-transactions-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array{0: Collection, 1: object, 2: array} */
    private function reportData(Request $request, bool $withItems = false): array
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'seller_id' => ['nullable', 'exists:sellers,id'],
            'buyer' => ['nullable', 'boolean'],
        ]);
        unset($filters['buyer']);

        [$invoices, $totals] = $this->dataForFilters($filters, $withItems);

        return [$invoices, $totals, $filters];
    }

    /** @return array{0: Collection, 1: object} */
    private function dataForFilters(array $filters, bool $withItems, string $orderMode = 'screen'): array
    {
        $relations = $withItems ? ['seller', 'items'] : ['seller'];
        $query = Invoice::with($relations);

        if ($orderMode === 'fifo') {
            // First recorded transaction first; ID is a stable tie-breaker.
            $query->orderBy('created_at')->orderBy('id');
        } else {
            $query->orderByDesc('sold_at')->orderByDesc('id');
        }

        if (! empty($filters['from'])) {
            $query->whereDate('sold_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('sold_at', '<=', $filters['to']);
        }
        if (! empty($filters['seller_id'])) {
            $query->where('seller_id', $filters['seller_id']);
        }

        $invoices = $query->get();

        if ($orderMode === 'seller') {
            $invoices = $invoices->sortBy(function (Invoice $invoice): string {
                $seller = mb_strtolower($invoice->seller->name ?? '');
                $created = optional($invoice->created_at)->format('YmdHis') ?? '';

                return $seller.'|'.$created.'|'.str_pad((string) $invoice->id, 12, '0', STR_PAD_LEFT);
            }, SORT_NATURAL | SORT_FLAG_CASE)->values();
        }

        $totals = (object) [
            'volume' => (float) $invoices->sum('subtotal'),
            'commission' => (float) $invoices->sum('commission_amount'),
            'net' => (float) $invoices->sum('net_payable'),
            'pieces' => (int) $invoices->sum('total_pieces'),
            'count' => $invoices->count(),
        ];

        return [$invoices, $totals];
    }

    private function renderPdf(
        Request $request,
        Collection $invoices,
        object $totals,
        array $filters,
        string $reportTitle,
        string $periodLabel,
        string $filenamePrefix,
        string $orderMode = 'fifo',
    ): Response {
        $buyerVersion = $this->wipe->enabled() || $request->boolean('buyer');
        $pdf = Pdf::loadView('reports.pdf', compact(
            'invoices', 'totals', 'filters', 'buyerVersion', 'reportTitle', 'periodLabel', 'orderMode'
        ))->setPaper('a4');

        // Neutral filenames: the PDF itself never says buyer/seller version.
        $copy = $buyerVersion ? 'purchase' : 'settlement';
        $filename = 'gemdesk-'.$filenamePrefix.'-'.$copy.'-'.now()->format('Ymd-His').'.pdf';

        return $pdf->download($filename);
    }

    private function orderMode(Request $request): string
    {
        return $request->input('order') === 'fifo' ? 'fifo' : 'seller';
    }

    private function periodLabel(array $filters): string
    {
        if (! empty($filters['from']) && ! empty($filters['to'])) {
            return \Carbon\Carbon::parse($filters['from'])->format('d M Y').' to '.\Carbon\Carbon::parse($filters['to'])->format('d M Y');
        }
        if (! empty($filters['from'])) {
            return 'From '.\Carbon\Carbon::parse($filters['from'])->format('d M Y');
        }
        if (! empty($filters['to'])) {
            return 'Up to '.\Carbon\Carbon::parse($filters['to'])->format('d M Y');
        }

        return 'All recorded transactions';
    }
}
