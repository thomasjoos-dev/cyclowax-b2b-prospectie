<?php

namespace Database\Seeders;

use App\Enums\BrandCategory;
use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * @var array<string, array<int, array{name: string, locator_url: string|null}>>
     */
    private const BRANDS = [
        'race_bike' => [
            ['name' => 'Specialized', 'locator_url' => 'https://www.specialized.com/store-finder'],
            ['name' => 'Trek', 'locator_url' => 'https://www.trekbikes.com/store-finder'],
            ['name' => 'Orbea', 'locator_url' => 'https://www.orbea.com/dealers'],
            ['name' => 'Cervélo', 'locator_url' => null],
            ['name' => 'Giant', 'locator_url' => 'https://www.giant-bicycles.com/dealers'],
            ['name' => 'Bianchi', 'locator_url' => 'https://www.bianchi.com/dealers'],
            ['name' => 'Scott', 'locator_url' => 'https://www.scott-sports.com/dealer-locator'],
            ['name' => 'Pinarello', 'locator_url' => null],
            ['name' => 'Cannondale', 'locator_url' => 'https://www.cannondale.com/store-locator'],
            ['name' => 'Ridley', 'locator_url' => 'https://www.ridley-bikes.com/dealers'],
            ['name' => 'Merida', 'locator_url' => 'https://www.merida-bikes.com/dealer-locator'],
        ],
        'nutrition' => [
            ['name' => 'SIS', 'locator_url' => null],
            ['name' => 'Etixx', 'locator_url' => null],
            ['name' => '4Gold', 'locator_url' => null],
            ['name' => 'Maurten', 'locator_url' => null],
        ],
        'clothing' => [
            ['name' => 'Assos', 'locator_url' => 'https://www.assos.com/store-locator'],
            ['name' => 'MAAP', 'locator_url' => null],
            ['name' => 'Rapha', 'locator_url' => 'https://www.rapha.cc/stores'],
            ['name' => 'Castelli', 'locator_url' => null],
        ],
        'tools' => [
            ['name' => 'Park Tool', 'locator_url' => null],
        ],
    ];

    public function run(): void
    {
        foreach (self::BRANDS as $categoryValue => $brands) {
            $category = BrandCategory::from($categoryValue);

            foreach ($brands as $brandData) {
                Brand::query()->updateOrCreate(
                    ['name' => $brandData['name']],
                    [
                        'slug' => str($brandData['name'])->slug(),
                        'category' => $category,
                        'locator_url' => $brandData['locator_url'],
                    ],
                );
            }
        }
    }
}
