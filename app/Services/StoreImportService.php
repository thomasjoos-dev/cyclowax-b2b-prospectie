<?php

namespace App\Services;

use App\Models\Store;

class StoreImportService
{
    /**
     * Import stores from a normalized data array.
     *
     * @param  array<int, array<string, mixed>>  $stores
     * @return array{found: int, created: int, duplicates: int}
     */
    public function import(array $stores): array
    {
        $created = 0;
        $duplicates = 0;

        foreach ($stores as $data) {
            if ($this->isDuplicate($data['name'], $data['postal_code'] ?? null)) {
                $duplicates++;

                continue;
            }

            Store::query()->create([
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
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
        ];
    }

    private function isDuplicate(string $name, ?string $postalCode): bool
    {
        return Store::query()
            ->where('name', $name)
            ->where('postal_code', $postalCode)
            ->exists();
    }
}
