<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin:14mm 12mm; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:DejaVu Sans, sans-serif; font-size:12px; color:#171717; }
        .document-head { width:100%; border-collapse:collapse; margin-bottom:10px; border-bottom:1px solid #777; }
        .document-head td { padding:0 0 8px; vertical-align:bottom; }
        .company { font-size:16px; font-weight:bold; }
        .company-line { font-size:10px; color:#666; margin-top:3px; }
        .title { text-align:right; font-size:14px; font-weight:bold; letter-spacing:.7px; }
        .generated { text-align:right; font-size:9px; color:#777; margin-top:3px; }
        .grand-lines { font-size:11px; color:#444; margin:4px 0 10px; }
        .day-head { font-size:15px; font-weight:bold; margin:10px 0 6px; padding-bottom:4px; border-bottom:1.5px solid #444; }
        .section { margin-bottom:14px; page-break-inside:auto; }
        .section-title { font-size:13px; font-weight:bold; padding:5px 7px; background:#eceeea; border:1px solid #aaa; border-bottom:0; }
        table.lines { width:100%; border-collapse:collapse; table-layout:fixed; }
        table.lines th { background:#f4f4f1; border:1px solid #aaa; padding:5px 6px; font-size:10px; text-transform:uppercase; color:#555; }
        table.lines td { border:1px solid #bbb; padding:6px 8px; vertical-align:top; overflow-wrap:anywhere; }
        .right { text-align:right; }
        .center { text-align:center; }
        .section-total td { background:#f1f1ed; font-weight:bold; border-top:1.5px solid #444 !important; }
    </style>
</head>
<body>
@php use App\Support\Lkr; @endphp

@php
    $sub = $mode === 'daily' ? 'GEM EXPORT — BY DAY' : ($mode === 'day' ? 'GEM EXPORT' : 'GEM EXPORT — ALL TIME');
    if (! empty($category_title)) { $sub = 'GEM EXPORT — '.strtoupper($category_title); }
    $usingFlat = ($layout ?? 'category') === 'serial';
@endphp

<table class="document-head">
    <tr>
        <td>
            <div class="company">{{ config('gemdesk.company_name') }}</div>
            <div class="company-line">{{ config('gemdesk.company_address') }}</div>
        </td>
        <td>
            <div class="title">{{ $sub }}</div>
            <div class="generated">Generated {{ now()->format('d M Y, h:i A') }}</div>
        </td>
    </tr>
</table>

@if ($usingFlat)
    {{-- Sort by serial: continuous ascending list, category as a column --}}
    @if ($mode === 'daily')
        @forelse ($daily as $day)
            <div class="day-head">
                {{ $day->date->format('l, d F Y') }}
                &nbsp;·&nbsp; {{ number_format($day->grand->gems) }} gem(s) ·
                {{ number_format($day->grand->pieces) }} pc ·
                {{ rtrim(rtrim(number_format($day->grand->weight, 2), '0'), '.') }} ct
            </div>
            @include('export._flat_pdf', ['rows' => $day->flat])
        @empty
            <div style="padding:30px; text-align:center; border:1px solid #aaa;">No gems recorded yet.</div>
        @endforelse
    @else
        <div class="grand-lines">
            {{ number_format($grand->gems) }} gem row(s) &nbsp;·&nbsp;
            {{ number_format($grand->pieces) }} piece(s) &nbsp;·&nbsp;
            {{ rtrim(rtrim(number_format($grand->weight, 2), '0'), '.') }} ct
            @if ($mode === 'day' && $date)
                &nbsp;·&nbsp; {{ \Carbon\Carbon::parse($date)->format('d M Y') }}
            @endif
        </div>
        @include('export._flat_pdf', ['rows' => $flat])
    @endif
@else
    {{-- Categorized sections --}}
    @if ($mode === 'daily')
        @forelse ($daily as $day)
            <div class="day-head">
                {{ $day->date->format('l, d F Y') }}
                &nbsp;·&nbsp; {{ number_format($day->grand->gems) }} gem(s) ·
                {{ number_format($day->grand->pieces) }} pc ·
                {{ rtrim(rtrim(number_format($day->grand->weight, 2), '0'), '.') }} ct
            </div>
            @foreach ($day->sections as $section)
                @if ($section->rows->isNotEmpty())
                <div class="section">
                    <div class="section-title">{{ $section->title }} &nbsp;·&nbsp; {{ number_format($section->count) }} · {{ number_format($section->pieces) }} pc · {{ rtrim(rtrim(number_format($section->weight, 2), '0'), '.') }} ct</div>
                    <table class="lines">
                        <thead><tr><th style="width:18%;">Serial</th><th style="width:52%;">Name</th><th style="width:18%;" class="right">Weight (ct)</th><th style="width:12%;" class="right">Pieces</th></tr></thead>
                        <tbody>
                        @foreach ($section->rows as $row)
                            <tr><td>{{ $row['serial'] }}</td><td>{{ $row['name'] }}</td><td class="right">{{ $row['weight'] === null ? '—' : rtrim(rtrim(number_format((float) $row['weight'], 2), '0'), '.') }}</td><td class="right">{{ number_format((int) $row['pieces']) }}</td></tr>
                        @endforeach
                            <tr class="section-total"><td colspan="2">SECTION TOTAL</td><td class="right">{{ rtrim(rtrim(number_format($section->weight, 2), '0'), '.') }}</td><td class="right">{{ number_format($section->pieces) }}</td></tr>
                        </tbody>
                    </table>
                </div>
                @endif
            @endforeach
        @empty
            <div style="padding:30px; text-align:center; border:1px solid #aaa;">No gems recorded yet.</div>
        @endforelse
    @else
        <div class="grand-lines">
            {{ number_format($grand->gems) }} gem row(s) &nbsp;·&nbsp;
            {{ number_format($grand->pieces) }} piece(s) &nbsp;·&nbsp;
            {{ rtrim(rtrim(number_format($grand->weight, 2), '0'), '.') }} ct
            @if ($mode === 'day' && $date)
                &nbsp;·&nbsp; {{ \Carbon\Carbon::parse($date)->format('d M Y') }}
            @endif
        </div>
        @foreach ($sections as $section)
            @if ($section->rows->isNotEmpty())
            <div class="section">
                <div class="section-title">{{ $section->title }} &nbsp;·&nbsp; {{ number_format($section->count) }} · {{ number_format($section->pieces) }} pc · {{ rtrim(rtrim(number_format($section->weight, 2), '0'), '.') }} ct</div>
                <table class="lines">
                    <thead><tr><th style="width:18%;">Serial</th><th style="width:52%;">Name</th><th style="width:18%;" class="right">Weight (ct)</th><th style="width:12%;" class="right">Pieces</th></tr></thead>
                    <tbody>
                    @foreach ($section->rows as $row)
                        <tr><td>{{ $row['serial'] }}</td><td>{{ $row['name'] }}</td><td class="right">{{ $row['weight'] === null ? '—' : rtrim(rtrim(number_format((float) $row['weight'], 2), '0'), '.') }}</td><td class="right">{{ number_format((int) $row['pieces']) }}</td></tr>
                    @endforeach
                        <tr class="section-total"><td colspan="2">SECTION TOTAL</td><td class="right">{{ rtrim(rtrim(number_format($section->weight, 2), '0'), '.') }}</td><td class="right">{{ number_format($section->pieces) }}</td></tr>
                    </tbody>
                </table>
            </div>
            @endif
        @endforeach
    @endif
@endif
</body>
</html>
