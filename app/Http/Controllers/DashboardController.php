<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Seller;
use App\Models\SellerPayment;
use Carbon\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'volume'          => (float) Invoice::sum('subtotal'),
            'commission'      => (float) Invoice::sum('commission_amount'),
            'commission_paid' => (float) Invoice::where('status', Invoice::STATUS_PAID)->sum('commission_amount'),
            'payable'         => (float) Invoice::where('status', Invoice::STATUS_PAID)->sum('net_payable'),
            'pending_amount'  => (float) Invoice::where('status', Invoice::STATUS_PENDING)->sum('net_payable'),
            'pending_count'   => Invoice::where('status', Invoice::STATUS_PENDING)->count(),
            'pieces'          => (int) Invoice::sum('total_pieces'),
            'invoices'        => Invoice::count(),
            'sellers'         => Seller::count(),
        ];


        // Monthly purchase volume for the last 12 months (built in PHP so it works
        // identically on PostgreSQL and the SQLite test database).
        $start = Carbon::now()->startOfMonth()->subMonths(11);
        $byMonth = [];
        for ($i = 0; $i < 12; $i++) {
            $byMonth[$start->copy()->addMonths($i)->format('Y-m')] = 0.0;
        }
        Invoice::where('sold_at', '>=', $start->toDateString())
            ->get(['sold_at', 'subtotal'])
            ->each(function (Invoice $invoice) use (&$byMonth) {
                $key = $invoice->sold_at->format('Y-m');
                if (array_key_exists($key, $byMonth)) {
                    $byMonth[$key] += (float) $invoice->subtotal;
                }
            });

        $monthlyLabels = [];
        $monthlyValues = [];
        foreach ($byMonth as $ym => $total) {
            $monthlyLabels[] = Carbon::createFromFormat('Y-m', $ym)->format('M Y');
            $monthlyValues[] = round($total, 2);
        }

        $topSellers = Seller::withSum('invoices', 'subtotal')
            ->withCount('invoices')
            ->orderByDesc('invoices_sum_subtotal')
            ->take(5)
            ->get();

        $recentInvoices = Invoice::with('seller')
            ->latest('sold_at')->latest('id')
            ->take(8)
            ->get();

        // Finance Department summary (exact, no abbreviations).
        $finance = (object) [
            'net'         => (string) Invoice::sum('net_payable'),
            'paid'        => (string) SellerPayment::sum('amount'),
            'outstanding' => (string) (function_exists('bcsub')
                ? bcsub((string) Invoice::sum('net_payable'), (string) SellerPayment::sum('amount'), 2)
                : number_format((float) Invoice::sum('net_payable') - (float) SellerPayment::sum('amount'), 2, '.', '')),
        ];

        return view('dashboard', compact('stats', 'monthlyLabels', 'monthlyValues', 'topSellers', 'recentInvoices', 'finance'));
    }
}
