<?php

namespace App\Enums;

enum DiscoverySource: string
{
    case Overpass = 'overpass';
    case GooglePlaces = 'google_places';
    case BrandLocator = 'brand_locator';
    case CsvImport = 'csv_import';
    case Manual = 'manual';
    case CyclowaxCrm = 'cyclowax_crm';

    public function label(): string
    {
        return match ($this) {
            self::Overpass => 'OpenStreetMap',
            self::GooglePlaces => 'Google Places',
            self::BrandLocator => 'Brand Locator',
            self::CsvImport => 'CSV Import',
            self::Manual => 'Handmatig',
            self::CyclowaxCrm => 'Cyclowax CRM',
        };
    }
}
