<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Services\GemRegisterService;
use App\Services\InvoiceService;
use App\Services\SimpleXlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class GemRegisterTest extends TestCase
{
    use RefreshDatabase;

    private function seedRegister(): void
    {
        $seller = Seller::factory()->create(['name' => 'RAVI PERERA', 'phone' => '0777123456']);
        app(InvoiceService::class)->create([
            'seller_id' => $seller->id,
            'sold_at' => '2026-08-18',
            'commission_percent' => 10,
            'items' => [
                ['serial_number' => 'SER-10', 'description' => 'RUBY', 'carats' => 1.2, 'pieces' => 1, 'unit_price' => 100000],
                ['serial_number' => 'SER-2', 'description' => 'SAPPHIRE A', 'carats' => 2, 'pieces' => 1, 'unit_price' => 150000],
                ['serial_number' => 'SER-2', 'description' => 'SAPPHIRE B', 'carats' => null, 'pieces' => 3, 'unit_price' => 20000],
            ],
        ]);
    }

    public function test_rows_are_naturally_sorted_by_serial_and_duplicates_remain_separate(): void
    {
        $this->seedRegister();
        $service = app(GemRegisterService::class);
        $data = $service->data($service->normalizeColumns(['serial', 'description']));

        $this->assertSame(['SER-2', 'SER-2', 'SER-10'], $data['rows']->pluck('serial')->all());
        $this->assertSame(['SAPPHIRE A', 'SAPPHIRE B', 'RUBY'], $data['rows']->pluck('description')->all());
    }

    public function test_custom_columns_apply_to_pdf_without_commission_or_net_fields(): void
    {
        $this->seedRegister();
        $service = app(GemRegisterService::class);
        $selected = $service->normalizeColumns(['serial', 'purchase_date', 'seller', 'pieces', 'bought_price']);
        $data = $service->data($selected);
        $html = view('gems.pdf', $data)->render();

        $this->assertStringContainsString('SER-2', $html);
        $this->assertStringContainsString('RAVI PERERA', $html);
        $this->assertStringContainsString('Bought Price', $html);
        $this->assertStringNotContainsString('Commission', $html);
        $this->assertStringNotContainsString('Net Payable', $html);

        $this->get(route('gems.pdf', ['columns' => $selected]))->assertOk();
    }

    public function test_excel_export_is_a_valid_xlsx_workbook(): void
    {
        $this->seedRegister();
        $service = app(GemRegisterService::class);
        $data = $service->data($service->normalizeColumns(['serial', 'description', 'seller_phone', 'bought_price']));
        $path = app(SimpleXlsx::class)->create($data['columns'], $data['rows'], $data['totals']);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $this->assertNotFalse($zip->getFromName('xl/workbook.xml'));
        $this->assertNotFalse($zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        @unlink($path);
    }
}
