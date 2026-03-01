<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NominatimService
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/reverse';

    private const RATE_LIMIT_SECONDS = 1;

    private static float $lastRequestAt = 0.0;

    /** @var list<string> */
    private const CITY_KEYS = ['city', 'town', 'village', 'municipality'];

    /**
     * Reverse geocode coordinates to city, postal code and country.
     *
     * @return array{city: ?string, postal_code: ?string, country: ?string}
     */
    public function reverseGeocode(float $lat, float $lon): array
    {
        $this->respectRateLimit();

        $response = Http::withHeaders([
            'User-Agent' => 'CyclowaxB2B/1.0',
        ])
            ->timeout(10)
            ->get(self::ENDPOINT, [
                'lat' => $lat,
                'lon' => $lon,
                'format' => 'json',
                'addressdetails' => 1,
                'zoom' => 18,
            ]);

        if ($response->failed()) {
            Log::warning('Nominatim reverse geocode failed', [
                'lat' => $lat,
                'lon' => $lon,
                'status' => $response->status(),
            ]);

            return ['city' => null, 'postal_code' => null, 'country' => null];
        }

        $address = $response->json('address', []);

        return [
            'city' => $this->resolveCity($address),
            'postal_code' => $address['postcode'] ?? null,
            'country' => isset($address['country_code']) ? strtoupper($address['country_code']) : null,
        ];
    }

    /**
     * Resolve city name from address using fallback chain.
     *
     * @param  array<string, mixed>  $address
     */
    private function resolveCity(array $address): ?string
    {
        foreach (self::CITY_KEYS as $key) {
            if (! empty($address[$key])) {
                return $address[$key];
            }
        }

        return null;
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
