@extends('layouts.app')

@section('title', 'Payin')

@section('content')
@php
    use App\Support\Lkr;
@endphp

<div class="page-heading">
    <div>
        <h1 class="page-title">💵 Payin</h1>
        <p class="page-subtitle">Sellers we have paid so far — full payouts and partial payouts. Open one to see remaining, commission, and every gem they sold.</p>
    </div>
    <a href="{{ route('finance.index') }}" class="btn btn-outline">🏦 Finance Department</a>
</div>

<form method="GET" action="{{ route('payin.index') }}" class="card" style="padding:16px; margin-bottom:16px;">
    <input type="hidden" name="status" value="{{ $filter }}">
    <div style="position:relative;">
        <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:16px; pointer-events:none;">🔍</span>
        <input type="text" name="search" value="{{ $search }}"
               placeholder="Search by seller name, phone, or bill / receipt number…"
               class="form-input" style="padding-left:38px;">
    </div>
    <div style="margin-top:8px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
        <button type="submit" class="btn btn-gold">Search</button>
        @if ($search !== '')
            <a href="{{ route('payin.index', ['status' => $filter]) }}" class="btn btn-outline">✕ Clear</a>
        @endif
        <span style="font-size:11.5px; color:var(--text-muted);">
            Only sellers with at least one recorded payment.
        </span>
    </div>
</form>

<div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
    <a href="{{ route('payin.index', ['search' => $search, 'status' => 'all']) }}"
       class="btn {{ $filter === 'all' ? 'btn-gold' : 'btn-outline' }}">All paid ({{ $counts['all'] }})</a>
    <a href="{{ route('payin.index', ['search' => $search, 'status' => 'partial']) }}"
       class="btn {{ $filter === 'partial' ? 'btn-gold' : 'btn-outline' }}">Partial ({{ $counts['partial'] }})</a>
    <a href="{{ route('payin.index', ['search' => $search, 'status' => 'settled']) }}"
       class="btn {{ $filter === 'settled' ? 'btn-gold' : 'btn-outline' }}">Fully paid ({{ $counts['settled'] }})</a>
</div>

<div class="card" style="padding:16px; margin-bottom:16px;">
    <div class="card-header" style="padding:0 0 12px; border-bottom:1px solid var(--border);">
        <h2>💰 Paid-so-far totals (exact)</h2>
    </div>
    <div style="display:grid; grid-template-columns:repeat(5, 1fr); gap:12px; padding-top:12px;">
        <div><div class="stat-label">Total Sold</div><div class="stat-value" style="font-size:16px;">{{ Lkr::format($overall->sold) }}</div></div>
        <div><div class="stat-label">Commission Cut</div><div class="stat-value" style="font-size:16px;">{{ Lkr::format($overall->commission) }}</div></div>
        <div><div class="stat-label">Net to Sellers</div><div class="stat-value" style="font-size:16px;">{{ Lkr::format($overall->net) }}</div></div>
        <div><div class="stat-label">Already Paid</div><div class="stat-value" style="font-size:16px;">{{ Lkr::format($overall->paid) }}</div></div>
        <div><div class="stat-label">Remaining</div><div class="stat-value" style="font-size:16px;">{{ Lkr::format($overall->outstanding) }}</div></div>
    </div>
</div>

<div class="card" style="overflow:hidden;">
    <div class="card-header">
        <h2>👤 Paid sellers</h2>
        <span style="font-size:12px; color:var(--text-muted);">{{ $rows->count() }} seller(s)</span>
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Seller</th>
                    <th>Last paid</th>
                    <th class="center">Status</th>
                    <th class="right">Bills</th>
                    <th class="right">Total Sold</th>
                    <th class="right">Commission</th>
                    <th class="right">Net to Seller</th>
                    <th class="right">Already Paid</th>
                    <th class="right">Remaining</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php $seller = $row['seller']; $s = $row['summary']; @endphp
                    <tr>
                        <td>
                            <a href="{{ route('payin.show', $seller) }}" class="link-gold font-semibold">{{ $seller->name }}</a>
                            @if ($seller->phone)<div style="font-size:11px; color:var(--text-muted);">{{ $seller->phone }}</div>@endif
                        </td>
                        <td class="muted">
                            @if ($s->last_paid_at)
                                {{ \Carbon\Carbon::parse($s->last_paid_at)->format('d M Y, h:i A') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="center">
                            @if ($s->status === 'partial')
                                <span class="badge badge-pending">Partial</span>
                            @elseif ($s->status === 'overpaid')
                                <span class="badge badge-paid">Overpaid</span>
                            @else
                                <span class="badge badge-paid">Fully paid</span>
                            @endif
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
                        <td class="right"><a href="{{ route('payin.show', $seller) }}" class="btn btn-outline btn-sm">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" style="padding:30px; text-align:center; color:var(--text-muted);">
                        @if ($search !== '')
                            No paid sellers match “{{ $search }}”.
                        @else
                            No payments recorded yet. Record a payment from <a href="{{ route('finance.index') }}" class="link-gold">Finance Department</a>, then it will appear here.
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
