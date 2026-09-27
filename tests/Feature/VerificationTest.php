<?php

namespace Tests\Feature;

use App\Models\InvoiceItem;
use App\Models\Seller;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeItems(): array
    {
        $seller = Seller::factory()->create();
        $invoice = app(InvoiceService::class)->create([
            'seller_id' => $seller->id,
            'sold_at' => '2026-08-18',
            'commission_percent' => 10,
            'items' => [
                [
                    'serial_number' => 'a-1',
                    'description' => 'blue sapphire',
                    'carats' => 2.5,
                    'pieces' => 1,
                    'unit_price' => 150000,
                    'verification_actions' => ['recheck', 'recut'],
                    'stone_notes' => 'check the colour again',
                ],
                [
                    'serial_number' => '2',
                    'description' => 'clean stone',
                    'carats' => 1,
                    'pieces' => 1,
                    'unit_price' => 10000,
                    'verification_actions' => [],
                    'stone_notes' => '',
                ],
            ],
        ]);

        return $invoice->items()->orderBy('id')->get()->all();
    }

    public function test_only_flagged_or_noted_gems_appear_and_text_is_uppercase(): void
    {
        [$flagged, $normal] = $this->makeItems();

        $this->assertSame('A-1', $flagged->serial_number);
        $this->assertSame('BLUE SAPPHIRE', $flagged->description);
        $this->assertSame('CHECK THE COLOUR AGAIN', $flagged->stone_notes);
        $this->assertSame(['recheck', 'recut'], $flagged->verification_actions);

        $this->get(route('verification.index'))
            ->assertOk()
            ->assertSee('BLUE SAPPHIRE')
            ->assertSee('RECHECK')
            ->assertSee('RECUT')
            ->assertDontSee('CLEAN STONE');
    }

    public function test_changes_in_verification_update_the_original_item_and_can_remove_it(): void
    {
        [$flagged] = $this->makeItems();
        $folder = sys_get_temp_dir().'/gemdesk-verification-'.uniqid();
        config()->set('gemdesk.receipts_folder', $folder);

        $this->patch(route('verification.update', $flagged), [
            'verification_actions' => ['repolish'],
            'stone_notes' => 'polish the surface',
        ])->assertRedirect(route('verification.index'));

        $flagged->refresh();
        $this->assertSame(['repolish'], $flagged->verification_actions);
        $this->assertSame('POLISH THE SURFACE', $flagged->stone_notes);

        $this->patch(route('verification.update', $flagged), [
            'verification_actions' => [],
            'stone_notes' => '',
        ])->assertRedirect(route('verification.index'));

        $this->get(route('verification.index'))
            ->assertOk()
            ->assertDontSee('BLUE SAPPHIRE');

        foreach (glob($folder.'/*.pdf') ?: [] as $file) @unlink($file);
        @rmdir($folder);
    }
}
