<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\DataRequest;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class UsersAndPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_staff_who_sets_own_password(): void
    {
        Notification::fake();
        $this->actingAs($this->userWithRole(Role::Owner));
        $this->post('/painel/usuarios', ['name' => 'Barbeiro Real', 'email' => 'b@example.com', 'role' => 'barber', 'is_active' => '1'])->assertRedirect();
        $u = User::where('email', 'b@example.com')->firstOrFail();
        $this->assertTrue($u->isBarber());
        Notification::assertSentTo($u, ResetPassword::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created']);
    }

    public function test_last_owner_cannot_be_demoted_or_deactivated(): void
    {
        $owner = $this->userWithRole(Role::Owner);
        $this->actingAs($owner);
        $this->put("/painel/usuarios/{$owner->id}", ['name' => $owner->name, 'email' => $owner->email, 'role' => 'manager', 'is_active' => '1'])
            ->assertSessionHasErrors('role');
        $this->assertTrue($owner->fresh()->isOwner());
    }

    public function test_owner_adjusts_manager_permissions(): void
    {
        $this->actingAs($this->userWithRole(Role::Owner));
        $this->put('/painel/cargos', ['permissions' => [Permission::ContentManage->value]])->assertRedirect();
        $perms = RoleModel::findByName(Role::Manager->value)->permissions->pluck('name')->all();
        $this->assertSame([Permission::ContentManage->value], $perms);
    }

    public function test_customer_exports_only_own_data_and_opens_request(): void
    {
        $other = $this->userWithRole(Role::Customer, ['email' => 'outro@example.com']);
        $me = $this->userWithRole(Role::Customer, ['email' => 'eu@example.com']);
        $this->actingAs($me);

        $json = $this->get('/minha-conta/privacidade/exportar')->assertOk()->json();
        $this->assertSame('eu@example.com', $json['conta']['email']);
        $this->assertStringNotContainsString('outro@example.com', json_encode($json));
        $this->assertArrayNotHasKey('password', $json['conta']);

        $this->post('/minha-conta/privacidade/solicitacao', ['type' => 'deletion', 'message' => 'Quero excluir'])->assertRedirect();
        $this->assertDatabaseHas('data_requests', ['user_id' => $me->id, 'type' => 'deletion', 'status' => 'open']);
    }

    public function test_rejecting_data_request_requires_justification(): void
    {
        $dr = DataRequest::create(['type' => 'deletion', 'user_id' => $this->userWithRole(Role::Customer)->id]);
        $this->actingAs($this->userWithRole(Role::Manager));
        $this->put("/painel/lgpd/{$dr->id}", ['status' => 'rejected'])->assertSessionHasErrors('resolution_note');
        $this->put("/painel/lgpd/{$dr->id}", ['status' => 'rejected', 'resolution_note' => 'Retenção fiscal obrigatória'])->assertRedirect();
        $this->assertSame('rejected', $dr->fresh()->status);
    }

    public function test_profile_email_change_requires_reverification(): void
    {
        Notification::fake();
        $me = $this->userWithRole(Role::Customer);
        $this->actingAs($me)->put('/minha-conta/perfil', ['name' => 'Novo Nome', 'email' => 'novo@example.com', 'phone' => '21988887777'])->assertRedirect();
        $this->assertNull($me->fresh()->email_verified_at);
    }
}
