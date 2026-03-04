<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TrekLocatorService
{
    private const API_BASE = 'https://api.trekbikes.com/occ/v2';

    private const PAGE_SIZE = 50;

    /**
     * OCC base site IDs per country.
     *
     * @var array<string, string>
     */
    private const BASE_SITES = [
        'BE' => 'be',
        'DE' => 'de',
        'CH' => 'ch',
    ];

    /**
     * Fetch all Trek retailers for a given country via the OCC API.
     *
     * @return array{dealers: array<int, array<string, mixed>>, queries: int}
     */
    public function fetchDealersForCountry(string $countryCode, ?callable $onProgress = null): array
    {
        $countryCode = strtoupper($countryCode);
        $baseSite = self::BASE_SITES[$countryCode] ?? null;

        if (! $baseSite) {
            Log::warning('Trek: unsupported country code', ['country' => $countryCode]);

            return ['dealers' => [], 'queries' => 0];
        }

        $page = 0;
        $queryCount = 0;
        $seen = [];
        $dealers = [];

        do {
            $response = $this->fetchPage($baseSite, $page);
            $queryCount++;

            if (! $response) {
                break;
            }

            $stores = $response['stores'] ?? [];
            $totalPages = $response['pagination']['totalPages'] ?? 0;

            foreach ($stores as $store) {
                $dealer = $this->normalizeStore($store, $countryCode);

                if (! $dealer) {
                    continue;
                }

                $key = mb_strtolower($dealer['name']).'|'.($dealer['postal_code'] ?? '');

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $dealers[] = $dealer;
            }

            if ($onProgress) {
                $onProgress($page + 1, $totalPages);
            }

            $page++;
        } while ($page < $totalPages);

        return ['dealers' => $dealers, 'queries' => $queryCount];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPage(string $baseSite, int $page): ?array
    {
        $url = self::API_BASE."/{$baseSite}/stores";

        $response = Http::timeout(30)->get($url, [
            'fields' => 'FULL',
            'pageSize' => self::PAGE_SIZE,
            'currentPage' => $page,
        ]);

        if ($response->failed()) {
            Log::error('Trek: API request failed', [
                'baseSite' => $baseSite,
                'page' => $page,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeStore(array $store, string $countryCode): ?array
    {
        $name = $store['displayName'] ?? null;

        if (! $name || trim($name) === '') {
            return null;
        }

        $address = $store['address'] ?? [];

        return [
            'name' => trim($name),
            'address' => trim($address['line1'] ?? '') ?: null,
            'city' => trim($address['town'] ?? '') ?: null,
            'postal_code' => trim($address['postalCode'] ?? '') ?: null,
            'country' => $countryCode,
            'phone' => trim($address['phone'] ?? '') ?: null,
            'email' => trim($address['email'] ?? '') ?: null,
            'latitude' => $store['geoPoint']['latitude'] ?? null,
            'longitude' => $store['geoPoint']['longitude'] ?? null,
        ];
    }
}
