<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Services\Calendar\CalendarEvent;
use App\Support\BusinessTime;
use App\Support\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mails de confirmação, reagendamento, cancelamento e lembrete — com convite .ics anexo. */
class AppointmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Appointment $appointment, public string $event) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $a = $this->appointment->fresh(['barber', 'customer']);
        $site = app(SiteSettings::class);
        $calendar = app(CalendarEvent::class);
        $when = BusinessTime::format($a->starts_at, "l, d/m/Y \à\s H:i");

        $subject = match ($this->event) {
            'rescheduled' => 'Horário reagendado',
            'cancelled' => 'Agendamento cancelado',
            'reminder' => 'Lembrete do seu horário',
            'payment_issue' => 'Atenção: seu horário do plano precisa de regularização',
            default => 'Horário confirmado',
        };

        $mail = (new MailMessage)
            ->subject($subject.' — '.$site->businessName())
            ->greeting('Olá, '.strtok($a->customer->name, ' ').'!')
            ->line(match ($this->event) {
                'cancelled' => 'Seu agendamento foi cancelado:',
                'rescheduled' => 'Seu agendamento foi alterado para:',
                'reminder' => 'Passando para lembrar do seu horário:',
                'payment_issue' => 'O mês do plano que cobre este horário não está pago. Regularize ou reagende como avulso:',
                default => 'Seu horário está confirmado:',
            })
            ->line("**{$a->service_name_snapshot}** com **{$a->barber->display_name}**")
            ->line($when.' ('.$a->duration_minutes_snapshot.' min)');

        if ($address = $site->fullAddress()) {
            $mail->line('Local: '.$site->businessName().' — '.$address);
        }
        if ($this->event !== 'cancelled') {
            $mail->action('Ver, remarcar ou cancelar', route('account.appointments.show', $a->uid));
            if ($dir = $site->directionsUrl()) {
                $mail->line('[Como chegar]('.$dir.') · [Adicionar ao Google Agenda]('.$calendar->googleUrl($a).')');
            }
        }

        return $mail->attachData($calendar->ics($a), 'zero1-agendamento.ics', ['mime' => 'text/calendar; charset=UTF-8; method='.($this->event === 'cancelled' ? 'CANCEL' : 'PUBLISH')]);
    }
}
