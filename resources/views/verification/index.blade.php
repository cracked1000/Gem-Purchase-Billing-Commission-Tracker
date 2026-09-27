@extends('layouts.app')

@section('title', 'Further Verification')

@section('content')
<div class="page-heading">
    <div>
        <h1 class="page-title">Further Verification</h1>
        <p class="page-subtitle">Only gems marked for action or carrying a stone note appear here.</p>
    </div>
</div>

<div style="display:grid; gap:14px;">
@forelse ($items as $item)
    @php $actions = $item->verification_actions ?? []; @endphp
    <form method="POST" action="{{ route('verification.update', $item) }}" class="card" style="padding:16px;">
        @csrf @method('PATCH')
        <div style="display:grid; grid-template-columns:1.2fr 1fr 1.2fr 1.5fr auto; gap:16px; align-items:start;">
            <div>
                <div class="form-label">Gem</div>
                <div style="font-size:14px; font-weight:700;">{{ $item->description }}</div>
                <div style="font-size:11.5px; color:var(--text-muted); margin-top:4px;">
                    Serial: <strong>{{ $item->serial_number ?: '—' }}</strong> ·
                    {{ $item->carats !== null && (float) $item->carats > 0 ? rtrim(rtrim(number_format((float) $item->carats, 2), '0'), '.').' ct' : 'No weight' }} ·
                    {{ $item->pieces }} pcs
                </div>
            </div>
            <div>
                <div class="form-label">Receipt</div>
                <a href="{{ route('invoices.show', $item->invoice) }}" class="link-gold">{{ $item->invoice->invoice_number }}</a>
                <div style="font-size:11.5px; color:var(--text-muted); margin-top:4px;">{{ $item->invoice->sold_at->format('d M Y') }}</div>
            </div>
            <div>
                <div class="form-label">Seller</div>
                <div style="font-size:13px; font-weight:600;">{{ $item->invoice->seller->name ?? '—' }}</div>
                <div style="font-size:11.5px; color:var(--text-muted); margin-top:4px;">{{ $item->invoice->seller->phone ?? '' }}</div>
            </div>
            <div>
                <div class="form-label">Required Actions</div>
                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                    @foreach (['recheck' => 'RECHECK', 'repolish' => 'REPOLISH', 'recut' => 'RECUT'] as $value => $label)
                        <label style="display:flex; align-items:center; gap:5px; font-size:11.5px; cursor:pointer;">
                            <input type="checkbox" name="verification_actions[]" value="{{ $value }}" @checked(in_array($value, $actions, true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <label class="form-label">Stone Note</label>
                <textarea name="stone_notes" rows="2" maxlength="1000" data-uppercase class="form-textarea" placeholder="STONE NOTE">{{ $item->stone_notes }}</textarea>
                <div style="font-size:10px; color:var(--text-muted); margin-top:5px;">Clear all actions and the note to remove this gem from this section.</div>
            </div>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <button type="submit" class="btn btn-gold">Save</button>
                <a href="{{ route('invoices.edit', $item->invoice) }}" class="btn btn-outline">Edit Bill</a>
            </div>
        </div>
    </form>
@empty
    <div class="card" style="padding:44px; text-align:center;">
        <div style="font-size:24px; margin-bottom:10px;">✓</div>
        <div style="font-weight:700;">No gems require further verification</div>
        <div style="font-size:12px; color:var(--text-muted); margin-top:5px;">Gems set to NONE without a stone note stay out of this section.</div>
    </div>
@endforelse
</div>
@endsection
