<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Seller;
use App\Services\BackupService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(Seller $seller, string $date = '2026-08-18'): Invoice
    {
        return app(InvoiceService::class)->create([
            'seller_id' => $seller->id,
            'sold_at' => $date,
            'commission_percent' => 10,
            'status' => 'pending',
            'items' => [[
                'description' => 'Blue Sapphire',
                'carats' => 2.55,
                'pieces' => 1,
                'unit_price' => 123456.78,
            ]],
        ]);
    }

    public function test_archiving_a_bill_keeps_it_recoverable_with_items(): void
    {
        $invoice = $this->invoice(Seller::factory()->create());

        $this->delete(route('invoices.destroy', $invoice))->assertRedirect(route('invoices.index'));

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoice->id]);

        $this->patch(route('invoices.restore', $invoice->id))->assertRedirect();
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'deleted_at' => null]);
    }

    public function test_invoice_numbers_are_not_reused_after_archive(): void
    {
        $seller = Seller::factory()->create();
        $first = $this->invoice($seller);
        $first->delete();
        $second = $this->invoice($seller);

        $this->assertNotSame($first->invoice_number, $second->invoice_number);
        $this->assertSame('1808202601', $first->invoice_number);
        $this->assertSame('1808202602', $second->invoice_number);
    }

    public function test_backup_has_a_valid_checksum_and_includes_archived_records(): void
    {
        $invoice = $this->invoice(Seller::factory()->create());
        $invoice->delete();

        $service = app(BackupService::class);
        $decoded = $service->decodeAndVerify($service->json());

        $this->assertSame('gemdesk-backup', $decoded['format']);
        $this->assertCount(1, $decoded['sellers']);
        $this->assertCount(1, $decoded['invoices']);
        $this->assertNotNull($decoded['invoices'][0]['deleted_at']);
        $this->assertCount(1, $decoded['invoices'][0]['items']);
    }

    public function test_tampered_backup_is_rejected(): void
    {
        $service = app(BackupService::class);
        $json = str_replace('gemdesk-backup', 'changed-backup', $service->json());

        $this->expectException(\RuntimeException::class);
        $service->decodeAndVerify($json);
    }
}
