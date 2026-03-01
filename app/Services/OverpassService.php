<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OverpassService
{
    private const ENDPOINT = 'https://overpass-api.de/api/interpreter';

    private const RATE_LIMIT_SECONDS = 1;

    private const REGION_RATE_LIMIT_SECONDS = 3;

    private static float $lastRequestAt = 0.0;

    /**
     * Fetch bicycle shops from Overpass API for a given city.
     *
     * @return array<int, array{name: string, address: string|null, city: string|null, country: string|null, postal_code: string|null, phone: string|null, website: string|null, latitude: float|null, longitude: float|null}>
     */
    public function fetchBicycleShops(string $city): array
    {
        $this->respectRateLimit(self::RATE_LIMIT_SECONDS);

        $query = $this->buildQuery($city);

        $response = Http::timeout(60)
            ->asForm()
            ->post(self::ENDPOINT, ['data' => $query]);

        if ($response->failed()) {
            Log::error('Overpass API request failed', [
                'city' => $city,
                'status' => $response->status(),
            ]);

            return [];
        }

        return $this->parseElements($response->json('elements', []));
    }

    /**
     * Fetch bicycle shops from Overpass API for a given region (e.g. Bundesland).
     *
     * @return array<int, array{name: string, address: string|null, city: string|null, country: string|null, postal_code: string|null, phone: string|null, website: string|null, latitude: float|null, longitude: float|null}>
     */
    public function fetchBicycleShopsInRegion(string $region, int $adminLevel = 4): array
    {
        $this->respectRateLimit(self::REGION_RATE_LIMIT_SECONDS);

        $query = $this->buildRegionQuery($region, $adminLevel);

        $response = Http::timeout(90)
            ->asForm()
            ->post(self::ENDPOINT, ['data' => $query]);

        if ($response->failed()) {
            Log::error('Overpass API region request failed', [
                'region' => $region,
                'admin_level' => $adminLevel,
                'status' => $response->status(),
            ]);

            return [];
        }

        return $this->parseElements($response->json('elements', []));
    }

    private function buildRegionQuery(string $region, int $adminLevel): string
    {
        return <<<OVERPASS
        [out:json][timeout:90];
        area["name"="{$region}"]["admin_level"="{$adminLevel}"]->.searchArea;
        nwr["shop"="bicycle"](area.searchArea);
        out center tags;
        OVERPASS;
    }

    private function buildQuery(string $city): string
    {
        return <<<OVERPASS
        [out:json][timeout:25];
        area["name"="{$city}"]["admin_level"]->.searchArea;
        nwr["shop"="bicycle"](area.searchArea);
        out center tags;
        OVERPASS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $elements
     * @return array<int, array<string, mixed>>
     */
    private function parseElements(array $elements): array
    {
        $stores = [];

        foreach ($elements as $element) {
            $tags = $element['tags'] ?? [];

            [$lat, $lon] = $this->resolveCoordinates($element);

            $name = $tags['name'] ?? null;

            if (! $name) {
                continue;
            }

            $street = trim(($tags['addr:street'] ?? '').' '.($tags['addr:housenumber'] ?? ''));

            $stores[] = [
                'name' => $name,
                'address' => $street ?: null,
                'city' => $tags['addr:city'] ?? null,
                'country' => $tags['addr:country'] ?? null,
                'postal_code' => $tags['addr:postcode'] ?? null,
                'phone' => $tags['phone'] ?? $tags['contact:phone'] ?? null,
                'email' => $tags['email'] ?? $tags['contact:email'] ?? null,
                'website' => $tags['website'] ?? $tags['contact:website'] ?? null,
                'latitude' => $lat,
                'longitude' => $lon,
            ];
        }

        return $stores;
    }

    /**
     * @param  array<string, mixed>  $element
     * @return array{float|null, float|null}
     */
    private function resolveCoordinates(array $element): array
    {
        if (isset($element['lat'], $element['lon'])) {
            return [(float) $element['lat'], (float) $element['lon']];
        }

        if (isset($element['center']['lat'], $element['center']['lon'])) {
            return [(float) $element['center']['lat'], (float) $element['center']['lon']];
        }

        return [null, null];
    }

    private function respectRateLimit(int $seconds = self::RATE_LIMIT_SECONDS): void
    {
        $now = microtime(true);
        $elapsed = $now - self::$lastRequestAt;

        if (self::$lastRequestAt > 0 && $elapsed < $seconds) {
            usleep((int) (($seconds - $elapsed) * 1_000_000));
        }

        self::$lastRequestAt = microtime(true);
    }
}
