<?php

use App\Services\OverpassService;
use Illuminate\Support\Facades\Http;

test('fetchBicycleShopsInRegion builds correct query with admin_level', function () {
    Http::fake([
        'overpass-api.de/*' => Http::response(['elements' => [
            [
                'tags' => [
                    'name' => 'Fahrrad Schmidt',
                    'addr:city' => 'München',
                    'addr:postcode' => '80331',
                    'addr:country' => 'DE',
                ],
                'lat' => 48.137,
                'lon' => 11.575,
            ],
        ]]),
    ]);

    $stores = app(OverpassService::class)->fetchBicycleShopsInRegion('Bayern', 4);

    expect($stores)->toHaveCount(1)
        ->and($stores[0]['name'])->toBe('Fahrrad Schmidt')
        ->and($stores[0]['city'])->toBe('München');

    Http::assertSent(function ($request) {
        $query = $request->data()['data'] ?? '';

        return str_contains($query, '"admin_level"="4"')
            && str_contains($query, '"name"="Bayern"')
            && str_contains($query, 'timeout:90');
    });
});

test('fetchBicycleShopsInRegion returns empty array on failure', function () {
    Http::fake([
        'overpass-api.de/*' => Http::response([], 500),
    ]);

    $stores = app(OverpassService::class)->fetchBicycleShopsInRegion('Bayern');

    expect($stores)->toBeEmpty();
});

test('fetchBicycleShops still works for city queries', function () {
    Http::fake([
        'overpass-api.de/*' => Http::response(['elements' => [
            [
                'tags' => ['name' => 'Bike Shop Gent'],
                'lat' => 51.05,
                'lon' => 3.72,
            ],
        ]]),
    ]);

    $stores = app(OverpassService::class)->fetchBicycleShops('Gent');

    expect($stores)->toHaveCount(1)
        ->and($stores[0]['name'])->toBe('Bike Shop Gent');
});
