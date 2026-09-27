<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Seller;
use App\Services\BackupService;
use App\Services\InvoiceService;
use App\Services\ReceiptStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function __construct(
        private BackupService $backups,
        private InvoiceService $invoices,
    ) {
    }

    public function index(): View
    {
        $files = collect(glob(storage_path('app/backups/*.json')) ?: [])
            ->map(fn (string $path) => [
                'name' => basename($path),
                'size' => filesize($path),
                'modified' => filemtime($path),
            ])
            ->sortByDesc('modified')
            ->take(10)
            ->values();

        return view('backups.index', compact('files'));
    }

    public function download(): StreamedResponse
    {
        $json = $this->backups->json();
        $filename = 'gemdesk-backup-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(
            fn () => print($json),
            $filename,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    public function snapshot(): RedirectResponse
    {
        $path = $this->backups->snapshot('manual');

        return back()->with('success', 'Backup saved safely as '.basename($path).'.');
    }

    public function restore(Request $request): RedirectResponse
    {
        $request->validate([
            'backup' => ['required', 'file', 'max:102400', 'extensions:json'],
            'confirmation' => ['required', 'in:RESTORE'],
        ]);

        try {
            $data = $this->backups->decodeAndVerify($request->file('backup')->get());
            $this->backups->snapshot('before-restore');

            DB::transaction(function () use ($data): void {
                $sellerMap = [];

                foreach ($data['sellers'] ?? [] as $row) {
                    $seller = Seller::withTrashed()->find($row['original_id'] ?? 0);

                    if ($seller && ($seller->name !== ($row['name'] ?? '') || $seller->phone !== ($row['phone'] ?? null))) {
                        $seller = null; // ID belongs to a different record in this database.
                    }

                    $seller ??= Seller::withTrashed()
                        ->where('name', $row['name'] ?? '')
                        ->where('phone', $row['phone'] ?? null)
                        ->first();

                    $seller ??= new Seller();
                    $seller->fill([
                        'name' => $row['name'],
                        'phone' => $row['phone'] ?? null,
                        'notes' => $row['notes'] ?? null,
                    ])->save();

                    if (! empty($row['deleted_at'])) {
                        $seller->delete();
                    } elseif ($seller->trashed()) {
                        $seller->restore();
                    }

                    $sellerMap[(string) ($row['original_id'] ?? '')] = $seller->id;
                }

                foreach ($data['invoices'] ?? [] as $row) {
                    $items = $row['items'] ?? [];
                    $sellerId = $sellerMap[(string) ($row['seller_original_id'] ?? '')] ?? null;

                    if (! $sellerId || ! is_array($items) || count($items) < 1) {
                        continue;
                    }

                    $invoice = Invoice::withTrashed()->firstOrNew([
                        'invoice_number' => $row['invoice_number'],
                    ]);
                    $invoice->fill([
                        'seller_id' => $sellerId,
                        'sold_at' => $row['sold_at'],
                        'commission_percent' => $row['commission_percent'],
                        'status' => in_array($row['status'] ?? '', ['pending', 'paid'], true) ? $row['status'] : 'pending',
                        'notes' => $row['notes'] ?? null,
                    ])->save();

                    $invoice->items()->delete();
                    foreach ($items as $item) {
                        $pieces = max(1, (int) ($item['pieces'] ?? 1));
                        $carats = isset($item['carats']) && (float) $item['carats'] > 0 ? (float) $item['carats'] : null;
                        $unitPrice = max(0, (float) ($item['unit_price'] ?? 0));
                        $lineTotal = isset($item['total_price'])
                            ? max(0, (float) $item['total_price'])
                            : round(($carats ?? $pieces) * $unitPrice, 2);
                        $invoice->items()->create([
                            'serial_number' => isset($item['serial_number']) && trim((string) $item['serial_number']) !== '' ? mb_strtoupper(trim((string) $item['serial_number'])) : null,
                            'price_mode' => ($item['price_mode'] ?? 'rate') === 'total' ? 'total' : 'rate',
                            'verification_actions' => collect($item['verification_actions'] ?? [])->filter(fn ($action) => in_array($action, ['recheck', 'repolish', 'recut'], true))->unique()->values()->all() ?: null,
                            'stone_notes' => ($stoneNotes = mb_strtoupper(trim((string) ($item['stone_notes'] ?? '')))) !== '' ? $stoneNotes : null,
                            'description' => mb_strtoupper(trim((string) ($item['description'] ?? 'GEM'))),
                            'carats' => $carats,
                            'pieces' => $pieces,
                            'unit_price' => $unitPrice,
                            'amount' => $lineTotal,
                        ]);
                    }
                    $this->invoices->recomputeTotals($invoice);

                    if (! empty($row['deleted_at'])) {
                        $invoice->delete();
                    } elseif ($invoice->trashed()) {
                        $invoice->restore();
                    }
                }
            }, 3);

            // PDFs are derived data, so active receipts can safely be rebuilt.
            Invoice::with(['items', 'seller'])->each(function (Invoice $invoice): void {
                try {
                    ReceiptStorage::save($invoice);
                } catch (\Throwable $e) {
                    Log::warning('Receipt regeneration failed during restore', ['invoice' => $invoice->invoice_number]);
                }
            });

            $this->backups->snapshot('after-restore');

            return redirect()->route('backups.index')->with('success', 'Backup verified and restored successfully. Receipt PDFs were regenerated.');
        } catch (\Throwable $e) {
            Log::error('Backup restore failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['backup' => 'Restore failed: '.$e->getMessage()]);
        }
    }
}
