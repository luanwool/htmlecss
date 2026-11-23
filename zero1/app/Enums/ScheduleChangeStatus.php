<?php

namespace App\Enums;

enum ScheduleChangeStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled'; // o próprio barbeiro cancelou antes da análise

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando análise',
            self::Approved => 'Aprovada',
            self::Rejected => 'Rejeitada',
            self::Cancelled => 'Cancelada pelo barbeiro',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'badge-amber',
            self::Approved => 'badge-green',
            self::Rejected => 'badge-red',
            self::Cancelled => 'badge-gray',
        };
    }
}
