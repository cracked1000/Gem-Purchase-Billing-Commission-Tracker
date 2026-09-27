@extends('layouts.app')

@section('title', 'Sellers')

@section('content')
@php
    use App\Support\Lkr;
    use App\Support\WaNumber;
@endphp

<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:24px;">
    <div>
        <h1 class="page-title">👤 Sellers</h1>
        <p class="page-subtitle">Everyone who sells gems through your office.</p>
    </div>
    <a href="{{ route('sellers.create') }}" class="btn btn-gold">+ Add Seller</a>
</div>

@if ($duplicateGroupCount > 0)
    <div style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); color:#fca5a5; border-radius:12px; padding:12px 16px; margin-bottom:16px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; font-size:13px;">
        <span>⚠️ {{ $duplicateSellersCount }} seller profiles share phone numbers with other profiles.</span>
        <a href="{{ route('sellers.duplicates') }}" class="btn btn-danger btn-sm" style="text-decoration:none;">Open Duplicates Panel</a>
    </div>
@endif

{{-- Live Search — searches EVERY seller in the database, all pages at once --}}
<form method="GET" action="{{ route('sellers.index') }}" class="card" style="padding:16px; margin-bottom:16px;" id="sellerSearchForm">
    <div style="position:relative;">
        <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:16px; pointer-events:none;">🔍</span>
        <input type="text" name="search" id="sellerSearch" value="{{ $search }}"
               placeholder="Search all sellers by name or phone number…"
               class="form-input" style="padding-left:38px;">
    </div>
    <div style="font-size:11.5px; color:var(--text-muted); margin-top:8px; display:flex; align-items:center; flex-wrap:wrap; gap:8px;">
        <span id="searchCount">
            @if ($search !== '')
                {{ $sellers->total() }} seller(s) match “{{ $search }}” — searched across all pages
            @else
                Showing all {{ $sellers->total() }} sellers
            @endif
        </span>
        @if ($search !== '')
            <a href="{{ route('sellers.index') }}" class="btn btn-outline btn-sm">✕ Clear</a>
        @endif
    </div>
</form>

{{-- Sellers Table --}}
<div class="card" style="overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="data-table" id="sellersTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>WhatsApp / Phone</th>
                    <th class="right">Bills</th>
                    <th class="right">Total Sold</th>
                    @unless ($wipeMode ?? false)<th class="right">Net Paid / Payable</th>@endunless
                    <th class="right"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sellers as $seller)
                    <tr>
                        <td>
                            <a href="{{ route('sellers.show', $seller) }}" class="link-gold font-semibold" style="font-size:14px;">
                                {{ $seller->name }}
                            </a>
                        </td>
                        <td>
                            @if ($seller->phone)
                                <a href="https://wa.me/{{ WaNumber::normalize($seller->phone) }}" target="_blank"
                                   style="color:#25D366; text-decoration:none; font-weight:500; display:inline-flex; align-items:center; gap:5px;"
                                   onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                                    {{ $seller->phone }}
                                </a>
                            @else
                                <span style="color:var(--text-muted);">—</span>
                            @endif
                        </td>
                        <td class="right">{{ $seller->invoices_count }}</td>
                        <td class="right font-semibold text-gold">{{ Lkr::format($seller->invoices_sum_subtotal) }}</td>
                        @unless ($wipeMode ?? false)<td class="right">{{ Lkr::format($seller->invoices_sum_net_payable) }}</td>@endunless
                        <td class="right" style="white-space:nowrap;">
                            <a href="{{ route('sellers.show', $seller) }}" class="btn btn-outline btn-sm">View</a>
                            <a href="{{ route('invoices.create', ['seller_id' => $seller->id]) }}" class="btn btn-gold btn-sm" style="margin-left:4px;">+ Bill</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:40px;">
                        @if ($search !== '')
                            No sellers match “{{ $search }}”.
                        @else
                            No sellers yet — <a href="{{ route('sellers.create') }}" class="link-gold">add your first seller</a>.
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($sellers->hasPages())
        <div style="padding:14px 20px; border-top:1px solid var(--border);">{{ $sellers->onEachSide(2)->links('partials.pagination') }}</div>
    @endif
</div>

@push('scripts')
<script>
// Live search: submit the form automatically shortly after the user stops
// typing. Search happens on the server across ALL sellers, not just the page.
(function () {
    var input = document.getElementById('sellerSearch');
    var form  = document.getElementById('sellerSearchForm');
    var timer = null;

    if (!input || !form) return;

    input.addEventListener('input', function () {
        if (timer) clearTimeout(timer);
        timer = setTimeout(function () { form.submit(); }, 400);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (timer) clearTimeout(timer);
            form.submit();
        }
    });
})();
</script>
@endpush
@endsection
