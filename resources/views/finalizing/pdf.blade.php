<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin:12mm 9mm; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:DejaVu Sans, sans-serif; font-size:10px; color:#171717; }
        .document-head { width:100%; border-collapse:collapse; margin-bottom:8px; border-bottom:1.5px solid #777; }
        .document-head td { padding:0 0 6px; vertical-align:bottom; }
        .company { font-size:13px; font-weight:bold; }
        .company-line { font-size:8.5px; color:#666; margin-top:2px; }
        .title { text-align:right; font-size:12px; font-weight:bold; letter-spacing:.6px; }
        .generated { text-align:right; font-size:8.5px; color:#777; margin-top:2px; }
        .day { margin-bottom:12px; page-break-inside:auto; }
        .day-title { font-size:11px; font-weight:bold; padding:5px 6px; background:#eceeea; border:1px solid #aaa; border-bottom:0; }
        table.lines { width:100%; border-collapse:collapse; table-layout:fixed; }
        table.lines th { background:#f4f4f1; border:1px solid #aaa; padding:5px 4px; font-size:8.5px; text-transform:uppercase; color:#444; }
        table.lines td { border:1px solid #bbb; padding:5px 4px; vertical-align:top; overflow-wrap:anywhere; font-size:10px; }
        tr.receipt-start td { border-top:1.5px solid #333; }
        .right { text-align:right; }
        .center { text-align:center; }
        .muted { color:#555; }
        .day-total td { background:#f1f1ed; font-weight:bold; border-top:1.5px solid #444 !important; font-size:10px; }
        .grand { margin-top:10px; border:1.5px solid #222; padding:8px 9px; font-size:10.5px; page-break-inside:avoid; }
        .grand-table { width:100%; border-collapse:collapse; }
        .grand-table td { padding:3px 5px; font-size:10.5px; }
        .footer { margin-top:8px; padding-top:5px; border-top:1px solid #bbb; text-align:center; color:#777; font-size:8px; }
        .note { font-size:8.5px; color:#555; margin-top:1px; }
    </style>
</head>
<body>
@php
    use App\Support\Lkr;
    $sort = $sort ?? 'day';
@endphp

<table class="document-head">
    <tr>
        <td>
            <div class="company">{{ config('gemdesk.company_name') }}</div>
            <div class="company-line">{{ config('gemdesk.company_address') }}</div>
        </td>
        <td>
            <div class="title">FINALIZED TRANSACTIONS</div>
            <div class="generated">
                Generated {{ now()->format('d M Y, h:i A') }}
                @if ($sort === 'serial') · Sorted by serial 1 → N @endif
            </div>
        </td>
    </tr>
</table>

@forelse ($days as $day)
    <div class="day">
        <div class="day-title">{{ $day->date->format('l, d F Y') }} &nbsp;·&nbsp; {{ $day->receipts }} bill(s)</div>
        <table class="lines">
            <thead>
                <tr>
                    @if ($sort === 'serial')
                        <th style="width:7%;">Serial</th>
                        <th style="width:7%;">Time</th>
                        <th style="width:{{ $buyerVersion ? '14' : '11' }}%;">Name</th>
                        @if (! $buyerVersion)<th style="width:10%;">Phone</th>@endif
                    @else
                        <th style="width:7%;">Time</th>
                        <th style="width:{{ $buyerVersion ? '14' : '11' }}%;">Name</th>
                        @if (! $buyerVersion)<th style="width:10%;">Phone</th>@endif
                        <th style="width:7%;">Serial</th>
                    @endif
                    <th style="width:{{ $buyerVersion ? ($sort === 'serial' ? '32' : '32') : '16' }}%;">Stone / Lot</th>
                    <th style="width:8%;">Weight</th>
                    <th style="width:5%;">Pcs</th>
                    <th style="width:11%;">Rate</th>
                    <th style="width:12%;">Item Amount</th>
                    @if (! $buyerVersion && $sort !== 'serial')
                        <th style="width:9%;">Purchased</th>
                        <th style="width:8%;">Commission</th>
                        <th style="width:9%;">Net</th>
                        <th style="width:7%;">Status</th>
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
                <tr class="{{ ($sort !== 'serial' && $first) ? 'receipt-start' : '' }}">
                    @if ($sort === 'serial')
                        <td><strong>{{ $item->serial_number ?: '—' }}</strong></td>
                        <td class="center">{{ $invoice->created_at->format('h:i A') }}</td>
                        <td>{{ $invoice->seller->name ?? '—' }}</td>
                        @if (! $buyerVersion)
                            <td>{{ $invoice->seller->phone ?? '—' }}</td>
                        @endif
                    @else
                        <td class="center">{{ $first ? $invoice->created_at->format('h:i A') : '' }}</td>
                        <td>{{ $first ? ($invoice->seller->name ?? '—') : '' }}</td>
                        @if (! $buyerVersion)
                            <td>{{ $first ? ($invoice->seller->phone ?? '—') : '' }}</td>
                        @endif
                        <td><strong>{{ $item->serial_number ?: '—' }}</strong></td>
                    @endif
                    <td>
                        <strong>{{ $item->description }}</strong>
                        @if (! empty($item->verification_actions))
                            <div class="note">{{ strtoupper(implode(' + ', $item->verification_actions)) }}</div>
                        @endif
                        @if ($item->stone_notes)
                            <div class="note">{{ $item->stone_notes }}</div>
                        @endif
                    </td>
                    <td class="right">{{ $item->carats !== null && (float) $item->carats > 0 ? rtrim(rtrim(number_format((float) $item->carats, 2), '0'), '.').' ct' : '—' }}</td>
                    <td class="right">{{ number_format($item->pieces) }}</td>
                    <td class="right">{{ number_format((float) $item->unit_price, 2) }}{{ $item->carats !== null && (float) $item->carats > 0 ? '/ct' : '/pc' }}</td>
                    <td class="right"><strong>{{ number_format((float) $item->amount, 2) }}</strong></td>
                    @if (! $buyerVersion && $sort !== 'serial')
                        <td class="right">{{ $first ? number_format((float) $invoice->subtotal, 2) : '' }}</td>
                        <td class="right">{{ $first ? number_format((float) $invoice->commission_amount, 2) : '' }}</td>
                        <td class="right"><strong>{{ $first ? number_format((float) $invoice->net_payable, 2) : '' }}</strong></td>
                        <td class="center">{{ $first ? strtoupper($invoice->status) : '' }}</td>
                    @endif
                </tr>
            @endforeach
                <tr class="day-total">
                    <td colspan="{{ $buyerVersion ? 5 : 6 }}">DAY TOTAL · {{ $day->receipts }} BILL(S)</td>
                    <td class="right">{{ number_format($day->pieces) }}</td>
                    <td></td>
                    <td class="right">{{ number_format($day->purchased, 2) }}</td>
                    @if (! $buyerVersion && $sort !== 'serial')
                        <td class="right">{{ number_format($day->purchased, 2) }}</td>
                        <td class="right">{{ number_format($day->commission, 2) }}</td>
                        <td class="right">{{ number_format($day->net, 2) }}</td>
                        <td></td>
                    @endif
                </tr>
            </tbody>
        </table>
    </div>
@empty
    <div style="padding:30px; text-align:center; border:1px solid #aaa;">No transactions have been recorded.</div>
@endforelse

@if ($grand->receipts > 0)
    <div class="grand">
        <table class="grand-table">
            <tr>
                <td><strong>ALL-TIME TOTAL</strong></td>
                <td>{{ $grand->days }} day(s)</td>
                <td>{{ $grand->receipts }} bill(s)</td>
                <td>{{ $grand->items }} item row(s)</td>
                <td>{{ $grand->pieces }} pieces</td>
                <td class="right">Purchased: <strong>{{ Lkr::format($grand->purchased) }}</strong></td>
                @if (! $buyerVersion)
                    <td class="right">Commission: <strong>{{ Lkr::format($grand->commission) }}</strong></td>
                    <td class="right">Net: <strong>{{ Lkr::format($grand->net) }}</strong></td>
                @endif
            </tr>
        </table>
    </div>
@endif

<div class="footer">{{ config('gemdesk.company_name') }} · Itemized transaction register</div>
</body>
</html>
