@php
    use App\Support\Lkr;

    /** Expects: $action, $method ('POST'|'PUT'), $sellers, $invoice (?Invoice), $commissionDefault */
    $isEdit = isset($invoice) && $invoice->exists;

    $initialItems = old('items');
    if ($initialItems === null) {
        $initialItems = $isEdit
            ? $invoice->items->map(fn ($i) => [
                'serial_number' => $i->serial_number,
                'description' => $i->description,
                'carats'      => $i->carats !== null ? (float) $i->carats : '',
                'pieces'      => $i->pieces,
                'unit_price'  => (float) $i->unit_price,
                'total_price' => (float) $i->amount,
                'price_mode'  => $i->price_mode ?: 'rate',
                'verification_actions' => $i->verification_actions ?? [],
                'stone_notes' => $i->stone_notes ?? '',
            ])->values()->all()
            : [['serial_number' => '', 'description' => '', 'carats' => '', 'pieces' => 1, 'unit_price' => '', 'total_price' => '', 'price_mode' => 'rate', 'verification_actions' => [], 'stone_notes' => '']];
    }

    $sellerValue      = old('seller_id', $isEdit ? $invoice->seller_id : request('seller_id', ''));
    $soldAtValue      = old('sold_at', $isEdit ? $invoice->sold_at->format('Y-m-d') : now()->setTimezone('Asia/Colombo')->format('Y-m-d'));
    $commissionValue  = old('commission_percent', $isEdit ? (float) $invoice->commission_percent : $commissionDefault);
    $notesValue       = old('notes', $isEdit ? $invoice->notes : '');
@endphp

