<?php

namespace App\Services;

use App\Models\Store;

class StoreImportService
{
    public function __construct(private NominatimService $nominatim) {}

    /**
     * Import stores from a normalized data array.
     *
     * @param  array<int, array<string, mixed>>  $stores
     * @return array{found: int, created: int, duplicates: int, updated: int}
     */
    public function import(array $stores, ?string $fallbackCity = null, ?string $fallbackCountry = null): array
    {
        $created = 0;
        $duplicates = 0;
        $updated = 0;

        foreach ($stores as $data) {
            $city = $data['city'] ?? $fallbackCity;
            $country = $data['country'] ?? $fallbackCountry;

            $existing = $this->findDuplicate($data['name'], $data['postal_code'] ?? null);

            if ($existing) {
                if (! $existing->city && $city) {
                    $existing->update(['city' => $city]);
                    $updated++;
                }

                $duplicates++;

                continue;
            }

            Store::query()->create([
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'city' => $city,
                'country' => $country,
                'postal_code' => $data['postal_code'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'website' => $data['website'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'discovery_source' => 'overpass',
                'pipeline_status' => 'niet_gecontacteerd',
                'is_existing_customer' => false,
            ]);

            $created++;
        }

        return [
            'found' => count($stores),
            'created' => $created,
            'duplicates' => $duplicates,
            'updated' => $updated,
        ];
    }

    /**
     * Geocode stores that are missing a city using Nominatim reverse geocoding.
     *
     * @param  array<int, array<string, mixed>>  $stores  Modified in place
     * @param  ?callable(int $current, int $total): void  $onProgress
     */
    public function geocodeMissingCities(array &$stores, ?callable $onProgress = null): int
    {
        $geocoded = 0;
        $missing = [];

        foreach ($stores as $index => $store) {
            if (empty($store['city']) && ! empty($store['latitude']) && ! empty($store['longitude'])) {
                $missing[] = $index;
            }
        }

        $total = count($missing);

        foreach ($missing as $i => $index) {
            $result = $this->nominatim->reverseGeocode(
                (float) $stores[$index]['latitude'],
                (float) $stores[$index]['longitude']
            );

            if ($result['city']) {
                $stores[$index]['city'] = $result['city'];
                $geocoded++;
            }

            if ($result['postal_code'] && empty($stores[$index]['postal_code'])) {
                $stores[$index]['postal_code'] = $result['postal_code'];
            }

            if ($result['country'] && empty($stores[$index]['country'])) {
                $stores[$index]['country'] = $result['country'];
            }

            if ($onProgress) {
                $onProgress($i + 1, $total);
            }
        }

        return $geocoded;
    }

    private function findDuplicate(string $name, ?string $postalCode): ?Store
    {
        return Store::query()
            ->where('name', $name)
            ->where('postal_code', $postalCode)
            ->first();
    }
}
