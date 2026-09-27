<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Seller;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalizingTest extends TestCase
{
    use RefreshDatabase;

    private function makeTransaction(): void
    {
        $seller = Seller::factory()->create(['name' => 'Ravi Perera', 'phone' => '0777123456']);

        app(InvoiceService::class)->create([
            'seller_id' => $seller->id,
            'sold_at' => '2026-08-18',
            'commission_percent' => 10,
            'status' => 'pending',
            'items' => [
                ['serial_number' => '1', 'description' => 'Blue Sapphire', 'carats' => 2.5, 'pieces' => 1, 'unit_price' => 150000],
                ['serial_number' => '2', 'description' => 'Mixed Lot', 'carats' => null, 'pieces' => 4, 'unit_price' => 12000],
            ],
        ]);
    }

    public function test_buyer_view_is_itemized_without_private_settlement_fields(): void
    {
        $this->makeTransaction();

        $this->get(route('finalizing.index', ['version' => 'buyer']))
            ->assertOk()
            ->assertSee('Finalizing')
            ->assertSee('Ravi Perera')
            ->assertSee('BLUE SAPPHIRE')
            ->assertSee('MIXED LOT')
            ->assertSee('Serial')
            ->assertSee('All-Time Total')
            ->assertDontSee('Receipt #')
            ->assertDontSee('0777123456')
            ->assertDontSee('Commission')
            ->assertDontSee('Net to Seller');
    }

    public function test_seller_view_has_complete_settlement_columns(): void
    {
        $this->makeTransaction();

        $this->get(route('finalizing.index', ['version' => 'seller']))
            ->assertOk()
            ->assertSee('0777123456')
            ->assertSee('Commission')
            ->assertSee('Purchased')
            ->assertSee('Net')
            ->assertSee('Pending')
            ->assertDontSee('Receipt #');
    }

    public function test_receipt_numbers_are_hidden_from_the_panel(): void
    {
        $this->makeTransaction();

        $number = Invoice::query()->value('invoice_number');

        $this->assertNotEmpty($number);

        $this->get(route('finalizing.index', ['version' => 'seller']))
            ->assertOk()
            ->assertDontSee('Receipt #')
            ->assertDontSee($number);

        $this->get(route('finalizing.index', ['version' => 'seller', 'sort' => 'serial']))
            ->assertOk()
            ->assertDontSee('Receipt #')
            ->assertDontSee($number);
    }

    public function test_serial_sort_lists_gems_in_ascending_index_order(): void
    {
        $seller = Seller::factory()->create(['name' => 'Serial Seller', 'phone' => '0777000111']);

        // Recorded out of serial order on purpose: 10 first, then 2, then 1.
        app(InvoiceService::class)->create([
            'seller_id' => $seller->id,
            'sold_at' => '2026-08-18',
            'commission_percent' => 10,
            'status' => 'pending',
            'items' => [
                ['serial_number' => '10', 'description' => 'Yellow Marker Stone', 'carats' => 1, 'pieces' => 1, 'unit_price' => 1000],
            ],
        ]);
        app(InvoiceService::class)->create([
            'seller_id' => $seller->id,
            'sold_at' => '2026-08-18',
            'commission_percent' => 10,
            'status' => 'pending',
            'items' => [
                ['serial_number' => '2', 'description' => 'Pink Marker Stone', 'carats' => 1, 'pieces' => 1, 'unit_price' => 1000],
                ['serial_number' => '1', 'description' => 'Blue Marker Stone', 'carats' => 1, 'pieces' => 1, 'unit_price' => 1000],
            ],
        ]);

        $this->get(route('finalizing.index', ['version' => 'buyer', 'sort' => 'serial']))
            ->assertOk()
            ->assertSee('Serial 1 → N')
            ->assertSeeInOrder([
                'BLUE MARKER STONE',
                'PINK MARKER STONE',
                'YELLOW MARKER STONE',
            ]);
    }

    public function test_both_finalizing_pdfs_are_generated(): void
    {
        $this->makeTransaction();

        $this->get(route('finalizing.pdf', ['buyer' => 1]))->assertOk();
        $this->get(route('finalizing.pdf', ['buyer' => 0]))->assertOk();
        $this->get(route('finalizing.pdf', ['buyer' => 1, 'sort' => 'serial']))->assertOk();
        $this->get(route('finalizing.pdf', ['buyer' => 0, 'sort' => 'serial']))->assertOk();
    }
}
