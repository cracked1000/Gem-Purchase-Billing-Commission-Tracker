<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayinTest extends TestCase
{
    use RefreshDatabase;

    private function seller(string $name = 'RAVI PERERA', string $phone = '0777123456'): Seller
    {
        return Seller::factory()->create(['name' => $name, 'phone' => $phone]);
    }

    private function invoice(Seller $seller, string $description = 'BLUE SAPPHIRE', float $rate = 150000, float $carats = 2.5): \App\Models\Invoice
    {
        return app(InvoiceService::class)->create([
            'seller_id' => $seller->id,
            'sold_at'   => '2026-08-18',
            'commission_percent' => 10,
            'status'    => 'pending',
            'items'     => [[
                'serial_number' => '1',
                'description'   => $description,
                'carats'        => $carats,
                'pieces'        => 1,
                'unit_price'    => $rate,
            ]],
        ]);
    }

    public function test_unpaid_sellers_do_not_appear_on_payin(): void
    {
        $unpaid = $this->seller('UNPAID SELLER', '0777000001');
        $this->invoice($unpaid);

        $paid = $this->seller('PAID SELLER', '0777000002');
        $this->invoice($paid);
        $this->post(route('finance.payments.store', $paid), [
            'amount'  => '10000.00',
            'paid_at' => '2026-08-20T10:00:00',
        ]);

        $this->get(route('payin.index'))
            ->assertOk()
            ->assertSee('Payin')
            ->assertSee('PAID SELLER')
            ->assertDontSee('UNPAID SELLER');
    }

    public function test_partial_and_fully_paid_sellers_are_listed_with_remaining(): void
    {
        $seller = $this->seller();
        // net = 337500.00
        $this->invoice($seller);

        $this->post(route('finance.payments.store', $seller), [
            'amount'  => '50000.00',
            'paid_at' => '2026-08-20T10:30:00',
            'method'  => 'cash',
        ])->assertRedirect();

        $this->get(route('payin.index'))
            ->assertOk()
            ->assertSee('RAVI PERERA')
            ->assertSee('Partial')
            ->assertSee('LKR 50,000.00')
            ->assertSee('LKR 287,500.00'); // remaining

        $this->get(route('payin.index', ['status' => 'partial']))
            ->assertOk()
            ->assertSee('RAVI PERERA');

        $this->get(route('payin.index', ['status' => 'settled']))
            ->assertOk()
            ->assertDontSee('RAVI PERERA');
    }

    public function test_payin_detail_shows_sold_commission_gems_and_bill_link(): void
    {
        $seller = $this->seller();
        $invoice = $this->invoice($seller, 'PINK SAPPHIRE');

        $this->post(route('finance.payments.store', $seller), [
            'amount'  => '10000.00',
            'paid_at' => '2026-08-20T10:00:00',
        ]);

        $this->get(route('payin.show', $seller))
            ->assertOk()
            ->assertSee('RAVI PERERA')
            ->assertSee('Remaining to pay')
            ->assertSee('Commission Cut')
            ->assertSee('What they sold')
            ->assertSee('PINK SAPPHIRE')
            ->assertSee($invoice->invoice_number)
            ->assertSee('View bill')
            ->assertSee(route('invoices.show', $invoice), false);
    }

    public function test_search_finds_a_paid_seller_by_name_and_bill(): void
    {
        $seller = $this->seller('MOHAMED RIZAN', '0771234567');
        $invoice = $this->invoice($seller);
        $this->post(route('finance.payments.store', $seller), [
            'amount'  => '1000.00',
            'paid_at' => '2026-08-20T10:00:00',
        ]);

        $this->get(route('payin.index', ['search' => 'RIZAN']))
            ->assertOk()
            ->assertSee('MOHAMED RIZAN');

        $this->get(route('payin.index', ['search' => $invoice->invoice_number]))
            ->assertOk()
            ->assertSee('MOHAMED RIZAN');
    }
}
