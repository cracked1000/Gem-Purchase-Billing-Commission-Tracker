@extends('layouts.app')

@section('title', 'Gem Export')

@section('content')
@php
    use App\Support\Lkr;
    $query = collect(['mode' => $mode]);
    if ($mode === 'day' && $date) { $query['date'] = $date; }
    $query['layout'] = $layout;
    $query = $query->all();
@endphp

<div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
    <div>
        <h1 class="page-title">📦 Gem Export</h1>
        <p class="page-subtitle">Categorized gem list for your buyer / export house.</p>
    </div>
    <div style="display:flex; flex-wrap:wrap; gap:8px;">
        <a href="{{ route('export.registry') }}" class="btn btn-outline">🧭 Gem Registry</a>
        <a href="{{ route('export.pdf', $query ?? []) }}" class="btn btn-outline">⬇️ PDF</a>
        <a href="{{ route('export.excel', $query ?? []) }}" class="btn btn-gold">⬇️ Excel</a>
    </div>
</div>

{{-- Mode + layout toggle --}}
<div class="card" style="padding:14px 16px; margin-bottom:16px;">
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        <a href="{{ route('export.index', ['mode' => 'all', 'layout' => $layout]) }}" class="btn {{ $mode === 'all' ? 'btn-gold' : 'btn-outline' }}">All-time</a>
        <a href="{{ route('export.index', ['mode' => 'day', 'date' => $days->keys()->last() ?? null, 'layout' => $layout]) }}" class="btn {{ $mode === 'day' ? 'btn-gold' : 'btn-outline' }}">By day</a>
        <a href="{{ route('export.index', ['mode' => 'daily', 'layout' => $layout]) }}" class="btn {{ $mode === 'daily' ? 'btn-gold' : 'btn-outline' }}">Every day</a>
    </div>

    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:10px; padding-top:10px; border-top:1px solid var(--border);">
        <span style="font-size:12.5px; color:var(--text-muted); font-weight:600;">Sort:</span>
        <a href="{{ route('export.index', ['mode' => $mode, 'date' => $date, 'layout' => 'serial']) }}" class="btn {{ $layout === 'serial' ? 'btn-gold' : 'btn-outline' }}" title="Show all gems as one continuous ascending-serial list">🔢 By serial number</a>
        <a href="{{ route('export.index', ['mode' => $mode, 'date' => $date, 'layout' => 'category']) }}" class="btn {{ $layout === 'category' ? 'btn-gold' : 'btn-outline' }}" title="Show gems grouped by category">🗂️ By category</a>
        @if ($mode === 'day' && $date)
            <span style="font-size:12px; color:var(--text-muted); margin-left:6px;">Showing <strong>{{ \Carbon\Carbon::parse($date)->format('l, d F Y') }}</strong>.</span>
        @endif
    </div>

    {{-- Day navigation (only in day mode) --}}
    @if ($mode === 'day' && $date)
        @php
            $dateList = $days->keys()->values();
            $idx = $dateList->search($date);
            $prev = $idx !== false && $idx > 0 ? $dateList[$idx - 1] : null;
            $next = $idx !== false && $idx < $dateList->count() - 1 ? $dateList[$idx + 1] : null;
        @endphp
        <form method="GET" action="{{ route('export.index') }}" style="display:flex; gap:6px; align-items:center; margin-top:8px;">
            <input type="hidden" name="mode" value="day">
            <input type="hidden" name="layout" value="{{ $layout }}">
            <button type="submit" name="date" value="{{ $prev }}" class="btn btn-outline btn-sm" @disabled(! $prev)>‹ Prev</button>
            <select name="date" class="form-select" style="padding:6px 10px; font-size:12.5px;" onchange="this.form.submit()">
                @foreach ($days as $d)
                    <option value="{{ $d->date }}" @selected($d->date === $date)>{{ \Carbon\Carbon::parse($d->date)->format('d M Y') }} · {{ $d->gems }} gems</option>
                @endforeach
            </select>
            <button type="submit" name="date" value="{{ $next }}" class="btn btn-outline btn-sm" @disabled(! $next)>Next ›</button>
        </form>
    @endif
</div>

{{-- Overall summary card --}}
@php $usingFlat = ($layout === 'serial'); @endphp

@if ($mode === 'daily')
    <div class="card" style="padding:14px 16px; margin-bottom:16px; display:flex; flex-wrap:wrap; gap:14px; align-items:center;">
        <span style="font-size:13px; font-weight:600;">🧾 {{ $grand->gems }} gem(s) total</span>
        <span style="font-size:12.5px; color:var(--text-muted);">{{ number_format($grand->pieces) }} pieces</span>
        <span style="font-size:12.5px; color:var(--text-muted);">{{ rtrim(rtrim(number_format($grand->weight, 2), '0'), '.') }} ct</span>
        <span style="font-size:12.5px; color:var(--text-muted);">across {{ $daily->count() }} day(s)</span>
        @if ($review_count > 0)
            <span style="font-size:12.5px; color:#fca5a5;">⚠️ {{ number_format($review_count) }} need review</span>
        @endif
    </div>

    @forelse ($daily as $day)
        <div style="margin-bottom:28px;">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
                <h2 style="font-size:17px; font-weight:700;">🗓️ {{ $day->date->format('l, d F Y') }}</h2>
                <span style="font-size:12px; color:var(--text-muted);">
                    {{ number_format($day->grand->gems) }} gem(s) ·
                    {{ number_format($day->grand->pieces) }} pc ·
                    {{ rtrim(rtrim(number_format($day->grand->weight, 2), '0'), '.') }} ct
                </span>
            </div>
            @if ($usingFlat)
                @include('export._flat', ['rows' => $day->flat])
            @else
                @include('export._sections', ['sections' => $day->sections])
            @endif
        </div>
    @empty
        <div class="card" style="padding:30px; text-align:center; color:var(--text-muted);">No gems recorded yet.</div>
    @endforelse
@else
    <div class="card" style="padding:16px; margin-bottom:16px;">
        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px;">
            <div>
                <div class="stat-label">Gem Rows</div>
                <div class="stat-value" style="font-size:22px;">{{ number_format($grand->gems) }}</div>
            </div>
            <div>
                <div class="stat-label">Pieces</div>
                <div class="stat-value" style="font-size:22px;">{{ number_format($grand->pieces) }}</div>
            </div>
            <div>
                <div class="stat-label">Weight (ct)</div>
                <div class="stat-value" style="font-size:22px;">{{ rtrim(rtrim(number_format($grand->weight, 2), '0'), '.') }}</div>
            </div>
        </div>
        @if ($review_count > 0)
            <div style="margin-top:10px; font-size:12.5px; color:#fca5a5;">
                ⚠️ {{ number_format($review_count) }} gem(s) need a category — assign them below.
            </div>
        @endif
    </div>

    @if ($usingFlat)
        @include('export._flat', ['rows' => $flat])
    @else
        @include('export._sections', ['sections' => $sections])
    @endif
@endif
@endsection
