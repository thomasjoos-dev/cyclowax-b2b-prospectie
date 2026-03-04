<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SchwalbeLocatorService
{
    private const ENDPOINT = 'https://www.schwalbe.com/store-locator/retailer/list';

    /**
     * Country bounding boxes [minLat, maxLat, minLon, maxLon].
     *
     * @var array<string, array{float, float, float, float}>
     */
    private const COUNTRY_BOUNDS = [
        'BE' => [49.5, 51.6, 2.5, 6.5],
        'DE' => [47.2, 55.1, 5.8, 15.1],
        'CH' => [45.8, 47.9, 5.9, 10.5],
    ];

    /**
     * Postcode regex per country. Eliminates false positives from overlapping bounding boxes.
     * BE: exactly 4 digits. DE: exactly 5 digits.
     *
     * @var array<string, string>
     */
    private const POSTCODE_PATTERNS = [
        'BE' => '/^\d{4}$/',
        'DE' => '/^\d{5}$/',
        'CH' => '/^\d{4}$/',
    ];

    /**
     * Fetch all Schwalbe dealers for a given country.
     *
     * The API returns all ~21k dealers worldwide in a single response.
     * We filter by GPS bounding box since there's no country field.
     *
     * @return array{dealers: array<int, array<string, mixed>>, queries: int}
     */
    public function fetchDealersForCountry(string $countryCode): array
    {
        $countryCode = strtoupper($countryCode);
        $bounds = self::COUNTRY_BOUNDS[$countryCode] ?? null;

        if (! $bounds) {
            Log::warning('Schwalbe: unsupported country code', ['country' => $countryCode]);

            return ['dealers' => [], 'queries' => 0];
        }

        $allDealers = $this->fetchAll();

        if ($allDealers === null) {
            return ['dealers' => [], 'queries' => 0];
        }

        $filtered = $this->filterByBoundsAndType($allDealers, $bounds, $countryCode);

        return ['dealers' => $filtered, 'queries' => 1];
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchAll(): ?array
    {
        $response = Http::timeout(60)->get(self::ENDPOINT);

        if ($response->failed()) {
            Log::error('Schwalbe: API request failed', ['status' => $response->status()]);

            return null;
        }

        return $response->json('handler');
    }

    /**
     * Filter dealers by GPS bounding box and type (haendler = retail dealer).
     *
     * @param  array<int, array<string, mixed>>  $dealers
     * @param  array{float, float, float, float}  $bounds  [minLat, maxLat, minLon, maxLon]
     * @return array<int, array<string, mixed>>
     */
    private function filterByBoundsAndType(array $dealers, array $bounds, string $countryCode): array
    {
        [$minLat, $maxLat, $minLon, $maxLon] = $bounds;
        $postcodePattern = self::POSTCODE_PATTERNS[$countryCode] ?? null;
        $result = [];
        $seen = [];

        foreach ($dealers as $dealer) {
            if (empty($dealer['haendler'])) {
                continue;
            }

            $lat = (float) ($dealer['lat'] ?? 0);
            $lon = (float) ($dealer['lang'] ?? 0);

            if ($lat < $minLat || $lat > $maxLat || $lon < $minLon || $lon > $maxLon) {
                continue;
            }

            // Validate postcode format to exclude neighbouring countries
            $postalCode = trim($dealer['plz'] ?? '');
            if ($postcodePattern && ! preg_match($postcodePattern, $postalCode)) {
                continue;
            }

            $name = trim($dealer['nam'] ?? '');

            if ($name === '') {
                continue;
            }

            $postalCode = trim($dealer['plz'] ?? '');
            $dedupeKey = mb_strtolower($name).'|'.$postalCode;

            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;

            $result[] = [
                'name' => $name,
                'address' => trim($dealer['adresse'] ?? '') ?: null,
                'city' => trim($dealer['ort'] ?? '') ?: null,
                'postal_code' => $postalCode ?: null,
                'country' => $countryCode,
                'phone' => trim($dealer['tel'] ?? '') ?: null,
                'email' => trim($dealer['email'] ?? '') ?: null,
                'website' => trim($dealer['web'] ?? '') ?: null,
                'latitude' => $lat !== 0.0 ? $lat : null,
                'longitude' => $lon !== 0.0 ? $lon : null,
            ];
        }

        return $result;
    }
}
