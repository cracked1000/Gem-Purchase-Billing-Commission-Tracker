<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItem;
use App\Services\BackupService;
use App\Services\ReceiptStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function __construct(private BackupService $backups)
    {
    }

    public function index(): View
    {
        $items = InvoiceItem::with(['invoice.seller'])
            ->whereHas('invoice') // automatically excludes archived invoices
            ->where(function ($query) {
                $query->whereJsonLength('verification_actions', '>', 0)
                    ->orWhere(function ($notes) {
                        $notes->whereNotNull('stone_notes')->where('stone_notes', '<>', '');
                    });
            })
            ->get()
            ->sortBy(fn (InvoiceItem $item) =>
                $item->invoice->sold_at->format('Y-m-d').'|'.
                str_pad((string) $item->invoice_id, 12, '0', STR_PAD_LEFT).'|'.
                str_pad((string) $item->id, 12, '0', STR_PAD_LEFT)
            )
            ->values();

        return view('verification.index', compact('items'));
    }

    public function update(Request $request, InvoiceItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'verification_actions' => ['nullable', 'array'],
            'verification_actions.*' => ['in:recheck,repolish,recut'],
            'stone_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $actions = collect($validated['verification_actions'] ?? [])->unique()->values()->all();
        $notes = mb_strtoupper(trim((string) ($validated['stone_notes'] ?? '')));

        $this->backups->snapshot('before-verification-update');
        $item->update([
            'verification_actions' => $actions ?: null,
            'stone_notes' => $notes !== '' ? $notes : null,
        ]);

        try {
            ReceiptStorage::save($item->invoice()->with(['items', 'seller'])->firstOrFail());
        } catch (\Throwable $e) {
            Log::warning('Receipt refresh failed after verification update', [
                'item' => $item->id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('verification.index')
            ->with('success', 'Gem verification details updated throughout GemDesk.');
    }
}
