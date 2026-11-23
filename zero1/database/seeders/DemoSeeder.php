<?php

namespace Database\Seeders;

use App\Enums\PlanUsageMode;
use App\Enums\Role;
use App\Models\Barber;
use App\Models\BarberWorkSchedule;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\MembershipPlan;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\Club\SubscriptionService;
use App\Services\Payments\GatewayPayment;
use App\Services\Payments\SimulatedGateway;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * DADOS DE DEMONSTRAÇÃO — apenas desenvolvimento/homologação.
 * Todos os registros levam "[DEMO]" no nome e e-mails @demo.invalid.
 * Nomes, preços e durações aqui NÃO são informações reais da Zero 1 Barbearia.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'demo12345';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder não pode rodar em produção.');
        }

        $mk = function (string $name, string $email, Role $role) {
            $user = User::query()->firstOrCreate(['email' => $email], [
                'name' => $name, 'password' => Hash::make(self::PASSWORD), 'is_active' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([$role->value]);

            return $user;
        };

        $mk('[DEMO] Proprietário', 'dono@demo.invalid', Role::Owner);
        $mk('[DEMO] Gestor', 'gestor@demo.invalid', Role::Manager);
        $bx = $mk('[DEMO] Barbeiro X', 'barbeiro.x@demo.invalid', Role::Barber);
        $by = $mk('[DEMO] Barbeiro Y', 'barbeiro.y@demo.invalid', Role::Barber);
        $client = $mk('[DEMO] Cliente', 'cliente@demo.invalid', Role::Customer);
        Customer::query()->firstOrCreate(['user_id' => $client->id], [
            'name' => $client->name, 'email' => $client->email, 'terms_accepted_at' => now(), 'privacy_accepted_at' => now(),
        ]);

        $cat = ServiceCategory::query()->firstOrCreate(['slug' => 'demo-cabelo-barba'], ['name' => '[DEMO] Cabelo e barba']);

        $corte = Service::query()->firstOrCreate(['slug' => 'demo-corte'], [
            'service_category_id' => $cat->id, 'name' => '[DEMO] Corte', 'description' => 'Serviço de demonstração (valor fictício).',
            'price_cents' => 5000, 'duration_minutes' => 30, 'buffer_minutes' => 0, 'is_plan_eligible' => true, 'sort_order' => 10,
        ]);
        $combo = Service::query()->firstOrCreate(['slug' => 'demo-corte-barba'], [
            'service_category_id' => $cat->id, 'name' => '[DEMO] Corte + barba', 'description' => 'Serviço de demonstração (valor fictício).',
            'price_cents' => 8000, 'price_is_from' => true, 'duration_minutes' => 60, 'buffer_minutes' => 0, 'is_plan_eligible' => true, 'sort_order' => 20,
        ]);
        $barba = Service::query()->firstOrCreate(['slug' => 'demo-barba'], [
            'service_category_id' => $cat->id, 'name' => '[DEMO] Barba', 'description' => 'Serviço de demonstração (valor fictício).',
            'price_cents' => 4000, 'duration_minutes' => 30, 'buffer_minutes' => 10, 'sort_order' => 30,
        ]);

        foreach ([[$bx, '[DEMO] Barbeiro X', 'demo-barbeiro-x'], [$by, '[DEMO] Barbeiro Y', 'demo-barbeiro-y']] as $i => [$u, $name, $slug]) {
            $barber = Barber::query()->firstOrCreate(['slug' => $slug], [
                'user_id' => $u->id, 'display_name' => $name, 'bio' => 'Perfil de demonstração — sem relação com profissionais reais.',
                'specialties' => ['Demonstração'], 'sort_order' => ($i + 1) * 10,
            ]);
            $barber->services()->syncWithoutDetaching([$corte->id, $combo->id, $barba->id]);
            if (! $barber->schedules()->exists()) {
                foreach (range(1, 6) as $d) { // seg–sáb, almoço 12–13 (escala FICTÍCIA)
                    BarberWorkSchedule::create(['barber_id' => $barber->id, 'weekday' => $d, 'starts_at' => '09:00', 'ends_at' => '12:00']);
                    BarberWorkSchedule::create(['barber_id' => $barber->id, 'weekday' => $d, 'starts_at' => '13:00', 'ends_at' => '19:00']);
                }
            }
        }
        CommissionRule::query()->firstOrCreate(['barber_id' => null, 'service_id' => null], ['percent' => 40, 'plan_visit_cents' => 1000]);

        $plan = MembershipPlan::query()->firstOrCreate(['slug' => 'demo-clube-essencial'], [
            'name' => '[DEMO] Clube Essencial', 'description' => 'Plano de demonstração com preço fictício.',
            'price_cents' => 9900, 'is_published' => true, 'is_featured' => true,
            'usage_mode' => PlanUsageMode::LimitedGlobal, 'uses_per_cycle' => 4, 'max_future_bookings' => 1,
            'min_days_between_uses' => 5, 'benefits' => ['Benefício de demonstração'], 'sort_order' => 10,
        ]);
        $plan->services()->syncWithoutDetaching([$corte->id]);

        // Assinante demo com o 1º mês "pago" pelo gateway SIMULADO.
        $member = $mk('[DEMO] Assinante', 'assinante@demo.invalid', Role::Customer);
        $memberCustomer = $member->customerProfile();
        if (! $memberCustomer->subscriptions()->exists() && config('zero1.payments.driver') === 'simulated') {
            $sub = app(SubscriptionService::class)->start($memberCustomer, $plan->fresh(), 'card');
            app(SimulatedGateway::class)->simulateCheckout($sub->gateway_subscription_id, $sub->cycles()->first()->externalReference(), GatewayPayment::APPROVED);
        }
    }
}
