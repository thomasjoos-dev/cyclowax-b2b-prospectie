<?php

namespace App\Enums;

enum PipelineStatus: string
{
    case NietGecontacteerd = 'niet_gecontacteerd';
    case Gecontacteerd = 'gecontacteerd';
    case InGesprek = 'in_gesprek';
    case Partner = 'partner';
    case Afgewezen = 'afgewezen';

    public function label(): string
    {
        return match ($this) {
            self::NietGecontacteerd => 'Niet gecontacteerd',
            self::Gecontacteerd => 'Gecontacteerd',
            self::InGesprek => 'In gesprek',
            self::Partner => 'Partner',
            self::Afgewezen => 'Afgewezen',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NietGecontacteerd => 'neutral',
            self::Gecontacteerd => 'info',
            self::InGesprek => 'warning',
            self::Partner => 'success',
            self::Afgewezen => 'error',
        };
    }

    public function badgeClass(): string
    {
        return "badge-{$this->color()} badge-soft";
    }
}
