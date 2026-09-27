<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Services\FinanceService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    private function seller(string $name, string $phone): Seller
    {
        return Seller::factory()->create(['name' => $name, 'phone' => $phone]);
    }

    private function invoice(Seller $seller, float $rate, float $carats, float $commission = 10): \App\Models\Invoice
    {
        return app(InvoiceService::class)->create([
            'seller_id' => $seller->id,
            'sold_at'   => '2026-08-18',
            'commission_percent' => $commission,
            'status'    => 'pending',
            'items'     => [[
                'serial_number' => '1',
                'description'   => 'BLUE SAPPHIRE',
                'carats'        => $carats,
                'pieces'        => 1,
                'unit_price'    => $rate,
            ]],
        ]);
    }

    public function test_finance_summary_is_exact_with_no_rounding(): void
    {
        $seller = $this->seller('RAVI PERERA', '0777123456');
        // 2.5 ct @ 150000 = 375000.00 subtotal; commission 10% = 37500.00; net = 337500.00
        $this->invoice($seller, 150000, 2.5, 10);

        $summary = app(FinanceService::class)->summaryFor($seller);

        $this->assertSame('375000.00', $summary->sold);
        $this->assertSame('37500.00', $summary->commission);
        $this->assertSame('337500.00', $summary->net);
        $this->assertSame('0.00', $summary->paid);
        $this->assertSame('337500.00', $summary->outstanding);
        $this->assertFalse($summary->is_overpaid);
    }

    public function test_recording_a_payment_reduces_outstanding_and_stores_timestamp(): void
    {
        $seller = $this->seller('RAVI PERERA', '0777123456');
        $this->invoice($seller, 150000, 2.5, 10); // net 337500.00

        $this->post(route('finance.payments.store', $seller), [
            'amount'  => '50000.00',
            'paid_at' => '2026-08-20T10:30:00',
            'method'  => 'bank',
            'reference' => 'CHQ-1001',
        ])->assertRedirect();

        $summary = app(FinanceService::class)->summaryFor($seller);
        $this->assertSame('50000.00', $summary->paid);
        $this->assertSame('287500.00', $summary->outstanding); // 337500 - 50000

        // Ledger shows the payment with date & timestamp (comma-formatted).
        $this->get(route('finance.show', $seller))
            ->assertOk()
            ->assertSee('LKR 50,000.00')
            ->assertSee('20 Aug 2026');
    }

    public function test_multiple_payments_accumulate_and_can_go_overpaid(): void
    {
        $seller = $this->seller('GREEN LOT', '0779991111');
        $this->invoice($seller, 100000, 1.0, 10); // subtotal 100000, net 90000

        $this->post(route('finance.payments.store', $seller), ['amount' => '60000.00', 'paid_at' => '2026-08-20T10:00:00']);
        $this->post(route('finance.payments.store', $seller), ['amount' => '60000.00', 'paid_at' => '2026-08-21T10:00:00']);

        $summary = app(FinanceService::class)->summaryFor($seller);
        // Paid 120000, net 90000 -> overpaid by 30000.
        $this->assertSame('120000.00', $summary->paid);
        $this->assertSame('-30000.00', $summary->outstanding);
        $this->assertTrue($summary->is_overpaid);
    }

    public function test_removing_a_payment_recalculates_balance(): void
    {
        $seller = $this->seller('RAVI PERERA', '0777123456');
        $this->invoice($seller, 150000, 2.5, 10);

        $this->post(route('finance.payments.store', $seller), ['amount' => '50000.00', 'paid_at' => '2026-08-20T10:00:00']);
        $payment = $seller->payments()->first();

        $this->delete(route('finance.payments.destroy', $payment))->assertRedirect();

        $summary = app(FinanceService::class)->summaryFor($seller);
        $this->assertSame('0.00', $summary->paid);
        $this->assertSame('337500.00', $summary->outstanding);
    }

    public function test_search_by_name_and_phone_finds_seller(): void
    {
        $seller = $this->seller('MOHAMED RIZAN', '0771234567');
        $this->invoice($seller, 100000, 1.0, 10);

        $this->get(route('finance.index', ['search' => 'RIZAN']))
            ->assertOk()
            ->assertSee('MOHAMED RIZAN');

        $this->get(route('finance.index', ['search' => '0771234567']))
            ->assertOk()
            ->assertSee('MOHAMED RIZAN');
    }

    public function test_search_by_bill_number_shows_the_bill(): void
    {
        $seller = $this->seller('MOHAMED RIZAN', '0771234567');
        $invoice = $this->invoice($seller, 100000, 1.0, 10);

        $this->get(route('finance.index', ['search' => $invoice->invoice_number]))
            ->assertOk()
            ->assertSee($invoice->invoice_number);
    }

    public function test_finance_page_shows_every_bill_for_a_seller(): void
    {
        $seller = $this->seller('MOHAMED RIZAN', '0771234567');
        $this->invoice($seller, 100000, 1.0, 10);
        $this->invoice($seller, 200000, 1.0, 10);

        $this->get(route('finance.show', $seller))
            ->assertOk()
            ->assertSee('Bills from')
            ->assertSee('LKR 200,000.00');
    }
}
