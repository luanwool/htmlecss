<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Support\Money;
use App\Support\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription, public string $event) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->subscription->fresh('plan');
        $site = app(SiteSettings::class);
        [$subject, $line] = match ($this->event) {
            'paid' => ['Pagamento confirmado', 'Recebemos o pagamento do seu plano '.$s->plan->name.' ('.Money::format($s->price_cents).'). Seus atendimentos do mês estão liberados.'],
            'failed' => ['Pagamento não aprovado', 'A cobrança do seu plano não foi aprovada. Enquanto o mês não for pago, novos agendamentos pelo plano ficam bloqueados.'],
            'cancel_requested' => ['Cancelamento registrado', 'Seu plano não será renovado. Você pode usar os atendimentos do período já pago até '.$s->cancel_effective_on?->copy()->subDay()->format('d/m/Y').'.'],
            'refunded' => ['Pagamento estornado', 'Um pagamento do seu plano foi estornado. Os benefícios do período correspondente foram suspensos.'],
            'renewal_due' => ['Mensalidade disponível para pagamento', 'A mensalidade do próximo mês do seu plano já está disponível para pagamento.'],
            default => ['Atualização da assinatura', 'Sua assinatura foi atualizada: '.$s->status->label().'.'],
        };

        return (new MailMessage)->subject($subject.' — '.$site->businessName())->line($line)
            ->action('Ver minha assinatura', route('account.subscription'));
    }
}
