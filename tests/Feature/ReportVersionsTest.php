<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportVersionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTransaction(): \App\Models\Invoice
    {
        $seller = Seller::factory()->create(['name' => 'Ravi Perera', 'phone' => '0777123456']);

        return app(InvoiceService::class)->create([
            'seller_id' => $seller->id,
            'sold_at' => '2026-08-18',
            'commission_percent' => 10,
            'status' => 'pending',
            'notes' => 'Private settlement note',
            'items' => [
                ['description' => 'Blue Sapphire', 'carats' => 2.5, 'pieces' => 1, 'unit_price' => 150000],
                ['description' => 'Mixed Stone Lot', 'carats' => null, 'pieces' => 4, 'unit_price' => 12000],
            ],
        ]);
    }

    public function test_buyer_report_contains_seller_name_and_items_but_no_private_settlement_data(): void
    {
        $invoice = $this->makeTransaction()->load(['seller', 'items']);
        $totals = (object) ['count' => 1, 'pieces' => 5, 'volume' => 423000, 'commission' => 42300, 'net' => 380700];

        $html = view('reports.pdf', [
            'invoices' => collect([$invoice]),
            'totals' => $totals,
            'filters' => [],
            'buyerVersion' => true,
            'reportTitle' => 'Daily Purchase Report',
            'periodLabel' => '18 August 2026',
        ])->render();

        $this->assertStringContainsString($invoice->invoice_number, $html);
        $this->assertStringContainsString('Ravi Perera', $html);
        $this->assertStringContainsString('BLUE SAPPHIRE', $html);
        $this->assertStringContainsString('MIXED STONE LOT', $html);
        $this->assertStringNotContainsString('0777123456', $html);
        $this->assertStringNotContainsString('Commission', $html);
        $this->assertStringNotContainsString('Net Amount Payable', $html);
        $this->assertStringNotContainsString('Private settlement note', $html);
    }

    public function test_seller_report_contains_complete_settlement_data(): void
    {
        $invoice = $this->makeTransaction()->load(['seller', 'items']);
        $totals = (object) ['count' => 1, 'pieces' => 5, 'volume' => 423000, 'commission' => 42300, 'net' => 380700];

        $html = view('reports.pdf', [
            'invoices' => collect([$invoice]),
            'totals' => $totals,
            'filters' => [],
            'buyerVersion' => false,
            'reportTitle' => 'Complete Purchase Register',
            'periodLabel' => 'All recorded transactions',
        ])->render();

        $this->assertStringContainsString($invoice->invoice_number, $html);
        $this->assertStringContainsString('Ravi Perera', $html);
        $this->assertStringContainsString('0777123456', $html);
        $this->assertStringContainsString('Commission', $html);
        $this->assertStringContainsString('Net Amount Payable', $html);
        $this->assertStringContainsString('Private settlement note', $html);
    }

    public function test_daily_and_all_bill_pdf_routes_work_for_both_versions(): void
    {
        $this->makeTransaction();

        $this->get(route('reports.daily.pdf', ['date' => '2026-08-18', 'buyer' => 1, 'order' => 'seller']))->assertOk();
        $this->get(route('reports.daily.pdf', ['date' => '2026-08-18', 'buyer' => 0, 'order' => 'fifo']))->assertOk();
        $this->get(route('reports.all.pdf', ['buyer' => 1, 'order' => 'seller']))->assertOk();
        $this->get(route('reports.all.pdf', ['buyer' => 0, 'order' => 'fifo']))->assertOk();
    }
}
