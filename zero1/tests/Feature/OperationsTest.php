<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\CommissionRule;
use App\Models\EntitlementLedgerEntry;
use App\Models\FinancialEntry;
use App\Models\ServiceVisit;
use App\Models\User;
use App\Services\Club\EntitlementService;
use App\Services\Operations\DashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use BuildsScenario, RefreshDatabase;

    private $corte;

    private $x;

    private $y;

    private User $barberUserX;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->freezeAt('2026-03-05 16:00');
        $this->corte = $this->service('Corte', 30, attrs: ['price_cents' => 5000]);
        $this->barberUserX = User::factory()->role(Role::Barber->value)->create();
        $this->x = $this->barber('X', [$this->corte], ['user_id' => $this->barberUserX->id]);
        $this->y = $this->barber('Y', [$this->corte]);
        CommissionRule::create(['barber_id' => null, 'service_id' => null, 'percent' => 40, 'plan_visit_cents' => 1500]);
    }

    /** Aceitação 13 */
    public function test_walk_in_registered_by_barber_counts_once_in_production_and_finance(): void
    {
        $this->actingAs($this->barberUserX)->post('/barbeiro/atendimento', [
            'new_name' => 'Cliente Balcão', 'new_phone' => '21999990000', 'consent' => '1', 'service_id' => $this->corte->id,
            'date' => '2026-03-05', 'time' => '15:30', 'mode' => 'avulso', 'origin' => 'balcao',
            'price' => '50,00', 'discount' => '5', 'payment_method' => 'pix',
        ])->assertRedirect(route('barber.dashboard'))->assertSessionHasNoErrors();

        $this->assertSame(1, ServiceVisit::count());
        $v = ServiceVisit::first();
        $this->assertSame([4500, $this->x->id, 'avulso'], [$v->final_cents, $v->barber_id, $v->mode]);
        $this->assertSame(1, FinancialEntry::where('category', 'avulso')->count());
        $this->assertSame(1800, $v->commission->amount_cents, '40% de R$ 45,00');
        $this->assertSame(1, Appointment::where('status', 'completed')->count(), 'Ocupou a agenda (ocupação efetiva)');

        $m = app(DashboardMetrics::class)->compute($this->local('2026-03-01 00:00')->utc(), $this->local('2026-03-31 23:59')->utc());
        $this->assertSame(1, $m['visits_avulso']);
        $this->assertSame(4500, $m['walk_in_income']);
        $this->assertSame(4500, $m['avg_ticket_avulso']);

        // Barbeiro não consegue lançar em horário ocupado do próprio X (mesmo motor de conflitos).
        $this->actingAs($this->barberUserX)->post('/barbeiro/atendimento', [
            'new_name' => 'Outro', 'consent' => '1', 'service_id' => $this->corte->id, 'date' => '2026-03-05', 'time' => '15:45',
            'mode' => 'avulso', 'origin' => 'balcao', 'payment_method' => 'dinheiro',
        ])->assertSessionHasErrors('booking');
    }

    public function test_completing_appointment_creates_single_visit_and_consumes_plan_credit(): void
    {
        $this->freezeAt('2026-03-02 08:00');
        $c = $this->customer();
        $sub = $this->subscribe($c, $this->plan([$this->corte]));
        $a = $this->book($c, $this->corte, $this->x, '2026-03-05 10:00', 'plan');

        $this->freezeAt('2026-03-05 10:05');
        $this->actingAs($this->barberUserX)->post("/barbeiro/agendamentos/{$a->id}/concluir")->assertSessionHasNoErrors();
        $this->actingAs($this->barberUserX)->post("/barbeiro/agendamentos/{$a->id}/concluir")->assertSessionHasErrors('booking');

        $this->assertSame(1, ServiceVisit::count());
        $this->assertSame(0, FinancialEntry::where('category', 'avulso')->count(), 'Corte do plano não é receita nova');
        $this->assertSame(1, FinancialEntry::where('category', 'assinatura')->count());
        $this->assertSame(['reserve', 'consume'], EntitlementLedgerEntry::orderBy('id')->pluck('kind')->all());
        $u = app(EntitlementService::class)->summary($sub->cycles()->first());
        $this->assertSame([0, 1, 3], [$u['reserved'], $u['consumed'], $u['available']]);
        $this->assertSame(1500, ServiceVisit::first()->commission->amount_cents, 'Comissão fixa por atendimento de plano');
    }

    public function test_walk_in_for_member_requires_paid_cycle_and_balance(): void
    {
        $c = $this->customer();
        $this->subscribe($c, $this->plan([$this->corte]), pay: false);
        $manager = User::factory()->role(Role::Manager->value)->create();
        $this->actingAs($manager)->post('/painel/atendimentos', [
            'customer_id' => $c->id, 'barber_id' => $this->y->id, 'service_id' => $this->corte->id, 'date' => '2026-03-05', 'time' => '15:00',
            'mode' => 'plan', 'origin' => 'balcao',
        ])->assertSessionHasErrors('booking');
        $this->assertSame(0, ServiceVisit::count());
    }

    public function test_no_show_consumes_credit_per_policy(): void
    {
        $this->freezeAt('2026-03-02 08:00');
        $c = $this->customer();
        $this->subscribe($c, $this->plan([$this->corte]));
        $a = $this->book($c, $this->corte, $this->x, '2026-03-05 10:00', 'plan');
        $this->freezeAt('2026-03-05 10:20');
        $this->actingAs($this->barberUserX)->post("/barbeiro/agendamentos/{$a->id}/ausente")->assertSessionHasNoErrors();
        $this->assertSame('no_show', $a->fresh()->status->value);
        $this->assertSame(['reserve', 'forfeit'], EntitlementLedgerEntry::orderBy('id')->pluck('kind')->all());
    }

    /** Aceitação 14 */
    public function test_role_isolation(): void
    {
        $c1 = $this->customer('c1@example.com');
        $c2 = $this->customer('c2@example.com');
        $aY = $this->book($c1, $this->corte, $this->y, '2026-03-06 10:00');

        // Barbeiro X não age na agenda de Y e não acessa o painel gerencial.
        $this->actingAs($this->barberUserX)->post("/barbeiro/agendamentos/{$aY->id}/concluir")->assertForbidden();
        $this->actingAs($this->barberUserX)->get('/painel/agenda')->assertForbidden();
        $this->actingAs($this->barberUserX)->get('/barbeiro?periodo=7')->assertOk()->assertDontSee($c1->name);
        $own = $this->book($c2, $this->corte, $this->x, '2026-03-06 11:00');
        $this->actingAs($this->barberUserX)->get('/barbeiro?periodo=7')->assertOk()->assertSee($c2->name)->assertSee('Concluir');

        // Cliente só vê o próprio agendamento.
        $this->actingAs($c2->user)->get("/agendamento/{$aY->uid}")->assertNotFound();
        $this->actingAs($c1->user)->get("/agendamento/{$aY->uid}")->assertOk();
        $this->actingAs($c1->user)->get('/painel/financeiro')->assertForbidden();

        // Gestor sem permissão de financeiro.
        RoleModel::findByName(Role::Manager->value)->revokePermissionTo([Permission::FinanceView->value, Permission::FinanceManage->value]);
        $manager = User::factory()->role(Role::Manager->value)->create();
        $this->actingAs($manager)->get('/painel/financeiro')->assertForbidden();
        $this->actingAs($manager)->get('/painel/agenda?periodo=7')->assertOk()->assertSee($c1->name);
    }

    public function test_csv_export_is_restricted_and_safe(): void
    {
        $c = $this->customer();
        $c->update(['name' => '=HYPERLINK("http://x")']);
        $this->book($c, $this->corte, $this->x, '2026-03-06 10:00');
        $owner = User::factory()->role(Role::Owner->value)->create();
        $res = $this->actingAs($owner)->get('/painel/relatorios/exportar/agendamentos?periodo=mes');
        $res->assertOk();
        $csv = $res->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'Injeção de fórmula neutralizada');
        $this->actingAs($this->barberUserX)->get('/painel/relatorios/exportar/agendamentos')->assertForbidden();
    }
}
