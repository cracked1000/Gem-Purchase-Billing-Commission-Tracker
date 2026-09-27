@extends('layouts.app')

@section('title', 'Gem Registry')

@section('content')
@php
    use App\Support\Lkr;
    use App\Services\GemExportService;
@endphp

<div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
    <div>
        <h1 class="page-title">🧭 Gem Registry</h1>
        <p class="page-subtitle">
            Every gem in the system. Assign any gem to a category here — it only affects the Export, never your bills, receipts or pricing.
        </p>
    </div>
    <a href="{{ route('export.index') }}" class="btn btn-outline">← Back to Gem Export</a>
</div>

{{-- Serial integrity check --}}
<div class="card" style="margin-bottom:18px; overflow:hidden;">
    <div class="card-header">
        <h2>🔢 Serial Number Integrity Check</h2>
        <span style="font-size:12px; color:var(--text-muted); display:flex; align-items:center; gap:10px;">
            <form method="GET" action="{{ route('export.registry') }}" style="display:flex; gap:6px; align-items:center;">
                @if ($filter !== '')<input type="hidden" name="category" value="{{ $filter }}">@endif
                <label style="font-size:11.5px;">Check up to</label>
                <input type="number" name="checktop" value="{{ $check['top'] }}" min="1"
                       class="form-input" style="width:90px; padding:5px 8px; font-size:12px;">
                <button type="submit" class="btn btn-gold btn-sm">Check</button>
                @if ($checktop !== null)
                    <a href="{{ route('export.registry', $filter !== '' ? ['category' => $filter] : []) }}" class="btn btn-outline btn-sm">✕ Reset</a>
                @endif
            </form>
            @if ($check['consistent'])
                <span class="badge badge-paid">✓ double-checked</span>
            @else
                <span class="badge badge-pending">⚠ re-check advised</span>
            @endif
        </span>
    </div>

    <div style="padding:14px 20px; display:flex; flex-wrap:wrap; gap:14px; align-items:center; border-bottom:1px solid var(--border);">
        <span style="font-size:13px; font-weight:600;">{{ number_format($check['total_rows']) }} serial row(s)</span>
        <span style="font-size:12.5px; color:var(--text-muted);">{{ number_format($check['unique_numeric']) }} unique number(s)</span>
        <span style="font-size:12.5px; color:var(--text-muted);">range 1 → {{ number_format($check['top']) }}</span>
        @if ($check['missing_count'] > 0)
            <span style="font-size:12.5px; color:#fca5a5; font-weight:600;">⬜ {{ number_format($check['missing_count']) }} missing</span>
        @else
            <span style="font-size:12.5px; color:#86efac;">⬜ 0 missing</span>
        @endif
        @if ($check['duplicates']->count() > 0)
            <span style="font-size:12.5px; color:#fca5a5; font-weight:600;">🔁 {{ number_format($check['duplicates']->count()) }} duplicate(s)</span>
        @else
            <span style="font-size:12.5px; color:#86efac;">🔁 0 duplicates</span>
        @endif
        @if ($check['non_numeric_count'] > 0)
            <span style="font-size:12.5px; color:var(--text-secondary);">🔣 {{ number_format($check['non_numeric_count']) }} non-numeric</span>
        @endif
    </div>

    @if ($check['missing_count'] > 0)
        <div style="padding:14px 20px; border-bottom:1px solid var(--border);">
            <div style="font-size:12px; font-weight:600; color:#fca5a5; margin-bottom:6px;">⬜ Missing serial numbers ({{ number_format($check['missing_count']) }})</div>
            <div style="font-size:12px; line-height:1.7; color:var(--text-secondary); font-family:monospace;">
                @foreach ($check['missing'] as $i => $num)
                    {{ $num }}{{ $i < count($check['missing']) - 1 ? ', ' : '' }}
                @endforeach
            </div>
        </div>
    @endif

    @if ($check['duplicates']->count() > 0)
        <div style="padding:14px 20px; border-bottom:1px solid var(--border);">
            <div style="font-size:12px; font-weight:600; color:#fca5a5; margin-bottom:6px;">🔁 Duplicate serial numbers ({{ number_format($check['duplicates']->count()) }})</div>
            <div style="overflow-x:auto;">
                <table class="data-table" style="font-size:12px;">
                    <thead>
                        <tr><th>Serial</th><th class="center">Rows</th><th>Details</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($check['duplicates'] as $dup)
                            <tr>
                                <td class="font-semibold">{{ $dup['serial'] }}</td>
                                <td class="center">{{ $dup['count'] }}</td>
                                <td style="font-size:11.5px; color:var(--text-secondary);">
                                    @foreach ($dup['rows'] as $r)
                                        <div>{{ $r['serial'] }} · {{ $r['name'] }} · {{ $r['receipt'] }} · {{ $r['sold_display'] }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($check['non_numeric_count'] > 0)
        <div style="padding:14px 20px; border-bottom:1px solid var(--border);">
            <div style="font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;">🔣 Non-numeric serials ({{ number_format($check['non_numeric_count']) }}) — these can't be lined up in 1 → {{ number_format($check['top']) }}</div>
            <div style="font-size:11.5px; line-height:1.7; color:var(--text-secondary); font-family:monospace;">
                @foreach ($check['non_numeric'] as $r)
                    <span style="margin-right:8px;">{{ $r['serial'] }}</span>
                @endforeach
            </div>
        </div>
    @endif

    @if ($check['missing_count'] === 0 && $check['duplicates']->count() === 0 && $check['non_numeric_count'] === 0)
        <div style="padding:14px 20px; color:#86efac; font-size:12.5px;">✅ Every number from 1 to {{ number_format($check['top']) }} is present, with no duplicates.</div>
    @endif
</div>

{{-- Summary + filter --}}
<div class="card" style="padding:14px 16px; margin-bottom:16px; display:flex; flex-wrap:wrap; gap:14px; align-items:center;">
    <span style="font-size:13px; font-weight:600;">💎 {{ number_format($grand->gems) }} gem(s)</span>
    <span style="font-size:12.5px; color:var(--text-muted);">{{ number_format($grand->pieces) }} pieces</span>
    <span style="font-size:12.5px; color:var(--text-muted);">{{ rtrim(rtrim(number_format($grand->weight, 2), '0'), '.') }} ct</span>

    <form method="GET" action="{{ route('export.registry') }}" style="display:flex; gap:6px; align-items:center; margin-left:auto; flex-wrap:wrap;">
        <select name="category" class="form-select" style="padding:6px 10px; font-size:12.5px; max-width:260px;" onchange="this.form.submit()">
            <option value="">All categories</option>
            @foreach ($buckets as $bkey => $bucket)
                <option value="{{ $bkey }}" @selected($filter === $bkey)>{{ $bucket['title'] }}</option>
            @endforeach
        </select>
        @if ($filter !== '')
            <a href="{{ route('export.registry') }}" class="btn btn-outline btn-sm">✕ Clear</a>
        @endif
    </form>
</div>

{{-- Registry table --}}
<div class="card" style="overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="right" style="width:11%;">Serial</th>
                    <th style="width:28%;">Name</th>
                    <th class="right">Weight (ct)</th>
                    <th class="right">Pieces</th>
                    <th style="width:11%;">Date</th>
                    <th style="width:24%;">Current category</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="right font-semibold">{{ $row['serial'] }}</td>
                        <td>
                            {{ $row['name'] }}
                            @if ($row['has_override'])
                                <span class="badge badge-gold" style="font-size:10px; margin-left:5px;">manual</span>
                            @endif
                        </td>
                        <td class="right">{{ $row['weight'] === null ? '—' : rtrim(rtrim(number_format((float) $row['weight'], 2), '0'), '.') }}</td>
                        <td class="right">{{ number_format((int) $row['pieces']) }}</td>
                        <td class="muted">{{ $row['sold_display'] }}</td>
                        <td>
                            <form method="POST" action="{{ route('export.assign') }}" style="display:flex; gap:5px; align-items:center; margin:0;">
                                @csrf
                                <input type="hidden" name="item_id" value="{{ $row['id'] }}">
                                <span style="font-size:11px; color:var(--text-muted);">{{ \Illuminate\Support\Str::limit($row['category'], 32) }}</span>
                                <select name="category" class="form-select" style="padding:4px 6px; font-size:11.5px; flex:1;">
                                    <option value="auto">Auto</option>
                                    @foreach (GemExportService::assignOptionsFor($row['category_key']) as $bkey => $btitle)
                                        <option value="{{ $bkey }}" @selected($row['override'] === $bkey)>{{ $btitle }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-gold btn-sm">Assign</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:30px; text-align:center; color:var(--text-muted);">No gems in this category.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
