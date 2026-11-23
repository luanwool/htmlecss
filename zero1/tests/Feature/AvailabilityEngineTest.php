<?php

namespace Tests\Feature;

use App\Exceptions\BookingException;
use App\Models\BlockedInterval;
use App\Models\ScheduleException;
use App\Services\Booking\BookingService;
use App\Services\Calendar\CalendarEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class AvailabilityEngineTest extends TestCase
{
    use BuildsScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->freezeAt();
    }

    private function slots($service, $barber, string $date): array
    {
        return array_column(app(BookingService::class)->availableSlots($service, $barber?->id, $this->local($date)), 'time');
    }

    /** Aceitação 6 */
    public function test_two_barbers_can_serve_at_exactly_17h_simultaneously(): void
    {
        $corte = $this->service('Corte', 30);
        [$x, $y] = [$this->barber('X', [$corte]), $this->barber('Y', [$corte])];

        $a1 = $this->book($this->customer(), $corte, $x, '2026-03-05 17:00');
        $a2 = $this->book($this->customer(), $corte, $y, '2026-03-05 17:00');

        $this->assertSame($a1->starts_at->toIso8601String(), $a2->starts_at->toIso8601String());
        $this->assertNotSame($a1->barber_id, $a2->barber_id);
    }

    /** Aceitação 7 (sequencial) + intervalo semiaberto */
    public function test_same_barber_never_gets_overlapping_intervals_but_back_to_back_is_allowed(): void
    {
        $corte = $this->service('Corte', 30);
        $x = $this->barber('X', [$corte]);
        $this->book($this->customer(), $corte, $x, '2026-03-05 17:00');

        try {
            $this->book($this->customer(), $corte, $x, '2026-03-05 17:15');
            $this->fail('Sobreposição deveria ser recusada');
        } catch (BookingException $e) {
            $this->assertSame('slot_taken', $e->reason);
            $this->assertNotEmpty($e->alternatives, 'Deve oferecer alternativas');
            $this->assertContains('17:30', array_column($e->alternatives, 'time'));
        }

        $next = $this->book($this->customer(), $corte, $x, '2026-03-05 17:30'); // fim exato = início do próximo
        $this->assertSame('17:30', $next->localStart()->format('H:i'));
    }

    /** Aceitação 8 */
    public function test_durations_and_buffers_block_the_right_times_only_for_that_barber(): void
    {
        $corte = $this->service('Corte', 30);
        $combo = $this->service('Corte + barba', 60);
        $barba = $this->service('Barba', 30, buffer: 10);
        [$x, $y] = [$this->barber('X', [$corte, $combo, $barba]), $this->barber('Y', [$corte, $combo, $barba])];

        $this->book($this->customer(), $combo, $x, '2026-03-05 17:00'); // 17:00–18:00
        $xSlots = $this->slots($corte, $x, '2026-03-05');
        foreach (['17:00', '17:15', '17:30', '17:45'] as $t) {
            $this->assertNotContains($t, $xSlots, "X não pode ter {$t}");
        }
        $this->assertContains('16:30', $xSlots);
        $this->assertContains('18:00', $xSlots);
        $this->assertContains('17:15', $this->slots($corte, $y, '2026-03-05'), 'Y continua livre');

        // Barba 30 + 10 de limpeza às 10:00 ocupa até 10:40 → próximo início de 15 em 15 é 10:45.
        $this->book($this->customer(), $barba, $y, '2026-03-05 10:00');
        $ySlots = $this->slots($corte, $y, '2026-03-05');
        $this->assertNotContains('10:30', $ySlots);
        $this->assertContains('10:45', $ySlots);
    }

    /** Aceitação 9 */
    public function test_absences_holidays_lunch_and_closing_remove_slots(): void
    {
        $combo = $this->service('Combo', 60);
        $x = $this->barber('X', [$combo]);

        $slots = $this->slots($combo, $x, '2026-03-05');
        $this->assertContains('11:00', $slots);
        $this->assertNotContains('11:30', $slots, '11:30–12:30 invade o almoço');
        $this->assertNotContains('12:00', $slots);
        $this->assertContains('18:00', $slots);
        $this->assertNotContains('18:15', $slots, 'Não pode terminar depois do fechamento');
        $this->assertSame([], $this->slots($combo, $x, '2026-03-08'), 'Domingo sem escala');

        ScheduleException::create(['barber_id' => null, 'date' => '2026-03-06', 'is_closed' => true, 'reason' => 'Feriado']);
        $this->assertSame([], $this->slots($combo, $x, '2026-03-06'));

        ScheduleException::create(['barber_id' => $x->id, 'date' => '2026-03-07', 'is_closed' => true, 'reason' => 'Folga']);
        $this->assertSame([], $this->slots($combo, $x, '2026-03-07'));

        BlockedInterval::create(['barber_id' => $x->id, 'starts_at' => $this->local('2026-03-09 00:00')->utc(), 'ends_at' => $this->local('2026-03-14 00:00')->utc(), 'reason' => 'Férias']);
        $this->assertSame([], $this->slots($combo, $x, '2026-03-10'));

        $x->update(['status' => 'away']);
        $this->assertSame([], $this->slots($combo, $x, '2026-03-05'), 'Afastado não recebe agendamentos');

        $x->update(['status' => 'active']);
        ScheduleException::create(['barber_id' => $x->id, 'date' => '2026-03-15', 'is_closed' => false, 'starts_at' => '10:00', 'ends_at' => '14:00', 'reason' => 'Abertura extra']);
        $this->assertSame(['10:00', '10:15', '10:30', '10:45', '11:00', '11:15', '11:30', '11:45', '12:00', '12:15', '12:30', '12:45', '13:00'], $this->slots($combo, $x, '2026-03-15'));
    }

    /** Aceitação 10 */
    public function test_any_available_only_assigns_an_eligible_free_barber(): void
    {
        $corte = $this->service('Corte', 30);
        $x = $this->barber('X', [$corte]);
        $y = $this->barber('Y', [$corte]);
        $notEligible = $this->barber('Z', []); // não faz o serviço
        $outOfRotation = $this->barber('W', [$corte], ['accepts_any_assignment' => false]);

        $this->book($this->customer(), $corte, $x, '2026-03-05 17:00');
        $a = $this->book($this->customer(), $corte, null, '2026-03-05 17:00');
        $this->assertSame($y->id, $a->barber_id);

        $this->expectException(BookingException::class);
        $this->book($this->customer(), $corte, null, '2026-03-05 17:00'); // X e Y ocupados; Z não apto; W fora do rodízio
    }

    public function test_no_bookings_in_the_past_and_times_are_stored_in_utc(): void
    {
        $corte = $this->service('Corte', 30);
        $x = $this->barber('X', [$corte]);
        $a = $this->book($this->customer(), $corte, $x, '2026-03-05 17:00');
        $this->assertSame('2026-03-05 20:00:00', $a->getRawOriginal('starts_at'), 'São Paulo UTC-3');

        $this->expectException(BookingException::class);
        $this->book($this->customer(), $corte, $x, '2026-03-01 10:00');
    }

    public function test_cancelled_appointments_free_the_slot(): void
    {
        $corte = $this->service('Corte', 30);
        $x = $this->barber('X', [$corte]);
        $a = $this->book($this->customer(), $corte, $x, '2026-03-05 17:00');
        app(BookingService::class)->cancel($a, true, null, 'teste');
        $this->assertContains('17:00', $this->slots($corte, $x, '2026-03-05'));
        $this->book($this->customer(), $corte, $x, '2026-03-05 17:00');
    }

    /** Aceitação 12 */
    public function test_calendar_data_is_consistent_through_confirm_reschedule_cancel(): void
    {
        $corte = $this->service('Corte', 30);
        $x = $this->barber('X', [$corte]);
        $a = $this->book($this->customer(), $corte, $x, '2026-03-05 17:00');
        $cal = app(CalendarEvent::class);

        $ics1 = $cal->ics($a);
        $this->assertStringContainsString('UID:'.$a->uid, $ics1);
        $this->assertStringContainsString('SEQUENCE:0', $ics1);
        $this->assertStringContainsString('DTSTART:20260305T200000Z', $ics1);
        $this->assertStringContainsString('DTEND:20260305T203000Z', $ics1);
        $this->assertStringContainsString('dates=20260305T200000Z%2F20260305T203000Z', $cal->googleUrl($a));

        app(BookingService::class)->reschedule($a, $this->local('2026-03-06 10:00'), null, null);
        $ics2 = $cal->ics($a->fresh());
        $this->assertStringContainsString('UID:'.$a->uid, $ics2, 'UID estável');
        $this->assertStringContainsString('SEQUENCE:1', $ics2);
        $this->assertStringContainsString('DTSTART:20260306T130000Z', $ics2);

        app(BookingService::class)->cancel($a->fresh(), false, null);
        $ics3 = $cal->ics($a->fresh());
        $this->assertStringContainsString('SEQUENCE:2', $ics3);
        $this->assertStringContainsString('METHOD:CANCEL', $ics3);
        $this->assertStringContainsString('STATUS:CANCELLED', $ics3);
    }

    /** Aceitação 15 */
    public function test_changing_service_price_and_duration_does_not_corrupt_history(): void
    {
        $corte = $this->service('Corte', 30);
        $x = $this->barber('X', [$corte]);
        $a = $this->book($this->customer(), $corte, $x, '2026-03-05 17:00');
        $corte->update(['duration_minutes' => 45, 'price_cents' => 9000, 'name' => 'Corte Premium']);

        $a->refresh();
        $this->assertSame(30, $a->duration_minutes_snapshot);
        $this->assertSame(5000, $a->price_cents_snapshot);
        $this->assertSame('Corte', $a->service_name_snapshot);
        $this->assertSame('17:30', $a->localEnd()->format('H:i'));
        $this->assertContains('17:30', $this->slots($corte, $x, '2026-03-05'), 'Ocupação usa o snapshot, não a duração nova');
    }

    public function test_reschedule_uses_same_conflict_engine(): void
    {
        $corte = $this->service('Corte', 30);
        $x = $this->barber('X', [$corte]);
        $a = $this->book($this->customer(), $corte, $x, '2026-03-05 17:00');
        $this->book($this->customer(), $corte, $x, '2026-03-05 18:00');

        $this->expectException(BookingException::class);
        app(BookingService::class)->reschedule($a, $this->local('2026-03-05 17:45'), $x->id, null);
    }
}
