@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php use App\Support\Lkr; @endphp

<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:28px;">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Welcome back — here's your gem trading overview.</p>
    </div>
    <a href="{{ route('invoices.create') }}" class="btn btn-gold">+ New Bill</a>
</div>

{{-- ── Primary Stats ── --}}
<div class="grid-4" style="margin-bottom:20px;">
    <div class="stat-card gold">
        <div class="stat-label">Total Purchased</div>
        <div class="stat-value">{{ Lkr::compact($stats['volume']) }}</div>
        <div class="stat-sub">{{ Lkr::format($stats['volume']) }}</div>
    </div>
    @unless ($wipeMode ?? false)
        <div class="stat-card">
            <div class="stat-label">Net Paid to Sellers</div>
            <div class="stat-value">{{ Lkr::compact($stats['payable']) }}</div>
            <div class="stat-sub">After brokerage</div>
        </div>
        <div class="stat-card red">
            <div class="stat-label">Still Owed to Sellers</div>
            <div class="stat-value">{{ Lkr::compact($stats['pending_amount']) }}</div>
            <div class="stat-sub">{{ $stats['pending_count'] }} bill(s) unpaid</div>
        </div>
    @endunless
    <div class="stat-card">
        <div class="stat-label">Total Bills</div>
        <div class="stat-value">{{ number_format($stats['invoices']) }}</div>
        <div class="stat-sub">Transactions recorded</div>
    </div>
</div>

{{-- Finance Department summary (exact values) --}}
<div class="card" style="padding:16px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px;">
    <div>
        <div class="stat-label">🏦 Finance Department — Outstanding to pay (exact)</div>
        <div class="stat-value" style="font-size:24px; {{ (float) $finance->outstanding > 0 ? 'color:#fca5a5;' : 'color:#4ade80;' }}">{{ Lkr::format($finance->outstanding) }}</div>
        <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">
            Net to sellers {{ Lkr::format($finance->net) }} · Already paid {{ Lkr::format($finance->paid) }}
        </div>
    </div>
    <a href="{{ route('finance.index') }}" class="btn btn-gold">🏦 Open Finance Department</a>
</div>

<div class="grid-4" style="margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-label">Pieces Purchased</div>
        <div class="stat-value">{{ number_format($stats['pieces']) }}</div>
        <div class="stat-sub">Total stones / pieces</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Sellers</div>
        <div class="stat-value">{{ number_format($stats['sellers']) }}</div>
        <div class="stat-sub">Registered sellers</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Avg. Bill Value</div>
        <div class="stat-value">{{ $stats['invoices'] ? Lkr::compact($stats['volume'] / $stats['invoices']) : '—' }}</div>
        <div class="stat-sub">Per transaction</div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Bills Paid Out</div>
        <div class="stat-value">{{ $stats['invoices'] - $stats['pending_count'] }}</div>
        <div class="stat-sub">of {{ $stats['invoices'] }} total</div>
    </div>
</div>

{{-- ── Internal earnings overview ── --}}
@unless ($wipeMode ?? false)
<div class="card" style="margin-bottom:28px; overflow:hidden;">
    <div class="card-header" style="background:rgba(255,255,255,0.02);">
        <h2>💼 Commission Overview</h2>
        <a href="{{ route('invoices.index') }}" class="btn btn-ghost btn-sm">All Bills →</a>
    </div>
    <div style="padding:20px; display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:16px;">
        <div style="padding:16px 18px; background:rgba(255,255,255,0.03); border:1px solid var(--border); border-radius:10px;">
            <div style="font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); margin-bottom:8px;">Total Earned</div>
            <div style="font-size:22px; font-weight:700; color:var(--text-primary); line-height:1;">{{ Lkr::compact($stats['commission']) }}</div>
            <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">{{ Lkr::format($stats['commission']) }}</div>
        </div>
        <div style="padding:16px 18px; background:rgba(255,255,255,0.03); border:1px solid var(--border); border-radius:10px;">
            <div style="font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); margin-bottom:8px;">On Paid Bills</div>
            <div style="font-size:22px; font-weight:700; color:var(--text-primary); line-height:1;">{{ Lkr::compact($stats['commission_paid'] ?? 0) }}</div>
            <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">Collected</div>
        </div>
        <div style="padding:16px 18px; background:rgba(255,255,255,0.03); border:1px solid var(--border); border-radius:10px;">
            <div style="font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); margin-bottom:8px;">On Pending Bills</div>
            <div style="font-size:22px; font-weight:700; color:var(--text-secondary); line-height:1;">{{ Lkr::compact(($stats['commission'] ?? 0) - ($stats['commission_paid'] ?? 0)) }}</div>
            <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">Awaiting settlement</div>
        </div>
        <div style="padding:16px 18px; background:rgba(255,255,255,0.03); border:1px solid var(--border); border-radius:10px;">
            <div style="font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); margin-bottom:8px;">Avg Commission %</div>
            <div style="font-size:22px; font-weight:700; color:var(--text-primary); line-height:1;">
                {{ $stats['invoices'] && $stats['volume'] ? rtrim(rtrim(number_format(($stats['commission'] / $stats['volume']) * 100, 2), '0'), '.') : '—' }}%
            </div>
            <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">Effective rate</div>
        </div>
    </div>
