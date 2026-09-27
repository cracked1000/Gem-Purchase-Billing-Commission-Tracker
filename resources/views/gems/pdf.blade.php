<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin:11mm 8mm; }
        body { margin:0; font-family:DejaVu Sans, sans-serif; font-size:7px; color:#171717; }
        .head { width:100%; border-collapse:collapse; margin-bottom:8px; border-bottom:1px solid #555; }
        .head td { padding:0 0 5px; }
        .company { font-size:11px; font-weight:bold; }
        .title { text-align:right; font-size:10px; font-weight:bold; }
        .sub { color:#666; font-size:6.5px; margin-top:2px; }
        table.data { width:100%; border-collapse:collapse; table-layout:fixed; }
        table.data th { background:#e9ece8; border:1px solid #999; padding:3px 2px; font-size:6px; text-transform:uppercase; overflow-wrap:anywhere; }
        table.data td { border:1px solid #bbb; padding:3px 2px; vertical-align:top; overflow-wrap:anywhere; }
        .right { text-align:right; }
        .total td { background:#eeeeea; border-top:1.5px solid #333 !important; font-weight:bold; }
        .footer { margin-top:7px; text-align:center; color:#777; font-size:6px; }
    </style>
</head>
<body>
<table class="head">
    <tr>
        <td><div class="company">{{ config('gemdesk.company_name') }}</div><div class="sub">{{ config('gemdesk.company_address') }}</div></td>
        <td><div class="title">GEM PURCHASE REGISTER</div><div class="sub" style="text-align:right;">Serial order · Generated {{ now()->format('d M Y, h:i A') }}</div></td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            @foreach ($columns as $definition)
                <th class="{{ in_array($definition['type'], ['integer', 'decimal', 'money'], true) ? 'right' : '' }}">{{ $definition['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                @foreach ($columns as $key => $definition)
                    @php $value = $row[$key] ?? null; @endphp
                    <td class="{{ in_array($definition['type'], ['integer', 'decimal', 'money'], true) ? 'right' : '' }}">
                        @if ($definition['type'] === 'money')
                            {{ number_format((float) $value, 2) }}
                        @elseif ($definition['type'] === 'decimal')
                            {{ $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 2), '0'), '.') }}
                        @elseif ($definition['type'] === 'integer')
                            {{ number_format((int) $value) }}
                        @elseif ($definition['type'] === 'date' && $value)
                            {{ \Carbon\Carbon::parse($value)->format('d M Y') }}
                        @else
                            {{ $value }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($columns) }}" style="padding:20px; text-align:center;">No purchased gems have been recorded.</td></tr>
        @endforelse
        @if ($rows->isNotEmpty())
            <tr class="total">
                @foreach ($columns as $key => $definition)
                    <td class="{{ in_array($definition['type'], ['integer', 'decimal', 'money'], true) ? 'right' : '' }}">
                        @if ($key === 'pieces')
                            {{ number_format($totals->pieces) }}
                        @elseif ($key === 'weight')
                            {{ number_format($totals->weight, 2) }}
                        @elseif ($key === 'bought_price')
                            {{ number_format($totals->bought_price, 2) }}
                        @elseif ($key === array_key_first($columns))
                            ALL-TIME TOTAL
                        @endif
                    </td>
                @endforeach
            </tr>
        @endif
    </tbody>
</table>

<div class="footer">{{ number_format($totals->gems) }} gem row(s) · {{ number_format($totals->pieces) }} pieces · Total bought price LKR {{ number_format($totals->bought_price, 2) }}</div>
</body>
</html>
