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
     * Approximate degree offset for 300m (~0.003°).
     * Used as cheap bounding-box pre-filter before haversine.
     */
    private const PROXIMITY_DEGREE_THRESHOLD = 0.003;

    /**
     * Match an array of external dealers against existing stores in the database.
     *
     * Uses a postal-code index for O(1) lookups in steps 1+2,
     * with a GPS proximity fallback for unmatched dealers.
     *
     * @param  array<int, array<string, mixed>>  $dealers
     * @param  string  $brandName  Used to strip brand suffixes from dealer names
     * @return array{matched: array<int, array{dealer: array<string, mixed>, store: Store, confidence: float}>, unmatched: array<int, array<string, mixed>>}
     */
    public function match(array $dealers, string $brandName = ''): array
    {
        $stores = Store::all();

        // Build postal code index for fast lookup in steps 1+2
        $postalIndex = [];
        foreach ($stores as $store) {
            if ($store->postal_code) {
                $postalIndex[$store->postal_code][] = $store;
            }
        }

        $matched = [];
        $unmatched = [];

        foreach ($dealers as $dealer) {
            $result = $this->findBestMatch($dealer, $stores, $postalIndex, $brandName);

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
     * @param  array<string, array<int, Store>>  $postalIndex
     * @return array{dealer: array<string, mixed>, store: Store, confidence: float}|null
     */
    private function findBestMatch(array $dealer, Collection $stores, array $postalIndex, string $brandName): ?array
    {
        $normalizedDealer = $this->normalizeName($dealer['name'], $brandName);
        $dealerPostal = $dealer['postal_code'] ?? null;
        $dealerLat = $dealer['latitude'] ?? null;
        $dealerLon = $dealer['longitude'] ?? null;

        $bestMatch = null;
        $bestConfidence = 0.0;

        // Steps 1+2: Match against stores with the same postal code (fast)
        if ($dealerPostal && isset($postalIndex[$dealerPostal])) {
            foreach ($postalIndex[$dealerPostal] as $store) {
                $normalizedStore = $this->normalizeName($store->name, $brandName);

                // Step 1: Exact match — return immediately
                if ($normalizedDealer === $normalizedStore) {
                    return [
                        'dealer' => $dealer,
                        'store' => $store,
                        'confidence' => 1.0,
                    ];
                }

                // Step 2: Fuzzy match (>= 70% similarity)
                similar_text($normalizedDealer, $normalizedStore, $percent);

                if ($percent >= 70 && $percent / 100 > $bestConfidence) {
                    $bestConfidence = $percent / 100;
                    $bestMatch = $store;
                }
            }
        }

        // Step 3: GPS proximity fallback (< 200m) — only when postal code didn't match
        if ($bestConfidence === 0.0 && $dealerLat && $dealerLon) {
            foreach ($stores as $store) {
                if (! $store->latitude || ! $store->longitude) {
                    continue;
                }

                // Cheap bounding-box pre-filter before expensive haversine
                if (abs($dealerLat - (float) $store->latitude) > self::PROXIMITY_DEGREE_THRESHOLD
                    || abs($dealerLon - (float) $store->longitude) > self::PROXIMITY_DEGREE_THRESHOLD) {
                    continue;
                }

                $distance = $this->haversineDistance($dealerLat, $dealerLon, (float) $store->latitude, (float) $store->longitude);

                if ($distance < 200) {
                    $normalizedStore = $this->normalizeName($store->name, $brandName);
                    similar_text($normalizedDealer, $normalizedStore, $percent);

                    if ($percent >= 60) {
                        $confidence = ($percent / 100) * 0.9;

                        if ($confidence > $bestConfidence) {
                            $bestConfidence = $confidence;
                            $bestMatch = $store;
                        }
                    }
                }
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
