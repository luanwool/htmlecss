<?php

namespace App\Services\Payments;

use App\Models\PaymentWebhookEvent;
use App\Services\Club\SubscriptionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recebe notificações do gateway: valida autenticidade, registra de forma idempotente (unique gateway+event_id),
 * CONSULTA A API para obter o estado final e só então aplica. Tolera reenvios e eventos fora de ordem,
 * porque sempre aplica o estado atual informado pela API, não o conteúdo do evento.
 */
class WebhookIntake
{
    public function receive(PaymentGatewayInterface $gateway, Request $request): string
    {
        $notification = $gateway->parseWebhook($request);
        if (! $notification) {
            Log::warning('Webhook de pagamento com assinatura inválida', ['gateway' => $gateway->name(), 'ip' => $request->ip()]);

            return 'invalid_signature';
        }

        try {
            $event = PaymentWebhookEvent::create([
                'gateway' => $gateway->name(), 'event_id' => $notification->eventId, 'topic' => $notification->topic,
                'resource_id' => $notification->resourceId,
                'payload' => ['type' => $notification->topic, 'data_id' => $notification->resourceId], // mínimo, sem dados sensíveis
            ]);
        } catch (UniqueConstraintViolationException) {
            $event = PaymentWebhookEvent::where('gateway', $gateway->name())->where('event_id', $notification->eventId)->first();
            if ($event->processed_at) {
                return 'duplicate';
            }
        }

        $event->increment('attempts');
        $result = match ($notification->topic) {
            'payment', 'subscription_authorized_payment' => app(SubscriptionService::class)->applyPayment($gateway->getPayment($notification->resourceId)),
            'subscription_preapproval' => app(SubscriptionService::class)->syncGatewaySubscription($gateway->getSubscription($notification->resourceId)),
            default => 'ignored_topic',
        };
        $event->update(['result' => $result, 'processed_at' => now()]);

        return $result;
    }
}
