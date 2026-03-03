<?php

use App\Models\Store;
use App\Services\StoreMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('exact match on postal code and normalized name returns confidence 1.0', function () {
    Store::factory()->create([
        'name' => 'Fietsen Janssens',
        'postal_code' => '9000',
        'city' => 'Gent',
    ]);

    $service = app(StoreMatchingService::class);

    $result = $service->match([
        ['name' => 'Fietsen Janssens', 'postal_code' => '9000', 'latitude' => null, 'longitude' => null],
    ]);

    expect($result['matched'])->toHaveCount(1)
        ->and($result['matched'][0]['confidence'])->toBe(1.0)
        ->and($result['matched'][0]['store']->name)->toBe('Fietsen Janssens')
        ->and($result['unmatched'])->toBeEmpty();
});

test('fuzzy match strips brand suffix and matches on postal code', function () {
    Store::factory()->create([
        'name' => 'Fietsen Janssens',
        'postal_code' => '9000',
        'city' => 'Gent',
    ]);

    $service = app(StoreMatchingService::class);

    $result = $service->match([
        ['name' => 'Fietsen Janssens - Specialized Gent', 'postal_code' => '9000', 'latitude' => null, 'longitude' => null],
    ], 'Specialized');

    expect($result['matched'])->toHaveCount(1)
        ->and($result['matched'][0]['confidence'])->toBeGreaterThanOrEqual(0.7);
});

test('name normalization strips legal forms', function () {
    $service = app(StoreMatchingService::class);

    expect($service->normalizeName('Bike Shop GmbH'))
        ->toBe('bike shop')
        ->and($service->normalizeName('Fietsen BVBA Janssens'))
        ->toBe('fietsen janssens')
        ->and($service->normalizeName('Radsport e.K. Berlin'))
        ->toBe('radsport berlin');
});

test('name normalization is case insensitive and strips special chars', function () {
    $service = app(StoreMatchingService::class);

    expect($service->normalizeName('Fiets & Co.'))
        ->toBe('fiets co')
        ->and($service->normalizeName('BIKE-CENTER GENT'))
        ->toBe('bikecenter gent');
});

test('name normalization strips brand suffix', function () {
    $service = app(StoreMatchingService::class);

    expect($service->normalizeName('Hot Wheelz - Specialized Bruxelles', 'Specialized'))
        ->toBe('hot wheelz');
});

test('proximity match works when postal codes differ but location is close', function () {
    Store::factory()->create([
        'name' => 'Bike Shop Berlin',
        'postal_code' => '10115',
        'latitude' => 52.520000,
        'longitude' => 13.405000,
    ]);

    $service = app(StoreMatchingService::class);

    // ~100m away, different postal code
    $result = $service->match([
        ['name' => 'Bike Shop Berlin', 'postal_code' => '10117', 'latitude' => 52.5209, 'longitude' => 13.405],
    ]);

    expect($result['matched'])->toHaveCount(1)
        ->and($result['matched'][0]['confidence'])->toBeGreaterThan(0.0);
});

test('no match returns dealer as unmatched', function () {
    Store::factory()->create([
        'name' => 'Completely Different Shop',
        'postal_code' => '1000',
        'city' => 'Brussels',
    ]);

    $service = app(StoreMatchingService::class);

    $result = $service->match([
        ['name' => 'Unknown Dealer', 'postal_code' => '9999', 'latitude' => 10.0, 'longitude' => 20.0],
    ]);

    expect($result['matched'])->toBeEmpty()
        ->and($result['unmatched'])->toHaveCount(1)
        ->and($result['unmatched'][0]['name'])->toBe('Unknown Dealer');
});

test('best match wins when multiple candidates exist', function () {
    Store::factory()->create([
        'name' => 'Fietsen Janssens',
        'postal_code' => '9000',
        'city' => 'Gent',
    ]);

    Store::factory()->create([
        'name' => 'Fietsen De Smet',
        'postal_code' => '9000',
        'city' => 'Gent',
    ]);

    $service = app(StoreMatchingService::class);

    $result = $service->match([
        ['name' => 'Fietsen Janssens', 'postal_code' => '9000', 'latitude' => null, 'longitude' => null],
    ]);

    expect($result['matched'])->toHaveCount(1)
        ->and($result['matched'][0]['store']->name)->toBe('Fietsen Janssens')
        ->and($result['matched'][0]['confidence'])->toBe(1.0);
});

test('matching handles empty dealer list', function () {
    $service = app(StoreMatchingService::class);

    $result = $service->match([]);

    expect($result['matched'])->toBeEmpty()
        ->and($result['unmatched'])->toBeEmpty();
});
