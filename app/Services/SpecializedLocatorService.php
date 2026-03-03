<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SpecializedLocatorService
{
    private const ENDPOINT = 'https://www.specialized.com/api/graphql';

    private const RATE_LIMIT_SECONDS = 1;

    private static float $lastRequestAt = 0.0;

    /**
     * Country configurations: baseSiteId and bounding box [minLat, maxLat, minLon, maxLon].
     *
     * @var array<string, array{baseSiteId: string, bounds: array{float, float, float, float}}>
     */
    private const COUNTRIES = [
        'BE' => [
            'baseSiteId' => 'SBCBelgium',
            'bounds' => [49.5, 51.5, 2.5, 6.5],
        ],
        'DE' => [
            'baseSiteId' => 'SBCGermany',
            'bounds' => [47.3, 55.1, 5.9, 15.1],
        ],
    ];

    private const GRID_SPACING = 0.5;

    private const GRAPHQL_QUERY = <<<'GRAPHQL'
    query GET_RETAILERS($latitude: Float!, $longitude: Float!, $baseSiteId: String!, $deliveryStyle: String!) {
        getRetailers(
            latitude: $latitude
            longitude: $longitude
            baseSiteId: $baseSiteId
            deliveryStyle: $deliveryStyle
        ) {
            retailers {
                name
                address
                city
                postalCode
                country
                telephone
                email
                latitude
                longitude
            }
        }
    }
    GRAPHQL;

    /**
     * Fetch all Specialized dealers for a country using a grid sweep.
     *
     * @return array{dealers: array<int, array<string, mixed>>, queries: int}
     */
    public function fetchDealersForCountry(string $countryCode, ?callable $onProgress = null): array
    {
        $country = self::COUNTRIES[strtoupper($countryCode)] ?? null;

        if (! $country) {
            Log::warning('Specialized: unsupported country code', ['country' => $countryCode]);

            return ['dealers' => [], 'queries' => 0];
        }

        $gridPoints = $this->generateGridPoints($country['bounds']);
        $totalPoints = count($gridPoints);
        $seen = [];
        $dealers = [];
        $queryCount = 0;

        foreach ($gridPoints as $index => $point) {
            $results = $this->fetchDealersNearPoint($point[0], $point[1], $country['baseSiteId']);
            $queryCount++;

            foreach ($results as $dealer) {
                $key = $this->deduplicationKey($dealer['name'], $dealer['postal_code']);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $dealers[] = $dealer;
            }

            if ($onProgress) {
                $onProgress($index + 1, $totalPoints);
            }
        }

        return ['dealers' => $dealers, 'queries' => $queryCount];
    }

    /**
     * Fetch dealers near a single coordinate via the Specialized GraphQL API.
     *
     * @return array<int, array{name: string, address: string|null, city: string|null, postal_code: string|null, country: string|null, phone: string|null, email: string|null, latitude: float|null, longitude: float|null}>
     */
    public function fetchDealersNearPoint(float $lat, float $lon, string $baseSiteId): array
    {
        $this->respectRateLimit();

        $response = Http::timeout(30)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'x-apollo-operation-name' => 'GET_RETAILERS',
            ])
            ->post(self::ENDPOINT, [
                'operationName' => 'GET_RETAILERS',
                'query' => self::GRAPHQL_QUERY,
                'variables' => [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'baseSiteId' => $baseSiteId,
                    'deliveryStyle' => 'CLICK_AND_COLLECT',
                ],
            ]);

        if ($response->failed()) {
            Log::error('Specialized API request failed', [
                'lat' => $lat,
                'lon' => $lon,
                'baseSiteId' => $baseSiteId,
                'status' => $response->status(),
            ]);

            return [];
        }

        return $this->parseResponse($response->json() ?? []);
    }

    /**
     * @param  array{float, float, float, float}  $bounds  [minLat, maxLat, minLon, maxLon]
     * @return array<int, array{float, float}>
     */
    private function generateGridPoints(array $bounds): array
    {
        [$minLat, $maxLat, $minLon, $maxLon] = $bounds;
        $points = [];

        for ($lat = $minLat; $lat <= $maxLat; $lat += self::GRID_SPACING) {
            for ($lon = $minLon; $lon <= $maxLon; $lon += self::GRID_SPACING) {
                $points[] = [round($lat, 2), round($lon, 2)];
            }
        }

        return $points;
    }

    /**
     * @return array<int, array{name: string, address: string|null, city: string|null, postal_code: string|null, country: string|null, phone: string|null, email: string|null, latitude: float|null, longitude: float|null}>
     */
    private function parseResponse(array $data): array
    {
        $retailers = data_get($data, 'data.getRetailers.retailers') ?? [];
        $dealers = [];

        foreach ($retailers as $retailer) {
            $name = $retailer['name'] ?? null;

            if (! $name) {
                continue;
            }

            $dealers[] = [
                'name' => $name,
                'address' => $retailer['address'] ?? null,
                'city' => $retailer['city'] ?? null,
                'postal_code' => $retailer['postalCode'] ?? null,
                'country' => $retailer['country'] ?? null,
                'phone' => $retailer['telephone'] ?? null,
                'email' => $retailer['email'] ?? null,
                'latitude' => isset($retailer['latitude']) ? (float) $retailer['latitude'] : null,
                'longitude' => isset($retailer['longitude']) ? (float) $retailer['longitude'] : null,
            ];
        }

        return $dealers;
    }

    private function deduplicationKey(string $name, ?string $postalCode): string
    {
        return mb_strtolower($name).'|'.($postalCode ?? '');
    }

    private function respectRateLimit(): void
    {
        $now = microtime(true);
        $elapsed = $now - self::$lastRequestAt;

        if (self::$lastRequestAt > 0 && $elapsed < self::RATE_LIMIT_SECONDS) {
            usleep((int) ((self::RATE_LIMIT_SECONDS - $elapsed) * 1_000_000));
        }

        self::$lastRequestAt = microtime(true);
    }
}
