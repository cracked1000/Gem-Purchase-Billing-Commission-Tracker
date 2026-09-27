<?php

namespace Database\Seeders;

use App\Models\Seller;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo data so the dashboard is meaningful on first run.
     * Wipe it any time with:  php artisan migrate:fresh
     */
    public function run(): void
    {
        $sellers = collect([
            ['name' => 'M. F. Rizan',        'phone' => '077 341 8890', 'notes' => 'Beruwala — mostly sapphires'],
            ['name' => 'Saman Kumara',       'phone' => '071 552 0034', 'notes' => 'Ratnapura dealer'],
            ['name' => 'Abdul Cader',        'phone' => '076 118 7723', 'notes' => null],
            ['name' => 'Nimal Perera',       'phone' => '077 909 4411', 'notes' => 'Elahera — garnets & spinel'],
            ['name' => 'R. V. Gnanasekaran', 'phone' => '075 264 9800', 'notes' => null],
        ])->map(fn ($row) => Seller::create($row));

        $gems = [
            ['Blue Sapphire', 45_000, 380_000],
            ['Yellow Sapphire', 30_000, 220_000],
            ['Padparadscha Sapphire', 120_000, 850_000],
            ['Ruby', 60_000, 420_000],
            ['Pink Sapphire', 35_000, 260_000],
            ['Alexandrite', 90_000, 700_000],
            ["Cat's Eye Chrysoberyl", 25_000, 180_000],
            ['Spinel', 18_000, 120_000],
            ['Garnet (Hessonite)', 8_000, 60_000],
            ['Tourmaline', 10_000, 75_000],
            ['Moonstone', 5_000, 30_000],
            ['Zircon', 7_000, 45_000],
        ];

        $service = app(InvoiceService::class);
        $rand = fn (int $min, int $max) => random_int($min, $max);

        for ($i = 0; $i < 16; $i++) {
            $seller = $sellers->random();
            $itemCount = $rand(1, 4);
            $items = [];

            for ($j = 0; $j < $itemCount; $j++) {
                [$name, $lo, $hi] = $gems[array_rand($gems)];
                $items[] = [
                    'description' => $name,
                    'carats'      => $rand(0, 1) ? round($rand(8, 120) / 10, 2) : null,
                    'pieces'      => $rand(1, 3),
                    'unit_price'  => $rand((int) ($lo / 1000), (int) ($hi / 1000)) * 1000,
                ];
            }

            $service->create([
                'seller_id'          => $seller->id,
                'sold_at'            => Carbon::now()->subDays($rand(0, 120))->toDateString(),
                'commission_percent' => [10, 10, 10, 8, 12][array_rand([0, 1, 2, 3, 4])],
                'status'             => $rand(0, 1) ? 'paid' : 'pending',
                'notes'              => null,
                'items'              => $items,
            ]);
        }
    }
}
