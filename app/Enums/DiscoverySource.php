<?php

namespace App\Enums;

enum DiscoverySource: string
{
    case Overpass = 'overpass';
    case GooglePlaces = 'google_places';
    case BrandLocator = 'brand_locator';
    case CsvImport = 'csv_import';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Overpass => 'OpenStreetMap',
            self::GooglePlaces => 'Google Places',
            self::BrandLocator => 'Brand Locator',
            self::CsvImport => 'CSV Import',
            self::Manual => 'Handmatig',
        };
    }
}
