<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\ScheduleChangeStatus;
use App\Models\BarberWorkSchedule;
use App\Models\ScheduleChangeRequest;
use App\Models\ScheduleException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class ScheduleChangeRequestTest extends TestCase
{
    use BuildsScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->freezeAt();
    }

    private function barberWithUser(string $name): array
    {
        $user = User::factory()->role(Role::Barber->value)->create();
        $corte = $this->service('Corte', 30);
        $b = $this->barber($name, [$corte], ['user_id' => $user->id]);

        return [$user, $b];
    }

    public function test_barber_submits_weekly_change_and_admin_approves_applying_schedule(): void
    {
        [$user, $barber] = $this->barberWithUser('X');
        $this->assertSame(12, $barber->schedules()->count()); // 6 dias × 2 períodos

        $this->actingAs($user)->post('/barbeiro/escala', [
            'kind' => 'weekly', 'weekday' => 2,
            'periods' => [['start' => '13:00', 'end' => '19:00']],
            'reason' => 'Faculdade pela manhã',
        ])->assertRedirect(route('barber.schedule.index'))->assertSessionHasNoErrors();

        $req = ScheduleChangeRequest::firstOrFail();
        $this->assertSame(ScheduleChangeStatus::Pending, $req->status);

        $owner = $this->userWithRole(Role::Owner);
        $this->actingAs($owner)->post("/painel/escala/pedidos/{$req->id}/aprovar")->assertRedirect();
        $req->refresh();
        $this->assertSame(ScheduleChangeStatus::Approved, $req->status);

        $tuesday = BarberWorkSchedule::where('barber_id', $barber->id)->where('weekday', 2)->get();
        $this->assertCount(1, $tuesday);
        $this->assertSame('13:00:00', $tuesday->first()->starts_at);
    }

    public function test_barber_cannot_see_other_barbers_requests(): void
    {
        [$u1, $b1] = $this->barberWithUser('X');
        [$u2, $b2] = $this->barberWithUser('Y');
        $other = ScheduleChangeRequest::create(['barber_id' => $b2->id, 'kind' => 'weekly',
            'payload' => ['weekday' => 3, 'periods' => []], 'status' => 'pending']);
        $this->actingAs($u1)->get('/barbeiro/escala')->assertOk()->assertDontSee('schedule-change-request-'.$other->id);
        $this->actingAs($u1)->post("/barbeiro/escala/{$other->id}/cancelar")->assertNotFound();
    }

    public function test_exception_request_creates_schedule_exception_on_approval(): void
    {
        [$user, $barber] = $this->barberWithUser('X');
        $this->actingAs($user)->post('/barbeiro/escala', [
            'kind' => 'exception', 'date' => '2026-03-20', 'exception_type' => 'closed', 'reason' => 'Médico',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $req = ScheduleChangeRequest::firstOrFail();
        $this->actingAs($this->userWithRole(Role::Owner))->post("/painel/escala/pedidos/{$req->id}/aprovar")->assertRedirect();

        $this->assertDatabaseHas('schedule_exceptions', ['barber_id' => $barber->id, 'date' => '2026-03-20', 'is_closed' => true]);
    }

    public function test_reject_requires_review_note_and_does_not_apply(): void
    {
        [$user, $barber] = $this->barberWithUser('X');
        $this->actingAs($user)->post('/barbeiro/escala', ['kind' => 'exception', 'date' => '2026-03-20', 'exception_type' => 'closed']);
        $req = ScheduleChangeRequest::firstOrFail();
        $owner = $this->userWithRole(Role::Owner);
        $this->actingAs($owner)->post("/painel/escala/pedidos/{$req->id}/rejeitar")->assertSessionHasErrors('review_note');
        $this->assertSame(0, ScheduleException::count());

        $this->actingAs($owner)->post("/painel/escala/pedidos/{$req->id}/rejeitar", ['review_note' => 'Já há compromisso nesse dia.'])->assertRedirect();
        $this->assertSame(ScheduleChangeStatus::Rejected, $req->fresh()->status);
    }

    public function test_only_users_with_permission_see_review_queue(): void
    {
        // gestor sem a permissão específica
        RoleModel::findByName(Role::Manager->value)->revokePermissionTo(Permission::ScheduleReview->value);
        $this->actingAs($this->userWithRole(Role::Manager))->get('/painel/escala/pedidos')->assertForbidden();

        $this->actingAs($this->userWithRole(Role::Owner))->get('/painel/escala/pedidos')->assertOk();
    }
}
