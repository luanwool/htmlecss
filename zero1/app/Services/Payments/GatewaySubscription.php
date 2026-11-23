<?php

namespace App\Services\Payments;

final class GatewaySubscription
{
    public function __construct(
        public string $id,
        public string $status, // pending | authorized | paused | cancelled
        public ?string $checkoutUrl = null,
    ) {}
}
