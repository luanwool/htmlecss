<?php

namespace App\Notifications;

use App\Enums\ScheduleChangeStatus;
use App\Models\ScheduleChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduleChangeDecision extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ScheduleChangeRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->request->fresh();
        $approved = $r->status === ScheduleChangeStatus::Approved;
        $mail = (new MailMessage)->subject(($approved ? 'Pedido aprovado' : 'Pedido de escala — resposta').' · Zero 1 Barbearia')
            ->greeting('Olá!')
            ->line($approved ? 'Seu pedido de alteração de agenda foi aprovado:' : 'Seu pedido de alteração de agenda foi analisado:')
            ->line('**'.$r->kind->label().'**')
            ->line($r->summary());
        if ($r->review_note) {
            $mail->line('Observação: '.$r->review_note);
        }

        return $mail->action('Ver no painel do barbeiro', route('barber.schedule.index'));
    }
}
