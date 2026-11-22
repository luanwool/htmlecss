<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class PaymentGatewayManager
{
    public function driver(?string $name = null): PaymentGatewayInterface
    {
        $name ??= config('zero1.payments.driver');

        return match ($name) {
            'simulated' => app(SimulatedGateway::class),
            // 'mercadopago' => app(MercadoPagoGateway::class),  ← próxima fase
            default => throw new InvalidArgumentException("Gateway de pagamento desconhecido: {$name}"),
        };
    }

    public function isSimulated(): bool
    {
        return config('zero1.payments.driver') === 'simulated';
    }
}
