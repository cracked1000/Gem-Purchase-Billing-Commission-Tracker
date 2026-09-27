<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:DejaVu Sans, sans-serif; font-size:9.5px; color:#181818; }
        .header { background:#0d2016; color:#fff; padding:17px 20px; margin-bottom:14px; }
        .header-table { width:100%; border-collapse:collapse; }
        .company { font-size:16px; font-weight:bold; color:#f3f0e8; }
        .company-sub { color:#91a699; font-size:8.5px; line-height:1.6; margin-top:4px; }
        .report-title { text-align:right; color:#d4a853; font-size:16px; font-weight:bold; letter-spacing:1px; }
        .period { text-align:right; color:#b7c3bb; font-size:9px; margin-top:5px; }
        .summary { width:100%; border-collapse:collapse; margin-bottom:15px; }
        .summary td { padding:8px 10px; background:#f6f6f2; border:1px solid #e5e5df; }
        .label { color:#888; text-transform:uppercase; letter-spacing:.7px; font-size:7.5px; }
        .value { font-size:11px; font-weight:bold; margin-top:2px; }
        .seller-group { margin:14px 0 7px; padding:7px 10px; background:#0d2016; color:#fff; font-size:11px; font-weight:bold; page-break-after:avoid; }
        .seller-group small { color:#aebdb3; font-size:8px; font-weight:normal; margin-left:8px; }
        .transaction { margin-bottom:16px; border:1px solid #deded8; page-break-inside:avoid; }
        .transaction-head { width:100%; border-collapse:collapse; background:#f1f2ed; border-bottom:2px solid #d4a853; }
        .transaction-head td { padding:8px 10px; vertical-align:top; }
        .receipt-no { font-size:12px; font-weight:bold; }
        .seller { font-size:11px; font-weight:bold; margin-top:2px; }
        .meta { color:#666; font-size:8.5px; line-height:1.55; }
        table.items { width:100%; border-collapse:collapse; }
        table.items th { background:#fafaf8; color:#666; font-size:7.5px; text-transform:uppercase; letter-spacing:.5px; padding:6px 7px; text-align:left; border-bottom:1px solid #ddd; }
        table.items td { padding:6px 7px; border-bottom:1px solid #eeeeea; vertical-align:top; }
        table.items tr:last-child td { border-bottom:none; }
        .right { text-align:right !important; }
        .settlement { width:100%; border-collapse:collapse; border-top:1px solid #ddd; }
        .settlement td { padding:5px 8px; }
        .settlement .grand td { font-size:11px; font-weight:bold; border-top:1px solid #c6a85f; background:#fffdf5; }
        .purchase-total { width:100%; border-collapse:collapse; border-top:1px solid #ddd; }
        .purchase-total td { padding:7px 8px; font-size:10.5px; font-weight:bold; background:#fafaf8; }
        .notes { padding:7px 9px; border-top:1px solid #eee; color:#555; font-size:8.5px; }
        .empty { padding:30px; text-align:center; color:#888; border:1px solid #ddd; }
        .footer { margin-top:18px; padding-top:8px; border-top:1px solid #eee; text-align:center; color:#999; font-size:7.5px; }
    </style>
</head>
<body>
@php use App\Support\Lkr; @endphp

<div class="header">
    <table class="header-table">
        <tr>
            <td>
                <div class="company">{{ config('gemdesk.company_name') }}</div>
                <div class="company-sub">
                    {{ config('gemdesk.company_address') }}<br>
                    {{ config('gemdesk.company_phone') }}
                </div>
            </td>
            <td>
                <div class="report-title">{{ strtoupper($reportTitle ?? 'PURCHASE REPORT') }}</div>
                <div class="period">{{ $periodLabel ?? 'All recorded transactions' }}</div>
                <div class="period">Generated {{ now()->format('d M Y, h:i A') }}</div>
            </td>
        </tr>
    </table>
</div>

<table class="summary">
    <tr>
        <td><div class="label">Receipts</div><div class="value">{{ number_format($totals->count) }}</div></td>
        <td><div class="label">Pieces</div><div class="value">{{ number_format($totals->pieces) }}</div></td>
        <td><div class="label">Total Purchased</div><div class="value">{{ Lkr::format($totals->volume) }}</div></td>
        @if (! $buyerVersion)
            <td><div class="label">Commission</div><div class="value">{{ Lkr::format($totals->commission) }}</div></td>
            <td><div class="label">Net Payable</div><div class="value">{{ Lkr::format($totals->net) }}</div></td>
        @endif
    </tr>
</table>

@php $lastSellerId = null; @endphp
@forelse ($invoices as $invoice)
    @if (($orderMode ?? 'fifo') === 'seller' && $lastSellerId !== $invoice->seller_id)
        <div class="seller-group">
            {{ $invoice->seller->name ?? 'Unknown seller' }}
            @if (! $buyerVersion && $invoice->seller?->phone)
                <small>{{ $invoice->seller->phone }}</small>
            @endif
        </div>
        @php $lastSellerId = $invoice->seller_id; @endphp
    @endif
    <div class="transaction">
        <table class="transaction-head">
            <tr>
                <td style="width:58%;">
                    <div class="label">Receipt Number</div>
                    <div class="receipt-no">{{ $invoice->invoice_number }}</div>
                    <div class="label" style="margin-top:6px;">Purchased From</div>
                    <div class="seller">{{ $invoice->seller->name ?? '—' }}</div>
                    @if (! $buyerVersion && $invoice->seller?->phone)
                        <div class="meta">{{ $invoice->seller->phone }}</div>
                    @endif
                </td>
                <td class="right">
                    <div class="label">Transaction Date</div>
                    <div class="value">{{ $invoice->sold_at->format('d F Y') }}</div>
                    @if (($orderMode ?? 'fifo') === 'fifo')
                        <div class="meta">Recorded {{ $invoice->created_at->format('h:i:s A') }}</div>
                    @endif
                    <div class="meta" style="margin-top:5px;">{{ number_format($invoice->total_pieces) }} piece(s)</div>
                    @if (! $buyerVersion)
                        <div class="meta" style="margin-top:3px;">Payout: <strong>{{ strtoupper($invoice->status) }}</strong></div>
                    @endif
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:4%;">#</th>
                    <th style="width:9%;">Serial</th>
                    <th style="width:29%;">Description</th>
                    <th class="right" style="width:13%;">Weight (ct)</th>
                    <th class="right" style="width:9%;">Pieces</th>
                    <th class="right" style="width:17%;">Rate (LKR)</th>
                    <th class="right" style="width:19%;">Amount (LKR)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item->serial_number ?: '—' }}</td>
                        <td>
                            <strong>{{ $item->description }}</strong>
                            @if (! empty($item->verification_actions))
                                <div style="font-size:7px; color:#8a6a00; margin-top:2px;">{{ strtoupper(implode(' + ', $item->verification_actions)) }}</div>
                            @endif
                            @if ($item->stone_notes)
                                <div style="font-size:7px; color:#777; margin-top:2px;">{{ $item->stone_notes }}</div>
                            @endif
                        </td>
                        <td class="right">{{ $item->carats !== null && (float) $item->carats > 0 ? rtrim(rtrim(number_format((float) $item->carats, 2), '0'), '.') : '—' }}</td>
                        <td class="right">{{ number_format($item->pieces) }}</td>
                        <td class="right">{{ Lkr::format($item->unit_price) }}{{ $item->carats !== null && (float) $item->carats > 0 ? '/ct' : '/pc' }}</td>
                        <td class="right"><strong>{{ Lkr::format($item->amount) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($buyerVersion)
            <table class="purchase-total">
                <tr><td>Total Purchase Amount</td><td class="right">{{ Lkr::format($invoice->subtotal) }}</td></tr>
            </table>
        @else
            @php $pct = rtrim(rtrim(number_format((float) $invoice->commission_percent, 2), '0'), '.'); @endphp
            <table class="settlement">
                <tr><td>Purchased Amount</td><td class="right"><strong>{{ Lkr::format($invoice->subtotal) }}</strong></td></tr>
                <tr><td>Commission ({{ $pct }}%)</td><td class="right">− {{ Lkr::format($invoice->commission_amount) }}</td></tr>
                <tr class="grand"><td>Net Amount Payable</td><td class="right">{{ Lkr::format($invoice->net_payable) }}</td></tr>
            </table>
            @if ($invoice->notes)
                <div class="notes"><strong>Notes:</strong> {{ $invoice->notes }}</div>
            @endif
        @endif
    </div>
@empty
    <div class="empty">No transactions were recorded for this report.</div>
@endforelse

<div class="footer">
    {{ config('gemdesk.company_name') }} · {{ config('gemdesk.company_address') }} · Receipt numbers match the corresponding transaction records.
</div>
</body>
</html>
