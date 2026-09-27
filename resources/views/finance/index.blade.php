@extends('layouts.app')

@section('title', 'Finance Department')

@section('content')
@php
    use App\Support\Lkr;
@endphp

<div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
    <div>
        <h1 class="page-title">🏦 Finance Department</h1>
        <p class="page-subtitle">
            Exact seller balances — no abbreviations, no rounding. Search by seller name, phone number, or bill/receipt number.
        </p>
    </div>
</div>

{{-- Search --}}
<form method="GET" action="{{ route('finance.index') }}" class="card" style="padding:16px; margin-bottom:16px;">
    <div style="position:relative;">
        <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:16px; pointer-events:none;">🔍</span>
        <input type="text" name="search" value="{{ $search }}"
               placeholder="Search by seller name, phone number, or bill / receipt number…"
               class="form-input" style="padding-left:38px;">
    </div>
    <div style="margin-top:8px; display:flex; align-items:center; gap:8px;">
        <button type="submit" class="btn btn-gold">Search</button>
        @if ($search !== '')
            <a href="{{ route('finance.index') }}" class="btn btn-outline">✕ Clear</a>
        @endif
        @if (\Illuminate\Support\Facades\Route::has('payin.index'))
            <a href="{{ route('payin.index') }}" class="btn btn-outline">💵 Payin — paid sellers</a>
        @endif
        <span style="font-size:11.5px; color:var(--text-muted);">
            @if ($search !== '')
                Showing matches for “{{ $search }}” — every result with exact balances
            @else
                All sellers with exact balances
            @endif
        </span>
    </div>
</form>

{{-- Overall totals --}}
<div class="card" style="padding:16px; margin-bottom:16px;">
    <div class="card-header" style="padding:0 0 12px; border-bottom:1px solid var(--border);">
        <h2>💰 Overall (exact)</h2>
    </div>
    <div style="display:grid; grid-template-columns:repeat(5, 1fr); gap:12px; padding-top:12px;">
        <div><div class="stat-label">Total Sold</div><div class="stat-value" style="font-size:16px;">{{ Lkr::format($overall->sold) }}</div></div>
        <div><div class="stat-label">Our Commission</div><div class="stat-value" style="font-size:16px;">{{ Lkr::format($overall->commission) }}</div></div>
        <div><div class="stat-label">Net to Sellers</div><div class="stat-value" style="font-size:16px;">{{ Lkr::format($overall->net) }}</div></div>
        <div><div class="stat-label">Already Paid</div><div class="stat-value" style="font-size:16px;">{{ Lkr::format($overall->paid) }}</div></div>
        <div><div class="stat-label">Outstanding</div><div class="stat-value" style="font-size:16px; color:var(--text-primary);">{{ Lkr::format($overall->outstanding) }}</div></div>
    </div>
</div>

{{-- Matching bills (when searching by receipt/bill number) --}}
@if ($matchingBills->isNotEmpty())
    <div class="card" style="margin-bottom:16px; overflow:hidden;">
        <div class="card-header"><h2>🧾 Bills matching “{{ $search }}”</h2><span style="font-size:12px; color:var(--text-muted);">{{ $matchingBills->count() }} found</span></div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead><tr><th>Bill #</th><th>Date</th><th>Seller</th><th class="right">Total</th><th class="right">Commission</th><th class="right">Net to Seller</th><th class="center">Payout</th></tr></thead>
                <tbody>
                    @foreach ($matchingBills as $i)
                        <tr>
                            <td><a href="{{ route('invoices.show', $i) }}" class="link-gold font-semibold">{{ $i->invoice_number }}</a></td>
                            <td class="muted">{{ $i->sold_at->format('d M Y') }}</td>
                            <td><a href="{{ route('finance.show', $i->seller) }}" class="link-gold">{{ $i->seller?->name ?? '—' }}</a></td>
                            <td class="right">{{ Lkr::format($i->subtotal) }}</td>
                            <td class="right">{{ Lkr::format($i->commission_amount) }}</td>
                            <td class="right font-semibold">{{ Lkr::format($i->net_payable) }}</td>
                            <td class="center">@if ($i->isPaid())<span class="badge badge-paid">Paid</span>@else<span class="badge badge-pending">Pending</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

{{-- Seller rows --}}
<div class="card" style="overflow:hidden;">
    <div class="card-header"><h2>👤 Sellers</h2><span style="font-size:12px; color:var(--text-muted);">{{ $rows->count() }} seller(s)</span></div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Seller</th>
                    <th class="right">Bills</th>
                    <th class="right">Total Sold</th>
                    <th class="right">Commission</th>
                    <th class="right">Net to Seller</th>
                    <th class="right">Already Paid</th>
                    <th class="right">Outstanding</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php $seller = $row['seller']; $s = $row['summary']; @endphp
                    <tr>
                        <td>
                            <a href="{{ route('finance.show', $seller) }}" class="link-gold font-semibold">{{ $seller->name }}</a>
                            @if ($seller->phone)<div style="font-size:11px; color:var(--text-muted);">{{ $seller->phone }}</div>@endif
                        </td>
                        <td class="right">{{ $s->bill_count }}</td>
                        <td class="right">{{ Lkr::format($s->sold) }}</td>
                        <td class="right">{{ Lkr::format($s->commission) }}</td>
                        <td class="right">{{ Lkr::format($s->net) }}</td>
                        <td class="right">{{ Lkr::format($s->paid) }}</td>
                        <td class="right font-semibold">
                            @if ($s->is_overpaid)
                                <span style="color:#4ade80;">{{ Lkr::format($s->outstanding) }}</span>
                            @elseif ((float) $s->outstanding > 0)
                                <span style="color:#fca5a5;">{{ Lkr::format($s->outstanding) }}</span>
                            @else
                                <span style="color:#4ade80;">{{ Lkr::format($s->outstanding) }}</span>
                            @endif
                        </td>
                        <td class="right"><a href="{{ route('finance.show', $seller) }}" class="btn btn-outline btn-sm">View Ledger</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="padding:30px; text-align:center; color:var(--text-muted);">
                        @if ($search !== '')
                            No sellers match “{{ $search }}”. Try the name, phone, or a bill number.
                        @else
                            No sellers yet.
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
