<?php

use App\Services\SpecializedLocatorService;
use Illuminate\Support\Facades\Http;

test('fetchDealersNearPoint returns normalized dealers from GraphQL response', function () {
    Http::fake([
        'www.specialized.com/api/graphql' => Http::response([
            'data' => [
                'getRetailers' => [
                    'retailers' => [
                        [
                            'name' => 'Bike Center Brussels',
                            'address' => 'Rue de la Loi 1',
                            'city' => 'Brussels',
                            'postalCode' => '1000',
                            'country' => 'BE',
                            'telephone' => '+32 2 123 45 67',
                            'email' => 'info@bikecenter.be',
                            'latitude' => '50.8503',
                            'longitude' => '4.3517',
                        ],
                        [
                            'name' => 'Velo Gent',
                            'address' => 'Korenmarkt 10',
                            'city' => 'Gent',
                            'postalCode' => '9000',
                            'country' => 'BE',
                            'telephone' => null,
                            'email' => null,
                            'latitude' => '51.0543',
                            'longitude' => '3.7174',
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $service = app(SpecializedLocatorService::class);
    $dealers = $service->fetchDealersNearPoint(50.85, 4.35, 'SBCBelgium');

    expect($dealers)->toHaveCount(2)
        ->and($dealers[0])->toMatchArray([
            'name' => 'Bike Center Brussels',
            'address' => 'Rue de la Loi 1',
            'city' => 'Brussels',
            'postal_code' => '1000',
            'country' => 'BE',
            'phone' => '+32 2 123 45 67',
            'email' => 'info@bikecenter.be',
        ])
        ->and($dealers[0]['latitude'])->toBe(50.8503)
        ->and($dealers[0]['longitude'])->toBe(4.3517)
        ->and($dealers[1]['name'])->toBe('Velo Gent')
        ->and($dealers[1]['phone'])->toBeNull();
});

test('fetchDealersNearPoint sends correct headers and variables', function () {
    Http::fake([
        'www.specialized.com/api/graphql' => Http::response(['data' => ['getRetailers' => ['retailers' => []]]]),
    ]);

    $service = app(SpecializedLocatorService::class);
    $service->fetchDealersNearPoint(50.85, 4.35, 'SBCBelgium');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->hasHeader('x-apollo-operation-name', 'GET_RETAILERS')
            && $body['operationName'] === 'GET_RETAILERS'
            && $body['variables']['latitude'] === 50.85
            && $body['variables']['longitude'] === 4.35
            && $body['variables']['baseSiteId'] === 'SBCBelgium'
            && $body['variables']['deliveryStyle'] === 'CLICK_AND_COLLECT';
    });
});

test('fetchDealersNearPoint returns empty array on API failure', function () {
    Http::fake([
        'www.specialized.com/api/graphql' => Http::response(null, 500),
    ]);

    $service = app(SpecializedLocatorService::class);
    $dealers = $service->fetchDealersNearPoint(50.85, 4.35, 'SBCBelgium');

    expect($dealers)->toBe([]);
});

test('fetchDealersNearPoint skips entries without name', function () {
    Http::fake([
        'www.specialized.com/api/graphql' => Http::response([
            'data' => [
                'getRetailers' => [
                    'retailers' => [
                        ['name' => null, 'city' => 'Brussels', 'postalCode' => '1000'],
                        ['name' => 'Valid Shop', 'city' => 'Gent', 'postalCode' => '9000'],
                    ],
                ],
            ],
        ]),
    ]);

    $service = app(SpecializedLocatorService::class);
    $dealers = $service->fetchDealersNearPoint(50.85, 4.35, 'SBCBelgium');

    expect($dealers)->toHaveCount(1)
        ->and($dealers[0]['name'])->toBe('Valid Shop');
});

test('fetchDealersForCountry deduplicates across grid points', function () {
    $sameDealer = [
        'name' => 'Bike Center Brussels',
        'address' => 'Rue de la Loi 1',
        'city' => 'Brussels',
        'postalCode' => '1000',
        'country' => 'BE',
        'telephone' => null,
        'email' => null,
        'latitude' => '50.85',
        'longitude' => '4.35',
    ];

    Http::fake([
        'www.specialized.com/api/graphql' => Http::response([
            'data' => [
                'getRetailers' => [
                    'retailers' => [$sameDealer],
                ],
            ],
        ]),
    ]);

    $service = app(SpecializedLocatorService::class);
    $result = $service->fetchDealersForCountry('BE');

    // The same dealer should appear only once despite multiple grid point queries
    expect($result['dealers'])->toHaveCount(1)
        ->and($result['queries'])->toBeGreaterThan(1)
        ->and($result['dealers'][0]['name'])->toBe('Bike Center Brussels');
});

test('fetchDealersForCountry returns empty for unsupported country', function () {
    Http::fake();

    $service = app(SpecializedLocatorService::class);
    $result = $service->fetchDealersForCountry('XX');

    expect($result)->toBe(['dealers' => [], 'queries' => 0]);
    Http::assertNothingSent();
});

test('fetchDealersForCountry calls progress callback', function () {
    Http::fake([
        'www.specialized.com/api/graphql' => Http::response([
            'data' => ['getRetailers' => ['retailers' => []]],
        ]),
    ]);

    $service = app(SpecializedLocatorService::class);
    $progressCalls = [];

    $service->fetchDealersForCountry('BE', function (int $current, int $total) use (&$progressCalls) {
        $progressCalls[] = [$current, $total];
    });

    expect($progressCalls)->not->toBeEmpty()
        ->and($progressCalls[0][1])->toBeGreaterThan(0)
        ->and(end($progressCalls)[0])->toBe(end($progressCalls)[1]);
});
