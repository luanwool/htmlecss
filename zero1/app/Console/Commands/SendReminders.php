<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Notifications\AppointmentNotification;
use App\Services\Booking\BookingPolicy;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'zero1:send-reminders';

    protected $description = 'Envia lembretes de agendamento por e-mail (ex.: 24h e 2h antes)';

    public function handle(BookingPolicy $policy): int
    {
        $hours = $policy->reminderHours();
        $sent = 0;
        foreach (array_slice($hours, 0, 2) as $i => $h) {
            $column = $i === 0 ? 'reminder_24h_sent_at' : 'reminder_2h_sent_at';
            Appointment::query()->with('customer')->where('status', AppointmentStatus::Confirmed->value)
                ->whereNull($column)->where('starts_at', '>', now())->where('starts_at', '<=', now()->addHours($h))
                // não manda o lembrete "longo" se já estiver dentro da janela do "curto"
                ->when($i === 0 && isset($hours[1]), fn ($q) => $q->where('starts_at', '>', now()->addHours($hours[1])))
                ->each(function (Appointment $a) use ($column, &$sent) {
                    $a->forceFill([$column => now()])->saveQuietly();
                    if ($a->customer?->email) {
                        $a->customer->notify(new AppointmentNotification($a, 'reminder'));
                        $sent++;
                    }
                });
        }
        $this->info("{$sent} lembrete(s) enfileirado(s).");

        return self::SUCCESS;
    }
}
