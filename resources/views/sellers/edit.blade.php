@extends('layouts.app')

@section('title', 'Edit Seller')

@section('content')
<div style="max-width:560px;">
    <div style="margin-bottom:24px;">
        <h1 class="page-title">✏️ Edit Seller — {{ $seller->name }}</h1>
        <p class="page-subtitle">Update seller name, phone, or notes.</p>
    </div>

    <form method="POST" action="{{ route('sellers.update', $seller) }}" class="card" style="padding:24px;">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label class="form-label">Name <span style="color:#fca5a5;">*</span></label>
            <input type="text" name="name" data-uppercase value="{{ old('name', $seller->name) }}" required maxlength="150" class="form-input">
        </div>

        <div class="form-group">
            <label class="form-label">WhatsApp / Phone</label>
            <input type="text" name="phone" value="{{ old('phone', $seller->phone) }}" maxlength="32" class="form-input" placeholder="077 123 4567">
        </div>

        <div class="form-group">
            <label class="form-label">Notes (optional)</label>
            <textarea name="notes" data-uppercase rows="3" maxlength="1000" class="form-textarea">{{ old('notes', $seller->notes) }}</textarea>
        </div>

        <div style="display:flex; gap:12px; padding-top:8px;">
            <button type="submit" class="btn btn-gold">💾 Update Seller</button>
            <a href="{{ route('sellers.show', $seller) }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>
@endsection
