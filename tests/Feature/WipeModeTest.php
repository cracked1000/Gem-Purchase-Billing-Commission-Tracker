<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Services\InvoiceService;
use App\Services\WipeMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WipeModeTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(): \App\Models\Invoice
    {
        $seller = Seller::factory()->create(['name' => 'RAVI PERERA', 'phone' => '0777123456']);

        return app(InvoiceService::class)->create([
            'seller_id' => $seller->id,
            'sold_at' => '2026-08-19',
            'commission_percent' => 10,
            'status' => 'pending',
            'items' => [[
                'serial_number' => '1',
                'description' => 'BLUE SAPPHIRE',
                'carats' => 2.5,
                'pieces' => 1,
                'unit_price' => 150000,
            ]],
        ]);
    }

    public function test_setting_is_persistent_and_can_be_switched_on_and_off(): void
    {
        $this->patch(route('settings.update'), ['wipe' => '1'])->assertRedirect();
        $this->assertTrue(app(WipeMode::class)->enabled());
        $this->assertDatabaseHas('app_settings', ['key' => 'wipe_mode', 'value' => '1']);

        $this->app->forgetInstance(WipeMode::class);
        $this->assertTrue(app(WipeMode::class)->enabled());

        $this->patch(route('settings.update'), [])->assertRedirect();
        $this->assertFalse(app(WipeMode::class)->enabled());
    }

    public function test_wipe_hides_private_financial_values_from_main_screens(): void
    {
        $invoice = $this->invoice();
        app(WipeMode::class)->set(true);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Commission Overview')
            ->assertDontSee('Net Paid to Sellers')
            ->assertSee('Total Purchased');

        $this->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Buyer Receipt')
            ->assertDontSee('Seller Receipt')
            ->assertDontSee('Net to Seller')
            ->assertDontSee('Our Commission');

        $this->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Buyer PDF')
            ->assertDontSee('Seller PDF')
            ->assertDontSee('Net to Seller');
    }

    public function test_direct_pdf_requests_are_forced_to_purchase_versions(): void
    {
        $invoice = $this->invoice();
        app(WipeMode::class)->set(true);

        $this->get(route('invoices.pdf', ['invoice' => $invoice, 'buyer' => 0]))
            ->assertOk()
            ->assertDownload($invoice->invoice_number.'-purchase.pdf');

        $report = $this->get(route('reports.all.pdf', ['buyer' => 0, 'order' => 'fifo']))->assertOk();
        $this->assertStringContainsString('-purchase-', (string) $report->headers->get('content-disposition'));

        $finalizing = $this->get(route('finalizing.pdf', ['buyer' => 0]))->assertOk();
        $this->assertStringContainsString('-purchase-', (string) $finalizing->headers->get('content-disposition'));
    }
}
