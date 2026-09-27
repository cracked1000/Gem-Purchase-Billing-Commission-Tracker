@extends('layouts.app')

@section('title', 'Transactions')

@section('content')
@php use App\Support\Lkr; @endphp

<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:24px;">
    <div>
        <h1 class="page-title">📋 Transactions</h1>
        <p class="page-subtitle">Every bill ever generated — your permanent record.</p>
    </div>
    <a href="{{ route('invoices.create') }}" class="btn btn-gold">+ New Bill</a>
</div>

{{-- Search / Filter --}}
<form method="GET" action="{{ route('invoices.index') }}" class="card" style="padding:16px; margin-bottom:16px;">
    <div style="display:grid; grid-template-columns:2fr 1fr 1fr auto auto; gap:12px; align-items:end;">
        <div>
            <label class="form-label" style="margin-bottom:4px;">Search</label>
            <input type="text" name="q" value="{{ request('q') }}"
                   placeholder="Bill #, seller name, or gem…" class="form-input">
        </div>
        <div>
            <label class="form-label" style="margin-bottom:4px;">Seller</label>
            <select name="seller_id" class="form-select">
                <option value="">All sellers</option>
                @foreach ($sellers as $seller)
                    <option value="{{ $seller->id }}" @selected((string) request('seller_id') === (string) $seller->id)>{{ $seller->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label" style="margin-bottom:4px;">Payout</label>
            <select name="status" class="form-select">
                <option value="">Any status</option>
                <option value="pending" @selected(request('status') === 'pending')>⏳ Pending</option>
                <option value="paid"    @selected(request('status') === 'paid')>✅ Paid</option>
            </select>
        </div>
        <button type="submit" class="btn btn-gold">Filter</button>
        <a href="{{ route('invoices.index') }}" class="btn btn-outline">Reset</a>
    </div>
</form>

{{-- Table --}}
<div class="card" style="overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Bill #</th>
                    <th>Date (SLT)</th>
                    <th>Seller</th>
                    <th class="right">Pcs</th>
                    <th class="right">Purchased</th>
                    @unless ($wipeMode ?? false)<th class="right">Net to Seller</th>@endunless
                    <th class="center">Paid?</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td>
                            <a href="{{ route('invoices.show', $invoice) }}" class="link-gold font-semibold">{{ $invoice->invoice_number }}</a>
                        </td>
                        <td class="muted">{{ $invoice->sold_at->format('d M Y') }}</td>
                        <td>
                            <a href="{{ route('sellers.show', $invoice->seller_id) }}" style="color:var(--text-primary); text-decoration:none; font-weight:600;" onmouseover="this.style.color='var(--text-secondary)'" onmouseout="this.style.color='var(--text-primary)'">
                                {{ $invoice->seller->name ?? '—' }}
                            </a>
                        </td>
                        <td class="right">{{ $invoice->total_pieces }}</td>
                        <td class="right font-semibold">{{ Lkr::format($invoice->subtotal) }}</td>
                        @unless ($wipeMode ?? false)<td class="right">{{ Lkr::format($invoice->net_payable) }}</td>@endunless
                        <td class="center">
                            <form method="POST" action="{{ route('invoices.status', $invoice) }}" style="display:inline;">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $invoice->isPaid() ? 'pending' : 'paid' }}">
                                <button type="submit" title="{{ $invoice->isPaid() ? 'Mark as unpaid' : 'Mark as paid' }}"
                                    style="background:none; border:none; cursor:pointer; padding:4px;">
                                    @if ($invoice->isPaid())
                                        <span style="font-size:20px; line-height:1;">✅</span>
                                    @else
                                        <span style="font-size:20px; line-height:1; opacity:0.3;">⬜</span>
                                    @endif
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:40px;">Nothing found. <a href="{{ route('invoices.create') }}" class="link-gold">Create a bill</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($invoices->hasPages())
        <div style="padding:14px 20px; border-top:1px solid var(--border);">{{ $invoices->onEachSide(2)->links('partials.pagination') }}</div>
    @endif
</div>
@endsection
