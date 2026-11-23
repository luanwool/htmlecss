<?php

namespace App\Services\Payments;

use App\Models\BillingCycle;
use App\Models\Subscription;
use Illuminate\Http\Request;

/**
 * Contrato do gateway. Agenda e regras do clube dependem só desta interface;
 * trocar o simulador pelo Mercado Pago não altera o restante do sistema.
 */
interface PaymentGatewayInterface
{
    public function name(): string;

    /** Pix com débito automático só se a API/conta comprovarem suporte. */
    public function supportsAutomaticPix(): bool;

    /** Cria a assinatura no provedor e devolve a URL do checkout oficial. */
    public function createSubscription(Subscription $subscription, BillingCycle $firstCycle): GatewaySubscription;

    /** Cobrança de um ciclo (renovação). Cartão: débito automático. Pix sem débito automático: gera cobrança para o cliente pagar. */
    public function createCharge(BillingCycle $cycle): ?string;

    public function getPayment(string $paymentId): GatewayPayment;

    public function getSubscription(string $subscriptionId): GatewaySubscription;

    /** @return GatewayPayment[] usado na conciliação periódica */
    public function listPayments(string $subscriptionId): array;

    public function cancelSubscription(string $subscriptionId): void;

    /** Valida autenticidade e extrai a notificação. Retorna null se a assinatura do webhook for inválida. */
    public function parseWebhook(Request $request): ?WebhookNotification;
}
