<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class OrbeaLocatorService
{
    /**
     * GPS bounding boxes per country [minLat, maxLat, minLon, maxLon].
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
        $scriptPath = base_path('scripts/orbea-fetch-dealers.mjs');

        if (! file_exists($scriptPath)) {
            throw new RuntimeException("Orbea fetch script not found: {$scriptPath}");
        }

        $result = Process::timeout(600)
            ->run("node {$scriptPath} --country={$countryCode}");

        if (! $result->successful()) {
            Log::error('Orbea Playwright script failed', [
                'country' => $countryCode,
                'stderr' => $result->errorOutput(),
                'exitCode' => $result->exitCode(),
            ]);

            throw new RuntimeException("Orbea fetch failed for {$countryCode}: {$result->errorOutput()}");
        }

        $raw = json_decode($result->output(), true);

        if (! is_array($raw)) {
            Log::error('Orbea Playwright output is not valid JSON', [
                'country' => $countryCode,
                'output' => substr($result->output(), 0, 500),
            ]);

            return ['dealers' => [], 'queries' => 0];
        }

        return ['dealers' => $this->normalizeDealers($raw, $countryCode), 'queries' => 1];
    }

    /**
     * Parse dealer data from a JSON file saved by the Playwright sweep script.
     *
     * @return array{dealers: array<int, array<string, mixed>>, queries: int}
     */
    public function parseDealersFromFile(string $path, ?string $countryCode = null): array
    {
        if (! file_exists($path)) {
            Log::error('Orbea JSON file not found', ['path' => $path]);

            return ['dealers' => [], 'queries' => 0];
        }

        $raw = json_decode(file_get_contents($path), true);

        if (! is_array($raw)) {
            Log::error('Orbea JSON parse failed', ['path' => $path]);

            return ['dealers' => [], 'queries' => 0];
        }

        return ['dealers' => $this->normalizeDealers($raw, $countryCode), 'queries' => 1];
    }

    /**
     * Normalize raw dealer data from the Playwright script into the standard format.
     *
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDealers(array $raw, ?string $countryCode = null): array
    {
        $dealers = [];

        foreach ($raw as $item) {
            $name = $item['name'] ?? null;

            if (! $name || trim($name) === '') {
                continue;
            }

            $lat = isset($item['latitude']) ? (float) $item['latitude'] : null;
            $lon = isset($item['longitude']) ? (float) $item['longitude'] : null;

            $dealers[] = [
                'name' => trim($name),
                'address' => $this->cleanField($item['street'] ?? null),
                'city' => $this->cleanField($item['city'] ?? null),
                'postal_code' => $this->cleanField($item['postalCode'] ?? null),
                'country' => $countryCode ? strtoupper($countryCode) : $this->detectCountry($item['postalCode'] ?? '', $lat, $lon),
                'phone' => $this->cleanField($item['phone'] ?? null),
                'email' => $this->cleanField($item['email'] ?? null),
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

        $zip = trim($zip);

        if (preg_match('/^\d{5}$/', $zip)) {
            return 'DE';
        }

        return 'BE';
    }
}
