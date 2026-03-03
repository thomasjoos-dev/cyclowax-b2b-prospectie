<?php

namespace Database\Factories;

use App\Enums\BrandCategory;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => str($name)->slug(),
            'category' => fake()->randomElement(BrandCategory::cases()),
            'locator_url' => fake()->boolean(30) ? fake()->url() : null,
        ];
    }
}
