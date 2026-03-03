<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class CsvDealerImportService
{
    /**
     * Parse a CSV dealer file and return normalized dealer arrays.
     *
     * @return array<int, array{name: string, address: string|null, city: string|null, postal_code: string|null, country: string|null, phone: string|null, email: string|null, website: string|null, latitude: float|null, longitude: float|null}>
     */
    public function parseCsvFile(string $filePath, ?string $countryFilter = null): array
    {
        if (! file_exists($filePath)) {
            Log::error('CSV file not found', ['path' => $filePath]);

            return [];
        }

        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            Log::error('Could not open CSV file', ['path' => $filePath]);

            return [];
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            return [];
        }

        // Normalize headers (trim BOM and whitespace)
        $headers = array_map(fn (string $h) => trim($h, "\xEF\xBB\xBF \t\n\r\0\x0B"), $headers);

        $seen = [];
        $dealers = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue;
            }

            $record = array_combine($headers, $row);
            $country = strtoupper(trim($record['Land'] ?? ''));

            if ($countryFilter && $country !== strtoupper($countryFilter)) {
                continue;
            }

            $name = trim($record['Naam'] ?? '');

            if ($name === '') {
                continue;
            }

            $parsed = $this->parseAddress($record['Adres'] ?? '');

            $dealer = [
                'name' => $name,
                'address' => $parsed['street'],
                'city' => $parsed['city'],
                'postal_code' => $parsed['postal_code'],
                'country' => $country,
                'phone' => $this->nullIfEmpty($record['Telefoon'] ?? ''),
                'email' => $this->nullIfEmpty($record['Email'] ?? ''),
                'website' => $this->nullIfEmpty($record['Website'] ?? ''),
                'latitude' => ($record['Lat'] ?? '') !== '' ? (float) $record['Lat'] : null,
                'longitude' => ($record['Lng'] ?? '') !== '' ? (float) $record['Lng'] : null,
            ];

            // Deduplicate within CSV on name + postal_code
            $key = $this->deduplicationKey($dealer['name'], $dealer['postal_code']);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $dealers[] = $dealer;
        }

        fclose($handle);

        return $dealers;
    }

    /**
     * Parse a composite address string into street, postal_code, and city.
     *
     * Expected format: "Street, Postcode City (Province). CountryCode"
     *
     * @return array{street: string|null, postal_code: string|null, city: string|null}
     */
    public function parseAddress(string $address): array
    {
        $result = ['street' => null, 'postal_code' => null, 'city' => null];

        if (trim($address) === '') {
            return $result;
        }

        $cleaned = $address;

        // Step 1: Strip trailing country code ". Be" / ". Nl" / ". Lu" / ". De"
        $cleaned = (string) preg_replace('/\.\s*[A-Za-z]{2}\s*$/', '', $cleaned);

        // Step 2: Strip (Province) from the end
        $cleaned = (string) preg_replace('/\s*\([^)]+\)\s*$/', '', $cleaned);

        $cleaned = trim($cleaned);

        // Step 3: Find the last comma — everything after it contains postcode + city
        $lastCommaPos = strrpos($cleaned, ',');

        if ($lastCommaPos === false) {
            // No comma — try to parse the whole string as postcode+city
            $result['street'] = null;
            $this->extractPostalAndCity(trim($cleaned), $result);

            return $result;
        }

        $result['street'] = trim(substr($cleaned, 0, $lastCommaPos));
        $postcodeCity = trim(substr($cleaned, $lastCommaPos + 1));

        // Step 4: Extract postcode and city via regex
        $this->extractPostalAndCity($postcodeCity, $result);

        return $result;
    }

    /**
     * Extract postal code and city from a string like "5100 Namur" or "8151 AP Lemelerveld".
     *
     * @param  array{street: string|null, postal_code: string|null, city: string|null}  $result
     */
    private function extractPostalAndCity(string $value, array &$result): void
    {
        // Match BE/DE postal (4-5 digits) optionally followed by NL suffix (space + 2 letters)
        // then the remaining text is the city
        if (preg_match('/^(\d{4,5})\s*([A-Z]{2})?\s+(.+)$/', $value, $matches)) {
            $postalCode = $matches[1];

            if (! empty($matches[2])) {
                // NL postcode: concatenate digits + letters without space
                $postalCode .= strtoupper($matches[2]);
            }

            $result['postal_code'] = $postalCode;
            $result['city'] = trim($matches[3]);
        }
    }

    private function deduplicationKey(string $name, ?string $postalCode): string
    {
        return mb_strtolower($name).'|'.($postalCode ?? '');
    }

    private function nullIfEmpty(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
