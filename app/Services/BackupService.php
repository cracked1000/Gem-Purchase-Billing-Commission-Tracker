<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Seller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class BackupService
{
    public const FORMAT_VERSION = 1;

    /** Build a complete, portable backup of business data. */
    public function payload(): array
    {
        $sellers = Seller::withTrashed()
            ->orderBy('id')
            ->get()
            ->map(fn (Seller $seller) => [
                'original_id' => $seller->id,
                'name' => $seller->name,
                'phone' => $seller->phone,
                'notes' => $seller->notes,
                'created_at' => optional($seller->created_at)?->toISOString(),
                'updated_at' => optional($seller->updated_at)?->toISOString(),
                'deleted_at' => optional($seller->deleted_at)?->toISOString(),
            ])->values()->all();

        $invoices = Invoice::withTrashed()
            ->with(['items', 'seller' => fn ($query) => $query->withTrashed()])
            ->orderBy('id')
            ->get()
            ->map(fn (Invoice $invoice) => [
                'invoice_number' => $invoice->invoice_number,
                'seller_original_id' => $invoice->seller_id,
                'sold_at' => $invoice->sold_at->format('Y-m-d'),
                'commission_percent' => (string) $invoice->commission_percent,
                'status' => $invoice->status,
                'notes' => $invoice->notes,
                'created_at' => optional($invoice->created_at)?->toISOString(),
                'updated_at' => optional($invoice->updated_at)?->toISOString(),
                'deleted_at' => optional($invoice->deleted_at)?->toISOString(),
                'items' => $invoice->items->map(fn ($item) => [
                    'serial_number' => $item->serial_number,
                    'price_mode' => $item->price_mode,
                    'verification_actions' => $item->verification_actions,
                    'stone_notes' => $item->stone_notes,
                    'description' => $item->description,
                    'carats' => $item->carats,
                    'pieces' => $item->pieces,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->amount,
                ])->values()->all(),
            ])->values()->all();

        $data = [
            'format' => 'gemdesk-backup',
            'version' => self::FORMAT_VERSION,
            'created_at' => now()->toISOString(),
            'app_timezone' => config('app.timezone'),
            'sellers' => $sellers,
            'invoices' => $invoices,
        ];

        $data['checksum'] = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $data;
    }

    public function json(): string
    {
        return json_encode($this->payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** Save a timestamped snapshot outside the public web directory. */
    public function snapshot(string $reason = 'manual'): string
    {
        $folder = storage_path('app/backups');
        File::ensureDirectoryExists($folder);

        $safeReason = Str::slug($reason) ?: 'backup';
        $path = $folder.'/gemdesk-'.now()->format('Ymd-His-v').'-'.$safeReason.'-'.Str::lower(Str::random(6)).'.json';
        if (File::put($path, $this->json(), true) === false) {
            throw new RuntimeException('Could not write the backup file. Check storage/app permissions and free disk space.');
        }

        $this->prune((int) config('gemdesk.backup_keep', 30));

        return $path;
    }

    /** Create at most one routine snapshot per calendar day. */
    public function dailySnapshot(): ?string
    {
        $folder = storage_path('app/backups');
        File::ensureDirectoryExists($folder);
        $prefix = 'gemdesk-'.now()->format('Ymd').'-';

        if (collect(File::files($folder))->contains(fn ($file) => str_starts_with($file->getFilename(), $prefix))) {
            return null;
        }

        return $this->snapshot('automatic');
    }

    public function decodeAndVerify(string $json): array
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (($data['format'] ?? null) !== 'gemdesk-backup' || ($data['version'] ?? null) !== self::FORMAT_VERSION) {
            throw new RuntimeException('This is not a supported GemDesk backup file.');
        }

        $checksum = $data['checksum'] ?? null;
        unset($data['checksum']);
        $actual = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if (! is_string($checksum) || ! hash_equals($checksum, $actual)) {
            throw new RuntimeException('Backup verification failed. The file may be incomplete or modified.');
        }

        $data['checksum'] = $checksum;

        return $data;
    }

    private function prune(int $keep): void
    {
        $keep = max(3, $keep);
        $files = collect(File::files(storage_path('app/backups')))
            ->filter(fn ($file) => $file->getExtension() === 'json')
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        $files->slice($keep)->each(fn ($file) => File::delete($file->getPathname()));
    }
}
