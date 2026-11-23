<?php

namespace App\Services\Payments;

final class WebhookNotification
{
    public function __construct(
        public string $eventId,
        public string $topic,       // payment | subscription_preapproval | subscription_authorized_payment
        public string $resourceId,
        public array $payload = [],
    ) {}
}
