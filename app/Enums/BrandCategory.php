<?php

namespace App\Enums;

enum BrandCategory: string
{
    case RaceBike = 'race_bike';
    case Nutrition = 'nutrition';
    case Clothing = 'clothing';
    case Tools = 'tools';
    case Accessories = 'accessories';
    case Tyres = 'tyres';

    public function label(): string
    {
        return match ($this) {
            self::RaceBike => 'Racefietsen',
            self::Nutrition => 'Voeding',
            self::Clothing => 'Kleding',
            self::Tools => 'Gereedschap',
            self::Accessories => 'Accessoires',
            self::Tyres => 'Banden',
        };
    }

    /** @return array<int, self> */
    public static function bikeCategories(): array
    {
        return [self::RaceBike];
    }

    /** @return array<int, self> */
    public static function accessoryCategories(): array
    {
        return [self::Nutrition, self::Clothing, self::Tools, self::Accessories];
    }
}
