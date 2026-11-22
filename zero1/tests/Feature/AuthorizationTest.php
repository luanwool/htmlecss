<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Barber;
use App\Models\DataRequest;
use App\Models\Faq;
use App\Models\MembershipPlan;
use App\Models\PageSection;
use App\Models\Review;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/painel')->assertRedirect(route('login'));
        $this->get('/minha-conta')->assertRedirect(route('login'));
        $this->get('/barbeiro')->assertRedirect(route('login'));
    }

    public function test_customer_cannot_reach_staff_areas(): void
    {
        $this->actingAs($this->userWithRole(Role::Customer));
        $this->get('/painel')->assertForbidden();
        $this->get('/painel/servicos')->assertForbidden();
        $this->get('/barbeiro')->assertForbidden();
        $this->get('/minha-conta')->assertOk();
    }

    public function test_barber_only_reaches_own_panel(): void
    {
        $this->actingAs($this->userWithRole(Role::Barber));
        $this->get('/barbeiro')->assertOk();
        $this->get('/painel')->assertForbidden();
        $this->post('/painel/servicos', ['name' => 'x', 'duration_minutes' => 30])->assertForbidden();
    }

    public function test_manager_has_default_permissions_but_not_owner_only_areas(): void
    {
        $this->actingAs($this->userWithRole(Role::Manager));
        $this->get('/painel')->assertOk();
        $this->get('/painel/servicos')->assertOk();
        $this->get('/painel/conteudo')->assertOk();
        $this->get('/painel/usuarios')->assertForbidden();
        $this->get('/painel/cargos')->assertForbidden();
        $this->get('/painel/auditoria')->assertForbidden();
    }

    public function test_manager_loses_access_when_owner_revokes_permission(): void
    {
        RoleModel::findByName(Role::Manager->value)->revokePermissionTo(Permission::ServicesManage->value);
        $this->actingAs($this->userWithRole(Role::Manager));
        $this->get('/painel/servicos')->assertForbidden();
        $this->get('/painel/planos')->assertOk();
    }

    public function test_owner_can_open_every_admin_screen(): void
    {
        $service = Service::factory()->create();
        $barber = Barber::factory()->create();
        $plan = MembershipPlan::factory()->create();
        $review = Review::create(['author_name' => 'A', 'content' => 'B']);
        $faq = Faq::first();
        DataRequest::create(['type' => 'deletion']);

        $this->actingAs($this->userWithRole(Role::Owner));
        $urls = [
            '/painel', '/painel/servicos', '/painel/servicos/create', "/painel/servicos/{$service->id}/edit", '/painel/categorias',
            '/painel/profissionais', '/painel/profissionais/create', "/painel/profissionais/{$barber->id}/edit",
            '/painel/planos', '/painel/planos/create', "/painel/planos/{$plan->id}/edit",
            '/painel/clientes', '/painel/conteudo', '/painel/conteudo/midia', '/painel/conteudo/depoimentos',
            '/painel/conteudo/depoimentos/create', "/painel/conteudo/depoimentos/{$review->id}/edit",
            '/painel/conteudo/faq', "/painel/conteudo/faq/{$faq->id}/edit", '/painel/conteudo/paginas',
            '/painel/configuracoes', '/painel/usuarios', '/painel/usuarios/create', '/painel/cargos',
            '/painel/lgpd', '/painel/auditoria', '/painel/modulo/agenda', '/painel/modulo/financeiro',
        ];
        foreach (PageSection::all() as $s) {
            $urls[] = "/painel/conteudo/secoes/{$s->id}";
        }
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/painel/modulo/inexistente')->assertNotFound();
    }
}
