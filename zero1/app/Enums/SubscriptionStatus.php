<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case AwaitingPayment = 'awaiting_payment';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case CancelRequested = 'cancel_requested';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::AwaitingPayment => 'Aguardando pagamento',
            self::Active => 'Ativa (ciclo pago)',
            self::PastDue => 'Pagamento atrasado',
            self::Suspended => 'Suspensa',
            self::CancelRequested => 'Cancelamento solicitado',
            self::Cancelled => 'Cancelada',
            self::Expired => 'Expirada',
            self::Refunded => 'Reembolsada',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'badge-green',
            self::CancelRequested, self::AwaitingPayment, self::Pending => 'badge-amber',
            self::PastDue, self::Suspended, self::Refunded => 'badge-red',
            default => 'badge-gray',
        };
    }

    /** Assinaturas "vivas" (impedem nova adesão concorrente). */
    public static function live(): array
    {
        return [self::Pending->value, self::AwaitingPayment->value, self::Active->value, self::PastDue->value, self::CancelRequested->value, self::Suspended->value];
    }
}