<form method="POST" action="{{ $action }}" id="billForm">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    {{-- Seller & Date --}}
    <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
            <h2>👤 Seller & Bill Details</h2>
        </div>
        <div style="padding:20px; display:grid; grid-template-columns:2fr 1fr 1fr; gap:16px;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Seller <span style="color:#fca5a5;">*</span></label>
                <div style="display:flex; gap:8px;">
                    <select name="seller_id" required class="form-select" style="flex:1;">
                        <option value="">— choose seller —</option>
                        @foreach ($sellers as $seller)
                            <option value="{{ $seller->id }}" @selected((string) $sellerValue === (string) $seller->id)>
                                {{ $seller->name }}{{ $seller->phone ? ' · ' . $seller->phone : '' }}
                            </option>
                        @endforeach
                    </select>
                    <a href="{{ route('sellers.create', ['return_url' => url()->current()]) }}"
                       class="btn btn-outline btn-sm" style="white-space:nowrap;" title="Add new seller">+ New</a>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Date (SLT) <span style="color:#fca5a5;">*</span></label>
                <input type="date" name="sold_at" value="{{ $soldAtValue }}" required class="form-input">
            </div>

            @if ($wipeMode ?? false)
                <input type="hidden" name="commission_percent" id="commissionPercent" value="{{ $commissionValue }}">
            @else
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Commission % <span style="color:#fca5a5;">*</span></label>
                    <div style="position:relative;">
                        <input type="number" name="commission_percent" id="commissionPercent"
                               value="{{ $commissionValue }}" min="0" max="100" step="0.01" required
                               class="form-input" style="padding-right:30px;">
                        <span style="position:absolute; right:10px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:13px;">%</span>
                    </div>
                    <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">Default {{ $commissionDefault }}%</div>
                </div>
            @endif
        </div>

        @if (! $isEdit)
            <div style="padding:0 20px 20px; display:grid; grid-template-columns:2fr 2fr; gap:16px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Payout Status</label>
                    <select name="status" class="form-select">
                        <option value="pending" @selected(old('status', 'pending') === 'pending')>⏳ Pending — seller not paid yet</option>
                        <option value="paid"    @selected(old('status') === 'paid')>✅ Paid — money sent to seller</option>
                    </select>
                </div>
            </div>
        @endif
    </div>

    {{-- Gems Table --}}
    <div class="card" style="margin-bottom:20px; overflow:hidden;">
        <div class="card-header">
            <h2>💎 Gems in this Bill</h2>
            <button type="button" id="addRowBtn" class="btn btn-gold btn-sm">+ Add Gem</button>
        </div>

        <div style="overflow-x:auto;">
            <table class="gem-table" id="itemsTable">
                <thead>
                    <tr>
                        <th>Serial <span style="color:#fca5a5;">*</span></th>
                        <th>Gem Name <span style="color:#fca5a5;">*</span></th>
                        <th class="right">Pcs <span style="color:#fca5a5;">*</span></th>
                        <th class="right">Total Weight</th>
                        <th class="right">Rate / ct or pc</th>
                        <th class="right">Total Price</th>
                        <th>Verification</th>
                        <th>Stone Note</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    @foreach ($initialItems as $index => $item)
                        @php $selectedActions = $item['verification_actions'] ?? []; @endphp
                        <tr class="item-row" data-price-mode="{{ $item['price_mode'] ?? 'rate' }}">
                            <td style="padding:8px 8px;">
                                <input type="hidden" name="items[{{ $index }}][price_mode]" value="{{ $item['price_mode'] ?? 'rate' }}" class="price-mode-input">
                                <input type="text" name="items[{{ $index }}][serial_number]" value="{{ $item['serial_number'] ?? '' }}"
                                       required maxlength="100" data-uppercase class="gem-input serial-input" placeholder="E.G. 1" style="width:76px;">
                            </td>
                            <td style="padding:8px 8px;">
                                <input type="text" name="items[{{ $index }}][description]" value="{{ $item['description'] ?? '' }}"
                                       required maxlength="255" list="gemNames" data-uppercase class="gem-input" placeholder="E.G. BLUE SAPPHIRE">
                            </td>
                            <td style="padding:8px 8px;">
                                <input type="number" name="items[{{ $index }}][pieces]" value="{{ $item['pieces'] ?? 1 }}"
                                       min="1" step="1" required class="gem-input right pieces-input" style="width:64px;">
                            </td>
                            <td style="padding:8px 8px;">
                                <input type="number" name="items[{{ $index }}][carats]" value="{{ $item['carats'] ?? '' }}"
                                       min="0" step="0.01" placeholder="—" class="gem-input right carats-input" style="width:86px;">
                            </td>
                            <td style="padding:8px 8px;">
                                <input type="number" name="items[{{ $index }}][unit_price]" value="{{ $item['unit_price'] ?? '' }}"
                                       min="0" step="0.01" placeholder="Auto / enter rate" class="gem-input right price-input" style="width:120px;">
                            </td>
                            <td style="padding:8px 8px;">
                                <input type="hidden" name="items[{{ $index }}][total_price]" value="{{ $item['total_price'] ?? '' }}" class="total-price-input">
                                <input type="text" inputmode="decimal"
                                       value="{{ isset($item['total_price']) && $item['total_price'] !== '' ? number_format((float) $item['total_price'], 2, '.', ',') : '' }}"
                                       placeholder="Auto / enter total" class="gem-input right total-price-display"
                                       style="width:145px; font-weight:700; color:var(--gold-light);">
                                <div class="price-words" style="width:145px; margin-top:4px; font-size:9.5px; line-height:1.35; color:var(--text-muted);"></div>
                            </td>
                            <td style="padding:8px 8px; vertical-align:top;">
                                <details class="verification-picker" style="min-width:125px;">
                                    <summary class="gem-input" style="cursor:pointer; list-style:none; width:125px;"><span class="verification-summary">NONE</span> ▾</summary>
                                    <div style="padding:8px; margin-top:4px; border:1px solid var(--border-strong); border-radius:7px; background:var(--bg-secondary); display:grid; gap:6px;">
                                        @foreach (['recheck' => 'RECHECK', 'repolish' => 'REPOLISH', 'recut' => 'RECUT'] as $value => $label)
                                            <label style="display:flex; gap:6px; align-items:center; font-size:11px; cursor:pointer;">
                                                <input type="checkbox" class="verification-action" name="items[{{ $index }}][verification_actions][]" value="{{ $value }}" @checked(in_array($value, $selectedActions, true))> {{ $label }}
                                            </label>
                                        @endforeach
                                        <button type="button" class="verification-none btn btn-outline btn-sm" style="justify-content:center;">SET NONE</button>
                                    </div>
                                </details>
                            </td>
                            <td style="padding:8px 8px; vertical-align:top;">
                                <textarea name="items[{{ $index }}][stone_notes]" maxlength="1000" rows="2" data-uppercase
                                          class="gem-input stone-notes-input" placeholder="STONE NOTE" style="width:170px; resize:vertical;">{{ $item['stone_notes'] ?? '' }}</textarea>
                            </td>
                            <td style="padding:8px 5px; text-align:center;">
                                <button type="button" class="remove-row" title="Remove" style="background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:18px; line-height:1;">×</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding:8px 14px; font-size:11px; color:var(--text-muted); border-top:1px solid var(--border);">
            Enter either the rate or the total price. GemDesk automatically calculates the other value using total weight, or pieces when no weight is entered.
        </div>

        <datalist id="gemNames">
            <option value="Blue Sapphire"></option>
            <option value="Yellow Sapphire"></option>
            <option value="Pink Sapphire"></option>
            <option value="Padparadscha Sapphire"></option>
            <option value="Ruby"></option>
            <option value="Alexandrite"></option>
            <option value="Cat's Eye Chrysoberyl"></option>
            <option value="Spinel"></option>
            <option value="Garnet (Hessonite)"></option>
            <option value="Tourmaline"></option>
            <option value="Moonstone"></option>
            <option value="Zircon"></option>
            <option value="Topaz"></option>
            <option value="Amethyst"></option>
            <option value="Star Sapphire"></option>
            <option value="Star Ruby"></option>
        </datalist>

        {{-- Totals + Notes --}}
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; padding:20px; background:rgba(212,168,83,0.03); border-top:1px solid var(--border);">
            <div>
                <label class="form-label">Notes (optional)</label>
                <textarea name="notes" rows="3" maxlength="1000" data-uppercase class="form-textarea"
                          placeholder="Bank account, payment reference, remarks…">{{ $notesValue }}</textarea>
            </div>
            <div class="totals-summary">
                <div class="totals-row">
                    <span style="color:var(--text-secondary);">Total Pieces</span>
                    <span style="font-weight:600;" id="totalPieces">0</span>
                </div>
                <div class="totals-row">
                    <span style="color:var(--text-secondary);">Total Weight</span>
                    <span style="font-weight:600;" id="totalCarats">— ct</span>
                </div>
                <div class="totals-row">
                    <span style="color:var(--text-secondary);">Subtotal</span>
                    <span style="font-weight:600;" id="subtotalCell">LKR 0.00</span>
                </div>
                <div id="subtotalWords" style="padding:6px 0 9px; font-size:10.5px; line-height:1.4; color:var(--text-muted); text-align:right;">Zero rupees only</div>
                @unless ($wipeMode ?? false)
                    <div class="totals-row grand">
                        <span>Net to Seller</span>
                        <span id="netCell">LKR 0.00</span>
                    </div>
                @else
                    <span id="netCell" style="display:none;"></span>
                @endunless
            </div>
        </div>
    </div>

    <div style="display:flex; gap:12px;">
        <button type="submit" class="btn btn-gold">
            {{ $isEdit ? '💾 Update Bill' : '💾 Save Bill & Generate Receipt' }}
        </button>
        <a href="{{ $isEdit ? route('invoices.show', $invoice) : route('invoices.index') }}" class="btn btn-outline">Cancel</a>
    </div>
