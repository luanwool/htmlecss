<?php

namespace App\Services\Payments;

use Carbon\CarbonImmutable;

/** Pagamento como o provedor o informa (fonte da verdade). */
final class GatewayPayment
{
    public const APPROVED = 'approved';

    public const PENDING = 'pending';

    public const REJECTED = 'rejected';

    public const REFUNDED = 'refunded';

    public const CHARGED_BACK = 'charged_back';

    public const CANCELLED = 'cancelled';

    public function __construct(
        public string $id,
        public string $status,
        public int $amountCents,
        public ?string $externalReference,
        public ?string $subscriptionId = null,
        public ?string $method = null,
        public ?int $feeCents = null,
        public ?int $netCents = null,
        public ?CarbonImmutable $paidAt = null,
        public ?string $receiptUrl = null,
    ) {}
}
