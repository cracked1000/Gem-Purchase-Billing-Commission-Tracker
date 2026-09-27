@extends('layouts.app')

@section('title', 'New Bill')

@section('content')
<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:24px;">
    <div>
        <h1 class="page-title">✏️ Create New Bill</h1>
        <p class="page-subtitle">Record a gem purchase from a seller.</p>
    </div>
    <a href="{{ route('invoices.index') }}" class="btn btn-outline">← Back to Transactions</a>
</div>

@include('invoices._form', [
    'action'            => route('invoices.store'),
    'method'            => 'POST',
    'sellers'           => $sellers,
    'commissionDefault' => $defaultCommission,
])
@endsection
