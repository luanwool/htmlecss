<?php

namespace App\Enums;

enum PlanUsageMode: string
{
    case LimitedGlobal = 'limited_global';
    case LimitedPerService = 'limited_per_service';
    case Unlimited = 'unlimited';

    public function label(): string
    {
        return match ($this) {
            self::LimitedGlobal => 'Franquia global por ciclo',
            self::LimitedPerService => 'Franquia por serviço',
            self::Unlimited => 'Ilimitado (com limites de uso justo)',
        };
    }
}
