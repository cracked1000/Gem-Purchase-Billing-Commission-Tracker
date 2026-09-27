@extends('layouts.app')

@section('title', 'Add Seller')

@section('content')
<div style="max-width:560px;">
    <div style="margin-bottom:24px;">
        <h1 class="page-title">👤 Add Seller</h1>
        <p class="page-subtitle">Enter the seller's name and phone for WhatsApp bill sending.</p>
    </div>

    <form method="POST" action="{{ route('sellers.store') }}" class="card" style="padding:24px;">
        @csrf
        @if (! empty($returnUrl))
            <input type="hidden" name="return_url" value="{{ $returnUrl }}">
        @endif

        <div class="form-group">
            <label class="form-label">Name <span style="color:#fca5a5;">*</span></label>
            <input type="text" name="name" data-uppercase value="{{ old('name') }}" required maxlength="150"
                   class="form-input" placeholder="e.g. M. F. Rizan">
        </div>

        <div class="form-group">
            <label class="form-label">WhatsApp / Phone</label>
            <input type="text" name="phone" value="{{ old('phone') }}" maxlength="32"
                   class="form-input" placeholder="077 123 4567">
            <div style="font-size:11.5px; color:var(--text-muted); margin-top:5px;">
                Local numbers (07x…) are auto-converted for WhatsApp. International (+94…) works too.
                Each seller keeps <strong>one unique number</strong> — a number already in use can't be saved again.
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Notes (optional)</label>
            <textarea name="notes" data-uppercase rows="3" maxlength="1000" class="form-textarea"
                      placeholder="Bank details, usual stones sold, etc.">{{ old('notes') }}</textarea>
        </div>

        <div style="display:flex; gap:12px; padding-top:8px;">
            <button type="submit" class="btn btn-gold">💾 Save Seller</button>
            <a href="{{ url()->previous() }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>
@endsection
