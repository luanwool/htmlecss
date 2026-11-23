# Próxima etapa: integração real com o Mercado Pago

Nesta versão os pagamentos são **simulados** (`PAYMENT_GATEWAY=simulated`). Todo o restante — ciclos, ledger de franquia,
bloqueio de inadimplentes, webhooks idempotentes, conciliação, estornos/chargeback, financeiro — já funciona e é testado
através da interface `App\Services\Payments\PaymentGatewayInterface`. A integração real consiste em **um novo adaptador**,
sem mudanças na agenda ou nas regras do clube.

## O que o adaptador `MercadoPagoGateway` precisa implementar

| Método da interface | Equivalente no Mercado Pago (validar na documentação vigente) |
|---|---|
| `createSubscription()` | Criar assinatura (preapproval) com `external_reference` = `z1-sub{id}-c{seq}`, `back_url` = `/assinatura/retorno`; devolver `init_point` como `checkoutUrl` |
| `createCharge()` | Cartão: nada (o MP cobra na recorrência). Pix sem débito automático: gerar cobrança Pix do ciclo |
| `getPayment()` | `GET /v1/payments/{id}` → mapear status (`approved`, `rejected`, `refunded`, `charged_back`, `cancelled`, `pending`), `transaction_details.net_received_amount`, `fee_details` |
| `getSubscription()` | `GET /preapproval/{id}` |
| `listPayments()` | Pagamentos autorizados da assinatura (conciliação) |
| `cancelSubscription()` | `PUT /preapproval/{id}` com status `cancelled` |
| `parseWebhook()` | Validar `x-signature` (HMAC com a chave secreta do webhook) e `x-request-id`; tópicos `payment`, `subscription_preapproval`, `subscription_authorized_payment` |

Depois: registrar `'mercadopago' => app(MercadoPagoGateway::class)` em `PaymentGatewayManager`, liberar os domínios do
checkout na CSP (`SecurityHeaders`) e trocar `PAYMENT_GATEWAY=mercadopago`.

## Verificações obrigatórias antes de ativar

- [ ] Credenciais **de teste** da conta da Zero 1 (no `.env` do servidor, nunca por mensagem ou Git).
- [ ] Confirmar na documentação e **na conta** se há **Pix Automático** via API para assinaturas. Se não houver,
      manter `supportsAutomaticPix() = false` (Pix com cobrança mensal paga pelo cliente — já implementado no fluxo).
- [ ] URL do webhook HTTPS: `https://SEU_DOMINIO/webhooks/pagamentos/mercadopago`.
- [ ] Rodar a suíte `tests/Feature/ClubSubscriptionTest.php` adaptada para o sandbox + homologação manual
      (aprovado, recusado, estorno, chargeback, webhook perdido → `php artisan zero1:reconcile-payments`).
