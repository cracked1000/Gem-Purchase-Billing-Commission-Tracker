<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\Seller;
use App\Services\ReceiptStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptStorageTest extends TestCase
{
    use RefreshDatabase;

    private function invoiceWith(string $name = 'Ravi Perera', ?string $phone = '0777123456', string $number = '0608202601'): Invoice
    {
        $seller = Seller::factory()->create(['name' => $name, 'phone' => $phone]);

        return Invoice::create([
            'invoice_number'     => $number,
            'seller_id'          => $seller->id,
            'sold_at'            => '2026-08-06',
            'commission_percent' => 10,
            'subtotal'           => 100000,
            'commission_amount'  => 10000,
            'net_payable'        => 90000,
            'total_pieces'       => 5,
            'status'             => 'pending',
        ]);
    }

    public function test_filename_contains_seller_name_phone_and_bill_number(): void
    {
        $invoice = $this->invoiceWith('Ravi Perera', '0777 123 456', '0608202601');

        $this->assertSame('Ravi-Perera-0777123456-0608202601.pdf', ReceiptStorage::filenameFor($invoice));
    }

    public function test_filename_is_filesystem_safe(): void
    {
        $invoice = $this->invoiceWith('Kumar/Silva  & Co. (Pvt)', '0777123456', '0608202601');

        $this->assertSame('Kumar-Silva-Co-Pvt-0777123456-0608202601.pdf', ReceiptStorage::filenameFor($invoice));
    }

    public function test_filename_falls_back_when_no_phone(): void
    {
        $invoice = $this->invoiceWith('Nimal', null, '0608202601');

        $this->assertSame('Nimal-no-number-0608202601.pdf', ReceiptStorage::filenameFor($invoice));
    }

    public function test_save_writes_pdf_into_configured_folder(): void
    {
        $folder = sys_get_temp_dir() . '/gemdesk-receipts-test-' . uniqid();
        config()->set('gemdesk.receipts_folder', $folder);

        try {
            $result = ReceiptStorage::save($this->invoiceWith());

            $this->assertNotNull($result);
            $this->assertSame($folder, $result['folder']);
            $this->assertFileExists($result['path']);
            $this->assertStringStartsWith('%PDF', file_get_contents($result['path']));
        } finally {
            @unlink($folder . '/Ravi-Perera-0777123456-0608202601.pdf');
            @rmdir($folder);
        }
    }

    public function test_relative_folder_resolves_against_project_root(): void
    {
        config()->set('gemdesk.receipts_folder', 'receipts');

        $this->assertSame(base_path('receipts'), ReceiptStorage::resolveFolder());
    }

    public function test_absolute_windows_path_is_used_as_is(): void
    {
        config()->set('gemdesk.receipts_folder', 'D:\GemDesk Receipts');

        $this->assertSame('D:\GemDesk Receipts', ReceiptStorage::resolveFolder());
    }
}
