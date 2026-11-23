<?php

namespace App\Enums;

enum ReferralStatus: string
{
    case Invited = 'invited';     // cliente indicado se cadastrou
    case Qualified = 'qualified'; // teve o primeiro atendimento concluído, créditos ainda não emitidos
    case Rewarded = 'rewarded';   // créditos emitidos para ambos
    case Blocked = 'blocked';     // anulado (fraude, estorno, falta repetida)

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Cadastrou-se',
            self::Qualified => 'Fez o primeiro atendimento',
            self::Rewarded => 'Recompensa liberada',
            self::Blocked => 'Bloqueada',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Rewarded => 'badge-green',
            self::Qualified => 'badge-amber',
            self::Blocked => 'badge-red',
            default => 'badge-gray',
        };
    }
}
