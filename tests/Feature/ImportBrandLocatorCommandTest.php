<?php

use App\Enums\BrandCategory;
use App\Enums\DiscoverySource;
use App\Models\Brand;
use App\Models\Store;
use App\Services\SpecializedLocatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('command creates pivot records for matched dealers', function () {
    $brand = Brand::factory()->create([
        'name' => 'Specialized',
        'slug' => 'specialized',
        'category' => BrandCategory::RaceBike,
    ]);

    $store = Store::factory()->create([
        'name' => 'Bike Center Brussels',
        'postal_code' => '1000',
        'city' => 'Brussels',
    ]);

    $mockLocator = Mockery::mock(SpecializedLocatorService::class);
    $mockLocator->shouldReceive('fetchDealersForCountry')
        ->once()
        ->andReturn([
            'dealers' => [
                [
                    'name' => 'Bike Center Brussels',
                    'postal_code' => '1000',
                    'city' => 'Brussels',
                    'address' => null,
                    'country' => 'BE',
                    'phone' => null,
                    'email' => null,
                    'latitude' => null,
                    'longitude' => null,
                ],
            ],
            'queries' => 15,
        ]);

    $this->app->instance(SpecializedLocatorService::class, $mockLocator);

    $this->artisan('brands:import-locator', ['brand' => 'specialized', '--country' => 'BE'])
        ->assertSuccessful();

    expect($brand->stores()->count())->toBe(1)
        ->and($brand->stores()->first()->id)->toBe($store->id)
        ->and($brand->stores()->first()->pivot->discovery_source)->toBe(DiscoverySource::BrandLocator->value);
});

test('unmatched dealers are created as new stores', function () {
    $brand = Brand::factory()->create([
        'name' => 'Specialized',
        'slug' => 'specialized',
        'category' => BrandCategory::RaceBike,
    ]);

    $storeCountBefore = Store::count();

    $mockLocator = Mockery::mock(SpecializedLocatorService::class);
    $mockLocator->shouldReceive('fetchDealersForCountry')
        ->once()
        ->andReturn([
            'dealers' => [
                [
                    'name' => 'New Bike Shop Couvin',
                    'postal_code' => '5660',
                    'city' => 'Couvin',
                    'address' => 'Rue Haute 10',
                    'country' => 'BE',
                    'phone' => '+32 60 12 34 56',
                    'email' => null,
                    'latitude' => 50.05,
                    'longitude' => 4.49,
                ],
            ],
            'queries' => 15,
        ]);

    $this->app->instance(SpecializedLocatorService::class, $mockLocator);

    $this->artisan('brands:import-locator', ['brand' => 'specialized', '--country' => 'BE'])
        ->assertSuccessful();

    $newStore = Store::query()->where('name', 'New Bike Shop Couvin')->first();

    expect(Store::count())->toBe($storeCountBefore + 1)
        ->and($newStore)->not->toBeNull()
        ->and($newStore->discovery_source)->toBe(DiscoverySource::BrandLocator)
        ->and($newStore->city)->toBe('Couvin')
        ->and($newStore->phone)->toBe('+32 60 12 34 56')
        ->and($brand->stores()->count())->toBe(1)
        ->and($brand->stores()->first()->id)->toBe($newStore->id);
});

test('dry-run does not create any records', function () {
    $brand = Brand::factory()->create([
        'name' => 'Specialized',
        'slug' => 'specialized',
        'category' => BrandCategory::RaceBike,
    ]);

    $storeCountBefore = Store::count();

    $mockLocator = Mockery::mock(SpecializedLocatorService::class);
    $mockLocator->shouldReceive('fetchDealersForCountry')
        ->once()
        ->andReturn([
            'dealers' => [
                [
                    'name' => 'Bike Center Brussels',
                    'postal_code' => '1000',
                    'city' => 'Brussels',
                    'address' => null,
                    'country' => 'BE',
                    'phone' => null,
                    'email' => null,
                    'latitude' => null,
                    'longitude' => null,
                ],
            ],
            'queries' => 15,
        ]);

    $this->app->instance(SpecializedLocatorService::class, $mockLocator);

    $this->artisan('brands:import-locator', ['brand' => 'specialized', '--country' => 'BE', '--dry-run' => true])
        ->assertSuccessful();

    expect($brand->stores()->count())->toBe(0)
        ->and(Store::count())->toBe($storeCountBefore);
});

test('unknown brand returns failure', function () {
    $this->artisan('brands:import-locator', ['brand' => 'nonexistent-brand', '--country' => 'BE'])
        ->assertFailed();
});

test('empty API response shows warning', function () {
    Brand::factory()->create([
        'name' => 'Specialized',
        'slug' => 'specialized',
        'category' => BrandCategory::RaceBike,
    ]);

    $mockLocator = Mockery::mock(SpecializedLocatorService::class);
    $mockLocator->shouldReceive('fetchDealersForCountry')
        ->once()
        ->andReturn(['dealers' => [], 'queries' => 15]);

    $this->app->instance(SpecializedLocatorService::class, $mockLocator);

    $this->artisan('brands:import-locator', ['brand' => 'specialized', '--country' => 'BE'])
        ->assertFailed()
        ->expectsOutput('Geen dealers gevonden.');
});
