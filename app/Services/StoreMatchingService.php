<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Collection;

class StoreMatchingService
{
    /**
     * Patterns stripped from names during normalization.
     *
     * @var array<int, string>
     */
    private const LEGAL_FORMS = [
        'bvba', 'bv', 'nv', 'sa', 'sprl', 'gmbh', 'ag', 'e\.?k\.?', 'ohg', 'kg', 'ug',
    ];

    /**
     * Match an array of external dealers against existing stores in the database.
     *
     * @param  array<int, array<string, mixed>>  $dealers
     * @param  string  $brandName  Used to strip brand suffixes from dealer names
     * @return array{matched: array<int, array{dealer: array<string, mixed>, store: Store, confidence: float}>, unmatched: array<int, array<string, mixed>>}
     */
    public function match(array $dealers, string $brandName = ''): array
    {
        $stores = Store::all();
        $matched = [];
        $unmatched = [];

        foreach ($dealers as $dealer) {
            $result = $this->findBestMatch($dealer, $stores, $brandName);

            if ($result) {
                $matched[] = $result;
            } else {
                $unmatched[] = $dealer;
            }
        }

        return ['matched' => $matched, 'unmatched' => $unmatched];
    }

    /**
     * @param  Collection<int, Store>  $stores
     * @return array{dealer: array<string, mixed>, store: Store, confidence: float}|null
     */
    private function findBestMatch(array $dealer, Collection $stores, string $brandName): ?array
    {
        $normalizedDealer = $this->normalizeName($dealer['name'], $brandName);
        $dealerPostal = $dealer['postal_code'] ?? null;
        $dealerLat = $dealer['latitude'] ?? null;
        $dealerLon = $dealer['longitude'] ?? null;

        $bestMatch = null;
        $bestConfidence = 0.0;

        foreach ($stores as $store) {
            $normalizedStore = $this->normalizeName($store->name, $brandName);
            $confidence = 0.0;

            // Step 1: Exact match on postal code + normalized name
            if ($dealerPostal && $store->postal_code === $dealerPostal && $normalizedDealer === $normalizedStore) {
                $confidence = 1.0;
            }

            // Step 2: Fuzzy match on postal code + similar name
            if ($confidence === 0.0 && $dealerPostal && $store->postal_code === $dealerPostal) {
                similar_text($normalizedDealer, $normalizedStore, $percent);

                if ($percent >= 70) {
                    $confidence = $percent / 100;
                }
            }

            // Step 3: Proximity match (< 200m) + similar name
            if ($confidence === 0.0 && $dealerLat && $dealerLon && $store->latitude && $store->longitude) {
                $distance = $this->haversineDistance($dealerLat, $dealerLon, (float) $store->latitude, (float) $store->longitude);

                if ($distance < 200) {
                    similar_text($normalizedDealer, $normalizedStore, $percent);

                    if ($percent >= 60) {
                        $confidence = ($percent / 100) * 0.9;
                    }
                }
            }

            if ($confidence > $bestConfidence) {
                $bestConfidence = $confidence;
                $bestMatch = $store;
            }
        }

        if (! $bestMatch || $bestConfidence === 0.0) {
            return null;
        }

        return [
            'dealer' => $dealer,
            'store' => $bestMatch,
            'confidence' => round($bestConfidence, 2),
        ];
    }

    /**
     * Normalize a dealer/store name for comparison.
     *
     * Strips brand suffixes, legal forms, non-alphanumeric chars, and lowercases.
     */
    public function normalizeName(string $name, string $brandName = ''): string
    {
        $normalized = mb_strtolower($name);

        // Strip brand-related suffixes like "- Specialized Bruxelles"
        if ($brandName !== '') {
            $brandPattern = preg_quote(mb_strtolower($brandName), '/');
            $normalized = (string) preg_replace('/\s*[-–—]\s*'.$brandPattern.'[\s\w]*/u', '', $normalized);
        }

        // Strip legal forms
        $legalPattern = '/\b('.implode('|', self::LEGAL_FORMS).')\b/iu';
        $normalized = (string) preg_replace($legalPattern, '', $normalized);

        // Remove non-alphanumeric chars (keep spaces)
        $normalized = (string) preg_replace('/[^a-z0-9\s]/u', '', $normalized);

        // Collapse whitespace
        $normalized = (string) preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    /**
     * Calculate distance between two points in meters using the Haversine formula.
     */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6_371_000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
