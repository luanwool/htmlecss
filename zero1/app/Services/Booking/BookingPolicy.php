<?php

namespace App\Services\Booking;

use App\Support\SiteSettings;

/**
 * Políticas de agenda lidas das configurações editáveis. Os valores padrão são técnicos e
 * devem ser confirmados pela barbearia (Configurações → Agenda).
 */
class BookingPolicy
{
    public function __construct(private SiteSettings $settings) {}

    public function slotInterval(): int
    {
        return max(5, (int) $this->settings->get('booking.slot_interval', 15));
    }

    public function minAdvanceMinutes(): int
    {
        return (int) $this->settings->get('booking.min_advance_minutes', 60);
    }

    public function maxAdvanceDays(): int
    {
        return max(1, (int) $this->settings->get('booking.max_advance_days', 30));
    }

    public function cancelMinHours(): int
    {
        return (int) $this->settings->get('booking.cancel_min_hours', 2);
    }

    public function lateCancelForfeits(): bool
    {
        return (bool) $this->settings->get('booking.late_cancel_forfeits', true);
    }

    public function noShowConsumesCredit(): bool
    {
        return (bool) $this->settings->get('booking.no_show_consumes_credit', true);
    }

    /** balance = equilíbrio de carga (menos atendimentos no dia); first = ordem de cadastro. */
    public function assignmentPolicy(): string
    {
        return $this->settings->get('booking.assignment_policy', 'balance') === 'first' ? 'first' : 'balance';
    }

    public function maxOpenPerCustomer(): int
    {
        return (int) $this->settings->get('booking.max_open_per_customer', 3);
    }

    /** flag = mantém e sinaliza para regularização; cancel = cancela e libera o horário. */
    public function unpaidPolicy(): string
    {
        return $this->settings->get('booking.unpaid_policy', 'flag') === 'cancel' ? 'cancel' : 'flag';
    }

    /** @return int[] horas de antecedência dos lembretes, ex.: [24, 2] */
    public function reminderHours(): array
    {
        return collect(explode(',', (string) $this->settings->get('booking.reminder_hours', '24,2')))
            ->map(fn ($h) => (int) trim($h))->filter(fn ($h) => $h > 0)->unique()->sortDesc()->values()->all();
    }

    public function walkInPastHours(): int
    {
        return (int) $this->settings->get('booking.walk_in_past_hours', 12);
    }
}
