@extends('layouts.app')

@section('title', 'Finalizing')

@section('content')
@php
    use App\Support\Lkr;
    $sort = $sort ?? 'day';
    $query = ['version' => $version, 'sort' => $sort];
@endphp

<style>
    .finalizing-table { font-size: 15px; }
    .finalizing-table th {
        font-size: 12px !important;
        letter-spacing: .4px;
        padding: 11px 12px !important;
    }
    .finalizing-table td {
        font-size: 15px !important;
        padding: 12px 12px !important;
        line-height: 1.35;
    }
    .finalizing-table td.muted { font-size: 14px !important; }
    .finalizing-table .stone-notes { font-size: 12px !important; }
</style>

<div class="page-heading">
    <div>
        <h1 class="page-title">Finalizing</h1>
        <p class="page-subtitle">
            @if ($sort === 'serial')
                Item-by-item register, grouped by day, listed in ascending serial order (1, 2, 3…).
            @else
                Complete item-by-item transaction register, grouped by day.
            @endif
        </p>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="{{ route('finalizing.pdf', ['buyer' => 1, 'sort' => $sort]) }}" class="btn {{ ($wipeMode ?? false) ? 'btn-gold' : 'btn-outline' }}">Print Buyer PDF</a>
        @unless ($wipeMode ?? false)
            <a href="{{ route('finalizing.pdf', ['buyer' => 0, 'sort' => $sort]) }}" class="btn btn-gold">Print Seller PDF</a>
        @endunless
    </div>
</div>

<div class="card" style="padding:12px 16px; margin-bottom:16px;">
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        <span style="font-size:12.5px; color:var(--text-muted); font-weight:600;">Sort:</span>
        <a href="{{ route('finalizing.index', ['version' => $version, 'sort' => 'serial']) }}"
           class="btn {{ $sort === 'serial' ? 'btn-gold' : 'btn-outline' }}"
           title="List every gem in the day as 1, 2, 3… by serial number">🔢 Serial 1 → N</a>
        <a href="{{ route('finalizing.index', ['version' => $version, 'sort' => 'day']) }}"
           class="btn {{ $sort === 'day' ? 'btn-gold' : 'btn-outline' }}"
           title="Keep gems under each bill, in the order they were recorded">📅 By day / bill</a>
    </div>
    @unless ($wipeMode ?? false)
        <div style="display:flex; gap:6px; margin-top:10px; padding-top:10px; border-top:1px solid var(--border);">
            <a href="{{ route('finalizing.index', ['version' => 'buyer', 'sort' => $sort]) }}"
               class="btn {{ $version === 'buyer' ? 'btn-gold' : 'btn-outline' }}">Buyer View</a>
            <a href="{{ route('finalizing.index', ['version' => 'seller', 'sort' => $sort]) }}"
               class="btn {{ $version === 'seller' ? 'btn-gold' : 'btn-outline' }}">Seller View</a>
        </div>
    @endunless
</div>

