@extends('layouts.app')

@section('title', 'Edit ' . $invoice->invoice_number)

@section('content')
<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:24px;">
    <div>
        <h1 class="page-title">✏️ Edit Bill {{ $invoice->invoice_number }}</h1>
        <p class="page-subtitle">{{ $invoice->sold_at->format('d M Y') }} · {{ $invoice->seller->name ?? '—' }}</p>
    </div>
    <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-outline">← Back to Bill</a>
</div>

@include('invoices._form', [
    'action'            => route('invoices.update', $invoice),
    'method'            => 'PUT',
    'sellers'           => $sellers,
    'invoice'           => $invoice,
    'commissionDefault' => config('gemdesk.default_commission'),
])
@endsection
