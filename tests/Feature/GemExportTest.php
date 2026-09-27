<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Services\GemExportService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GemExportTest extends TestCase
{
    use RefreshDatabase;

    private function bill(array $items, string $soldAt = '2026-08-19'): \App\Models\Invoice
    {
        $seller = Seller::factory()->create();
        return app(InvoiceService::class)->create([
            'seller_id' => $seller->id,
            'sold_at' => $soldAt,
            'commission_percent' => 10,
            'status' => 'pending',
            'items' => $items,
        ]);
    }

    private function exported(string $mode = 'all', ?string $date = null): array
    {
        return app(GemExportService::class)->data($mode, $date);
    }

    private function namesFor(array $data, string $key): array
    {
        return $data['sections'][$key]->rows->pluck('name')->all();
    }

    public function test_blue_sapphire_weight_split(): void
    {
        $this->bill([
            ['serial_number' => 'A1', 'description' => 'BLUE SAPPHIRE', 'carats' => 4.5, 'pieces' => 1, 'unit_price' => 100000],
            ['serial_number' => 'A2', 'description' => 'BLUE SAPPHIRE', 'carats' => 1.8, 'pieces' => 1, 'unit_price' => 100000],
        ]);

        $data = $this->exported();
        $this->assertContains('BLUE SAPPHIRE', $this->namesFor($data, 'blue_sapphire_above'));
        $this->assertContains('BLUE SAPPHIRE', $this->namesFor($data, 'blue_sapphire_below'));
        $this->assertEmpty($this->namesFor($data, 'blue_lot'));
    }

    public function test_two_piece_blue_sapphire_always_goes_below_three(): void
    {
        // Even a 5ct piece x2 must land in Below 3 ct (business rule).
        $this->bill([
            ['serial_number' => 'B1', 'description' => 'BLUE SAPPHIRE', 'carats' => 5.0, 'pieces' => 2, 'unit_price' => 50000],
        ]);

        $data = $this->exported();
        $this->assertContains('BLUE SAPPHIRE', $this->namesFor($data, 'blue_sapphire_below'));
        $this->assertEmpty($this->namesFor($data, 'blue_sapphire_above'));
        $this->assertEmpty($this->namesFor($data, 'blue_lot'));
    }

    public function test_three_plus_blue_sapphire_pieces_become_a_lot(): void
    {
        $this->bill([
            ['serial_number' => 'C1', 'description' => 'BLUE SAPPHIRE', 'carats' => 5.5, 'pieces' => 4, 'unit_price' => 50000],
        ]);

        $data = $this->exported();
        $this->assertContains('BLUE SAPPHIRE', $this->namesFor($data, 'blue_lot'));
        $this->assertEmpty($this->namesFor($data, 'blue_sapphire_above'));
    }

    public function test_lot_word_in_name_or_comments_makes_a_lot(): void
    {
        $this->bill([
            ['serial_number' => 'D1', 'description' => 'BLUE SAPPHIRE LOT', 'carats' => 6.2, 'pieces' => 2, 'unit_price' => 50000, 'stone_notes' => ''],
            ['serial_number' => 'D2', 'description' => 'BLUE SAPPHIRE', 'carats' => 7.0, 'pieces' => 2, 'unit_price' => 50000, 'stone_notes' => 'PARCEL OF 2'],
        ]);

        $data = $this->exported();
        $this->assertContains('BLUE SAPPHIRE LOT', $this->namesFor($data, 'blue_lot'));
        $this->assertContains('BLUE SAPPHIRE', $this->namesFor($data, 'blue_lot'));
    }

    public function test_white_gets_a_lot_section_but_green_stays_in_green(): void
    {
        $this->bill([
            ['serial_number' => 'E1', 'description' => 'WHITE SAPPHIRE', 'carats' => 2.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'E2', 'description' => 'WHITE SAPPHIRE LOT', 'carats' => 10.0, 'pieces' => 3, 'unit_price' => 50000],
            // Green sapphire parcel stays in Green Sapphire — no green lot section.
            ['serial_number' => 'E3', 'description' => 'GREEN SAPPHIRE PARCEL', 'carats' => 9.0, 'pieces' => 5, 'unit_price' => 50000],
            ['serial_number' => 'E4', 'description' => 'GREEN SAPPHIRE', 'carats' => 1.1, 'pieces' => 1, 'unit_price' => 50000],
        ]);

        $data = $this->exported();
        $this->assertContains('WHITE SAPPHIRE', $this->namesFor($data, 'white_sapphire'));
        $this->assertContains('WHITE SAPPHIRE LOT', $this->namesFor($data, 'white_lot'));
        // Green sapphire parcel is a multi-piece lot but still belongs to Green Sapphire.
        $this->assertContains('GREEN SAPPHIRE', $this->namesFor($data, 'green_sapphire'));
        $this->assertContains('GREEN SAPPHIRE PARCEL', $this->namesFor($data, 'green_sapphire'));
        // No green_lot bucket exists at all now.
        $this->assertArrayNotHasKey('green_lot', $data['buckets']);
    }

    public function test_other_coloured_lots_stay_in_their_colour_section(): void
    {
        $this->bill([
            ['serial_number' => 'K1', 'description' => 'PINK SAPPHIRE', 'carats' => 4.0, 'pieces' => 4, 'unit_price' => 50000],
            ['serial_number' => 'K2', 'description' => 'YELLOW SAPPHIRE PARCEL', 'carats' => 6.0, 'pieces' => 5, 'unit_price' => 50000],
            ['serial_number' => 'K3', 'description' => 'TEAL SAPPHIRE', 'carats' => 3.0, 'pieces' => 3, 'unit_price' => 50000],
        ]);

        $data = $this->exported();
        // Multi-piece lots of non-blue/white sapphires stay in their colour section.
        $this->assertContains('PINK SAPPHIRE', $this->namesFor($data, 'pink_sapphire'));
        $this->assertContains('YELLOW SAPPHIRE PARCEL', $this->namesFor($data, 'yellow_sapphire'));
        $this->assertContains('TEAL SAPPHIRE', $this->namesFor($data, 'teal_sapphire'));
    }

    public function test_cats_eye_goes_to_a_dedicated_section(): void
    {
        $this->bill([
            ['serial_number' => 'CT1', 'description' => "CAT'S EYE", 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'CT2', 'description' => 'CATSEYE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'CT3', 'description' => 'CHRYSOBERYL CATS EYE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
        ]);

        $data = $this->exported();
        $cat = $this->namesFor($data, 'catseye');
        $this->assertContains("CAT'S EYE", $cat);
        $this->assertContains('CATSEYE', $cat);
        $this->assertContains('CHRYSOBERYL CATS EYE', $cat);
        $this->assertNotEmpty($cat);
    }

    public function test_named_stone_categories(): void
    {
        $this->bill([
            ['serial_number' => 'F1', 'description' => 'TSV', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'F2', 'description' => 'TSAVORITE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'F3', 'description' => 'SPINEL', 'carats' => 1.2, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'F4', 'description' => 'TOURMALINE', 'carats' => 2.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'F5', 'description' => 'KORNARUPINE', 'carats' => 1.5, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'F6', 'description' => 'ALEXANDRITE', 'carats' => 0.8, 'pieces' => 1, 'unit_price' => 50000],
        ]);

        $data = $this->exported();
        $this->assertContains('TSV', $this->namesFor($data, 'tsv'));
        $this->assertContains('TSAVORITE', $this->namesFor($data, 'tsv'));
        $this->assertContains('SPINEL', $this->namesFor($data, 'spinel'));
        $this->assertContains('TOURMALINE', $this->namesFor($data, 'tourmaline'));
        $this->assertContains('KORNARUPINE', $this->namesFor($data, 'tourmaline'));
        $this->assertContains('ALEXANDRITE', $this->namesFor($data, 'alexandrite'));
    }

    public function test_moonstones_always_land_in_the_assign_section(): void
    {
        $invoice = $this->bill([
            ['serial_number' => 'M1', 'description' => 'MOONSTONE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'M2', 'description' => 'MOONSTONE FACETED', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'M3', 'description' => 'MOONSTONE CABOCHON', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
        ]);

        // Every moonstone lands in the assign section, never auto-split.
        $data = $this->exported();
        $assignNames = $this->namesFor($data, 'moonstone_assign');
        $this->assertContains('MOONSTONE', $assignNames);
        $this->assertContains('MOONSTONE FACETED', $assignNames);
        $this->assertContains('MOONSTONE CABOCHON', $assignNames);
        $this->assertEmpty($this->namesFor($data, 'moonstone_faceted'));
        $this->assertEmpty($this->namesFor($data, 'moonstone_nonfac'));

        // Assign the first moonstone to faceted -> it moves out of the assign section.
        $item = $invoice->items()->where('serial_number', 'M2')->first();
        $this->post(route('export.assign'), [
            'item_id' => $item->id,
            'category' => 'moonstone_faceted',
        ])->assertRedirect();

        $data = $this->exported();
        $this->assertContains('MOONSTONE FACETED', $this->namesFor($data, 'moonstone_faceted'));
        $this->assertNotContains('MOONSTONE FACETED', $this->namesFor($data, 'moonstone_assign'));
        $this->assertContains('MOONSTONE CABOCHON', $this->namesFor($data, 'moonstone_assign'));
    }

    public function test_pr_is_padparadscha_and_star_sapphire_is_separate(): void
    {
        $this->bill([
            ['serial_number' => 'P1', 'description' => 'PR', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'P2', 'description' => 'PADPARADSCHA SAPPHIRE', 'carats' => 1.5, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'P3', 'description' => 'STAR SAPPHIRE', 'carats' => 2.0, 'pieces' => 1, 'unit_price' => 50000],
        ]);

        $data = $this->exported();
        $this->assertContains('PR', $this->namesFor($data, 'padparadscha'));
        $this->assertContains('PADPARADSCHA SAPPHIRE', $this->namesFor($data, 'padparadscha'));
        $this->assertContains('STAR SAPPHIRE', $this->namesFor($data, 'star_sapphire'));
    }

    public function test_coloured_sapphires(): void
    {
        $this->bill([
            ['serial_number' => 'G1', 'description' => 'YELLOW SAPPHIRE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'G2', 'description' => 'PINK SAPPHIRE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'G3', 'description' => 'PURPLE SAPPHIRE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'G4', 'description' => 'PEACH SAPPHIRE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
        ]);

        $data = $this->exported();
        $this->assertContains('YELLOW SAPPHIRE', $this->namesFor($data, 'yellow_sapphire'));
        $this->assertContains('PINK SAPPHIRE', $this->namesFor($data, 'pink_sapphire'));
        $this->assertContains('PURPLE SAPPHIRE', $this->namesFor($data, 'purple_sapphire'));
        $this->assertContains('PEACH SAPPHIRE', $this->namesFor($data, 'peach_sapphire'));
    }

    public function test_semi_precious_and_spelling_review(): void
    {
        $this->bill([
            ['serial_number' => 'H1', 'description' => 'AMETHYST', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'H2', 'description' => 'GARNET', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'H3', 'description' => 'BLUE SAPPHIRRE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'H4', 'description' => 'WEIRD ROCK XYZ', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
        ]);

        $data = $this->exported();
        $this->assertContains('AMETHYST', $this->namesFor($data, 'semi'));
        $this->assertContains('GARNET', $this->namesFor($data, 'semi'));
        $this->assertContains('WEIRD ROCK XYZ', $this->namesFor($data, 'semi'));
        // Sapphire typo -> review, not semi-precious.
        $this->assertContains('BLUE SAPPHIRRE', $this->namesFor($data, 'review'));
    }

    public function test_teal_generic_and_mix_sapphire(): void
    {
        $this->bill([
            ['serial_number' => 'T1', 'description' => 'TEAL SAPPHIRE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'T2', 'description' => 'CEYLON SAPPHIRE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'T3', 'description' => 'MIX SAPPHIRE LOT', 'carats' => 5.0, 'pieces' => 5, 'unit_price' => 50000],
            ['serial_number' => 'T4', 'description' => 'MIXED SAPPHIRES', 'carats' => 4.0, 'pieces' => 4, 'unit_price' => 50000],
            ['serial_number' => 'T5', 'description' => 'FANCY SAPPHIRE', 'carats' => 2.0, 'pieces' => 1, 'unit_price' => 50000],
        ]);

        $data = $this->exported();
        $this->assertContains('TEAL SAPPHIRE', $this->namesFor($data, 'teal_sapphire'));
        $this->assertContains('CEYLON SAPPHIRE', $this->namesFor($data, 'sapphire'));
        $this->assertContains('FANCY SAPPHIRE', $this->namesFor($data, 'sapphire'));
        $this->assertContains('MIX SAPPHIRE LOT', $this->namesFor($data, 'mix_sapphire_lot'));
        $this->assertContains('MIXED SAPPHIRES', $this->namesFor($data, 'mix_sapphire_lot'));
    }

    public function test_rows_are_sorted_by_serial_number_naturally(): void
    {
        $this->bill([
            ['serial_number' => 'G10', 'description' => 'SPINEL', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'G2', 'description' => 'SPINEL', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'G1', 'description' => 'SPINEL', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
        ]);

        $serials = $this->exported()['sections']['spinel']->rows->pluck('serial')->all();
        // Natural ascending: G1, G2, G10 (not G1, G10, G2).
        $this->assertSame(['G1', 'G2', 'G10'], $serials);
    }

    public function test_day_mode_filters_to_one_day(): void
    {
        $this->bill([
            ['serial_number' => 'X1', 'description' => 'SPINEL', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
        ], '2026-08-19');
        $this->bill([
            ['serial_number' => 'X2', 'description' => 'ALEXANDRITE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
        ], '2026-08-20');

        $data = $this->exported('day', '2026-08-19');
        $this->assertContains('SPINEL', $this->namesFor($data, 'spinel'));
        $this->assertEmpty($this->namesFor($data, 'alexandrite'));
        $this->assertSame(1, $data['grand']->gems);
    }

    public function test_daily_mode_groups_by_purchase_day(): void
    {
        $this->bill([
            ['serial_number' => 'Y1', 'description' => 'SPINEL', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
        ], '2026-08-19');
        $this->bill([
            ['serial_number' => 'Y2', 'description' => 'TSAVORITE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
        ], '2026-08-20');

        $data = $this->exported('daily');
        $this->assertCount(2, $data['daily']);
        // Each day has its own sections.
        $day1 = $data['daily']->firstWhere(fn ($d) => $d->date->format('Y-m-d') === '2026-08-19');
        $day2 = $data['daily']->firstWhere(fn ($d) => $d->date->format('Y-m-d') === '2026-08-20');
        $this->assertContains('SPINEL', $day1->sections['spinel']->rows->pluck('name')->all());
        $this->assertContains('TSAVORITE', $day2->sections['tsv']->rows->pluck('name')->all());
    }

    public function test_serial_layout_lists_the_whole_day_in_ascending_order(): void
    {
        // A day whose serials run 304..350 (mixed categories).
        $this->bill([
            ['serial_number' => '350', 'description' => 'SPINEL', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => '304', 'description' => 'BLUE SAPPHIRE', 'carats' => 4.2, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => '322', 'description' => 'TSV', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => '305', 'description' => 'MOONSTONE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => '306', 'description' => 'GARNET', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
        ], '2026-08-24');

        $data = $this->exported('day', '2026-08-24');
        $serials = $data['flat']->pluck('serial')->all();

        // Must be exactly 304, 305, 306, 322, 350 — continuous and ascending.
        $this->assertSame(['304', '305', '306', '322', '350'], $serials);

        // Each row carries its category label.
        $bySerial = $data['flat']->keyBy('serial');
        $this->assertSame('Blue Sapphire Above 3 ct', $bySerial['304']['category']);
        $this->assertSame('TSV (Tsavorite)', $bySerial['322']['category']);
    }

    public function test_serial_layout_is_respected_by_page_pdf_and_excel(): void
    {
        $this->bill([
            ['serial_number' => '2', 'description' => 'SPINEL', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => '1', 'description' => 'TSV', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
        ]);

        // Page: toggle on -> serial list present.
        $this->get(route('export.index', ['mode' => 'all', 'layout' => 'serial']))
            ->assertOk()->assertSee('By serial number');
        $this->get(route('export.pdf', ['mode' => 'all', 'layout' => 'serial']))->assertOk();
        $this->get(route('export.excel', ['mode' => 'all', 'layout' => 'serial']))->assertOk();
    }

    public function test_bare_color_names_go_to_review_not_semi(): void
    {
        $this->bill([
            ['serial_number' => 'C1', 'description' => 'HOT PINK', 'carats' => 2.0, 'pieces' => 1, 'unit_price' => 50000],
        ]);

        $data = $this->exported();
        // A colour-only name is ambiguous -> needs review, so the user assigns it,
        // instead of silently landing in Semi-Precious.
        $this->assertContains('HOT PINK', $this->namesFor($data, 'review'));
        $this->assertNotContains('HOT PINK', $this->namesFor($data, 'semi'));
    }

    public function test_registry_lists_every_gem_with_category_and_supports_assign(): void
    {
        $invoice = $this->bill([
            ['serial_number' => 'R1', 'description' => 'GARNET', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'R2', 'description' => 'HOT PINK', 'carats' => 2.0, 'pieces' => 1, 'unit_price' => 50000],
        ]);

        // Registry page renders and shows both gems.
        $this->get(route('export.registry'))
            ->assertOk()
            ->assertSee('GARNET')
            ->assertSee('HOT PINK');

        // Registry data lists each gem with its current category.
        $registry = app(GemExportService::class)->registry();
        $bySerial = $registry['rows']->keyBy('serial');
        $this->assertSame('Semi-Precious', $bySerial['R1']['category']);
        $this->assertSame('⚠️ Needs Review', $bySerial['R2']['category']);
        $this->assertSame($invoice->items()->count(), $registry['grand']->gems);

        // Assign a gem via the registry -> the override sticks and re-categorises.
        $item = $invoice->items()->where('serial_number', 'R2')->first();
        $this->post(route('export.assign'), [
            'item_id' => $item->id,
            'category' => 'pink_sapphire',
        ])->assertRedirect();

        $this->assertDatabaseHas('invoice_items', ['id' => $item->id, 'export_category' => 'pink_sapphire']);
        $this->assertContains('HOT PINK', $this->namesFor($this->exported(), 'pink_sapphire'));
    }

    public function test_registry_filter_and_per_category_pdf(): void
    {
        $this->bill([
            ['serial_number' => 'F1', 'description' => 'PINK SAPPHIRE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'F2', 'description' => 'SPINEL', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
        ]);

        // Registry filter by category only shows that category.
        $this->get(route('export.registry', ['category' => 'spinel']))
            ->assertOk()
            ->assertSee('SPINEL')
            ->assertDontSee('PINK SAPPHIRE');

        // Per-category PDF renders.
        $this->get(route('export.pdf', ['mode' => 'all', 'layout' => 'category', 'category' => 'pink_sapphire']))
            ->assertOk();
    }

    public function test_category_pdfs_with_slash_or_emoji_titles_download_safely(): void
    {
        $this->bill([
            ['serial_number' => 'T1', 'description' => 'TOURMALINE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'T2', 'description' => 'KORNARUPINE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'T3', 'description' => "CAT'S EYE", 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'T4', 'description' => 'MOONSTONE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'T5', 'description' => 'HOT PINK', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
        ]);

        // Titles that used to break Symfony Content-Disposition: slash, apostrophe, emoji.
        foreach (['tourmaline', 'catseye', 'moonstone_assign', 'review'] as $category) {
            $response = $this->get(route('export.pdf', [
                'mode' => 'all',
                'layout' => 'category',
                'category' => $category,
            ]));

            $response->assertOk();
            $disposition = (string) $response->headers->get('content-disposition');
            $this->assertNotSame('', $disposition);
            $this->assertStringNotContainsString('/', $disposition);
            $this->assertStringNotContainsString('\\', $disposition);
            $this->assertMatchesRegularExpression('/filename[^;]*=/i', $disposition);
        }
    }

    public function test_serial_check_reports_missing_and_duplicates(): void
    {
        // Serials 1..5, with 3 missing and 2 duplicated.
        $this->bill([
            ['serial_number' => '1', 'description' => 'SPINEL', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => '2', 'description' => 'TSV', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => '2', 'description' => 'MOONSTONE', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => '4', 'description' => 'GARNET', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => '5', 'description' => 'SPINEL', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
        ]);

        $check = app(GemExportService::class)->serialCheck(5);

        // Serial 3 is missing.
        $this->assertContains(3, $check['missing']);
        $this->assertNotContains(1, $check['missing']);
        $this->assertSame(1, $check['missing_count']);

        // Serial 2 is duplicated (2 rows).
        $dupSerials = $check['duplicates']->pluck('serial')->all();
        $this->assertContains('2', $dupSerials);
        $two = $check['duplicates']->firstWhere('serial', '2');
        $this->assertSame(2, $two['count']);

        $this->assertSame(5, $check['total_rows']);
        $this->assertSame(4, $check['unique_numeric']); // 1,2,4,5
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $check['duplicates']);
    }

    public function test_serial_check_flags_non_numeric_and_double_checks(): void
    {
        $this->bill([
            ['serial_number' => '1', 'description' => 'SPINEL', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => 'S304', 'description' => 'TSV', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
        ]);

        $check = app(GemExportService::class)->serialCheck(1);

        // S304 is non-numeric -> can't be range-checked.
        $this->assertSame(1, $check['non_numeric_count']);
        $this->assertContains('S304', $check['non_numeric']->pluck('serial')->all());

        // The double-pass verification reports the scan as consistent.
        $this->assertTrue($check['consistent']);
    }

    public function test_serial_check_page_renders_with_results(): void
    {
        $this->bill([
            ['serial_number' => '1', 'description' => 'SPINEL', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
            ['serial_number' => '3', 'description' => 'TSV', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
        ]);

        // Serial 2 is missing; registry shows the integrity table.
        $this->get(route('export.registry', ['checktop' => 3]))
            ->assertOk()
            ->assertSee('Serial Number Integrity Check')
            ->assertSee('missing');
    }

    public function test_assign_override_moves_a_gem_out_of_review(): void
    {
        $invoice = $this->bill([
            ['serial_number' => 'R1', 'description' => 'UNKNOWN THING', 'carats' => 1.0, 'pieces' => 1, 'unit_price' => 50000],
        ]);

        $item = $invoice->items()->first();

        // Unassigned -> semi-precious.
        $this->assertContains('UNKNOWN THING', $this->namesFor($this->exported(), 'semi'));

        // Assign it to spinel.
        $this->post(route('export.assign'), [
            'item_id' => $item->id,
            'category' => 'spinel',
        ])->assertRedirect();

        $this->assertDatabaseHas('invoice_items', ['id' => $item->id, 'export_category' => 'spinel']);
        $this->assertContains('UNKNOWN THING', $this->namesFor($this->exported(), 'spinel'));

        // Clearing returns to automatic.
        $this->post(route('export.assign'), [
            'item_id' => $item->id,
            'category' => 'auto',
        ])->assertRedirect();
        $this->assertDatabaseHas('invoice_items', ['id' => $item->id, 'export_category' => null]);
    }

    public function test_export_pages_pdf_excel_render_in_all_modes(): void
    {
        $this->bill([
            ['serial_number' => 'X1', 'description' => 'BLUE SAPPHIRE', 'carats' => 4.0, 'pieces' => 1, 'unit_price' => 50000],
        ], '2026-08-19');

        $this->get(route('export.index', ['mode' => 'all']))
            ->assertOk()->assertSee('Gem Export')->assertSee('Blue Sapphire Above 3 ct');
        $this->get(route('export.index', ['mode' => 'day', 'date' => '2026-08-19']))
            ->assertOk()->assertSee('By day');
        $this->get(route('export.index', ['mode' => 'daily']))
            ->assertOk()->assertSee('Every day')->assertSee('2026-08-19');

        $this->get(route('export.pdf', ['mode' => 'all']))->assertOk();
        $this->get(route('export.pdf', ['mode' => 'daily']))->assertOk();
        $this->get(route('export.excel', ['mode' => 'all']))->assertOk();
        $this->get(route('export.excel', ['mode' => 'daily']))->assertOk();
    }
}
