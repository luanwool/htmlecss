<?php

namespace App\Enums;

enum BarberStatus: string
{
    case Active = 'active';
    case Away = 'away';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Away => 'Afastado',
            self::Inactive => 'Desativado',
        };
    }
}
