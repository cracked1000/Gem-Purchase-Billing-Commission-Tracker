<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemPricingAndSerialTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_price_calculates_rate_per_carat_and_saves_serial(): void
    {
        $seller = Seller::factory()->create();

        $invoice = app(InvoiceService::class)->create([
            'seller_id' => $seller->id,
            'sold_at' => '2026-08-18',
            'commission_percent' => 10,
            'status' => 'pending',
            'items' => [[
                'serial_number' => '1',
                'description' => 'Blue Sapphire',
                'carats' => 2.5,
                'pieces' => 1,
                'price_mode' => 'total',
                'unit_price' => '',
                'total_price' => 375001,
            ]],
        ]);

        $item = $invoice->items->first();
        $this->assertSame('1', $item->serial_number);
        $this->assertSame('total', $item->price_mode);
        $this->assertSame('150000.40', $item->unit_price);
        $this->assertSame('375001.00', $item->amount);
        $this->assertSame('375001.00', $invoice->subtotal);

        $html = view('invoices.pdf', ['invoice' => $invoice, 'buyerVersion' => true])->render();
        $this->assertStringContainsString('Serial', $html);
        $this->assertStringContainsString('>1<', $html);
    }

    public function test_bill_rejects_a_gem_without_a_serial_number(): void
    {
        $seller = Seller::factory()->create();

        $this->post(route('invoices.store'), [
            'seller_id' => $seller->id,
            'sold_at' => '2026-08-18',
            'commission_percent' => 10,
            'items' => [[
                'description' => 'BLUE SAPPHIRE',
                'carats' => 2.5,
                'pieces' => 1,
                'unit_price' => 150000,
            ]],
        ])->assertSessionHasErrors('items.0.serial_number');
    }

    public function test_bill_form_has_comma_formatted_total_and_amount_in_words_preview(): void
    {
        $this->get(route('invoices.create'))
            ->assertOk()
            ->assertSee('total-price-display', false)
            ->assertSee('price-words', false)
            ->assertSee('subtotalWords', false)
            ->assertSee('Enter either the rate or the total price');
    }

    public function test_total_price_without_weight_calculates_rate_per_piece(): void
    {
        $seller = Seller::factory()->create();

        $invoice = app(InvoiceService::class)->create([
            'seller_id' => $seller->id,
            'sold_at' => '2026-08-18',
            'commission_percent' => 10,
            'items' => [[
                'serial_number' => '2',
                'description' => 'Mixed Lot',
                'carats' => null,
                'pieces' => 4,
                'price_mode' => 'total',
                'total_price' => 48000,
            ]],
        ]);

        $item = $invoice->items->first();
        $this->assertSame('12000.00', $item->unit_price);
        $this->assertSame('48000.00', $item->amount);
    }
}
