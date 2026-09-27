@extends('layouts.app')

@section('title', 'Reports')

@section('content')
@php use App\Support\Lkr; @endphp

<div class="page-heading">
    <div>
        <h1 class="page-title">Reports</h1>
        <p class="page-subtitle">Generate transaction, daily, and complete purchase records.</p>
    </div>
    <a href="{{ route('reports.export', request()->query()) }}" class="btn btn-outline">⬇ Export Filtered CSV</a>
</div>

{{-- Report generation: controls only, without dashboard widgets. --}}
<div class="card" style="padding:18px; margin-bottom:16px;">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; padding-bottom:14px; margin-bottom:16px; border-bottom:1px solid var(--border);">
        <div>
            <div class="form-label" style="margin-bottom:3px;">PDF Arrangement</div>
            <div id="orderDescription" style="font-size:11.5px; color:var(--text-muted);">Grouped by seller</div>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
            <span style="font-size:12px; font-weight:600;">Seller-wise</span>
            <label style="position:relative; width:50px; height:26px; display:inline-block; cursor:pointer;">
                <input type="checkbox" id="reportOrderToggle" style="opacity:0; width:0; height:0;">
                <span id="reportOrderTrack" style="position:absolute; inset:0; border-radius:20px; background:var(--border-strong); transition:.2s;"></span>
                <span id="reportOrderKnob" style="position:absolute; width:20px; height:20px; left:3px; top:3px; border-radius:50%; background:var(--text-primary); transition:.2s;"></span>
            </label>
            <span style="font-size:12px; font-weight:600;">FIFO</span>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:1fr auto; gap:22px; align-items:end;">
        <form method="GET" action="{{ route('reports.daily.pdf') }}">
            <input type="hidden" name="order" id="dailyReportOrder" value="seller">
            <label class="form-label">Daily Report</label>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="date" name="date" value="{{ request('date', now()->format('Y-m-d')) }}" required class="form-input" style="max-width:190px;">
                <button type="submit" name="buyer" value="1" class="btn {{ ($wipeMode ?? false) ? 'btn-gold' : 'btn-outline' }}">Buyer PDF</button>
                @unless ($wipeMode ?? false)
                    <button type="submit" name="buyer" value="0" class="btn btn-gold">Seller PDF</button>
                @endunless
            </div>
        </form>

        <div>
            <label class="form-label">All Bills</label>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <a id="allBuyerPdf" href="{{ route('reports.all.pdf', ['buyer' => 1, 'order' => 'seller']) }}" class="btn {{ ($wipeMode ?? false) ? 'btn-gold' : 'btn-outline' }}">Buyer PDF</a>
                @unless ($wipeMode ?? false)
                    <a id="allSellerPdf" href="{{ route('reports.all.pdf', ['buyer' => 0, 'order' => 'seller']) }}" class="btn btn-gold">Seller PDF</a>
                @endunless
            </div>
        </div>
    </div>
</div>

{{-- On-screen ledger filters --}}
<form method="GET" action="{{ route('reports.index') }}" class="card" style="padding:16px; margin-bottom:16px;">
    <div style="display:grid; grid-template-columns:1fr 1fr 1.3fr auto auto; gap:12px; align-items:end;">
        <div>
            <label class="form-label">From</label>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-input">
        </div>
        <div>
            <label class="form-label">To</label>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-input">
        </div>
        <div>
            <label class="form-label">Seller</label>
            <select name="seller_id" class="form-select">
                <option value="">All sellers</option>
                @foreach ($sellers as $seller)
                    <option value="{{ $seller->id }}" @selected((string) ($filters['seller_id'] ?? '') === (string) $seller->id)>{{ $seller->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-gold">Apply</button>
        <a href="{{ route('reports.index') }}" class="btn btn-outline">Reset</a>
    </div>
</form>

<div class="card" style="overflow:hidden;">
    <div class="card-header">
        <h2>Transactions</h2>
        <span style="font-size:12px; color:var(--text-muted);">{{ number_format($invoices->count()) }} shown</span>
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Receipt #</th>
                    <th>Date</th>
                    <th>Seller</th>
                    <th class="right">Pieces</th>
                    <th class="right">Purchased</th>
                    @unless ($wipeMode ?? false)
                        <th class="right">Commission</th>
                        <th class="right">Net to Seller</th>
                    @endunless
                    <th class="center">Payout</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td><a href="{{ route('invoices.show', $invoice) }}" class="link-gold font-semibold">{{ $invoice->invoice_number }}</a></td>
                        <td class="muted">{{ $invoice->sold_at->format('d M Y') }}</td>
                        <td>{{ $invoice->seller->name ?? '—' }}</td>
                        <td class="right">{{ $invoice->total_pieces }}</td>
                        <td class="right font-semibold">{{ Lkr::format($invoice->subtotal) }}</td>
                        @unless ($wipeMode ?? false)
                            <td class="right">{{ Lkr::format($invoice->commission_amount) }}</td>
                            <td class="right font-semibold">{{ Lkr::format($invoice->net_payable) }}</td>
                        @endunless
                        <td class="center">
                            <span class="badge {{ $invoice->isPaid() ? 'badge-paid' : 'badge-pending' }}">{{ $invoice->isPaid() ? 'Paid' : 'Pending' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:40px;">No transactions match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const toggle = document.getElementById('reportOrderToggle');
    const hidden = document.getElementById('dailyReportOrder');
    const buyer = document.getElementById('allBuyerPdf');
    const seller = document.getElementById('allSellerPdf');
    const knob = document.getElementById('reportOrderKnob');
    const track = document.getElementById('reportOrderTrack');
    const description = document.getElementById('orderDescription');
    const allUrl = @json(route('reports.all.pdf'));

    function applyOrder() {
        const mode = toggle.checked ? 'fifo' : 'seller';
        hidden.value = mode;
        buyer.href = allUrl + '?buyer=1&order=' + mode;
        if (seller) seller.href = allUrl + '?buyer=0&order=' + mode;
        knob.style.left = toggle.checked ? '27px' : '3px';
        track.style.background = toggle.checked ? 'var(--text-secondary)' : 'var(--border-strong)';
        description.textContent = toggle.checked
            ? 'Oldest recorded bill first, using transaction timestamp'
            : 'Grouped by seller, then ordered by transaction timestamp';
        localStorage.setItem('gemdesk-report-order', mode);
    }

    toggle.checked = localStorage.getItem('gemdesk-report-order') === 'fifo';
    toggle.addEventListener('change', applyOrder);
    applyOrder();
})();
</script>
@endpush
@endsection
