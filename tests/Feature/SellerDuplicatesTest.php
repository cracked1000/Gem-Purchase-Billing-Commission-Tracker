<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    private function seller(string $name, string $phone): Seller
    {
        return Seller::factory()->create(['name' => $name, 'phone' => $phone]);
    }

    private function invoice(Seller $seller): \App\Models\Invoice
    {
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

    public function test_creating_a_seller_with_an_existing_number_is_blocked(): void
    {
        $this->seller('RAVI PERERA', '077 123 4567');

        // Same digits, different formatting — must still count as the same number.
        $this->post(route('sellers.store'), [
            'name' => 'RAVI PERERA COPY',
            'phone' => '0771234567',
        ])->assertSessionHasErrors('phone');

        $this->assertSame(1, Seller::count());
    }

    public function test_local_and_international_formats_of_the_same_number_are_blocked(): void
    {
        $this->seller('RAVI PERERA', '0771234567');

        $this->post(route('sellers.store'), [
            'name' => 'RAVI PERERA TWO',
            'phone' => '+94 77 123 4567',
        ])->assertSessionHasErrors('phone');

        $this->assertSame(1, Seller::count());
    }

    public function test_updating_a_seller_to_an_existing_number_is_blocked(): void
    {
        $a = $this->seller('SELLER A', '0771111111');
        $b = $this->seller('SELLER B', '0772222222');

        $this->put(route('sellers.update', $b), ['name' => 'SELLER B', 'phone' => '0771111111'])
            ->assertSessionHasErrors('phone');

        // Keeping its own number is fine.
        $this->put(route('sellers.update', $b), ['name' => 'SELLER B', 'phone' => '0772222222'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sellers', ['id' => $a->id, 'phone' => '0771111111']);
    }

    public function test_search_scans_every_seller_not_just_the_current_page(): void
    {
        for ($i = 1; $i <= 17; $i++) {
            $this->seller(
                'SELLER '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                '0770000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)
            );
        }

        // SELLER 17 lives on page 2 (15 sellers per page, sorted by name).
        $this->get(route('sellers.index', ['search' => 'SELLER 17']))
            ->assertOk()
            ->assertSee('SELLER 17')
            ->assertDontSee('SELLER 16')
            ->assertDontSee('SELLER 01');
    }

    public function test_duplicate_panel_lists_profiles_that_share_a_number(): void
    {
        $this->seller('SAME PERSON A', '077 123 4567');
        $this->seller('SAME PERSON B', '0771234567');
        $this->seller('UNIQUE PERSON', '0779999999');

        $this->get(route('sellers.duplicates'))
            ->assertOk()
            ->assertSee('SAME PERSON A')
            ->assertSee('SAME PERSON B')
            ->assertDontSee('UNIQUE PERSON');
    }

    public function test_merge_moves_all_bills_and_archives_duplicates_without_deleting(): void
    {
        $primary = $this->seller('PRIMARY SELLER', '0771234567');
        $dupe = $this->seller('DUPLICATE SELLER', '077 123 4567');

        $invoice = $this->invoice($dupe);

        $this->post(route('sellers.duplicates.merge'), [
            'primary_id' => $primary->id,
            'all_ids' => [$primary->id, $dupe->id],
        ])->assertRedirect(route('sellers.duplicates'));

        // Only the primary profile stays active…
        $this->assertSame(1, Seller::count());

        // …but NOTHING was deleted: the duplicate is archived (soft-deleted).
        $this->assertNull(Seller::find($dupe->id));
        $this->assertNotNull(Seller::withTrashed()->find($dupe->id));

        // Every bill moved under the primary profile.
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'seller_id' => $primary->id]);

        // The panel now reports the number as clean.
        $this->get(route('sellers.duplicates'))
            ->assertOk()
            ->assertSee('No duplicate numbers found');
    }

    public function test_merge_rejects_profiles_with_different_numbers(): void
    {
        $primary = $this->seller('PRIMARY SELLER', '0771234567');
        $other = $this->seller('DIFFERENT NUMBER', '0778888888');

        $this->post(route('sellers.duplicates.merge'), [
            'primary_id' => $primary->id,
            'all_ids' => [$primary->id, $other->id],
        ])->assertRedirect(route('sellers.duplicates'));

        // Both profiles remain untouched.
        $this->assertSame(2, Seller::count());
        $this->assertNotNull(Seller::find($other->id));
    }

    public function test_seller_show_page_lists_all_bills_under_the_shared_number(): void
    {
        $a = $this->seller('PROFILE A', '0771234567');
        $b = $this->seller('PROFILE B', '077 123 4567');

        $this->invoice($a);
        $this->invoice($b);

        $this->get(route('sellers.show', $a))
            ->assertOk()
            ->assertSee('profiles share this number')
            ->assertSee('PROFILE B');
    }
}
