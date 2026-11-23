<?php

namespace App\Services\Whatsapp;

use App\Models\Customer;
use App\Support\SiteSettings;

/**
 * Envio de mensagens por WhatsApp — implementação atual é "manual" (grátis, sem risco):
 * monta o link wa.me com a mensagem pronta, para o atendente abrir e enviar com um toque.
 *
 * A estrutura segue o mesmo padrão do adaptador de pagamentos: trocar o driver para
 * "cloud" (WhatsApp Cloud API da Meta) não muda nada no restante do sistema.
 */
class WhatsappSender
{
    public function __construct(private SiteSettings $settings) {}

    public function driver(): string
    {
        return $this->settings->get('whatsapp.driver', 'manual');
    }

    /** Número do destinatário em dígitos (DDI+DDD+número) ou null. */
    public function digits(?string $raw): ?string
    {
        $d = preg_replace('/\D/', '', (string) $raw);
        if (strlen($d) < 10) {
            return null;
        }
        if (strlen($d) <= 11) {         // 11 dígitos = DDD+celular (sem DDI)
            $d = '55'.$d;
        }

        return $d;
    }

    /** Devolve a URL wa.me com a mensagem URL-encoded. */
    public function manualUrl(string $to, string $message): ?string
    {
        $number = $this->digits($to);
        if (! $number) {
            return null;
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
    }

    public function urlForCustomer(Customer $customer, string $message): ?string
    {
        return $this->manualUrl((string) $customer->phone, $message);
    }

    /** Mensagem pronta de confirmação de agendamento (mesmo texto do e-mail). */
    public function appointmentConfirmationMessage(\App\Models\Appointment $a): string
    {
        $when = \App\Support\BusinessTime::format($a->starts_at, "l, d/m \à\s H:i");
        $site = $this->settings->businessName();
        $addr = $this->settings->fullAddress();

        return "Olá, ".\Illuminate\Support\Str::of($a->customer->name)->before(' ').
            "! Seu horário na {$site} está confirmado:\n".
            "{$a->service_name_snapshot} com {$a->barber->display_name}\n".
            "{$when}".
            ($addr ? "\nLocal: {$addr}" : '').
            "\nGerencie, remarque ou cancele em ".route('account.appointments.show', $a->uid);
    }

    /** True se puder (um dia) enviar sem interação humana. Hoje: só no driver cloud. */
    public function canSendAutomatically(): bool
    {
        return $this->driver() === 'cloud';
    }
}
