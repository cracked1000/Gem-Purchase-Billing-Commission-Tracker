<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Seller;
use App\Models\SellerPayment;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Payin — sellers we have paid (fully or partially).
 *
 * Finance Department lists everyone. This panel is the paid-so-far book:
 * remaining to pay, commission cuts, total sold, and every bill/gem they sold.
 */
class PayinController extends Controller
{
    public function __construct(private FinanceService $finance)
    {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $filter = (string) $request->query('status', 'all');
        if (! in_array($filter, ['all', 'partial', 'settled'], true)) {
            $filter = 'all';
        }

        $paidSellerIds = SellerPayment::query()->select('seller_id')->distinct()->pluck('seller_id');

        $query = Seller::with(['invoices', 'payments'])->whereIn('id', $paidSellerIds);

        if ($search !== '') {
            $nameIds = Seller::query()
                ->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%'])
                        ->orWhereRaw("LOWER(COALESCE(phone,'')) LIKE ?", ['%'.mb_strtolower($search).'%']);
                })
                ->pluck('id');

            $billIds = Invoice::query()
                ->where('invoice_number', 'like', '%'.$search.'%')
                ->whereNotNull('seller_id')
                ->pluck('seller_id')
                ->unique();

            $query->whereIn('id', $nameIds->concat($billIds)->unique()->values());
        }

        $sellers = $query->orderBy('name')->get();

        $rows = $sellers->map(fn (Seller $seller) => [
            'seller'  => $seller,
            'summary' => $this->finance->summaryFor($seller),
        ]);

        $counts = [
            'all'     => $rows->count(),
            'partial' => $rows->filter(fn ($r) => $r['summary']->status === 'partial')->count(),
            'settled' => $rows->filter(fn ($r) => in_array($r['summary']->status, ['settled', 'overpaid'], true))->count(),
        ];

        if ($filter === 'partial') {
            $rows = $rows->filter(fn ($r) => $r['summary']->status === 'partial')->values();
        } elseif ($filter === 'settled') {
            $rows = $rows->filter(fn ($r) => in_array($r['summary']->status, ['settled', 'overpaid'], true))->values();
        }

        $rows = $rows
            ->sortByDesc(function (array $row) {
                $at = $row['summary']->last_paid_at;

                return $at ? strtotime((string) $at) : 0;
            })
            ->values();

        $overall = (object) [
            'sold'        => '0.00',
            'commission'  => '0.00',
            'net'         => '0.00',
            'paid'        => '0.00',
            'outstanding' => '0.00',
        ];
        foreach ($rows as $row) {
            $s = $row['summary'];
            $overall->sold        = $this->add($overall->sold, $s->sold);
            $overall->commission  = $this->add($overall->commission, $s->commission);
            $overall->net         = $this->add($overall->net, $s->net);
            $overall->paid        = $this->add($overall->paid, $s->paid);
            $overall->outstanding = $this->add($overall->outstanding, $s->outstanding);
        }

        return view('payin.index', compact('rows', 'overall', 'search', 'filter', 'counts'));
    }

    public function show(Seller $seller): View
    {
        $seller->load([
            'invoices' => fn ($q) => $q->with('items')->latest('sold_at')->latest('id'),
            'payments' => fn ($q) => $q->latest('paid_at')->latest('id'),
        ]);

        $summary = $this->finance->summaryFor($seller);

        return view('payin.show', compact('seller', 'summary'));
    }

    private function add(string $a, string $b): string
    {
        return function_exists('bcadd')
            ? bcadd($a, $b, 2)
            : number_format((float) $a + (float) $b, 2, '.', '');
    }
}
