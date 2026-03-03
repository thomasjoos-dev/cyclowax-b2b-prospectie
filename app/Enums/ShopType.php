<?php

namespace App\Enums;

enum ShopType: string
{
    case LokaalKlein = 'lokaal_klein';
    case LokaalGroot = 'lokaal_groot';
    case Keten = 'keten';

    public function label(): string
    {
        return match ($this) {
            self::LokaalKlein => 'Lokaal (klein)',
            self::LokaalGroot => 'Lokaal (groot)',
            self::Keten => 'Keten',
        };
    }
}
