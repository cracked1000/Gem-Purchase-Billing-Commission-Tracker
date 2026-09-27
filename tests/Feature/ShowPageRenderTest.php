<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowPageRenderTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(Seller $seller): Invoice
    {
        return Invoice::create([
            'invoice_number'     => 'GD-TEST-1',
            'seller_id'          => $seller->id,
            'sold_at'            => '2026-08-01',
            'commission_percent' => 10,
            'subtotal'           => 100000,
            'commission_amount'  => 10000,
            'net_payable'        => 90000,
            'total_pieces'       => 5,
            'status'             => 'pending',
        ]);
    }

    public function test_receipt_page_renders_with_chat_and_folder_buttons(): void
    {
        $seller = Seller::factory()->create(['name' => 'Ravi Perera', 'phone' => '0777123456']);
        $invoice = $this->makeInvoice($seller);

        $this->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Seller Receipt')
            ->assertSee('Buyer Receipt')
            ->assertSee('final net amount')
            ->assertSee("buyer's purchase amount", false)
            ->assertSee('Open Chat')
            ->assertSee('Receipts Folder')
            ->assertSee('whatsapp://send?phone=94777123456', false)
            ->assertSee('openReceiptsFolder')
            ->assertSee('Ravi-Perera-0777123456-GD-TEST-1.pdf', false);
    }

    public function test_receipt_page_renders_without_phone(): void
    {
        $seller = Seller::factory()->create(['phone' => null]);
        $invoice = $this->makeInvoice($seller);

        $this->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Seller Receipt')
            ->assertSee('Buyer Receipt')
            ->assertDontSee('whatsapp://send?phone=9', false); // no number → no chat link
    }

    public function test_receipt_page_shows_saved_banner_after_finalise(): void
    {
        $seller = Seller::factory()->create(['name' => 'Ravi Perera', 'phone' => '0777123456']);
        $invoice = $this->makeInvoice($seller);

        $this->withSession([
            'receipt_saved' => [
                'filename' => 'Ravi-Perera-0777123456-GD-TEST-1.pdf',
                'folder'   => '/tmp/receipts',
                'path'     => '/tmp/receipts/Ravi-Perera-0777123456-GD-TEST-1.pdf',
            ],
        ])->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Receipt saved to the Receipts folder')
            ->assertSee('Ravi-Perera-0777123456-GD-TEST-1.pdf');
    }
}
