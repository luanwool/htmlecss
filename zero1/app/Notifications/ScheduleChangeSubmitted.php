<?php

namespace App\Notifications;

use App\Models\ScheduleChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduleChangeSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ScheduleChangeRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->request->fresh('barber');

        return (new MailMessage)
            ->subject('Novo pedido de escala · Zero 1 Barbearia')
            ->line($r->barber?->display_name.' pediu uma alteração na agenda:')
            ->line('**'.$r->kind->label().'**')
            ->line($r->summary())
            ->line($r->reason ? 'Motivo: '.$r->reason : '')
            ->action('Analisar pedido', route('admin.schedule-requests.index'));
    }
}
