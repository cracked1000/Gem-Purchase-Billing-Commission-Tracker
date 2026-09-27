<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Invoice;
use App\Models\Seller;
use App\Services\BackupService;
use App\Services\InvoiceService;
use App\Services\ReceiptStorage;
use App\Services\WipeMode;
use App\Support\WaNumber;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $service,
        private BackupService $backups,
        private WipeMode $wipe,
    ) {
    }

    public function index(Request $request): View
    {
        $query = Invoice::with('seller')->latest('sold_at')->latest('id');

        if ($request->filled('seller_id')) {
            $query->where('seller_id', $request->integer('seller_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('q')) {
            $q    = mb_strtolower(trim((string) $request->input('q')));
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $query->where(function ($sub) use ($like) {
                $sub->whereRaw('LOWER(invoice_number) LIKE ?', [$like])
                    ->orWhereHas('seller', fn ($s) => $s->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('items', fn ($i) => $i->whereRaw('LOWER(description) LIKE ?', [$like]));
            });
        }

        $invoices = $query->paginate(15)->withQueryString();
        $sellers  = Seller::orderBy('name')->get(['id', 'name']);

        return view('invoices.index', compact('invoices', 'sellers'));
    }

    public function create(): View
    {
        return view('invoices.create', [
            'sellers'           => Seller::orderBy('name')->get(),
            'defaultCommission' => config('gemdesk.default_commission'),
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $invoice = $this->service->create($request->validated());
        try {
            $this->backups->dailySnapshot();
        } catch (\Throwable $e) {
            Log::warning('Automatic backup failed after bill creation', ['error' => $e->getMessage()]);
        }

        $redirect = redirect()->route('invoices.show', $invoice)
            ->with('success', "Bill {$invoice->invoice_number} saved.");

        // Save the receipt PDF into the laptop's receipts folder so it can be
        // dragged straight into WhatsApp.
        $saved = $this->saveReceiptPdf($invoice);

        if ($saved !== null) {
            $redirect->with('receipt_saved', $saved);

            // Auto-open the seller's WhatsApp chat (and the receipts folder)
            // right after finalising — just drag & drop the PDF.
            if (! $this->wipe->enabled()
                && (bool) config('gemdesk.auto_open_chat_on_finalize', true)
                && $invoice->seller?->phone) {
                $redirect->with('wa_open', true);
            }
        }

        return $redirect;
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['items', 'seller']);

        // WhatsApp chat links for the seller (desktop app + web fallback)
        $waNumber   = WaNumber::normalize($invoice->seller->phone ?? '');
        $waAppLink  = $waNumber ? 'whatsapp://send?phone=' . $waNumber : null;
        $waWebLink  = $waNumber ? 'https://wa.me/' . $waNumber : null;

        // The exact filename this receipt has (or will have) in the receipts folder
        $receiptFilename = ReceiptStorage::filenameFor($invoice);

        return view('invoices.show', compact('invoice', 'waAppLink', 'waWebLink', 'receiptFilename'));
    }

    public function edit(Invoice $invoice): View
    {
        $invoice->load('items');

        return view('invoices.edit', [
            'invoice' => $invoice,
            'sellers' => Seller::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->backups->snapshot('before-bill-update');
        $oldReceipt = ReceiptStorage::filenameFor($invoice);
        $invoice = $this->service->update($invoice, $request->validated());

        // Keep the receipts folder in sync when a bill is edited and remove a
        // stale filename if changing the seller changed the generated name.
        $saved = $this->saveReceiptPdf($invoice);
        if ($saved && $oldReceipt !== $saved['filename']) {
            ReceiptStorage::deleteFilename($oldReceipt);
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Bill {$invoice->invoice_number} updated.");
    }

    public function updateStatus(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:pending,paid']]);

        $this->backups->snapshot('before-payout-change');
        $invoice->update(['status' => $validated['status']]);
        $this->saveReceiptPdf($invoice); // keep the saved PDF's payout status current

        return back()->with('success', "Bill {$invoice->invoice_number} marked as {$validated['status']}.");
    }

    /**
     * Open the receipts folder in the laptop's file manager (Explorer /
     * Finder / file manager), highlighting the given receipt file if it
     * exists. Called from the "Open Receipts Folder" button.
     */
    public function openReceiptsFolder(Request $request): JsonResponse
    {
        // Unit tests must never open Finder or other operating-system windows.
        if (app()->runningUnitTests()) {
            return response()->json(['ok' => true]);
        }

        $folder = ReceiptStorage::folder();

        // Only ever operate on files inside the receipts folder
        $file = basename((string) $request->input('file', ''));
        $path = $file !== '' && is_file($folder . DIRECTORY_SEPARATOR . $file)
            ? $folder . DIRECTORY_SEPARATOR . $file
            : $folder;

        return response()->json(['ok' => $this->revealInFileManager($path)]);
    }

    /**
     * Save the seller-facing receipt PDF into the receipts folder.
     *
     * @return array{folder: string, filename: string, path: string}|null
     */
    private function saveReceiptPdf(Invoice $invoice): ?array
    {
        try {
            return ReceiptStorage::save($invoice);
        } catch (\Throwable $e) {
            Log::error('Could not save receipt PDF', [
                'invoice' => $invoice->invoice_number,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Open a file (or folder) in the OS file manager, selecting the file so
     * it's ready to drag into WhatsApp.
     */
    private function revealInFileManager(string $path): bool
    {
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

        $command = match (PHP_OS_FAMILY) {
            'Windows' => 'explorer /select,' . escapeshellarg($path),
            'Darwin'  => 'open -R ' . escapeshellarg($path),
            default   => 'xdg-open ' . escapeshellarg(dirname($path)),
        };

        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

        @exec($command . ' > ' . $null . ' 2>&1', $output, $code);

        // explorer.exe notoriously returns 1 even when it opens fine
        return $code === 0 || PHP_OS_FAMILY === 'Windows';
    }

    public function pdf(Invoice $invoice, Request $request): Response
    {
        $invoice->load(['items', 'seller']);

        $buyerVersion = $this->wipe->enabled() || $request->boolean('buyer');

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice', 'buyerVersion'))->setPaper('a4');

        $filename = $invoice->invoice_number . ($buyerVersion ? '-purchase' : '-settlement') . '.pdf';

        return $pdf->download($filename);
    }

    public function trash(): View
    {
        $invoices = Invoice::onlyTrashed()
            ->with('seller')
            ->latest('deleted_at')
            ->paginate(20);

        return view('invoices.trash', compact('invoices'));
    }

    public function restore(int $invoice): RedirectResponse
    {
        $record = Invoice::onlyTrashed()->findOrFail($invoice);
        $record->restore();
        $this->saveReceiptPdf($record);
        $this->backups->snapshot('bill-restored');

        return redirect()->route('invoices.show', $record)
            ->with('success', "Bill {$record->invoice_number} restored.");
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $number = $invoice->invoice_number;
        $this->backups->snapshot('before-bill-archive');
        $invoice->delete(); // Soft delete: items and receipt remain recoverable.

        return redirect()->route('invoices.index')
            ->with('success', "Bill {$number} archived. You can restore it from Archived Bills.");
    }
}
