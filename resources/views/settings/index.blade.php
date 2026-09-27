@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div class="page-heading">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Application display preferences.</p>
    </div>
</div>

<div style="max-width:680px;">
    <form method="POST" action="{{ route('settings.update') }}" class="card" style="padding:22px;">
        @csrf @method('PATCH')
        <div style="display:flex; align-items:center; justify-content:space-between; gap:20px;">
            <div>
                <div style="font-size:16px; font-weight:700;">Wipe</div>
                <div style="font-size:12px; color:var(--text-muted); margin-top:5px; line-height:1.5;">
                    Use a simplified external-viewing layout and restricted export choices.
                </div>
                <div style="font-size:11px; margin-top:8px; color:{{ $enabled ? '#4ade80' : 'var(--text-muted)' }};">
                    {{ $enabled ? 'ON' : 'OFF' }}
                </div>
            </div>
            <label style="position:relative; width:58px; height:32px; display:inline-block; cursor:pointer; flex-shrink:0;">
                <input type="checkbox" name="wipe" value="1" @checked($enabled)
                       onchange="this.form.submit()" style="opacity:0; width:0; height:0;">
                <span style="position:absolute; inset:0; border-radius:20px; background:{{ $enabled ? '#22c55e' : 'var(--border-strong)' }};"></span>
                <span style="position:absolute; width:26px; height:26px; top:3px; left:{{ $enabled ? '29px' : '3px' }}; border-radius:50%; background:#fff; box-shadow:0 1px 4px rgba(0,0,0,.35);"></span>
            </label>
        </div>
        <noscript><button type="submit" class="btn btn-gold" style="margin-top:16px;">Save</button></noscript>
    </form>
</div>
@endsection
