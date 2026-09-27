@extends('layouts.app')

@section('title', $seller->name)

@section('content')
@php
    use App\Support\Lkr;
    use App\Support\WaNumber;
@endphp

<div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom:24px;">
    <div>
        <a href="{{ route('sellers.index') }}" style="font-size:12px; color:var(--text-muted); text-decoration:none; display:inline-flex; align-items:center; gap:4px; margin-bottom:8px;"
           onmouseover="this.style.color='var(--gold-light)'" onmouseout="this.style.color='var(--text-muted)'">
            ← Back to Sellers
        </a>
        <h1 class="page-title">{{ $seller->name }}</h1>
        <div style="display:flex; align-items:center; gap:12px; margin-top:6px; flex-wrap:wrap;">
            @if ($seller->phone)
                <a href="https://wa.me/{{ WaNumber::normalize($seller->phone) }}" target="_blank"
                   style="display:inline-flex; align-items:center; gap:5px; color:#25D366; text-decoration:none; font-size:13px; font-weight:500;">
                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                    {{ $seller->phone }}
                </a>
                @if ($linkedSellers->count() > 1)
                    <a href="{{ route('sellers.duplicates') }}"
                       style="display:inline-flex; align-items:center; gap:4px; color:#fca5a5; text-decoration:none; font-size:12px; border:1px solid rgba(239,68,68,0.3); background:rgba(239,68,68,0.08); padding:3px 8px; border-radius:20px;">
                        ⚠️ {{ $linkedSellers->count() }} profiles share this number
                    </a>
                @endif
            @else
                <span style="color:var(--text-muted); font-size:13px;">No phone saved</span>
            @endif
            @if ($seller->notes)
                <span style="color:var(--text-muted); font-size:13px;">· {{ $seller->notes }}</span>
            @endif
        </div>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="{{ route('invoices.create', ['seller_id' => $seller->id]) }}" class="btn btn-gold">+ New Bill</a>
        <a href="{{ route('sellers.edit', $seller) }}" class="btn btn-outline">✏️ Edit</a>
        <form method="POST" action="{{ route('sellers.destroy', $seller) }}"
              onsubmit="return confirm('Archive this seller? This is only allowed when they have no bills.');">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger">🗃️ Archive</button>
        </form>
    </div>
</div>

{{-- Stats Cards --}}
<div style="display:grid; grid-template-columns:repeat(5, 1fr); gap:12px; margin-bottom:24px;">
    <div class="stat-card gold">
        <div class="stat-label">💰 Total Sold</div>
        <div class="stat-value" style="font-size:20px;">{{ Lkr::compact($totals['volume']) }}</div>
        <div class="stat-sub">{{ Lkr::format($totals['volume']) }}</div>
    </div>
    @unless ($wipeMode ?? false)
        <div class="stat-card amber">
            <div class="stat-label">⚖️ Commission</div>
            <div class="stat-value" style="font-size:20px;">{{ Lkr::compact($totals['commission']) }}</div>
            <div class="stat-sub">Your earnings</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">💳 Net to Seller</div>
            <div class="stat-value" style="font-size:20px;">{{ Lkr::compact($totals['net']) }}</div>
            <div class="stat-sub">After commission</div>
        </div>
    @endunless
    <div class="stat-card">
        <div class="stat-label">💎 Pieces</div>
        <div class="stat-value" style="font-size:20px;">{{ number_format($totals['pieces']) }}</div>
        <div class="stat-sub">Total stones</div>
    </div>
    @unless ($wipeMode ?? false)
        <div class="stat-card red">
            <div class="stat-label">⏳ Pending Payout</div>
            <div class="stat-value" style="font-size:20px;">{{ Lkr::compact($totals['pending']) }}</div>
            <div class="stat-sub">Still owed</div>
        </div>
    @endunless
</div>

{{-- Finance line (exact, no abbreviations) --}}
<div class="card" style="padding:16px; margin-bottom:16px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
    <div>
        <div class="stat-label">🎯 Outstanding to pay (exact)</div>
        <div class="stat-value" style="font-size:24px; {{ (float) $finance->outstanding > 0 ? 'color:#fca5a5;' : 'color:#4ade80;' }}">{{ Lkr::format($finance->outstanding) }}</div>
        <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">
            Sold {{ Lkr::format($finance->sold) }} · Our commission {{ Lkr::format($finance->commission) }} · Net to seller {{ Lkr::format($finance->net) }} · Paid {{ Lkr::format($finance->paid) }}
        </div>
    </div>
    <a href="{{ route('finance.show', $seller) }}" class="btn btn-gold">🏦 Open Finance Ledger</a>
