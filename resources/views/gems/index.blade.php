@extends('layouts.app')

@section('title', 'Gem Register')

@section('content')
<div class="page-heading">
    <div>
        <h1 class="page-title">Gem Register</h1>
        <p class="page-subtitle">Every purchased gem or lot, ordered by serial number.</p>
    </div>
</div>

<form method="GET" action="{{ route('gems.index') }}" class="card" style="padding:16px; margin-bottom:16px;">
    <div class="form-label">Choose Columns</div>
    <div style="display:grid; grid-template-columns:repeat(5, minmax(140px, 1fr)); gap:8px 14px; margin:10px 0 16px;">
        @foreach ($available as $key => $definition)
            <label style="display:flex; align-items:center; gap:7px; font-size:12px; cursor:pointer;">
                <input type="checkbox" name="columns[]" value="{{ $key }}" @checked(in_array($key, $selected, true))>
                {{ $definition['label'] }}
            </label>
        @endforeach
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <button type="submit" class="btn btn-gold">Apply View</button>
        <button type="submit" formaction="{{ route('gems.pdf') }}" class="btn btn-outline">Download PDF</button>
        <button type="submit" formaction="{{ route('gems.excel') }}" class="btn btn-outline">Download Excel</button>
        <a href="{{ route('gems.index') }}" class="btn btn-ghost">Default Columns</a>
    </div>
</form>

<div class="card" style="overflow:hidden;">
    <div class="card-header">
        <h2>Purchased Gems</h2>
        <span style="font-size:12px; color:var(--text-muted);">{{ number_format($totals->gems) }} gem row(s)</span>
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table">
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
                                    LKR {{ number_format((float) $value, 2) }}
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
                    <tr><td colspan="{{ count($columns) }}" style="padding:40px; text-align:center; color:var(--text-muted);">No purchased gems have been recorded.</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot>
                    <tr style="border-top:2px solid var(--border-strong); background:var(--bg-card-hover); font-weight:700;">
                        @foreach ($columns as $key => $definition)
                            <td class="{{ in_array($definition['type'], ['integer', 'decimal', 'money'], true) ? 'right' : '' }}">
                                @if ($key === 'pieces')
                                    {{ number_format($totals->pieces) }}
                                @elseif ($key === 'weight')
                                    {{ number_format($totals->weight, 2) }}
                                @elseif ($key === 'bought_price')
                                    LKR {{ number_format($totals->bought_price, 2) }}
                                @elseif ($key === array_key_first($columns))
                                    ALL-TIME TOTAL
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
