<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AbusLocatorService
{
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

        $dealers = [];

        foreach ($raw as $item) {
            $name = $item['name'] ?? null;

            if (! $name || trim($name) === '') {
                continue;
            }

            $dealers[] = [
                'name' => trim($name),
                'address' => $this->cleanField($item['address'] ?? null),
                'city' => $this->cleanField($item['town'] ?? null),
                'postal_code' => $this->cleanField($item['zip'] ?? null),
                'country' => $this->detectCountry($item['zip'] ?? ''),
                'phone' => $this->cleanField($item['phone'] ?? null),
                'email' => $this->cleanField($item['mail'] ?? null),
                'website' => $this->cleanField($item['website'] ?? null),
                'latitude' => isset($item['geoLat']) ? (float) $item['geoLat'] : null,
                'longitude' => isset($item['geoLon']) ? (float) $item['geoLon'] : null,
            ];
        }

        return ['dealers' => $dealers, 'queries' => 1];
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
     * Detect country based on postal code format.
     * BE: 4 digits, DE: 5 digits.
     */
    private function detectCountry(string $zip): string
    {
        $zip = trim($zip);

        if (preg_match('/^\d{5}$/', $zip)) {
            return 'DE';
        }

        return 'BE';
    }
}
