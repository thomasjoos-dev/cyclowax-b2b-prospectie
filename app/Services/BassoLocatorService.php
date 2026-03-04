<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BassoLocatorService
{
    private const DEALER_PAGE_URL = 'https://bassobikes.com/en/find-your-dealer';

    /**
     * Fetch all Basso dealers for a given country.
     *
     * @return array{dealers: array<int, array<string, mixed>>, queries: int}
     */
    public function fetchDealersForCountry(string $countryCode): array
    {
        $countryCode = strtoupper($countryCode);
        $countryName = $this->countryName($countryCode);

        if (! $countryName) {
            Log::warning('Basso: unsupported country code', ['country' => $countryCode]);

            return ['dealers' => [], 'queries' => 0];
        }

        $html = $this->fetchPage();

        if (! $html) {
            return ['dealers' => [], 'queries' => 0];
        }

        $allDealers = $this->parseHtml($html);

        $filtered = array_values(array_filter(
            $allDealers,
            fn (array $dealer) => ($dealer['country'] ?? '') === $countryCode,
        ));

        return ['dealers' => $filtered, 'queries' => 1];
    }

    private function fetchPage(): ?string
    {
        $response = Http::timeout(30)->get(self::DEALER_PAGE_URL);

        if ($response->failed()) {
            Log::error('Basso: failed to fetch dealer page', ['status' => $response->status()]);

            return null;
        }

        return $response->body();
    }

    /**
     * Parse all dealer entries from the HTML.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseHtml(string $html): array
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        $doc->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);
        $dealerBoxes = $xpath->query("//div[contains(@class, 'Dealder-box')]");

        $dealers = [];

        foreach ($dealerBoxes as $box) {
            $dealer = $this->parseDealerBox($xpath, $box);

            if ($dealer) {
                $dealers[] = $dealer;
            }
        }

        return $dealers;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseDealerBox(DOMXPath $xpath, \DOMNode $box): ?array
    {
        $h4 = $xpath->query('.//h4', $box)->item(0);

        if (! $h4) {
            return null;
        }

        $name = trim($h4->textContent);

        if ($name === '') {
            return null;
        }

        // Google Maps link contains GPS + address text
        $mapsLink = $xpath->query('.//a[contains(@href, "google.com/maps")]', $box)->item(0);
        $latitude = null;
        $longitude = null;
        $addressText = '';

        if ($mapsLink) {
            $href = $mapsLink->getAttribute('href');
            $addressText = trim($mapsLink->textContent);

            if (preg_match('#/place/([-\d.]+),([-\d.]+)#', $href, $gps)) {
                $latitude = (float) $gps[1];
                $longitude = (float) $gps[2];
            }
        }

        // Parse address components: "STREET, POSTAL CITY, COUNTRY"
        $parsed = $this->parseAddress($addressText);

        // Phone from tel: link
        $telLink = $xpath->query('.//a[contains(@href, "tel:")]', $box)->item(0);
        $phone = $telLink ? trim($telLink->textContent) : null;

        if ($phone === '') {
            $phone = null;
        }

        // Email from first span after Contacts label (span without a child link)
        $email = $this->extractEmail($xpath, $box);

        return [
            'name' => $name,
            'address' => $parsed['street'],
            'city' => $parsed['city'],
            'postal_code' => $parsed['postal_code'],
            'country' => $parsed['country_code'],
            'phone' => $phone,
            'email' => $email,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    /**
     * Parse "STREET, POSTAL CITY, COUNTRY" into components.
     *
     * @return array{street: string|null, city: string|null, postal_code: string|null, country_code: string|null}
     */
    private function parseAddress(string $text): array
    {
        $text = preg_replace('/\s+/', ' ', trim($text));

        $result = ['street' => null, 'city' => null, 'postal_code' => null, 'country_code' => null];

        if ($text === '') {
            return $result;
        }

        // Split on commas
        $parts = array_map('trim', explode(',', $text));

        if (count($parts) < 2) {
            return $result;
        }

        // Last part is the country
        $countryRaw = array_pop($parts);
        $result['country_code'] = $this->countryCodeFromName($countryRaw);

        // Second-to-last part usually contains "POSTAL CITY"
        $cityPart = array_pop($parts);

        if (preg_match('/^(\d{4,5})\s+(.+)$/', $cityPart, $m)) {
            $result['postal_code'] = $m[1];
            $result['city'] = $m[2];
        } else {
            $result['city'] = $cityPart;
        }

        // Everything remaining is the street
        $result['street'] = implode(', ', $parts) ?: null;

        return $result;
    }

    private function extractEmail(DOMXPath $xpath, \DOMNode $box): ?string
    {
        // All spans in the dealer box
        $spans = $xpath->query('.//p/span[not(@class)]', $box);

        foreach ($spans as $span) {
            $text = trim($span->textContent);

            if (str_contains($text, '@')) {
                return $text;
            }
        }

        return null;
    }

    private function countryName(string $code): ?string
    {
        return match ($code) {
            'BE' => 'BELGIUM',
            'DE' => 'GERMANY',
            'CH' => 'SWITZERLAND',
            default => null,
        };
    }

    private function countryCodeFromName(string $name): ?string
    {
        return match (strtoupper(trim($name))) {
            'BELGIUM' => 'BE',
            'GERMANY' => 'DE',
            'FRANCE' => 'FR',
            'ITALY', 'ITALIA' => 'IT',
            'AUSTRIA' => 'AT',
            'NETHERLANDS' => 'NL',
            'SPAIN' => 'ES',
            'SWITZERLAND' => 'CH',
            'UNITED KINGDOM' => 'GB',
            default => null,
        };
    }
}
