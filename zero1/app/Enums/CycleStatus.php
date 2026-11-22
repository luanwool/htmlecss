<?php

namespace App\Enums;

enum CycleStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case ChargedBack = 'charged_back';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando pagamento',
            self::Paid => 'Pago',
            self::Failed => 'Recusado',
            self::Refunded => 'Reembolsado',
            self::ChargedBack => 'Contestado (chargeback)',
            self::Cancelled => 'Cancelado',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Paid => 'badge-green',
            self::Pending => 'badge-amber',
            self::Cancelled => 'badge-gray',
            default => 'badge-red',
        };
    }
}
