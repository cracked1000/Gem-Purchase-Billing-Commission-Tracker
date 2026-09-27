<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSellerRequest;
use App\Http\Requests\UpdateSellerRequest;
use App\Models\Invoice;
use App\Models\Seller;
use App\Services\BackupService;
use App\Services\FinanceService;
use App\Services\ReceiptStorage;
use App\Services\SellerDuplicateService;
use App\Support\WaNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SellerController extends Controller
{
    public function __construct(
        private BackupService $backups,
        private SellerDuplicateService $duplicates,
        private FinanceService $finance,
    ) {
    }

    public function index(): View
    {
        $search = trim((string) request()->query('search', ''));

        $sellers = Seller::withCount('invoices')
            ->withSum('invoices', 'subtotal')
            ->withSum('invoices', 'net_payable')
            ->when($search !== '', function ($query) use ($search) {
                // Server-side search across EVERY seller in the database,
                // not just the rows on the current page.
                $like = '%'.mb_strtolower($search).'%';
                $query->where(function ($sub) use ($like) {
                    $sub->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw("LOWER(COALESCE(phone, '')) LIKE ?", [$like]);
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $duplicateGroups = $this->duplicates->scan();
        $duplicateGroupCount = count($duplicateGroups);
        $duplicateSellersCount = collect($duplicateGroups)->sum(fn (array $group) => count($group['sellers']));

        return view('sellers.index', compact(
            'sellers',
            'search',
            'duplicateGroupCount',
            'duplicateSellersCount'
        ));
    }

    public function create(): View
    {
        return view('sellers.create', ['returnUrl' => request('return_url')]);
    }

    public function store(StoreSellerRequest $request): RedirectResponse
    {
        $seller = Seller::create($request->validated());
        try {
            $this->backups->dailySnapshot();
        } catch (\Throwable $e) {
            Log::warning('Automatic backup failed after seller creation', ['error' => $e->getMessage()]);
        }

        if ($request->filled('return_url')) {
            // Never redirect to an arbitrary external URL supplied by a form.
            $returnUrl = (string) $request->input('return_url');
            $app = rtrim(url('/'), '/');
            if (str_starts_with($returnUrl, $app.'/')) {
                $separator = str_contains($returnUrl, '?') ? '&' : '?';
                return redirect($returnUrl.$separator.'seller_id='.$seller->id)
                    ->with('success', "Seller “{$seller->name}” added and selected.");
            }
        }

        return redirect()->route('sellers.show', $seller)
            ->with('success', "Seller “{$seller->name}” added.");
    }

    public function show(Seller $seller): View
    {
        $seller->load(['invoices' => fn ($q) => $q->latest('sold_at')->latest('id')]);

        $totals = [
            'volume'     => (float) $seller->invoices->sum('subtotal'),
            'commission' => (float) $seller->invoices->sum('commission_amount'),
            'net'        => (float) $seller->invoices->sum('net_payable'),
            'pieces'     => (int) $seller->invoices->sum('total_pieces'),
            'pending'    => (float) $seller->invoices->where('status', 'pending')->sum('net_payable'),
        ];

        // Same number = same person: pull in every profile that shares this
        // seller's phone number plus every bill recorded under that number.
        $phoneKey = WaNumber::normalize($seller->phone);
        $linkedSellers = collect();
        $numberInvoices = collect();
        $numberTotals = null;

        if ($phoneKey !== null) {
            $linkedSellers = Seller::query()
                ->withCount('invoices')
                ->whereNotNull('phone')
                ->get()
                ->filter(fn (Seller $profile) => WaNumber::normalize($profile->phone) === $phoneKey)
                ->values();

            $numberInvoices = Invoice::with('seller')
                ->whereIn('seller_id', $linkedSellers->pluck('id')->all())
                ->latest('sold_at')
                ->latest('id')
                ->get();

            $numberTotals = [
                'bills'  => $numberInvoices->count(),
                'volume' => (float) $numberInvoices->sum('subtotal'),
                'pieces' => (int) $numberInvoices->sum('total_pieces'),
            ];
        }

        $finance = $this->finance->summaryFor($seller);

        return view('sellers.show', compact('seller', 'totals', 'linkedSellers', 'numberInvoices', 'numberTotals', 'finance'));
    }

    public function edit(Seller $seller): View
    {
        return view('sellers.edit', compact('seller'));
    }

    public function update(UpdateSellerRequest $request, Seller $seller): RedirectResponse
    {
        $this->backups->snapshot('before-seller-update');
        $invoices = $seller->invoices()->with('items')->get();
        $oldFilenames = $invoices->mapWithKeys(fn ($invoice) => [$invoice->id => ReceiptStorage::filenameFor($invoice)]);

        $seller->update($request->validated());
        $seller->refresh();

        foreach ($invoices as $invoice) {
            $invoice->setRelation('seller', $seller);
            try {
                $result = ReceiptStorage::save($invoice);
                $old = $oldFilenames->get($invoice->id);
                if ($result && $old && $old !== $result['filename']) {
                    ReceiptStorage::deleteFilename($old);
                }
            } catch (\Throwable $e) {
                Log::warning('Could not refresh receipt after seller update', ['invoice' => $invoice->invoice_number]);
            }
        }

        return redirect()->route('sellers.show', $seller)
            ->with('success', 'Seller updated.');
    }

    public function destroy(Seller $seller): RedirectResponse
    {
        if ($seller->invoices()->withTrashed()->exists()) {
            return redirect()->route('sellers.show', $seller)
                ->with('error', 'This seller has bills recorded and must be kept so historical records remain complete. Use the Duplicates panel to merge profiles instead.');
        }

        $this->backups->snapshot('before-seller-archive');
        $seller->delete();

        return redirect()->route('sellers.index')
            ->with('success', "Seller “{$seller->name}” archived safely.");
    }
}