</div>

{{-- Bills Table --}}
<div class="card" style="overflow:hidden;">
    <div class="card-header">
        <h2>📋 Bills from {{ $seller->name }}</h2>
        <span style="font-size:12px; color:var(--text-muted);">{{ $seller->invoices->count() }} bill(s) total</span>
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Bill #</th>
                    <th>Date (SLT)</th>
                    <th class="right">Pieces</th>
                    <th class="right">Total</th>
                    @unless ($wipeMode ?? false)
                        <th class="right">Commission</th>
                        <th class="right">Net to Seller</th>
                    @endunless
                    <th class="center">Payout</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($seller->invoices as $invoice)
                    <tr>
                        <td><a href="{{ route('invoices.show', $invoice) }}" class="link-gold font-semibold">{{ $invoice->invoice_number }}</a></td>
                        <td class="muted">{{ $invoice->sold_at->format('d M Y') }}</td>
                        <td class="right">{{ $invoice->total_pieces }}</td>
                        <td class="right font-semibold">{{ Lkr::format($invoice->subtotal) }}</td>
                        @unless ($wipeMode ?? false)
                            <td class="right text-amber">{{ Lkr::format($invoice->commission_amount) }}</td>
                            <td class="right font-semibold">{{ Lkr::format($invoice->net_payable) }}</td>
                        @endunless
                        <td class="center">
                            @if ($invoice->isPaid())
                                <span class="badge badge-paid">Paid</span>
                            @else
                                <span class="badge badge-pending">Pending</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:40px;">No bills from this seller yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Same number = same person: every profile + every bill under this number --}}
@if ($linkedSellers->count() > 1)
    <div class="card" style="margin-top:24px; overflow:hidden;">
        <div class="card-header">
            <h2>🔗 Same number — {{ $linkedSellers->count() }} profiles share {{ $seller->phone }}</h2>
            <a href="{{ route('sellers.duplicates') }}" class="btn btn-outline btn-sm">Manage duplicates</a>
        </div>

        <div style="padding:14px 20px; display:flex; flex-wrap:wrap; gap:8px; border-bottom:1px solid var(--border);">
            @foreach ($linkedSellers as $linked)
                <a href="{{ route('sellers.show', $linked) }}"
                   class="{{ $linked->id === $seller->id ? 'badge badge-gold' : 'badge' }}"
                   style="text-decoration:none; font-size:12px; padding:6px 12px; {{ $linked->id === $seller->id ? '' : 'border:1px solid var(--border-strong); color:var(--text-secondary);' }}">
                    {{ $linked->name }} · {{ $linked->invoices_count }} bills
                </a>
            @endforeach
        </div>

        <div style="padding:12px 20px; font-size:12px; color:var(--text-muted); background:rgba(255,255,255,0.02); border-bottom:1px solid var(--border);">
            All {{ $numberTotals['bills'] }} bill(s) under this number ·
            {{ Lkr::format($numberTotals['volume']) }} total ·
            {{ number_format($numberTotals['pieces']) }} pieces
        </div>

        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Date (SLT)</th>
                        <th>Profile</th>
                        <th class="right">Pieces</th>
                        <th class="right">Total</th>
                        <th class="center">Payout</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($numberInvoices as $invoice)
                        <tr>
                            <td><a href="{{ route('invoices.show', $invoice) }}" class="link-gold font-semibold">{{ $invoice->invoice_number }}</a></td>
                            <td class="muted">{{ $invoice->sold_at->format('d M Y') }}</td>
                            <td>
                                @if ($invoice->seller_id !== $seller->id)
                                    <a href="{{ route('sellers.show', $invoice->seller) }}" class="link-gold" style="font-size:12.5px;">{{ $invoice->seller?->name }}</a>
                                @else
                                    <span style="font-size:12.5px; color:var(--text-secondary);">This profile</span>
                                @endif
                            </td>
                            <td class="right">{{ $invoice->total_pieces }}</td>
                            <td class="right font-semibold">{{ Lkr::format($invoice->subtotal) }}</td>
                            <td class="center">
                                @if ($invoice->isPaid())
                                    <span class="badge badge-paid">Paid</span>
                                @else
                                    <span class="badge badge-pending">Pending</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:40px;">No bills under this number.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
