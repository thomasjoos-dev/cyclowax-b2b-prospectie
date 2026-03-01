<?php

namespace App\Services;

use App\Models\Store;

class StoreImportService
{
    /**
     * Import stores from a normalized data array.
     *
     * @param  array<int, array<string, mixed>>  $stores
     * @return array{found: int, created: int, duplicates: int, updated: int}
     */
    public function import(array $stores, ?string $fallbackCity = null): array
    {
        $created = 0;
        $duplicates = 0;
        $updated = 0;

        foreach ($stores as $data) {
            $city = $data['city'] ?? $fallbackCity;

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
                'country' => $data['country'] ?? null,
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

    private function findDuplicate(string $name, ?string $postalCode): ?Store
    {
        return Store::query()
            ->where('name', $name)
            ->where('postal_code', $postalCode)
            ->first();
    }
}
