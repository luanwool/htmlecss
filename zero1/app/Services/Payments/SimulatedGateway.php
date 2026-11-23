<?php

namespace App\Services\Payments;

use App\Models\BillingCycle;
use App\Models\SimulatedGatewayRecord;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * GATEWAY SIMULADO — nenhum dinheiro real circula.
 * Guarda o "estado do provedor" em simulated_gateway_records para que consultas, webhooks e conciliação
 * funcionem exatamente como funcionarão com a API real.
 */
class SimulatedGateway implements PaymentGatewayInterface
{
    public function __construct()
    {
        if (app()->environment('production') && ! config('zero1.payments.allow_simulated_in_production')) {
            throw new RuntimeException('Gateway simulado bloqueado em produção. Configure o gateway real ou ALLOW_SIMULATED_PAYMENTS=true para homologação.');
        }
    }

    public function name(): string
    {
        return 'simulated';
    }

    public function supportsAutomaticPix(): bool
    {
        return false; // espelha o cenário conservador: Pix exige pagamento a cada mês
    }

    public function createSubscription(Subscription $subscription, BillingCycle $firstCycle): GatewaySubscription
    {
        $id = 'sim_sub_'.Str::lower(Str::random(16));
        SimulatedGatewayRecord::create([
            'kind' => 'subscription', 'external_id' => $id, 'status' => 'pending',
            'method' => $subscription->payment_method, 'amount_cents' => $subscription->price_cents,
            'external_reference' => 'z1-sub'.$subscription->id,
        ]);

        return new GatewaySubscription($id, 'pending', route('simulator.checkout', [$id, 'ref' => $firstCycle->externalReference()]));
    }

    public function createCharge(BillingCycle $cycle): ?string
    {
        $sub = $cycle->subscription;
        if ($sub->payment_method === 'card') {
            // Cartão: o "provedor" debita sozinho na renovação.
            $outcome = config('zero1.payments.simulated.card_renewal_outcome') === 'rejected' ? GatewayPayment::REJECTED : GatewayPayment::APPROVED;
            $payment = $this->recordPayment($sub->gateway_subscription_id, $cycle->externalReference(), $cycle->amount_cents, $outcome, 'card');
            $this->dispatchWebhook('payment', $payment->external_id);

            return $payment->external_id;
        }

        // Pix sem débito automático: o cliente paga pela tela de checkout (link na área do cliente).
        return null;
    }

    public function getPayment(string $paymentId): GatewayPayment
    {
        return $this->toPayment(SimulatedGatewayRecord::where('kind', 'payment')->where('external_id', $paymentId)->firstOrFail());
    }

    public function getSubscription(string $subscriptionId): GatewaySubscription
    {
        $r = SimulatedGatewayRecord::where('kind', 'subscription')->where('external_id', $subscriptionId)->firstOrFail();

        return new GatewaySubscription($r->external_id, $r->status);
    }

    public function listPayments(string $subscriptionId): array
    {
        return SimulatedGatewayRecord::where('kind', 'payment')->where('parent_id', $subscriptionId)
            ->orderBy('id')->get()->map(fn ($r) => $this->toPayment($r))->all();
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        SimulatedGatewayRecord::where('kind', 'subscription')->where('external_id', $subscriptionId)->update(['status' => 'cancelled']);
    }

    public function parseWebhook(Request $request): ?WebhookNotification
    {
        $body = $request->getContent();
        $expected = hash_hmac('sha256', $body, (string) config('zero1.payments.webhook_secret'));
        if (! hash_equals($expected, (string) $request->header('X-Signature'))) {
            return null;
        }
        $data = json_decode($body, true) ?: [];
        if (empty($data['id']) || empty($data['type']) || empty($data['data']['id'])) {
            return null;
        }

        return new WebhookNotification((string) $data['id'], $data['type'], (string) $data['data']['id'], $data);
    }

    // ---------------- Ações do simulador (tela de checkout e painel) ----------------

    /** Cliente "paga" (ou tem o pagamento recusado) na tela simulada do provedor. */
    public function simulateCheckout(string $subscriptionId, string $externalReference, string $outcome, bool $loseWebhook = false): SimulatedGatewayRecord
    {
        $sub = SimulatedGatewayRecord::where('kind', 'subscription')->where('external_id', $subscriptionId)->firstOrFail();
        if ($outcome === GatewayPayment::APPROVED && $sub->status === 'pending') {
            $sub->update(['status' => 'authorized']);
        }
        $amount = BillingCycle::findByReference($externalReference)?->amount_cents ?? $sub->amount_cents;
        $payment = $this->recordPayment($subscriptionId, $externalReference, $amount, $outcome, $sub->method);
        if (! $loseWebhook) {
            $this->dispatchWebhook('payment', $payment->external_id);
        }

        return $payment;
    }

    /** Muda o status de um pagamento existente (estorno, chargeback) e notifica. */
    public function simulateStatusChange(string $paymentId, string $status, bool $loseWebhook = false): void
    {
        SimulatedGatewayRecord::where('kind', 'payment')->where('external_id', $paymentId)->update(['status' => $status]);
        if (! $loseWebhook) {
            $this->dispatchWebhook('payment', $paymentId);
        }
    }

    /** Reenvia a mesma notificação (testa idempotência). */
    public function redeliver(string $eventId, string $topic, string $resourceId): void
    {
        $this->dispatchWebhook($topic, $resourceId, $eventId);
    }

    public function signedRequest(array $payload): Request
    {
        $body = json_encode($payload);
        $request = Request::create('/webhooks/pagamentos/simulated', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
        $request->headers->set('X-Signature', hash_hmac('sha256', $body, (string) config('zero1.payments.webhook_secret')));

        return $request;
    }

    private function dispatchWebhook(string $topic, string $resourceId, ?string $eventId = null): void
    {
        $payload = ['id' => $eventId ?? 'evt_'.Str::lower(Str::random(18)), 'type' => $topic, 'action' => 'updated', 'data' => ['id' => $resourceId]];
        app(WebhookIntake::class)->receive($this, $this->signedRequest($payload));
    }

    private function recordPayment(string $subscriptionId, string $ref, int $amount, string $status, ?string $method): SimulatedGatewayRecord
    {
        return SimulatedGatewayRecord::create([
            'kind' => 'payment', 'external_id' => 'sim_pay_'.Str::lower(Str::random(16)), 'parent_id' => $subscriptionId,
            'external_reference' => $ref, 'status' => $status, 'method' => $method, 'amount_cents' => $amount,
            'paid_at' => $status === GatewayPayment::APPROVED ? now() : null,
        ]);
    }

    private function toPayment(SimulatedGatewayRecord $r): GatewayPayment
    {
        $fee = (int) round($r->amount_cents * config('zero1.payments.simulated.fee_percent') / 100);

        return new GatewayPayment(
            id: $r->external_id, status: $r->status, amountCents: (int) $r->amount_cents, externalReference: $r->external_reference,
            subscriptionId: $r->parent_id, method: $r->method, feeCents: $fee, netCents: $r->amount_cents - $fee,
            paidAt: $r->paid_at ? CarbonImmutable::instance($r->paid_at) : null,
        );
    }
}