</div>
@endunless

{{-- ── Charts + Top Sellers ── --}}
<div style="display:grid; grid-template-columns:2fr 1fr; gap:20px; margin-bottom:24px;">
    {{-- Monthly Chart --}}
    <div class="card">
        <div class="card-header">
            <h2>📈 Purchases — Last 12 Months (LKR)</h2>
        </div>
        <div style="padding:20px;">
            <div style="position:relative; height:240px;"><canvas id="monthlyChart"></canvas></div>
        </div>
    </div>

    {{-- Top Sellers --}}
    <div class="card">
        <div class="card-header">
            <h2>🏆 Top Sellers</h2>
            <a href="{{ route('sellers.index') }}" class="btn btn-ghost btn-sm">View all →</a>
        </div>
        <div style="padding:16px;">
            @php $max = max(1, (float) $topSellers->max('invoices_sum_subtotal')); @endphp
            <div style="display:flex; flex-direction:column; gap:14px;">
                @forelse ($topSellers as $seller)
                    <div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                            <a href="{{ route('sellers.show', $seller) }}" class="link-gold" style="font-size:13px; font-weight:600;">{{ $seller->name }}</a>
                            <span style="font-size:12px; color:var(--text-muted);">{{ Lkr::compact($seller->invoices_sum_subtotal) }}</span>
                        </div>
                        <div style="height:4px; background:rgba(255,255,255,0.06); border-radius:10px; overflow:hidden;">
                            <div style="height:100%; border-radius:10px; background:var(--text-secondary); width:{{ round(((float)$seller->invoices_sum_subtotal / $max) * 100) }}%; transition:width 1s ease;"></div>
                        </div>
                        <div style="font-size:11px; color:var(--text-muted); margin-top:3px;">{{ $seller->invoices_count }} bill(s)</div>
                    </div>
                @empty
                    <p style="color:var(--text-muted); font-size:13px;">No purchases recorded yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ── Recent Bills (no commission) ── --}}
<div class="card">
    <div class="card-header">
        <h2>🕒 Recent Bills</h2>
        <a href="{{ route('invoices.index') }}" class="btn btn-ghost btn-sm">View all →</a>
    </div>
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
                    <th class="center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentInvoices as $invoice)
                    <tr>
                        <td><a href="{{ route('invoices.show', $invoice) }}" class="link-gold font-semibold">{{ $invoice->invoice_number }}</a></td>
                        <td class="muted">{{ $invoice->sold_at->format('d M Y') }}</td>
                        <td>{{ $invoice->seller->name ?? '—' }}</td>
                        <td class="right">{{ $invoice->total_pieces }}</td>
                        <td class="right font-semibold">{{ Lkr::format($invoice->subtotal) }}</td>
                        @unless ($wipeMode ?? false)<td class="right">{{ Lkr::format($invoice->net_payable) }}</td>@endunless
                        <td class="center">
                            @if ($invoice->isPaid())
                                <span class="badge badge-paid">✓ Paid</span>
                            @else
                                <span class="badge badge-pending">Unpaid</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:40px;">No bills yet — <a href="{{ route('invoices.create') }}" class="link-gold">create your first bill</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    const ctx = document.getElementById('monthlyChart');
    if (ctx) {
        const isLight = document.getElementById('htmlRoot').classList.contains('light');
        const barColor    = isLight ? 'rgba(10,10,10,0.7)'  : 'rgba(242,242,242,0.7)';
        const barBorder   = isLight ? 'rgba(10,10,10,0.9)'  : 'rgba(242,242,242,0.9)';
        const gridColor   = isLight ? 'rgba(0,0,0,0.06)'    : 'rgba(255,255,255,0.05)';
        const tickColor   = isLight ? '#999999'              : '#484848';
        const tooltipBg   = isLight ? '#ffffff'              : '#1a1a1a';
        const tooltipBorder = isLight ? 'rgba(0,0,0,0.1)'   : 'rgba(255,255,255,0.1)';
        const tooltipTitle  = isLight ? '#0a0a0a'            : '#f2f2f2';
        const tooltipBody   = isLight ? '#505050'            : '#909090';

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: @json($monthlyLabels),
                datasets: [{
                    label: 'Purchases (LKR)',
                    data: @json($monthlyValues),
                    backgroundColor: barColor,
                    borderColor: barBorder,
                    borderWidth: 1,
                    borderRadius: 6,
                    maxBarThickness: 40,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: tooltipBg,
                        borderColor: tooltipBorder,
                        borderWidth: 1,
                        titleColor: tooltipTitle,
                        bodyColor: tooltipBody,
                        callbacks: {
                            label: (c) => ' LKR ' + new Intl.NumberFormat('en-LK', {minimumFractionDigits: 2}).format(c.parsed.y)
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: gridColor },
                        ticks: { color: tickColor, font: { size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: {
                            color: tickColor,
                            font: { size: 11 },
                            callback: (v) => 'LKR ' + new Intl.NumberFormat('en', {notation: 'compact'}).format(v)
                        }
                    }
                }
            }
        });
    }
</script>
@endpush
