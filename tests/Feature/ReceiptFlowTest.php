<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Seller;
use App\Services\ReceiptStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptFlowTest extends TestCase
{
    use RefreshDatabase;

    private function payload(Seller $seller): array
    {
        return [
            'seller_id'          => $seller->id,
            'sold_at'            => '2026-08-06',
            'commission_percent' => 10,
            'status'             => 'pending',
            'notes'              => null,
            'items'              => [
                ['serial_number' => '1', 'description' => 'Blue Sapphire', 'carats' => 2.5, 'pieces' => 1, 'unit_price' => 150000],
            ],
        ];
    }

    private function tempFolder(): string
    {
        $folder = sys_get_temp_dir() . '/gemdesk-flow-' . uniqid();
        config()->set('gemdesk.receipts_folder', $folder);

        return $folder;
    }

    public function test_finalising_bill_saves_pdf_to_receipts_folder(): void
    {
        $folder = $this->tempFolder();
        $seller = Seller::factory()->create(['name' => 'Ravi Perera', 'phone' => '0777123456']);

        $this->post(route('invoices.store'), $this->payload($seller))
            ->assertRedirect();

        $invoice = Invoice::first();
        $expected = $folder . '/Ravi-Perera-0777123456-' . $invoice->invoice_number . '.pdf';

        $this->assertFileExists($expected);
        $this->assertStringStartsWith('%PDF', file_get_contents($expected));

        @unlink($expected);
        @rmdir($folder);
    }

    public function test_seller_receipt_shows_commission_and_net_payable(): void
    {
        $seller = Seller::factory()->create(['name' => 'Ravi Perera', 'phone' => '0777123456']);
        $this->post(route('invoices.store'), $this->payload($seller));

        $invoice = Invoice::first();

        $html = view('invoices.pdf', ['invoice' => $invoice->loadMissing(['items', 'seller']), 'buyerVersion' => false])->render();

        $this->assertStringContainsString('Purchase Receipt', $html);
        $this->assertStringContainsString('Commission', $html);
        $this->assertStringContainsString('Net payable to seller', $html);
        $this->assertStringContainsString('0777123456', $html); // seller's own phone is fine here
    }

    public function test_buyer_receipt_has_seller_name_but_no_phone_or_commission(): void
    {
        $seller = Seller::factory()->create(['name' => 'Ravi Perera', 'phone' => '0777123456']);
        $this->post(route('invoices.store'), $this->payload($seller));

        $invoice = Invoice::first();

        $html = view('invoices.pdf', ['invoice' => $invoice->loadMissing(['items', 'seller']), 'buyerVersion' => true])->render();

        $this->assertStringContainsString('Purchase Receipt', $html);
        $this->assertStringContainsString('Total Amount', $html);
        $this->assertStringNotContainsString('Commission', $html);
        $this->assertStringNotContainsString('Net payable', $html);
        $this->assertStringNotContainsString('0777123456', $html); // seller number must not appear
        $this->assertStringContainsString('Ravi Perera', $html); // seller name is required for traceability
    }

    public function test_finalising_flashes_receipt_saved_banner(): void
    {
        $this->tempFolder();
        $seller = Seller::factory()->create(['name' => 'Ravi Perera', 'phone' => '0777123456']);

        $this->post(route('invoices.store'), $this->payload($seller))
            ->assertRedirect()
            ->assertSessionHas('receipt_saved', function ($saved) {
                return str_contains($saved['filename'], 'Ravi-Perera-0777123456-')
                    && isset($saved['folder'], $saved['path']);
            });
    }

    public function test_auto_open_flag_flashed_when_seller_has_phone(): void
    {
        config()->set('gemdesk.auto_open_chat_on_finalize', true);
        $this->tempFolder();

        $seller = Seller::factory()->create(['phone' => '0777123456']);

        $this->post(route('invoices.store'), $this->payload($seller))
            ->assertRedirect()
            ->assertSessionHas('wa_open', true);
    }

    public function test_no_auto_open_without_phone_or_when_disabled(): void
    {
        $this->tempFolder();

        // No phone on file
        $seller = Seller::factory()->create(['phone' => null]);
        $this->post(route('invoices.store'), $this->payload($seller))
            ->assertRedirect()
            ->assertSessionMissing('wa_open');

        // Feature disabled
        config()->set('gemdesk.auto_open_chat_on_finalize', false);
        $seller2 = Seller::factory()->create(['phone' => '0777123456']);
        $this->post(route('invoices.store'), $this->payload($seller2))
            ->assertRedirect()
            ->assertSessionMissing('wa_open');
    }

    public function test_open_folder_route_returns_json(): void
    {
        $folder = $this->tempFolder();
        $seller = Seller::factory()->create(['name' => 'Ravi Perera', 'phone' => '0777123456']);

        $this->post(route('invoices.store'), $this->payload($seller));
        $invoice = Invoice::first();
        $filename = ReceiptStorage::filenameFor($invoice);

        $this->postJson(route('receipts.open-folder'), ['file' => $filename])
            ->assertOk()
            ->assertJsonStructure(['ok']);
    }

    public function test_open_folder_route_ignores_path_traversal(): void
    {
        $this->tempFolder();

        $this->postJson(route('receipts.open-folder'), ['file' => '../../etc/passwd'])
            ->assertOk()
            ->assertJsonStructure(['ok']);
    }
}
