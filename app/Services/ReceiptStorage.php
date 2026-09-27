<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Saves the seller-facing receipt PDF into the laptop's "receipts" folder
 * the moment a bill is finalised, named after the seller so you can find it
 * in seconds and drag it into the WhatsApp chat.
 *
 * Folder: configurable via GEMDESK_RECEIPTS_FOLDER (.env), default
 * <project>/receipts — right next to run.bat / run.sh.
 */
class ReceiptStorage
{
    /**
     * Absolute path of the receipts folder (creates it if missing).
     */
    public static function folder(): string
    {
        $path = self::resolveFolder();

        if (! is_dir($path)) {
            @mkdir($path, 0775, true);
        }

        return $path;
    }

    /**
     * Resolve the configured folder to an absolute path without touching
     * the filesystem (relative paths resolve against the project root).
     */
    public static function resolveFolder(): string
    {
        $folder = (string) config('gemdesk.receipts_folder', 'receipts');

        // Absolute Windows (C:\…) or Unix (/…) paths are used as-is;
        // anything else is resolved against the project root.
        $isAbsolute = preg_match('#^([A-Za-z]:[\\\\/]|/)#', $folder) === 1;

        return $isAbsolute ? $folder : base_path($folder);
    }

    /**
     * Safe filename for a receipt, e.g.
     *   Ravi-Perera-0777123456-0608202601.pdf
     */
    public static function filenameFor(Invoice $invoice): string
    {
        $invoice->loadMissing('seller');

        $name = Str::ascii(trim((string) ($invoice->seller?->name ?? 'Seller')));
        $name = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-') ?: 'Seller';

        $phone = preg_replace('/\D+/', '', (string) ($invoice->seller?->phone ?? ''));
        $phone = $phone !== '' ? $phone : 'no-number';

        return $name . '-' . $phone . '-' . $invoice->invoice_number . '.pdf';
    }

    /** Delete one known receipt filename without allowing path traversal. */
    public static function deleteFilename(string $filename): void
    {
        $safe = basename($filename);
        $path = self::folder().DIRECTORY_SEPARATOR.$safe;

        if ($safe !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Generate the receipt PDF and store it in the receipts folder.
     *
     * The saved copy is the SELLER version — it shows the gems, our
     * commission and the net amount payable to the seller, which is what
     * gets dragged into the seller's WhatsApp chat.
     *
     * @return array{folder: string, filename: string, path: string}|null  null on failure
     */
    public static function save(Invoice $invoice): ?array
    {
        $filename = self::filenameFor($invoice);
        $folder   = self::folder();
        $path     = $folder . DIRECTORY_SEPARATOR . $filename;

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice'      => $invoice->loadMissing('items'),
            'buyerVersion' => false, // seller copy: commission + net payable
        ])->setPaper('a4');

        if (file_put_contents($path, $pdf->output()) === false) {
            return null;
        }

        return [
            'folder'   => $folder,
            'filename' => $filename,
            'path'     => $path,
        ];
    }
}
