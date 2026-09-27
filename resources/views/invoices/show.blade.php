@extends('layouts.app')

@section('title', $invoice->invoice_number)

@section('content')
@php
    use App\Support\Lkr;
    use App\Support\AmountInWords;
    $pct     = rtrim(rtrim(number_format((float) $invoice->commission_percent, 2), '0'), '.');
    $sltDate = $invoice->sold_at->setTimezone('Asia/Colombo')->format('d F Y');
@endphp

{{-- ─── Page Header ─── --}}
<div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom:20px;" class="no-print">
    <div>
        <h1 class="page-title">Bill {{ $invoice->invoice_number }}</h1>
        <p class="page-subtitle">📅 {{ $sltDate }} &nbsp;·&nbsp; {{ $invoice->seller->name ?? '—' }} &nbsp;·&nbsp; {{ $invoice->total_pieces }} piece(s)</p>
    </div>
    <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
        <button onclick="window.print()" class="btn btn-outline btn-sm">🖨 Print</button>
        <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-outline btn-sm">✏️ Edit</a>
    </div>
</div>

@php
    use App\Support\WaNumber;
    $sellerPhone    = $invoice->seller->phone ?? null;
    $sellerWaNumber = $sellerPhone ? WaNumber::normalize($sellerPhone) : null;
    $sellerWaLink   = $sellerWaNumber ? 'https://wa.me/' . $sellerWaNumber : null;
    $sellerName     = $invoice->seller->name ?? 'Seller';
    $waAppLink      = $waAppLink ?? ($sellerWaNumber ? 'whatsapp://send?phone=' . $sellerWaNumber : null);
    $waWebLink      = $waWebLink ?? ($sellerWaNumber ? 'https://wa.me/' . $sellerWaNumber : null);
    $receiptSaved   = session('receipt_saved');
@endphp

@if ($receiptSaved)
    <div class="no-print" style="margin-bottom:16px; padding:14px 16px; border-radius:10px; font-size:13px;
        background:rgba(34,197,94,0.08); border:1px solid rgba(34,197,94,0.3); color:#86efac;">
        <div style="font-weight:700; margin-bottom:4px;">✅ Receipt saved to the Receipts folder</div>
        <div style="color:var(--text-secondary);">
            <code style="font-size:12px;">{{ $receiptSaved['filename'] }}</code> &nbsp;in&nbsp;
            <code style="font-size:12px;">{{ $receiptSaved['folder'] }}</code>
            @if (session('wa_open') && $waAppLink)
                <br>💬 Opening WhatsApp chat with <strong>{{ $sellerName }}</strong> — just <strong>drag &amp; drop the PDF</strong> into the chat.
            @endif
        </div>
    </div>
@endif