@forelse ($days as $day)
    <section class="card" style="overflow:hidden; margin-bottom:16px;">
        <div style="padding:12px 16px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
            <strong style="font-size:15px;">{{ $day->date->format('l, d F Y') }}</strong>
            <span style="font-size:13px; color:var(--text-muted);">{{ $day->receipts }} bill(s) · {{ $day->pieces }} pieces</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table finalizing-table">
                <thead>
                    <tr>
                        @if ($sort === 'serial')
                            <th>Serial</th>
                            <th>Time</th>
                            <th>Seller</th>
                            @if ($version === 'seller')<th>Phone</th>@endif
                        @else
                            <th>Time</th>
                            <th>Seller</th>
                            @if ($version === 'seller')<th>Phone</th>@endif
                            <th>Serial</th>
                        @endif
                        <th>Stone / Lot</th>
                        <th class="right">Weight</th>
                        <th class="right">Pcs</th>
                        <th class="right">Rate</th>
                        <th class="right">Item Amount</th>
                        @if ($version === 'seller' && $sort !== 'serial')
                            <th class="right">Purchased</th>
                            <th class="right">Commission</th>
                            <th class="right">Net</th>
                            <th>Status</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                @foreach ($day->lines as $line)
                    @php
                        $invoice = $line->invoice;
                        $item = $line->item;
                        $first = $line->first;
                    @endphp
                    <tr style="{{ ($sort !== 'serial' && $first) ? 'border-top:2px solid var(--border-strong);' : '' }}">
                        @if ($sort === 'serial')
                            <td class="font-mono"><strong>{{ $item->serial_number ?: '—' }}</strong></td>
                            <td class="muted">{{ $invoice->created_at->format('h:i A') }}</td>
                            <td>{{ $invoice->seller->name ?? '—' }}</td>
                            @if ($version === 'seller')
                                <td class="muted">{{ $invoice->seller->phone ?? '—' }}</td>
                            @endif
                        @else
                            <td class="muted">{{ $first ? $invoice->created_at->format('h:i A') : '' }}</td>
                            <td>{{ $first ? ($invoice->seller->name ?? '—') : '' }}</td>
                            @if ($version === 'seller')
                                <td class="muted">{{ $first ? ($invoice->seller->phone ?? '—') : '' }}</td>
                            @endif
                            <td class="font-mono"><strong>{{ $item->serial_number ?: '—' }}</strong></td>
                        @endif
                        <td>
                            <strong>{{ $item->description }}</strong>
                            @if (! empty($item->verification_actions))
                                <div class="stone-notes" style="color:#fbbf24; margin-top:3px;">{{ strtoupper(implode(' + ', $item->verification_actions)) }}</div>
                            @endif
                            @if ($item->stone_notes)
                                <div class="stone-notes" style="color:var(--text-muted); margin-top:3px;">{{ $item->stone_notes }}</div>
                            @endif
                        </td>
                        <td class="right">{{ $item->carats !== null && (float) $item->carats > 0 ? rtrim(rtrim(number_format((float) $item->carats, 2), '0'), '.').' ct' : '—' }}</td>
                        <td class="right">{{ number_format($item->pieces) }}</td>
                        <td class="right">{{ Lkr::format($item->unit_price) }}{{ $item->carats !== null && (float) $item->carats > 0 ? '/ct' : '/pc' }}</td>
                        <td class="right"><strong>{{ Lkr::format($item->amount) }}</strong></td>
                        @if ($version === 'seller' && $sort !== 'serial')
                            <td class="right">{{ $first ? Lkr::format($invoice->subtotal) : '' }}</td>
                            <td class="right">{{ $first ? Lkr::format($invoice->commission_amount) : '' }}</td>
                            <td class="right"><strong>{{ $first ? Lkr::format($invoice->net_payable) : '' }}</strong></td>
                            <td>{{ $first ? ucfirst($invoice->status) : '' }}</td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr style="border-top:2px solid var(--border-strong); background:var(--bg-card-hover);">
                        <td colspan="{{ $version === 'seller' ? 6 : 5 }}"><strong>Day Total · {{ $day->receipts }} bill(s)</strong></td>
                        <td class="right"><strong>{{ number_format($day->pieces) }}</strong></td>
                        <td></td>
                        <td class="right"><strong>{{ Lkr::format($day->purchased) }}</strong></td>
                        @if ($version === 'seller' && $sort !== 'serial')
                            <td class="right"><strong>{{ Lkr::format($day->purchased) }}</strong></td>
                            <td class="right"><strong>{{ Lkr::format($day->commission) }}</strong></td>
                            <td class="right"><strong>{{ Lkr::format($day->net) }}</strong></td>
                            <td></td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
@empty
    <div class="card" style="padding:40px; text-align:center; color:var(--text-muted);">No transactions have been recorded.</div>
@endforelse

@if ($grand->receipts > 0)
    <div class="card" style="padding:16px 18px; border:2px solid var(--border-strong); display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:center;">
        <strong style="font-size:15px;">All-Time Total · {{ $grand->days }} day(s) · {{ $grand->receipts }} bill(s) · {{ $grand->pieces }} pieces</strong>
        <div style="display:flex; gap:20px; flex-wrap:wrap; font-size:15px;">
            <span>Purchased: <strong>{{ Lkr::format($grand->purchased) }}</strong></span>
            @if ($version === 'seller')
                <span>Commission: <strong>{{ Lkr::format($grand->commission) }}</strong></span>
                <span>Net: <strong>{{ Lkr::format($grand->net) }}</strong></span>
            @endif
        </div>
    </div>
@endif
@endsection
