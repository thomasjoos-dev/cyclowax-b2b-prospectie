<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SpecializedLocatorService
{
    private const ENDPOINT = 'https://www.specialized.com/api/graphql';

    private const RATE_LIMIT_SECONDS = 1;

    private const PAGE_LIMIT = 50;

    private static float $lastRequestAt = 0.0;

    /**
     * Country configurations: center GPS coordinates and search radius in miles.
     *
     * @var array<string, array{center: array{float, float}, radius: int}>
     */
    private const COUNTRIES = [
        'BE' => ['center' => [50.5039, 4.4699], 'radius' => 100],
        'DE' => ['center' => [51.1657, 10.4515], 'radius' => 400],
        'CH' => ['center' => [46.8182, 8.2275], 'radius' => 200],
    ];

    private const GRAPHQL_QUERY = <<<'GRAPHQL'
    query getYextGeoSearch($location: String!, $limit: String, $radius: String, $offset: String) {
        getYextGeoSearch(location: $location, limit: $limit, radius: $radius, offset: $offset) {
            response {
                count
                stores {
                    name
                    address { line1 city region postalCode countryCode }
                    mainPhone
                    emails
                    websiteUrl { url }
                    yextDisplayCoordinate { latitude longitude }
                }
            }
        }
    }
    GRAPHQL;

    /**
     * Fetch all Specialized dealers for a country using center-point + radius pagination.
     *
     * @return array{dealers: array<int, array<string, mixed>>, queries: int}
     */
    public function fetchDealersForCountry(string $countryCode, ?callable $onProgress = null): array
    {
        $countryCode = strtoupper($countryCode);
        $country = self::COUNTRIES[$countryCode] ?? null;

        if (! $country) {
            Log::warning('Specialized: unsupported country code', ['country' => $countryCode]);

            return ['dealers' => [], 'queries' => 0];
        }

        $location = "{$country['center'][0]},{$country['center'][1]}";
        $radius = (string) $country['radius'];

        $allStores = [];
        $offset = 0;
        $queryCount = 0;
        $totalCount = null;
        $totalPages = 1;

        do {
            $response = $this->fetchPage($location, $radius, $offset);
            $queryCount++;

            if ($response === null) {
                break;
            }

            if ($totalCount === null) {
                $totalCount = $response['count'];
                $totalPages = (int) ceil($totalCount / self::PAGE_LIMIT);
            }

            $allStores = array_merge($allStores, $response['stores']);

            if ($onProgress) {
                $currentPage = (int) floor($offset / self::PAGE_LIMIT) + 1;
                $onProgress($currentPage, $totalPages);
            }

            $offset += self::PAGE_LIMIT;
        } while ($offset < ($totalCount ?? 0));

        $filtered = $this->filterAndNormalize($allStores, $countryCode);

        return ['dealers' => $filtered, 'queries' => $queryCount];
    }

    /**
     * @return array{count: int, stores: array<int, array<string, mixed>>}|null
     */
    private function fetchPage(string $location, string $radius, int $offset): ?array
    {
        $this->respectRateLimit();

        $variables = json_encode([
            'location' => $location,
            'limit' => (string) self::PAGE_LIMIT,
            'radius' => $radius,
            'offset' => (string) $offset,
        ]);

        $response = Http::timeout(30)
            ->withHeaders([
                'Accept' => 'application/json',
                'x-apollo-operation-name' => 'getYextGeoSearch',
            ])
            ->get(self::ENDPOINT, [
                'operationName' => 'getYextGeoSearch',
                'query' => self::GRAPHQL_QUERY,
                'variables' => $variables,
            ]);

        if ($response->failed()) {
            Log::error('Specialized API request failed', [
                'status' => $response->status(),
                'offset' => $offset,
            ]);

            return null;
        }

        $data = $response->json('data.getYextGeoSearch.response');

        if (! $data) {
            Log::warning('Specialized: unexpected response structure', ['offset' => $offset]);

            return null;
        }

        return [
            'count' => $data['count'] ?? 0,
            'stores' => $data['stores'] ?? [],
        ];
    }

    /**
     * Filter stores by country code, normalize to standard dealer format, and deduplicate.
     *
     * @param  array<int, array<string, mixed>>  $stores
     * @return array<int, array<string, mixed>>
     */
    private function filterAndNormalize(array $stores, string $countryCode): array
    {
        $seen = [];
        $dealers = [];

        foreach ($stores as $store) {
            $storeCountry = strtoupper($store['address']['countryCode'] ?? '');

            if ($storeCountry !== $countryCode) {
                continue;
            }

            $name = trim($store['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $postalCode = trim($store['address']['postalCode'] ?? '');
            $dedupeKey = mb_strtolower($name).'|'.$postalCode;

            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;

            $emails = $store['emails'] ?? [];
            $email = is_array($emails) && count($emails) > 0 ? $emails[0] : null;

            $dealers[] = [
                'name' => $name,
                'address' => trim($store['address']['line1'] ?? '') ?: null,
                'city' => trim($store['address']['city'] ?? '') ?: null,
                'postal_code' => $postalCode ?: null,
                'country' => $countryCode,
                'phone' => trim($store['mainPhone'] ?? '') ?: null,
                'email' => $email,
                'website' => $store['websiteUrl']['url'] ?? null,
                'latitude' => $store['yextDisplayCoordinate']['latitude'] ?? null,
                'longitude' => $store['yextDisplayCoordinate']['longitude'] ?? null,
            ];
        }

        return $dealers;
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