{{-- ─── Action Cards Row ─── --}}
<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px;" class="no-print">

    {{-- Paid Status Card --}}
    <div class="card" style="padding:18px;">
        <div style="font-size:11px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:1.2px; margin-bottom:12px;">💳 Seller Payout Status</div>
        <form method="POST" action="{{ route('invoices.status', $invoice) }}" id="statusForm">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="{{ $invoice->isPaid() ? 'pending' : 'paid' }}">
            <label style="display:flex; align-items:center; gap:14px; cursor:pointer; user-select:none;">
                {{-- Custom checkbox --}}
                <div style="position:relative; width:52px; height:28px; flex-shrink:0;" onclick="document.getElementById('statusForm').submit()">
                    <div style="position:absolute; inset:0; border-radius:14px; background:{{ $invoice->isPaid() ? 'linear-gradient(135deg,#22c55e,#16a34a)' : 'rgba(255,255,255,0.1)' }}; border:1px solid {{ $invoice->isPaid() ? '#22c55e' : 'rgba(255,255,255,0.15)' }}; transition:all 0.2s;"></div>
                    <div style="position:absolute; top:3px; left:{{ $invoice->isPaid() ? '27px' : '3px' }}; width:22px; height:22px; border-radius:50%; background:#fff; box-shadow:0 1px 4px rgba(0,0,0,0.3); transition:left 0.2s;"></div>
                </div>
                <div>
                    @if ($invoice->isPaid())
                        <div style="font-weight:700; font-size:15px; color:#86efac;">✓ Paid to Seller</div>
                        <div style="font-size:11.5px; color:var(--text-muted); margin-top:2px;">Click to mark as unpaid</div>
                    @else
                        <div style="font-weight:700; font-size:15px; color:var(--text-secondary);">Not Paid Yet</div>
                        <div style="font-size:11.5px; color:var(--text-muted); margin-top:2px;">Click to mark as paid</div>
                    @endif
                </div>
            </label>
        </form>
    </div>

    {{-- Send / Download Card --}}
    <div class="card" style="padding:18px;">
        <div style="font-size:11px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:1.2px; margin-bottom:12px;">📤 Send / Download Bill</div>
        <div style="display:flex; flex-direction:column; gap:10px;">

            {{-- Detailed settlement copy --}}
            @unless ($wipeMode ?? false)
            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 12px; border-radius:8px; background:rgba(212,168,83,0.07); border:1px solid rgba(212,168,83,0.2);">
                <div>
                    <div style="font-size:12.5px; font-weight:600; color:var(--gold-light);">Seller Receipt</div>
                    <div style="font-size:11px; color:var(--text-muted);">Seller contact, item details, commission and final net amount. Saved for WhatsApp.</div>
                </div>
                <div style="display:flex; gap:6px; flex-shrink:0; flex-wrap:wrap;">
                    @if ($waAppLink)
                        <button onclick="openWhatsAppChat('{{ $waAppLink }}', '{{ $waWebLink }}')"
                                class="btn btn-whatsapp btn-sm">
                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                            Open Chat
                        </button>
                    @endif
                    <button onclick="openReceiptsFolder('{{ $receiptFilename }}', this)" class="btn btn-outline btn-sm">📁 Receipts Folder</button>
                    <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-gold btn-sm">⬇ Seller PDF</a>
                </div>
            </div>
            @endunless

            {{-- Purchase copy --}}
            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 12px; border-radius:8px; background:rgba(255,255,255,0.03); border:1px solid var(--border);">
                <div>
                    <div style="font-size:12.5px; font-weight:600; color:var(--text-primary);">Buyer Receipt</div>
                    <div style="font-size:11px; color:var(--text-muted);">Seller name, item details, weights, pieces, rates and the buyer's purchase amount.</div>
                </div>
                <div style="display:flex; gap:6px; flex-shrink:0;">
                    <a href="{{ route('invoices.pdf', $invoice) }}?buyer=1" class="btn btn-outline btn-sm">⬇ Buyer PDF</a>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- ─── WhatsApp Send Modal ─── --}}
{{-- ─── Receipt Card ─── --}}
<div style="max-width:780px; margin:0 auto;">
    <div class="card print-card" style="overflow:hidden;">

        {{-- Bill Header --}}
        <div style="background:linear-gradient(135deg, #0a1a10 0%, #122418 100%); padding:28px 32px; position:relative; overflow:hidden;">
            <div style="position:absolute; top:-40px; right:-40px; width:180px; height:180px; border-radius:50%; background:rgba(212,168,83,0.05);"></div>
            <div style="position:absolute; bottom:-50px; left:80px; width:120px; height:120px; border-radius:50%; background:rgba(212,168,83,0.03);"></div>
            <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:16px; position:relative;">
                <div>
                    <div style="font-size:22px; font-weight:800; color:#f0ece4; letter-spacing:-0.5px;">{{ config('gemdesk.company_name') }}</div>
                    <div style="font-size:11px; color:#6a8a72; margin-top:5px; line-height:1.7;">
                        {{ config('gemdesk.company_address') }}<br>
                        {{ config('gemdesk.company_phone') }}
                        @if(config('gemdesk.company_email')) · {{ config('gemdesk.company_email') }} @endif
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:20px; font-weight:800; color:var(--gold-light); letter-spacing:2px;">PURCHASE BILL</div>
                    <div style="font-size:13px; color:#6a8a72; margin-top:5px;">{{ $invoice->invoice_number }}</div>
                    <div style="font-size:12px; color:#4a6a52; margin-top:2px;">{{ $sltDate }}</div>
                </div>
            </div>
        </div>

        {{-- Seller Info + Status --}}
        <div style="padding:22px 32px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; flex-wrap:wrap; gap:16px;">
            <div>
                <div style="font-size:10px; text-transform:uppercase; letter-spacing:1.5px; color:var(--text-muted); margin-bottom:6px;">Received From (Seller)</div>
                <div style="font-size:18px; font-weight:700; color:var(--text-primary);">{{ $invoice->seller->name }}</div>
                <div style="font-size:13px; color:var(--text-muted); margin-top:3px;">{{ $invoice->seller->phone ?: 'No phone' }}</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:10px; text-transform:uppercase; letter-spacing:1.5px; color:var(--text-muted); margin-bottom:6px;">Payout to Seller</div>
                @if ($invoice->isPaid())
                    <span class="badge badge-paid" style="font-size:13px; padding:5px 14px;">✓ PAID</span>
                @else
                    <span class="badge badge-pending" style="font-size:13px; padding:5px 14px;">⏳ PENDING</span>
                @endif
                <div style="font-size:12px; color:var(--text-muted); margin-top:8px;">{{ $invoice->total_pieces }} pieces total</div>
            </div>
        </div>

        {{-- Gems Table --}}
        <div style="padding:22px 32px 14px;">
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border-strong);">
                        <th style="text-align:left; padding:6px 8px 10px 0; font-size:10px; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); font-weight:600;">#</th>
                        <th style="text-align:left; padding:6px 8px 10px; font-size:10px; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); font-weight:600;">Serial</th>
                        <th style="text-align:left; padding:6px 8px 10px; font-size:10px; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); font-weight:600;">Gem</th>
                        <th style="text-align:right; padding:6px 8px 10px; font-size:10px; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); font-weight:600;">Wt. (ct)</th>
                        <th style="text-align:right; padding:6px 8px 10px; font-size:10px; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); font-weight:600;">Pcs</th>
                        <th style="text-align:right; padding:6px 8px 10px; font-size:10px; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); font-weight:600;">Rate</th>
                        <th style="text-align:right; padding:6px 0 10px 8px; font-size:10px; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); font-weight:600;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->items as $i => $item)
                        <tr style="border-bottom:1px solid rgba(255,255,255,0.04);">
                            <td style="padding:10px 8px 10px 0; color:var(--text-muted);">{{ $i + 1 }}</td>
                            <td style="padding:10px 8px; color:var(--text-secondary);">{{ $item->serial_number ?: '—' }}</td>
                            <td style="padding:10px 8px; color:var(--text-primary);">
                                <div style="font-weight:600;">{{ $item->description }}</div>
                                @if (! empty($item->verification_actions))
                                    <div style="font-size:10px; color:#fbbf24; margin-top:3px;">{{ strtoupper(implode(' + ', $item->verification_actions)) }}</div>
                                @endif
                                @if ($item->stone_notes)
                                    <div style="font-size:10px; color:var(--text-muted); margin-top:3px;">{{ $item->stone_notes }}</div>
                                @endif
                            </td>
                            <td style="padding:10px 8px; text-align:right; color:var(--text-secondary);">
                                {{ $item->carats !== null ? rtrim(rtrim(number_format((float) $item->carats, 2), '0'), '.') : '—' }}
                            </td>
                            <td style="padding:10px 8px; text-align:right;">{{ $item->pieces }}</td>
                            <td style="padding:10px 8px; text-align:right; color:var(--text-secondary);">{{ Lkr::format($item->unit_price) }}{{ $item->carats !== null && (float) $item->carats > 0 ? '/ct' : '/pc' }}</td>
                            <td style="padding:10px 0 10px 8px; text-align:right; font-weight:700;">{{ Lkr::format($item->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Totals --}}
        <div style="padding:12px 32px 24px; display:flex; justify-content:flex-end;">
            <div style="width:300px;">
                <div style="display:flex; justify-content:space-between; padding:6px 0; font-size:13px; border-bottom:1px solid var(--border);">
                    <span style="color:var(--text-secondary);">Subtotal (Purchased)</span>
                    <span style="font-weight:600;">{{ Lkr::format($invoice->subtotal) }}</span>
                </div>
                @unless ($wipeMode ?? false)
                    <div style="display:flex; justify-content:space-between; padding:12px 0 4px; font-size:17px; font-weight:800; border-top:2px solid var(--border-strong); margin-top:4px;">
                        <span>Net to Seller</span>
                        <span>{{ Lkr::format($invoice->net_payable) }}</span>
                    </div>
                    <div style="font-size:11px; color:var(--text-muted); font-style:italic; text-align:right; margin-top:4px;">
                        {{ AmountInWords::convert($invoice->net_payable) }}.
                    </div>
                @endunless
            </div>
        </div>

        @if ($invoice->notes)
            <div style="margin:0 32px 24px; background:rgba(255,255,255,0.03); border:1px solid var(--border); border-radius:8px; padding:14px 16px;">
                <div style="font-size:10px; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); margin-bottom:5px;">Notes</div>
                <p style="font-size:13px; color:var(--text-secondary);">{{ $invoice->notes }}</p>
            </div>
        @endif

        {{-- Signatures --}}
        <div style="display:flex; justify-content:space-between; padding:0 32px 32px; margin-top:20px;">
            <div style="text-align:center;">
                <div style="width:160px; border-top:1px solid rgba(255,255,255,0.15); padding-top:6px; margin-top:40px; font-size:11px; color:var(--text-muted);">Seller's Signature</div>
            </div>
            <div style="text-align:center;">
                <div style="width:160px; border-top:1px solid rgba(255,255,255,0.15); padding-top:6px; margin-top:40px; font-size:11px; color:var(--text-muted);">Authorised By (Broker)</div>
            </div>
        </div>
    </div>

    {{-- Delete --}}
    <div class="no-print" style="margin-top:14px; display:flex; justify-content:flex-end;">
        <form method="POST" action="{{ route('invoices.destroy', $invoice) }}"
              onsubmit="return confirm('Archive bill {{ $invoice->invoice_number }}? It will be hidden from totals but can be restored later.');">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">🗃️ Archive this Bill</button>
        </form>
    </div>
</div>
@endsection






@push('scripts')
<script>
// ── Open the seller's chat in the WhatsApp desktop app ──────────────
// whatsapp://send?phone=… opens the native app (or WhatsApp Web if the
// app isn't installed / doesn't handle the link). wa.me is the web fallback.
function openWhatsAppChat(appLink, webLink) {
    const win = window.open(appLink, '_blank');
    // If the app scheme did nothing, the popup usually stays open/blank —
    // show a fallback prompt so sending never dead-ends.
    setTimeout(function () {
        if (win && !win.closed && win.location.href === 'about:blank') {
            window.location.href = webLink;
        }
    }, 1500);
}

// ── Open the Receipts folder in Explorer/Finder with the file selected ──
async function openReceiptsFolder(filename, btn = null) {
    const original = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '⏳ Opening…';
    }
    try {
        const body = new URLSearchParams({ file: filename || '' });
        const res = await fetch('{{ route('receipts.open-folder') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: body.toString()
        });
        const data = await res.json();
        if (!data.ok) {
            alert('Could not open the folder automatically.\n\nOpen the "receipts" folder manually (next to run.bat / run.sh) — the PDF is saved there.');
        }
    } catch (e) {
        alert('Could not open the folder automatically. The PDF is saved in the "receipts" folder.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    }
}

// ── After finalising a bill: open WhatsApp chat + receipts folder ────
@if (session('wa_open') && $waAppLink)
(function () {
    setTimeout(function () {
        openReceiptsFolder('{{ $receiptFilename }}');
        setTimeout(function () { window.location.href = '{{ $waAppLink }}'; }, 400);
    }, 500);
})();
@endif
</script>
@endpush
