@extends('layouts.app')

@section('title', 'Seller Duplicates')

@section('content')
@php
    use App\Support\Lkr;
@endphp

<div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:24px;">
    <div>
        <h1 class="page-title">🧩 Seller Duplicates</h1>
        <p class="page-subtitle">Profiles that share the same phone number. Merge them into one profile — nothing gets deleted.</p>
    </div>
    <a href="{{ route('sellers.index') }}" class="btn btn-outline">← Sellers</a>
</div>

{{-- Scan summary --}}
<div class="card" style="padding:16px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
    <div>
        @if (count($groups) === 0)
            <span style="color:#86efac;">✅ No duplicate numbers found — every active seller has a unique phone number right now.</span>
        @else
            <span style="color:#fca5a5;">
                ⚠️ {{ count($groups) }} phone number(s) are shared by {{ $profileCount }} profiles, covering {{ $billCount }} bill(s).
            </span>
        @endif
    </div>
    <a href="{{ route('sellers.duplicates') }}" class="btn btn-outline btn-sm">↻ Re-scan</a>
</div>

@foreach ($groups as $group)
    <div class="card" style="margin-bottom:20px; overflow:hidden;">
        <div class="card-header">
            <h2>📞 {{ $group['display_phone'] }}</h2>
            <span style="font-size:12px; color:var(--text-muted);">
                {{ count($group['sellers']) }} profiles · {{ $group['bill_count'] }} bills · {{ Lkr::format($group['total_sold']) }} sold
            </span>
        </div>

        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:44px;">Keep</th>
                        <th>Profile</th>
                        <th>Phone</th>
                        <th class="right">Bills</th>
                        <th class="right">Total Sold</th>
                        <th>Added</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group['sellers'] as $index => $profile)
                        <tr>
                            <td style="text-align:center;">
                                <input type="radio" name="primary_{{ $loop->parent->index }}"
                                       form="merge-{{ $loop->parent->index }}"
                                       value="{{ $profile->id }}"
                                       @checked($profile->id === $group['primary']->id)
                                       style="accent-color:var(--text-primary); width:16px; height:16px; cursor:pointer;">
                            </td>
                            <td>
                                <a href="{{ route('sellers.show', $profile) }}" class="link-gold font-semibold" style="font-size:14px;">
                                    {{ $profile->name }}
                                </a>
                            </td>
                            <td class="muted">{{ $profile->phone }}</td>
                            <td class="right">{{ $profile->bill_count }}</td>
                            <td class="right font-semibold">{{ Lkr::format($profile->total_sold) }}</td>
                            <td class="muted">{{ $profile->created_at?->format('d M Y') }}</td>
                            <td class="right">
                                @if ($profile->id === $group['primary']->id)
                                    <span class="badge badge-gold">Suggested primary</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="padding:14px 20px; border-top:1px solid var(--border); display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <form id="merge-{{ $loop->index }}" method="POST" action="{{ route('sellers.duplicates.merge') }}"
                  onsubmit="return confirm('Merge these profiles? Every bill moves to the profile you kept. The other profiles are archived (recoverable), never deleted.');">
                @csrf
                @foreach ($group['sellers'] as $profile)
                    <input type="hidden" name="all_ids[]" value="{{ $profile->id }}">
                @endforeach
                <button type="submit" class="btn btn-gold btn-sm">🔗 Merge into selected profile</button>
            </form>
            <span style="font-size:11.5px; color:var(--text-muted);">
                Bills + receipt PDFs move under the profile you pick. The other profiles are archived and can be restored later.
            </span>
        </div>
    </div>
@endforeach
@endsection
