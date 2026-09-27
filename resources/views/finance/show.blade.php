@extends('layouts.app')

@section('title', 'Finance — '.$seller->name)

@section('content')
@php
    use App\Support\Lkr;
@endphp

<div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
    <div>
        <a href="{{ route('finance.index') }}" style="font-size:12px; color:var(--text-muted); text-decoration:none; display:inline-flex; align-items:center; gap:4px; margin-bottom:8px;">← Back to Finance</a>
        <h1 class="page-title">🏦 {{ $seller->name }}</h1>
        @if ($seller->phone)<div style="font-size:12px; color:var(--text-muted);">{{ $seller->phone }}</div>@endif
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="{{ route('sellers.show', $seller) }}" class="btn btn-outline">👤 Seller Profile</a>
    </div>
</div>

{{-- Balance summary (exact) --}}
<div class="card" style="padding:16px; margin-bottom:16px;">
    <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:12px;">
        <div><div class="stat-label">Total Sold</div><div class="stat-value" style="font-size:18px;">{{ Lkr::format($summary->sold) }}</div></div>
        <div><div class="stat-label">Our Commission</div><div class="stat-value" style="font-size:18px;">{{ Lkr::format($summary->commission) }}</div></div>
        <div><div class="stat-label">Net to Seller</div><div class="stat-value" style="font-size:18px;">{{ Lkr::format($summary->net) }}</div></div>
        <div><div class="stat-label">Already Paid</div><div class="stat-value" style="font-size:18px;">{{ Lkr::format($summary->paid) }}</div></div>
    </div>
    <div style="margin-top:16px; padding-top:14px; border-top:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
        <div>
            <div class="stat-label">Outstanding to pay</div>
            <div class="stat-value" style="font-size:28px; {{ $summary->is_overpaid ? 'color:#4ade80;' : 'color:#fca5a5;' }}">{{ Lkr::format($summary->outstanding) }}</div>
            @if ($summary->is_overpaid)
                <div style="font-size:12px; color:#4ade80;">You have PAID MORE than owed. This is a credit balance.</div>
            @elseif ((float) $summary->outstanding == 0)
                <div style="font-size:12px; color:#4ade80;">Fully settled — nothing owed on the bills here.</div>
            @else
                <div style="font-size:12px; color:var(--text-muted);">Still owed to {{ $seller->name }}.</div>
            @endif
        </div>
    </div>
</div>

{{-- Record a payment --}}
<div class="card" style="padding:16px; margin-bottom:16px;">
    <div class="card-header" style="padding:0 0 12px; border-bottom:1px solid var(--border);"><h2>💸 Record a Payment</h2></div>
    <form method="POST" action="{{ route('finance.payments.store', $seller) }}" style="margin-top:12px;">
        @csrf
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px;">
            <div>
                <label class="form-label">Amount (LKR)</label>
                <input type="number" name="amount" step="0.01" min="0.01" required class="form-input" placeholder="50000.00">
            </div>
            <div>
                <label class="form-label">Paid at</label>
                <input type="datetime-local" name="paid_at" required value="{{ \Carbon\Carbon::now()->format('Y-m-d\TH:i') }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Method</label>
                <select name="method" class="form-select">
                    <option value="">— select —</option>
                    <option value="cash">Cash</option>
                    <option value="bank">Bank Transfer</option>
                    <option value="cheque">Cheque</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label class="form-label">Reference</label>
                <input type="text" name="reference" maxlength="120" class="form-input" placeholder="Cheque / bank ref">
            </div>
        </div>
        <div style="margin-top:12px;">
            <label class="form-label">Notes (optional)</label>
            <textarea name="notes" rows="2" maxlength="1000" class="form-textarea" placeholder="What this payment covers…"></textarea>
        </div>
        <div style="margin-top:12px;">
            <button type="submit" class="btn btn-gold">💾 Record Payment</button>
            <span style="font-size:11.5px; color:var(--text-muted); margin-left:8px;">A backup snapshot is taken before saving, so nothing is lost.</span>
        </div>
    </form>
</div>

{{-- Bills from this seller --}}
<div class="card" style="margin-bottom:16px; overflow:hidden;">
    <div class="card-header"><h2>🧾 Bills from {{ $seller->name }}</h2><span style="font-size:12px; color:var(--text-muted);">{{ $seller->invoices->count() }} bill(s)</span></div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Bill #</th>
                    <th>Date</th>
                    <th class="right">Pieces</th>
                    <th class="right">Total Sold</th>
                    <th class="right">Commission</th>
                    <th class="right">Net to Seller</th>
                    <th class="center">Payout</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($seller->invoices as $i)
                    <tr>
                        <td><a href="{{ route('invoices.show', $i) }}" class="link-gold font-semibold">{{ $i->invoice_number }}</a></td>
                        <td class="muted">{{ $i->sold_at->format('d M Y') }}</td>
                        <td class="right">{{ $i->total_pieces }}</td>
                        <td class="right">{{ Lkr::format($i->subtotal) }}</td>
                        <td class="right">{{ Lkr::format($i->commission_amount) }}</td>
                        <td class="right font-semibold">{{ Lkr::format($i->net_payable) }}</td>
                        <td class="center">@if ($i->isPaid())<span class="badge badge-paid">Paid</span>@else<span class="badge badge-pending">Pending</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="padding:30px; text-align:center; color:var(--text-muted);">No bills.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Payment history --}}
<div class="card" style="overflow:hidden;">
    <div class="card-header"><h2>💳 Payment History</h2><span style="font-size:12px; color:var(--text-muted);">{{ $seller->payments->count() }} payment(s) · total {{ Lkr::format($summary->paid) }}</span></div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date &amp; Time</th>
                    <th class="right">Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Notes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($seller->payments as $p)
                    <tr>
                        <td class="muted">{{ $p->paid_at->format('d M Y, h:i A') }}</td>
                        <td class="right font-semibold">{{ Lkr::format($p->amount) }}</td>
                        <td>{{ $p->method ? ucfirst($p->method) : '—' }}</td>
                        <td class="muted">{{ $p->reference ?: '—' }}</td>
                        <td class="muted" style="max-width:260px;">{{ $p->notes ?: '—' }}</td>
                        <td class="right">
                            <form method="POST" action="{{ route('finance.payments.destroy', $p) }}"
                                  onsubmit="return confirm('Remove this payment? A backup is taken first so nothing is lost.');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:30px; text-align:center; color:var(--text-muted);">No payments recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
