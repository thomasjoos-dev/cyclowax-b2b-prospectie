<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    /** @var array<int, string> */
    private const CITIES = [
        'Antwerpen', 'Gent', 'Leuven', 'Brugge', 'Mechelen',
        'Hasselt', 'Kortrijk', 'Aalst', 'Oostende', 'Turnhout',
        'Roeselare', 'Genk', 'Sint-Niklaas', 'Dendermonde', 'Knokke-Heist',
    ];

    /** @var array<string, string> */
    private const POSTAL_RANGES = [
        'Antwerpen' => '2000', 'Gent' => '9000', 'Leuven' => '3000',
        'Brugge' => '8000', 'Mechelen' => '2800', 'Hasselt' => '3500',
        'Kortrijk' => '8500', 'Aalst' => '9300', 'Oostende' => '8400',
        'Turnhout' => '2300', 'Roeselare' => '8800', 'Genk' => '3600',
        'Sint-Niklaas' => '9100', 'Dendermonde' => '9200', 'Knokke-Heist' => '8300',
    ];

    /** @var array<int, string> */
    private const SHOP_PREFIXES = [
        'Fietsenhuis', 'Bike Center', 'Velo', 'Cycling Store',
        'Fietsenwinkel', 'Bike Point', 'Tweewielers', 'Fietsparadijs',
        'Sportfietsen', 'Bike World', 'Fietsen', 'Wielershop',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->randomElement(self::CITIES);
        $postalCode = self::POSTAL_RANGES[$city];
        $prefix = fake()->randomElement(self::SHOP_PREFIXES);
        $name = $prefix.' '.fake()->lastName();

        return [
            'name' => $name,
            'address' => fake()->streetAddress(),
            'city' => $city,
            'country' => 'BE',
            'postal_code' => $postalCode,
            'phone' => fake()->boolean(55) ? fake()->phoneNumber() : null,
            'email' => fake()->boolean(39) ? fake()->safeEmail() : null,
            'website' => fake()->boolean(73) ? 'https://www.'.fake()->domainName() : null,
            'latitude' => fake()->latitude(50.8, 51.3),
            'longitude' => fake()->longitude(3.0, 5.5),
            'pipeline_status' => fake()->randomElement([
                'niet_gecontacteerd',
                'niet_gecontacteerd',
                'niet_gecontacteerd',
                'gecontacteerd',
                'in_gesprek',
                'partner',
                'afgewezen',
            ]),
            'is_existing_customer' => false,
            'discovery_source' => 'overpass',
            'notes' => null,
            'last_contacted_at' => null,
            'assigned_to' => null,
        ];
    }

    public function contacted(): static
    {
        return $this->state(fn () => [
            'pipeline_status' => 'gecontacteerd',
            'last_contacted_at' => fake()->dateTimeBetween('-30 days'),
        ]);
    }

    public function partner(): static
    {
        return $this->state(fn () => [
            'pipeline_status' => 'partner',
            'last_contacted_at' => fake()->dateTimeBetween('-60 days'),
        ]);
    }

    public function withCompleteContact(): static
    {
        return $this->state(fn () => [
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'website' => 'https://www.'.fake()->domainName(),
        ]);
    }

    public function assignedTo(string $member): static
    {
        return $this->state(fn () => ['assigned_to' => $member]);
    }

    public function withoutContact(): static
    {
        return $this->state(fn () => [
            'phone' => null,
            'email' => null,
            'website' => null,
        ]);
    }
}