</form>

<template id="itemRowTemplate">
    <tr class="item-row" data-price-mode="rate">
        <td style="padding:8px 8px;">
            <input type="hidden" name="items[__IDX__][price_mode]" value="rate" class="price-mode-input">
            <input type="text" name="items[__IDX__][serial_number]" required maxlength="100" data-uppercase
                   class="gem-input serial-input" placeholder="E.G. 1" style="width:76px;">
        </td>
        <td style="padding:8px 8px;">
            <input type="text" name="items[__IDX__][description]" required maxlength="255" list="gemNames" data-uppercase
                   class="gem-input" placeholder="E.G. BLUE SAPPHIRE">
        </td>
        <td style="padding:8px 8px;">
            <input type="number" name="items[__IDX__][pieces]" value="1" min="1" step="1" required
                   class="gem-input right pieces-input" style="width:64px;">
        </td>
        <td style="padding:8px 8px;">
            <input type="number" name="items[__IDX__][carats]" min="0" step="0.01" placeholder="—"
                   class="gem-input right carats-input" style="width:86px;">
        </td>
        <td style="padding:8px 8px;">
            <input type="number" name="items[__IDX__][unit_price]" min="0" step="0.01" placeholder="Auto / enter rate"
                   class="gem-input right price-input" style="width:120px;">
        </td>
        <td style="padding:8px 8px;">
            <input type="hidden" name="items[__IDX__][total_price]" class="total-price-input">
            <input type="text" inputmode="decimal" placeholder="Auto / enter total"
                   class="gem-input right total-price-display" style="width:145px; font-weight:700; color:var(--gold-light);">
            <div class="price-words" style="width:145px; margin-top:4px; font-size:9.5px; line-height:1.35; color:var(--text-muted);"></div>
        </td>
        <td style="padding:8px 8px; vertical-align:top;">
            <details class="verification-picker" style="min-width:125px;">
                <summary class="gem-input" style="cursor:pointer; list-style:none; width:125px;"><span class="verification-summary">NONE</span> ▾</summary>
                <div style="padding:8px; margin-top:4px; border:1px solid var(--border-strong); border-radius:7px; background:var(--bg-secondary); display:grid; gap:6px;">
                    <label style="display:flex; gap:6px; align-items:center; font-size:11px;"><input type="checkbox" class="verification-action" name="items[__IDX__][verification_actions][]" value="recheck"> RECHECK</label>
                    <label style="display:flex; gap:6px; align-items:center; font-size:11px;"><input type="checkbox" class="verification-action" name="items[__IDX__][verification_actions][]" value="repolish"> REPOLISH</label>
                    <label style="display:flex; gap:6px; align-items:center; font-size:11px;"><input type="checkbox" class="verification-action" name="items[__IDX__][verification_actions][]" value="recut"> RECUT</label>
                    <button type="button" class="verification-none btn btn-outline btn-sm" style="justify-content:center;">SET NONE</button>
                </div>
            </details>
        </td>
        <td style="padding:8px 8px; vertical-align:top;">
            <textarea name="items[__IDX__][stone_notes]" maxlength="1000" rows="2" data-uppercase
                      class="gem-input stone-notes-input" placeholder="STONE NOTE" style="width:170px; resize:vertical;"></textarea>
        </td>
        <td style="padding:8px 5px; text-align:center;">
            <button type="button" class="remove-row" title="Remove" style="background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:18px; line-height:1;">×</button>
        </td>
    </tr>
