<?php

use App\Services\NominatimService;
use Illuminate\Support\Facades\Http;

test('reverse geocode returns city from city key', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            'address' => [
                'city' => 'Berlin',
                'postcode' => '10115',
                'country_code' => 'de',
            ],
        ]),
    ]);

    $result = app(NominatimService::class)->reverseGeocode(52.52, 13.405);

    expect($result)
        ->city->toBe('Berlin')
        ->postal_code->toBe('10115')
        ->country->toBe('DE');
});

test('reverse geocode falls back to town when city is missing', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            'address' => [
                'town' => 'Friedberg',
                'postcode' => '61169',
                'country_code' => 'de',
            ],
        ]),
    ]);

    $result = app(NominatimService::class)->reverseGeocode(50.33, 8.75);

    expect($result)->city->toBe('Friedberg');
});

test('reverse geocode falls back to village when town is missing', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            'address' => [
                'village' => 'Kleindorf',
                'postcode' => '99999',
                'country_code' => 'de',
            ],
        ]),
    ]);

    $result = app(NominatimService::class)->reverseGeocode(51.0, 10.0);

    expect($result)->city->toBe('Kleindorf');
});

test('reverse geocode falls back to municipality', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            'address' => [
                'municipality' => 'Gemeinde Muster',
                'postcode' => '88888',
                'country_code' => 'de',
            ],
        ]),
    ]);

    $result = app(NominatimService::class)->reverseGeocode(51.0, 10.0);

    expect($result)->city->toBe('Gemeinde Muster');
});

test('reverse geocode returns nulls on API failure', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([], 500),
    ]);

    $result = app(NominatimService::class)->reverseGeocode(52.52, 13.405);

    expect($result)
        ->city->toBeNull()
        ->postal_code->toBeNull()
        ->country->toBeNull();
});

test('reverse geocode sends correct User-Agent header', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            'address' => ['city' => 'Test', 'country_code' => 'de'],
        ]),
    ]);

    app(NominatimService::class)->reverseGeocode(52.52, 13.405);

    Http::assertSent(function ($request) {
        return $request->hasHeader('User-Agent', 'CyclowaxB2B/1.0');
    });
});
