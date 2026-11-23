<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case CancelledByCustomer = 'cancelled_by_customer';
    case CancelledByShop = 'cancelled_by_shop';
    case NoShow = 'no_show';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmado',
            self::InProgress => 'Em atendimento',
            self::Completed => 'Concluído',
            self::CancelledByCustomer => 'Cancelado pelo cliente',
            self::CancelledByShop => 'Cancelado pela barbearia',
            self::NoShow => 'Não compareceu',
            self::Expired => 'Expirado',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Confirmed => 'badge-ink',
            self::InProgress => 'badge-amber',
            self::Completed => 'badge-green',
            self::NoShow => 'badge-red',
            default => 'badge-gray',
        };
    }

    /** Status que ocupam a agenda do barbeiro. Cancelados e expirados liberam o horário. */
    public static function occupying(): array
    {
        return [self::Confirmed->value, self::InProgress->value, self::Completed->value, self::NoShow->value];
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Confirmed, self::InProgress], true);
    }

    public function isCancelled(): bool
    {
        return in_array($this, [self::CancelledByCustomer, self::CancelledByShop, self::Expired], true);
    }
}
