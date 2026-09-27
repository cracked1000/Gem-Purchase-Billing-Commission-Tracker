@extends('layouts.app')

@section('title', 'Payin — '.$seller->name)

@section('content')
@php
    use App\Support\Lkr;
@endphp

<div class="page-heading">
    <div>
        <a href="{{ route('payin.index') }}" style="font-size:12px; color:var(--text-muted); text-decoration:none; display:inline-flex; align-items:center; gap:4px; margin-bottom:8px;">← Back to Payin</a>
        <h1 class="page-title">💵 {{ $seller->name }}</h1>
        @if ($seller->phone)<div style="font-size:12px; color:var(--text-muted);">{{ $seller->phone }}</div>@endif
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="{{ route('finance.show', $seller) }}" class="btn btn-outline">🏦 Finance ledger</a>
        <a href="{{ route('sellers.show', $seller) }}" class="btn btn-outline">👤 Seller profile</a>
    </div>
</div>

<div class="card" style="padding:16px; margin-bottom:16px;">
    <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:12px;">
        <div><div class="stat-label">Total Sold</div><div class="stat-value" style="font-size:18px;">{{ Lkr::format($summary->sold) }}</div></div>
        <div><div class="stat-label">Commission Cut</div><div class="stat-value" style="font-size:18px;">{{ Lkr::format($summary->commission) }}</div></div>
        <div><div class="stat-label">Net to Seller</div><div class="stat-value" style="font-size:18px;">{{ Lkr::format($summary->net) }}</div></div>
        <div><div class="stat-label">Already Paid</div><div class="stat-value" style="font-size:18px;">{{ Lkr::format($summary->paid) }}</div></div>
    </div>
    <div style="margin-top:16px; padding-top:14px; border-top:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
        <div>
            <div class="stat-label">Remaining to pay</div>
            <div class="stat-value" style="font-size:28px; {{ $summary->is_overpaid || (float) $summary->outstanding == 0 ? 'color:#4ade80;' : 'color:#fca5a5;' }}">{{ Lkr::format($summary->outstanding) }}</div>
            @if ($summary->status === 'partial')
                <div style="font-size:12px; color:#fca5a5; margin-top:4px;">Partial payout — still owed to {{ $seller->name }}.</div>
            @elseif ($summary->status === 'overpaid')
                <div style="font-size:12px; color:#4ade80; margin-top:4px;">Paid more than owed. This is a credit balance.</div>
            @elseif ($summary->status === 'settled')
                <div style="font-size:12px; color:#4ade80; margin-top:4px;">Fully paid — nothing remaining on these bills.</div>
            @else
                <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">No payment recorded yet.</div>
            @endif
        </div>
        <div>
            @if ($summary->status === 'partial')
                <span class="badge badge-pending">Partial</span>
            @elseif ($summary->status === 'overpaid')
                <span class="badge badge-paid">Overpaid</span>
            @elseif ($summary->status === 'settled')
                <span class="badge badge-paid">Fully paid</span>
            @else
                <span class="badge badge-pending">Unpaid</span>
            @endif
        </div>
    </div>
</div>

<div class="card" style="padding:16px; margin-bottom:16px;">
    <div class="card-header" style="padding:0 0 12px; border-bottom:1px solid var(--border);"><h2>💸 Record another payment</h2></div>
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
            <span style="font-size:11.5px; color:var(--text-muted); margin-left:8px;">A backup snapshot is taken first. Remaining updates immediately.</span>
        </div>
    </form>
</div>

<div style="margin-bottom:10px;">
    <h2 style="font-size:16px; font-weight:700;">🧾 What they sold</h2>
    <p style="font-size:12.5px; color:var(--text-muted); margin-top:4px;">Every bill from this seller, with the gems on it. Open a bill for the full receipt.</p>
</div>

@forelse ($seller->invoices as $invoice)
    <section class="card" style="overflow:hidden; margin-bottom:14px;">
        <div style="padding:12px 16px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center;">
            <div>
                <a href="{{ route('invoices.show', $invoice) }}" class="link-gold font-semibold" style="font-size:15px;">Bill {{ $invoice->invoice_number }}</a>
                <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">
                    {{ $invoice->sold_at->format('d M Y') }}
                    · {{ $invoice->items->count() }} gem(s)
                    · {{ $invoice->total_pieces }} pc
                    · Commission {{ rtrim(rtrim(number_format((float) $invoice->commission_percent, 2), '0'), '.') }}%
                </div>
            </div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                @if ($invoice->isPaid())
                    <span class="badge badge-paid">Bill marked paid</span>
                @else
                    <span class="badge badge-pending">Bill pending</span>
                @endif
                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-outline btn-sm">View bill</a>
            </div>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Serial</th>
                        <th>Stone / Lot</th>
                        <th class="right">Weight</th>
                        <th class="right">Pcs</th>
                        <th class="right">Rate</th>
                        <th class="right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoice->items as $item)
                        <tr>
                            <td class="font-mono">{{ $item->serial_number ?: '—' }}</td>
                            <td>
                                <strong>{{ $item->description }}</strong>
                                @if ($item->stone_notes)
                                    <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">{{ $item->stone_notes }}</div>
                                @endif
                            </td>
                            <td class="right">{{ $item->carats !== null && (float) $item->carats > 0 ? rtrim(rtrim(number_format((float) $item->carats, 2), '0'), '.').' ct' : '—' }}</td>
                            <td class="right">{{ number_format($item->pieces) }}</td>
                            <td class="right">{{ Lkr::format($item->unit_price) }}{{ $item->carats !== null && (float) $item->carats > 0 ? '/ct' : '/pc' }}</td>
                            <td class="right font-semibold">{{ Lkr::format($item->amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted" style="padding:16px; text-align:center;">No gem rows on this bill.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr style="border-top:2px solid var(--border-strong); background:var(--bg-card-hover);">
                        <td colspan="5"><strong>Bill totals</strong></td>
                        <td class="right"><strong>{{ Lkr::format($invoice->subtotal) }}</strong></td>
                    </tr>
                    <tr style="background:var(--bg-card-hover);">
                        <td colspan="5" class="muted">Commission cut</td>
                        <td class="right">{{ Lkr::format($invoice->commission_amount) }}</td>
                    </tr>
                    <tr style="background:var(--bg-card-hover);">
                        <td colspan="5"><strong>Net to seller</strong></td>
                        <td class="right"><strong>{{ Lkr::format($invoice->net_payable) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
@empty
    <div class="card" style="padding:40px; text-align:center; color:var(--text-muted); margin-bottom:16px;">No bills from this seller.</div>
@endforelse

<div class="card" style="overflow:hidden;">
    <div class="card-header"><h2>💳 Payment history</h2><span style="font-size:12px; color:var(--text-muted);">{{ $seller->payments->count() }} payment(s) · total {{ Lkr::format($summary->paid) }}</span></div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date &amp; Time</th>
                    <th class="right">Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Notes</th>
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
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding:30px; text-align:center; color:var(--text-muted);">No payments recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