</template>

@push('scripts')
<script>
(function () {
    const body = document.getElementById('itemsBody');
    const template = document.getElementById('itemRowTemplate');
    const addBtn = document.getElementById('addRowBtn');
    const commissionInput = document.getElementById('commissionPercent');
    const fmt = new Intl.NumberFormat('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let nextIndex = {{ count($initialItems) }};

    function roundMoney(value) {
        return Math.round((value + Number.EPSILON) * 100) / 100;
    }

    function formatTypedMoney(value) {
        let clean = String(value).replace(/,/g, '').replace(/[^0-9.]/g, '');
        const firstDot = clean.indexOf('.');
        if (firstDot !== -1) {
            clean = clean.slice(0, firstDot + 1) + clean.slice(firstDot + 1).replace(/\./g, '');
        }
        let [whole = '', decimals] = clean.split('.');
        whole = whole.replace(/^0+(?=\d)/, '') || (clean.startsWith('0') ? '0' : '');
        const grouped = whole === '' ? '' : whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        if (decimals !== undefined) return grouped + '.' + decimals.slice(0, 2);
        return grouped;
    }

    function numericMoney(value) {
        const number = parseFloat(String(value).replace(/,/g, ''));
        return Number.isFinite(number) && number >= 0 ? number : null;
    }

    function integerWords(number) {
        number = Math.floor(number);
        if (number === 0) return 'zero';
        const ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
            'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        const tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
        const scales = [[1_000_000_000_000, 'trillion'], [1_000_000_000, 'billion'], [1_000_000, 'million'], [1_000, 'thousand']];

        function underThousand(n) {
            const words = [];
            if (n >= 100) {
                words.push(ones[Math.floor(n / 100)], 'hundred');
                n %= 100;
            }
            if (n >= 20) {
                words.push(tens[Math.floor(n / 10)]);
                if (n % 10) words.push(ones[n % 10]);
            } else if (n > 0) {
                words.push(ones[n]);
            }
            return words.join(' ');
        }

        const words = [];
        for (const [value, name] of scales) {
            if (number >= value) {
                words.push(integerWords(Math.floor(number / value)), name);
                number %= value;
            }
        }
        if (number > 0) words.push(underThousand(number));
        return words.join(' ');
    }

    function moneyInWords(value) {
        if (!Number.isFinite(value) || value < 0) return '';
        let whole = Math.floor(value);
        let cents = Math.round((value - whole) * 100);
        if (cents === 100) { whole += 1; cents = 0; }
        let words = integerWords(whole) + ' rupees';
        if (cents > 0) words += ' and ' + integerWords(cents) + ' cents';
        return words.charAt(0).toUpperCase() + words.slice(1) + ' only';
    }

    function basisFor(row) {
        const pieces = parseInt(row.querySelector('.pieces-input').value, 10) || 0;
        const carats = parseFloat(row.querySelector('.carats-input').value);
        const hasCarats = Number.isFinite(carats) && carats > 0;
        return {
            pieces: pieces > 0 ? pieces : 0,
            carats: hasCarats ? carats : 0,
            basis: hasCarats ? carats : Math.max(pieces, 0),
        };
    }

    function setMode(row, mode) {
        row.dataset.priceMode = mode;
        row.querySelector('.price-mode-input').value = mode;
    }

    function showWords(row, total) {
        const words = row.querySelector('.price-words');
        words.textContent = total === null ? '' : moneyInWords(total);
    }

    function writeCalculatedTotal(row, total) {
        const hidden = row.querySelector('.total-price-input');
        const display = row.querySelector('.total-price-display');
        total = roundMoney(total);
        hidden.value = total.toFixed(2);
        display.value = fmt.format(total);
        showWords(row, total);
    }

    function readDisplayedTotal(row) {
        const display = row.querySelector('.total-price-display');
        const hidden = row.querySelector('.total-price-input');
        const total = numericMoney(display.value);
        hidden.value = total === null ? '' : roundMoney(total).toFixed(2);
        showWords(row, total);
        return total;
    }

    function syncRow(row, changedField = null) {
        const rateInput = row.querySelector('.price-input');
        const totalDisplay = row.querySelector('.total-price-display');
        const totalHidden = row.querySelector('.total-price-input');
        const n = basisFor(row);

        if (changedField && changedField.classList.contains('total-price-display')) {
            totalDisplay.value = formatTypedMoney(totalDisplay.value);
            readDisplayedTotal(row);
            setMode(row, 'total');
        }
        if (changedField && changedField.classList.contains('price-input')) setMode(row, 'rate');

        const mode = row.dataset.priceMode || row.querySelector('.price-mode-input').value || 'rate';
        const rate = numericMoney(rateInput.value);
        const total = numericMoney(totalHidden.value);

        if (mode === 'total' && total !== null && n.basis > 0) {
            rateInput.value = roundMoney(total / n.basis).toFixed(2);
            showWords(row, total);
        } else if (mode === 'rate' && rate !== null && n.basis > 0) {
            writeCalculatedTotal(row, rate * n.basis);
        } else if (total !== null && n.basis > 0) {
            setMode(row, 'total');
            rateInput.value = roundMoney(total / n.basis).toFixed(2);
            showWords(row, total);
        } else {
            showWords(row, total);
        }
    }

    function recalcTotals() {
        let subtotal = 0, totalPieces = 0, totalCarats = 0;

        body.querySelectorAll('.item-row').forEach((row) => {
            const n = basisFor(row);
            const total = numericMoney(row.querySelector('.total-price-input').value);
            subtotal += total ?? 0;
            totalPieces += n.pieces;
            totalCarats += n.carats;
        });

        let pct = parseFloat(commissionInput.value);
        if (!Number.isFinite(pct) || pct < 0) pct = 0;
        if (pct > 100) pct = 100;

        const commission = roundMoney(subtotal * pct / 100);
        const net = roundMoney(subtotal - commission);

        document.getElementById('totalPieces').textContent = totalPieces;
        document.getElementById('totalCarats').textContent = totalCarats > 0 ? fmt.format(totalCarats) + ' ct' : '— ct';
        document.getElementById('subtotalCell').textContent = 'LKR ' + fmt.format(subtotal);
        document.getElementById('subtotalWords').textContent = moneyInWords(subtotal);
        document.getElementById('netCell').textContent = 'LKR ' + fmt.format(net);
    }

    function updateVerificationSummary(row) {
        const selected = Array.from(row.querySelectorAll('.verification-action:checked'))
            .map(input => input.value.toUpperCase());
        const summary = row.querySelector('.verification-summary');
        if (summary) summary.textContent = selected.length ? selected.join(' + ') : 'NONE';
    }

    function recalcAll() {
        body.querySelectorAll('.item-row').forEach(row => {
            updateVerificationSummary(row);
            const display = row.querySelector('.total-price-display');
            if (display.value !== '') display.value = formatTypedMoney(display.value);
            syncRow(row);
        });
        recalcTotals();
    }

    function addRow() {
        const html = template.innerHTML.replaceAll('__IDX__', nextIndex++);
        const holder = document.createElement('tbody');
        holder.innerHTML = html;
        const row = holder.firstElementChild;
        body.appendChild(row);
        updateVerificationSummary(row);
        row.querySelector('.serial-input').focus();
        recalcTotals();
    }

    body.addEventListener('input', function (event) {
        const row = event.target.closest('.item-row');
        if (!row) return;
        if (event.target.matches('.price-input, .total-price-display, .pieces-input, .carats-input')) {
            syncRow(row, event.target);
        }
        recalcTotals();
    });

    body.addEventListener('change', function (event) {
        if (event.target.classList.contains('verification-action')) {
            updateVerificationSummary(event.target.closest('.item-row'));
        }
    });

    commissionInput.addEventListener('input', recalcTotals);

    body.addEventListener('click', function (event) {
        const noneButton = event.target.closest('.verification-none');
        if (noneButton) {
            const row = noneButton.closest('.item-row');
            row.querySelectorAll('.verification-action').forEach(input => input.checked = false);
            updateVerificationSummary(row);
            return;
        }

        const btn = event.target.closest('.remove-row');
        if (!btn) return;
        const rows = body.querySelectorAll('.item-row');
        if (rows.length <= 1) {
            const row = btn.closest('.item-row');
            row.querySelectorAll('input').forEach(input => {
                if (input.type === 'checkbox') {
                    input.checked = false;
                } else {
                    input.value = input.classList.contains('pieces-input') ? '1' : '';
                }
            });
            row.querySelectorAll('textarea').forEach(textarea => textarea.value = '');
            setMode(row, 'rate');
            updateVerificationSummary(row);
            showWords(row, null);
        } else {
            btn.closest('.item-row').remove();
        }
        recalcTotals();
    });

    addBtn.addEventListener('click', addRow);
    recalcAll();
})();
</script>
@endpush
