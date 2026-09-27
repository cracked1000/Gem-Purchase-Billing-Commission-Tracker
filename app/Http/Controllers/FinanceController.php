<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Seller;
use App\Models\SellerPayment;
use App\Services\BackupService;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function __construct(
        private FinanceService $finance,
        private BackupService $backups,
    ) {
    }

    /**
     * Finance index: search by seller name, phone, or bill/receipt number.
     *  - Name/phone match  -> every bill for that seller, with a running balance.
     *  - Bill number match -> that bill + its seller's balance.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $f = $this->finance;

        // Which seller ids should be shown?
        if ($search !== '') {
            $nameSellerIds = Seller::query()
                ->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%'])
                        ->orWhereRaw("LOWER(COALESCE(phone,'')) LIKE ?", ['%'.mb_strtolower($search).'%']);
                })
                ->pluck('id');

            $billSellerIds = Invoice::query()
                ->where('invoice_number', 'like', '%'.$search.'%')
                ->whereNotNull('seller_id')
                ->pluck('seller_id')
                ->unique();

            $sellerIds = $nameSellerIds->concat($billSellerIds)->unique()->values();

            $sellers = Seller::with(['invoices', 'payments'])
                ->whereIn('id', $sellerIds)
                ->orderBy('name')
                ->get();

            $matchingBills = Invoice::with('seller')
                ->where('invoice_number', 'like', '%'.$search.'%')
                ->orderByDesc('sold_at')
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        } else {
            $sellers = Seller::with(['invoices', 'payments'])
                ->orderBy('name')
                ->get();

            $matchingBills = collect();
        }

        // Compute an exact summary per seller + overall totals.
        $rows = $sellers->map(fn (Seller $seller) => [
            'seller'  => $seller,
            'summary' => $f->summaryFor($seller),
        ]);

        $overall = (object) [
            'sold'       => '0.00',
            'commission' => '0.00',
            'net'        => '0.00',
            'paid'       => '0.00',
            'outstanding'=> '0.00',
        ];
        foreach ($rows as $row) {
            $s = $row['summary'];
            $overall->sold        = $this->add($overall->sold, $s->sold);
            $overall->commission  = $this->add($overall->commission, $s->commission);
            $overall->net         = $this->add($overall->net, $s->net);
            $overall->paid        = $this->add($overall->paid, $s->paid);
            $overall->outstanding = $this->add($overall->outstanding, $s->outstanding);
        }

        return view('finance.index', compact('rows', 'overall', 'search', 'matchingBills'));
    }

    /** A single seller's full finance ledger: every bill + payment history. */
    public function show(Seller $seller): View
    {
        $seller->load([
            'invoices'     => fn ($q) => $q->latest('sold_at')->latest('id'),
            'payments'     => fn ($q) => $q->latest('paid_at')->latest('id'),
        ]);

        $summary = $this->finance->summaryFor($seller);

        return view('finance.show', compact('seller', 'summary'));
    }

    /** Record a payment against a seller. Takes a backup snapshot first. */
    public function storePayment(Request $request, Seller $seller): RedirectResponse
    {
        $data = $request->validate([
            'amount'     => ['required', 'numeric', 'min:0.01'],
            'paid_at'    => ['required', 'date'],
            'method'     => ['nullable', 'string', 'max:40'],
            'reference'  => ['nullable', 'string', 'max:120'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ]);

        $this->backups->snapshot('before-payment');

        SellerPayment::create([
            'seller_id' => $seller->id,
            'amount'    => $this->finance->normalizeMoney((string) $data['amount']),
            'paid_at'   => $data['paid_at'],
            'method'    => $data['method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes'     => $data['notes'] ?? null,
        ]);

        $summary = $this->finance->summaryFor($seller);

        return back()->with('success', sprintf(
            'Payment recorded: %s. Outstanding for %s is now %s.',
            $this->money($summary->paid),
            $seller->name,
            $this->money($summary->outstanding)
        ));
    }

    /** Reverse/remove a payment (snapshot first so nothing is lost). */
    public function destroyPayment(Request $request, SellerPayment $payment): RedirectResponse
    {
        $this->backups->snapshot('before-payment-delete');
        $seller = $payment->seller;
        $payment->delete();

        return back()->with('success', 'Payment removed. The seller balance has been recalculated.');
    }

    private function money(string $value): string
    {
        return 'LKR '.number_format((float) $value, 2);
    }

    private function add(string $a, string $b): string
    {
        return function_exists('bcadd')
            ? bcadd($a, $b, 2)
            : number_format((float) $a + (float) $b, 2, '.', '');
    }
}
