<?php

use App\Models\Store;
use App\Services\StoreImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('import uses fallbackCountry when store has no country', function () {
    $importer = app(StoreImportService::class);

    $stores = [
        [
            'name' => 'Fahrrad Müller',
            'address' => 'Hauptstraße 1',
            'city' => 'Berlin',
            'country' => null,
            'postal_code' => '10115',
            'phone' => null,
            'email' => null,
            'website' => null,
            'latitude' => 52.52,
            'longitude' => 13.405,
        ],
    ];

    $result = $importer->import($stores, fallbackCountry: 'DE');

    expect($result['created'])->toBe(1);

    $store = Store::query()->where('name', 'Fahrrad Müller')->first();
    expect($store->country)->toBe('DE');
});

test('import does not override existing country with fallbackCountry', function () {
    $importer = app(StoreImportService::class);

    $stores = [
        [
            'name' => 'Austrian Bikes',
            'address' => null,
            'city' => 'Wien',
            'country' => 'AT',
            'postal_code' => '1010',
            'phone' => null,
            'email' => null,
            'website' => null,
            'latitude' => 48.20,
            'longitude' => 16.37,
        ],
    ];

    $importer->import($stores, fallbackCountry: 'DE');

    $store = Store::query()->where('name', 'Austrian Bikes')->first();
    expect($store->country)->toBe('AT');
});

test('geocodeMissingCities fills in missing cities via Nominatim', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            'address' => [
                'city' => 'Hamburg',
                'postcode' => '20095',
                'country_code' => 'de',
            ],
        ]),
    ]);

    $importer = app(StoreImportService::class);

    $stores = [
        [
            'name' => 'Bike Shop ohne Stadt',
            'city' => null,
            'postal_code' => null,
            'country' => null,
            'latitude' => 53.55,
            'longitude' => 9.99,
        ],
        [
            'name' => 'Shop met Stadt',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country' => 'DE',
            'latitude' => 52.52,
            'longitude' => 13.40,
        ],
    ];

    $geocoded = $importer->geocodeMissingCities($stores);

    expect($geocoded)->toBe(1)
        ->and($stores[0]['city'])->toBe('Hamburg')
        ->and($stores[0]['postal_code'])->toBe('20095')
        ->and($stores[0]['country'])->toBe('DE')
        ->and($stores[1]['city'])->toBe('Berlin');
});

test('geocodeMissingCities skips stores without coordinates', function () {
    Http::fake();

    $importer = app(StoreImportService::class);

    $stores = [
        [
            'name' => 'No coords shop',
            'city' => null,
            'latitude' => null,
            'longitude' => null,
        ],
    ];

    $geocoded = $importer->geocodeMissingCities($stores);

    expect($geocoded)->toBe(0);
    Http::assertNothingSent();
});

test('geocodeMissingCities calls progress callback', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            'address' => ['city' => 'Test', 'country_code' => 'de'],
        ]),
    ]);

    $importer = app(StoreImportService::class);

    $stores = [
        ['name' => 'Shop 1', 'city' => null, 'latitude' => 52.0, 'longitude' => 13.0],
        ['name' => 'Shop 2', 'city' => null, 'latitude' => 53.0, 'longitude' => 10.0],
    ];

    $progressCalls = [];
    $importer->geocodeMissingCities($stores, function (int $current, int $total) use (&$progressCalls) {
        $progressCalls[] = [$current, $total];
    });

    expect($progressCalls)->toBe([[1, 2], [2, 2]]);
});

test('fallbackCity still works as before', function () {
    $importer = app(StoreImportService::class);

    $stores = [
        [
            'name' => 'Shop zonder city',
            'address' => null,
            'city' => null,
            'country' => 'BE',
            'postal_code' => '9000',
            'phone' => null,
            'email' => null,
            'website' => null,
            'latitude' => 51.05,
            'longitude' => 3.72,
        ],
    ];

    $importer->import($stores, 'Gent');

    $store = Store::query()->where('name', 'Shop zonder city')->first();
    expect($store->city)->toBe('Gent');
});
