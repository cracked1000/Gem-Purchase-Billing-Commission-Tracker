<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1200; }
        .page { padding: 10px 6px; }

        /* ── Header ── */
        .header { background: #0d2016; color: #fff; padding: 22px 26px; border-radius: 6px; margin-bottom: 18px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .company     { font-size: 19px; font-weight: bold; color: #f0ece4; }
        .company-sub { font-size: 9px; color: #7a9a82; margin-top: 5px; line-height: 1.7; }
        .doc-title   { font-size: 20px; font-weight: bold; text-align: right; letter-spacing: 2px; color: #d4a853; }
        .doc-meta    { font-size: 10px; color: #7a9a82; text-align: right; margin-top: 5px; line-height: 1.7; }

        /* ── Section ── */
        .section { padding: 0 4px; }

        /* ── Items table ── */
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.items th {
            background: #f5f5f0; color: #555; font-size: 8.5px; text-transform: uppercase;
            letter-spacing: 0.8px; padding: 8px 9px; border-bottom: 2px solid #d4a853; text-align: left;
        }
        table.items th.right, table.items td.right { text-align: right; }
        table.items td { padding: 8px 9px; border-bottom: 1px solid #eee; vertical-align: middle; }
        table.items tbody tr:nth-child(even) td { background: #fafaf8; }

        /* ── Totals ── */
        .totals-wrap   { width: 46%; margin-left: 54%; border-collapse: collapse; }
        .totals-wrap td { padding: 5px 9px; font-size: 11px; }
        .totals-wrap .grand td {
            font-size: 14px; font-weight: bold; color: #0d2016;
            border-top: 2px solid #d4a853; padding-top: 9px;
        }
        .totals-wrap .comm td { color: #8a6a00; }

        /* ── Notes ── */
        .notes { background: #fafaf9; border: 1px solid #e5e5e0; border-radius: 5px;
                 padding: 9px 12px; margin-top: 14px; font-size: 10px; color: #555; }

        /* ── Signatures ── */
        .sign-row  { width: 100%; margin-top: 60px; }
        .sign-cell { width: 33%; text-align: center; font-size: 9px; color: #888; }
        .sign-line { display: inline-block; width: 150px; border-top: 1px solid #ccc;
                     padding-top: 4px; margin-top: 30px; }

        /* ── Footer ── */
        .footer { margin-top: 24px; text-align: center; font-size: 8.5px; color: #aaa;
                  border-top: 1px solid #eee; padding-top: 10px; }

        /* ── Internal-only styles ── */
        .label       { font-size: 8px; text-transform: uppercase; letter-spacing: 1.2px; color: #999; margin-bottom: 3px; }
        .seller-name { font-size: 14px; font-weight: bold; color: #1a1a1a; }
        .status         { font-size: 10px; font-weight: bold; padding: 2px 8px; border-radius: 10px; display:inline-block; }
        .status-paid    { background: #d4edda; color: #155724; }
        .status-pending { background: #f8d7da; color: #721c24; }
        .words { font-style: italic; color: #888; font-size: 9px; text-align: right;
                 padding: 4px 9px 0; width: 46%; margin-left: 54%; }
        /* ── Copy badge (Seller / Buyer) ── */
        .badge-copy {
            display: inline-block; padding: 3px 10px; border-radius: 4px;
            font-size: 8.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;
        }
        .badge-copy.seller { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .badge-copy.buyer  { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta-table td { vertical-align: top; padding: 2px 0; }
    </style>
</head>
<body>
@php
    use App\Support\Lkr;
    use App\Support\AmountInWords;
    $pct     = rtrim(rtrim(number_format((float) $invoice->commission_percent, 2), '0'), '.');
    $sltDate = $invoice->sold_at->setTimezone('Asia/Colombo')->format('d F Y');
@endphp

<div class="page">

    {{-- ── Header ── --}}
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="company">{{ config('gemdesk.company_name') }}</div>
                    <div class="company-sub">
                        {{ config('gemdesk.company_address') }}<br>
                        {{ config('gemdesk.company_phone') }}
                        @if (config('gemdesk.company_email')) · {{ config('gemdesk.company_email') }} @endif
                    </div>
                </td>
                <td style="text-align:right;">
                    <div class="doc-title">PURCHASE BILL</div>
                    <div class="doc-meta">
                        {{ $invoice->invoice_number }}<br>
                        {{ $sltDate }}
                    </div>
                    <div style="margin-top:6px; text-align:right;">
                        <span class="badge-copy {{ $buyerVersion ? 'buyer' : 'seller' }}">Purchase Receipt</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">

        @if ($buyerVersion)
            {{-- Purchase copy: seller name is shown for traceability, while
                 phone, commission, net payout and payout status stay private. --}}

            <table class="meta-table">
                <tr>
                    <td>
                        <div class="label">Purchased From</div>
                        <div class="seller-name">{{ $invoice->seller->name }}</div>
                    </td>
                    <td style="text-align:right; vertical-align:top;">
                        <div class="label">Receipt Number</div>
                        <div style="font-size:12px; font-weight:bold;">{{ $invoice->invoice_number }}</div>
                        <div style="font-size:9px; color:#888; margin-top:6px;">Total pieces: <strong>{{ $invoice->total_pieces }}</strong></div>
                    </td>
                </tr>
            </table>

            <table class="items">
                <thead>
                    <tr>
                        <th style="width:4%;">#</th>
                        <th style="width:10%;">Serial</th>
                        <th style="width:30%;">Description</th>
                        <th class="right" style="width:13%;">Weight (ct)</th>
                        <th class="right" style="width:9%;">Pcs</th>
                        <th class="right" style="width:15%;">Rate (LKR)</th>
                        <th class="right" style="width:19%;">Amount (LKR)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->items as $i => $item)
                        <tr>
                            <td style="color:#999;">{{ $i + 1 }}</td>
                            <td>{{ $item->serial_number ?: '—' }}</td>
                            <td>
                                <strong>{{ $item->description }}</strong>
                                @if (! empty($item->verification_actions))
                                    <div style="font-size:7.5px; color:#8a6a00; margin-top:2px;">{{ strtoupper(implode(' + ', $item->verification_actions)) }}</div>
                                @endif
                                @if ($item->stone_notes)
                                    <div style="font-size:7.5px; color:#777; margin-top:2px;">{{ $item->stone_notes }}</div>
                                @endif
                            </td>
                            <td class="right" style="color:#555;">
                                {{ $item->carats !== null ? rtrim(rtrim(number_format((float) $item->carats, 2), '0'), '.') : '—' }}
                            </td>
                            <td class="right">{{ $item->pieces }}</td>
                            <td class="right" style="color:#555;">{{ Lkr::format($item->unit_price) }}{{ $item->carats !== null && (float) $item->carats > 0 ? '/ct' : '/pc' }}</td>
                            <td class="right"><strong>{{ Lkr::format($item->amount) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="totals-wrap">
                <tr class="grand">
                    <td>Total Amount</td>
                    <td style="text-align:right;">{{ Lkr::format($invoice->subtotal) }}</td>
                </tr>
            </table>

        @else
            {{-- Settlement details for the office-selected private copy. --}}

            <table class="meta-table">
                <tr>
                    <td>
                        <div class="label">Received from (Seller)</div>
                        <div class="seller-name">{{ $invoice->seller->name }}</div>
                        @if ($invoice->seller->phone)
                            <div style="color:#555; font-size:11px; margin-top:2px;">{{ $invoice->seller->phone }}</div>
                        @endif
                    </td>
                    <td style="text-align:right; vertical-align:top;">
                        <div class="label">Payout to Seller</div>
                        <span class="status {{ $invoice->isPaid() ? 'status-paid' : 'status-pending' }}">
                            {{ strtoupper($invoice->status) }}
                        </span>
                        <div style="font-size:9px; color:#888; margin-top:8px;">Total pieces: <strong>{{ $invoice->total_pieces }}</strong></div>
                    </td>
                </tr>
            </table>

            <table class="items">
                <thead>
                    <tr>
                        <th style="width:4%;">#</th>
                        <th style="width:9%;">Serial</th>
                        <th style="width:27%;">Description</th>
                        <th class="right" style="width:13%;">Weight (ct)</th>
                        <th class="right" style="width:9%;">Pcs</th>
                        <th class="right" style="width:19%;">Rate (LKR)</th>
                        <th class="right" style="width:19%;">Amount (LKR)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->items as $i => $item)
                        <tr>
                            <td style="color:#999;">{{ $i + 1 }}</td>
                            <td>{{ $item->serial_number ?: '—' }}</td>
                            <td>
                                <strong>{{ $item->description }}</strong>
                                @if (! empty($item->verification_actions))
                                    <div style="font-size:7.5px; color:#8a6a00; margin-top:2px;">{{ strtoupper(implode(' + ', $item->verification_actions)) }}</div>
                                @endif
                                @if ($item->stone_notes)
                                    <div style="font-size:7.5px; color:#777; margin-top:2px;">{{ $item->stone_notes }}</div>
                                @endif
                            </td>
                            <td class="right" style="color:#555;">
                                {{ $item->carats !== null ? rtrim(rtrim(number_format((float) $item->carats, 2), '0'), '.') : '—' }}
                            </td>
                            <td class="right">{{ $item->pieces }}</td>
                            <td class="right" style="color:#555;">{{ Lkr::format($item->unit_price) }}{{ $item->carats !== null && (float) $item->carats > 0 ? '/ct' : '/pc' }}</td>
                            <td class="right"><strong>{{ Lkr::format($item->amount) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="totals-wrap">
                <tr>
                    <td style="color:#555;">Total Purchased</td>
                    <td style="text-align:right;"><strong>{{ Lkr::format($invoice->subtotal) }}</strong></td>
                </tr>
                <tr class="comm">
                    <td>Our Commission ({{ $pct }}%)</td>
                    <td style="text-align:right;"><strong>− {{ Lkr::format($invoice->commission_amount) }}</strong></td>
                </tr>
                <tr class="grand">
                    <td>Net payable to seller</td>
                    <td style="text-align:right;">{{ Lkr::format($invoice->net_payable) }}</td>
                </tr>
            </table>

            <div class="words">{{ AmountInWords::convert($invoice->net_payable) }}.</div>

        @endif

        @if (! $buyerVersion && $invoice->notes)
            <div class="notes">
                <div class="label">Notes</div>
                {{ $invoice->notes }}
            </div>
        @endif

        {{-- ── Signatures ── --}}
        <table class="sign-row">
            <tr>
                <td class="sign-cell"><span class="sign-line">{{ $buyerVersion ? 'Checked By' : "Seller's Signature" }}</span></td>
                <td class="sign-cell" style="width:34%;"></td>
                <td class="sign-cell">
                    <span class="sign-line">Authorised By</span>
                </td>
            </tr>
        </table>

        <div class="footer">
            {{ config('gemdesk.company_name') }} &nbsp;·&nbsp; {{ config('gemdesk.company_address') }}
            &nbsp;·&nbsp; {{ now()->setTimezone('Asia/Colombo')->format('d M Y, h:i A') }} SLT
        </div>

    </div><!-- /section -->
</div><!-- /page -->
</body>
</html>
