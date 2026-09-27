@extends('layouts.app')

@section('title', 'Archived Bills')

@section('content')
@php use App\Support\Lkr; @endphp
<div class="page-heading">
    <div>
        <h1 class="page-title">Archived Bills</h1>
        <p class="page-subtitle">Archived bills are hidden from totals but remain recoverable.</p>
    </div>
    <a href="{{ route('invoices.index') }}" class="btn btn-outline">← Transactions</a>
</div>

<div class="card" style="overflow:hidden;">
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Bill #</th><th>Seller</th><th>Sold date</th><th>Archived</th><th class="right">Amount</th><th></th></tr></thead>
            <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td class="font-mono font-semibold">{{ $invoice->invoice_number }}</td>
                    <td>{{ $invoice->seller->name ?? '—' }}</td>
                    <td class="muted">{{ $invoice->sold_at->format('d M Y') }}</td>
                    <td class="muted">{{ $invoice->deleted_at->format('d M Y, h:i A') }}</td>
                    <td class="right">{{ Lkr::format($invoice->subtotal) }}</td>
                    <td class="right">
                        <form method="POST" action="{{ route('invoices.restore', $invoice->id) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-gold btn-sm">Restore</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="padding:40px; text-align:center; color:var(--text-muted);">No archived bills.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($invoices->hasPages())<div style="padding:14px 20px;">{{ $invoices->onEachSide(2)->links('partials.pagination') }}</div>@endif
</div>
@endsection
