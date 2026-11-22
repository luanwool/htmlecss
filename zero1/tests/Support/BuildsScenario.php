<?php

namespace Tests\Support;

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberWorkSchedule;
use App\Models\Customer;
use App\Models\MembershipPlan;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Booking\BookingRequest;
use App\Services\Booking\BookingService;
use App\Services\Club\SubscriptionService;
use App\Services\Payments\GatewayPayment;
use App\Services\Payments\SimulatedGateway;
use App\Support\BusinessTime;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

trait BuildsScenario
{
    /** "Agora" fixo: segunda-feira, 02/03/2026, 08:00 em São Paulo. */
    protected function freezeAt(string $local = '2026-03-02 08:00'): void
    {
        Carbon::setTestNow(CarbonImmutable::parse($local, BusinessTime::tz())->utc());
    }

    protected function local(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, BusinessTime::tz());
    }

    /** Barbeiro ativo com escala seg–sáb 09:00–12:00 e 13:00–19:00 (almoço 12–13). */
    protected function barber(string $name, array $services = [], array $attrs = []): Barber
    {
        $b = Barber::factory()->create($attrs + ['display_name' => $name]);
        foreach (range(1, 6) as $d) {
            BarberWorkSchedule::create(['barber_id' => $b->id, 'weekday' => $d, 'starts_at' => '09:00:00', 'ends_at' => '12:00:00']);
            BarberWorkSchedule::create(['barber_id' => $b->id, 'weekday' => $d, 'starts_at' => '13:00:00', 'ends_at' => '19:00:00']);
        }
        $b->services()->sync(collect($services)->pluck('id'));

        return $b;
    }

    protected function service(string $name, int $minutes, int $buffer = 0, array $attrs = []): Service
    {
        return Service::factory()->create($attrs + ['name' => $name, 'duration_minutes' => $minutes, 'buffer_minutes' => $buffer, 'price_cents' => 5000, 'is_plan_eligible' => true]);
    }

    protected function customer(?string $email = null): Customer
    {
        $user = User::factory()->role(Role::Customer->value)->create($email ? ['email' => $email] : []);

        return $user->customerProfile();
    }

    protected function book(Customer $c, Service $s, ?Barber $b, string $localStart, string $mode = 'avulso', bool $staff = false): Appointment
    {
        return app(BookingService::class)->book(new BookingRequest($c, $s, $b?->id, $this->local($localStart), $mode, staff: $staff));
    }

    protected function plan(array $services, array $attrs = []): MembershipPlan
    {
        $plan = MembershipPlan::factory()->create($attrs + ['usage_mode' => 'limited_global', 'uses_per_cycle' => 4, 'price_cents' => 9900]);
        $plan->services()->sync(collect($services)->mapWithKeys(fn ($s) => [$s->id => ['uses_per_cycle' => null]]));

        return $plan;
    }

    /** Adere ao plano e, se $pay, simula o pagamento aprovado do 1º ciclo via webhook. */
    protected function subscribe(Customer $c, MembershipPlan $plan, bool $pay = true, string $method = 'card'): Subscription
    {
        $sub = app(SubscriptionService::class)->start($c, $plan, $method);
        if ($pay) {
            $this->gateway()->simulateCheckout($sub->gateway_subscription_id, $sub->cycles()->first()->externalReference(), GatewayPayment::APPROVED);
        }

        return $sub->fresh();
    }

    protected function gateway(): SimulatedGateway
    {
        return app(SimulatedGateway::class);
    }
}
