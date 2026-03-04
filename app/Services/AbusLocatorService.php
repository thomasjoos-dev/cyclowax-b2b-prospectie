<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class AbusLocatorService
{
    /**
     * GPS bounding boxes per country.
     * CH is checked before DE because their boxes overlap in southern Germany.
     *
     * @var array<string, array{float, float, float, float}>
     */
    private const COUNTRY_BOUNDS = [
        'CH' => [45.8, 47.9, 5.9, 10.5],
        'BE' => [49.5, 51.6, 2.5, 6.5],
        'DE' => [47.2, 55.1, 5.8, 15.1],
    ];

    /**
     * Fetch dealers for a country via the Playwright browser script.
     *
     * @return array{dealers: array<int, array<string, mixed>>, queries: int}
     */
    public function fetchDealersForCountry(string $countryCode): array
    {
        $countryCode = strtoupper($countryCode);
        $scriptPath = base_path('scripts/abus-fetch-dealers.mjs');

        if (! file_exists($scriptPath)) {
            throw new RuntimeException("Abus fetch script not found: {$scriptPath}");
        }

        $result = Process::timeout(120)
            ->run("node {$scriptPath} --country={$countryCode}");

        if (! $result->successful()) {
            Log::error('Abus Playwright script failed', [
                'country' => $countryCode,
                'stderr' => $result->errorOutput(),
                'exitCode' => $result->exitCode(),
            ]);

            throw new RuntimeException("Abus fetch failed for {$countryCode}: {$result->errorOutput()}");
        }

        $raw = json_decode($result->output(), true);

        if (! is_array($raw)) {
            Log::error('Abus Playwright output is not valid JSON', [
                'country' => $countryCode,
                'output' => substr($result->output(), 0, 500),
            ]);

            return ['dealers' => [], 'queries' => 0];
        }

        return ['dealers' => $this->normalizeDealers($raw), 'queries' => 1];
    }

    /**
     * Parse dealer data from a JSON file downloaded via the browser sweep script.
     *
     * @return array{dealers: array<int, array<string, mixed>>, queries: int}
     */
    public function parseDealersFromFile(string $path): array
    {
        if (! file_exists($path)) {
            Log::error('Abus JSON file not found', ['path' => $path]);

            return ['dealers' => [], 'queries' => 0];
        }

        $raw = json_decode(file_get_contents($path), true);

        if (! is_array($raw)) {
            Log::error('Abus JSON parse failed', ['path' => $path]);

            return ['dealers' => [], 'queries' => 0];
        }

        return ['dealers' => $this->normalizeDealers($raw), 'queries' => 1];
    }

    /**
     * Normalize raw dealer data into the standard format used by StoreMatchingService.
     *
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDealers(array $raw): array
    {
        $dealers = [];

        foreach ($raw as $item) {
            $name = $item['name'] ?? null;

            if (! $name || trim($name) === '') {
                continue;
            }

            $lat = isset($item['geoLat']) ? (float) $item['geoLat'] : null;
            $lon = isset($item['geoLon']) ? (float) $item['geoLon'] : null;

            $dealers[] = [
                'name' => trim($name),
                'address' => $this->cleanField($item['address'] ?? null),
                'city' => $this->cleanField($item['town'] ?? null),
                'postal_code' => $this->cleanField($item['zip'] ?? null),
                'country' => $this->detectCountry($item['zip'] ?? '', $lat, $lon),
                'phone' => $this->cleanField($item['phone'] ?? null),
                'email' => $this->cleanField($item['mail'] ?? null),
                'website' => $this->cleanField($item['website'] ?? null),
                'latitude' => $lat,
                'longitude' => $lon,
            ];
        }

        return $dealers;
    }

    private function cleanField(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Detect country based on GPS coordinates (primary) or postal code format (fallback).
     * CH bounds are checked before DE because they overlap in southern Germany.
     */
    private function detectCountry(string $zip, ?float $lat, ?float $lon): string
    {
        if ($lat !== null && $lon !== null) {
            foreach (self::COUNTRY_BOUNDS as $code => [$minLat, $maxLat, $minLon, $maxLon]) {
                if ($lat >= $minLat && $lat <= $maxLat && $lon >= $minLon && $lon <= $maxLon) {
                    return $code;
                }
            }
        }

        // Fallback: postal code format
        $zip = trim($zip);

        if (preg_match('/^\d{5}$/', $zip)) {
            return 'DE';
        }

        return 'BE';
    }
}
