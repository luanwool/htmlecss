<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Barber;
use App\Models\Faq;
use App\Models\MembershipPlan;
use App\Models\Page;
use App\Models\Review;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_verified_business_data_and_local_schema(): void
    {
        $res = $this->get('/')->assertOk();
        $res->assertSee('Zero 1 Barbearia')
            ->assertSee('Av. Cesário de Melo, 4070')
            ->assertSee('(21) 97349-6399')
            ->assertSee('"@type":"BarberShop"', false)
            ->assertSee('"postalCode":"23050-102"', false)
            ->assertSee('horário a confirmar');
    }

    public function test_how_it_works_section_is_shown_and_editable(): void
    {
        $this->get('/')->assertOk()->assertSee('id="como-funciona"', false)->assertSee('Escolha o serviço')->assertSee('Passo 1:');

        $steps = \App\Models\PageSection::where('key', 'steps')->firstOrFail();
        $this->actingAs($this->userWithRole(Role::Manager))->put("/painel/conteudo/secoes/{$steps->id}", [
            'title' => 'Simples assim', 'is_visible' => '1',
            'content' => ['step_1_title' => 'Passo editado', 'step_1_text' => str_repeat('a', 150), 'step_2_title' => 'Dois', 'step_3_title' => 'Três'],
        ])->assertSessionHasNoErrors();
        $this->get('/')->assertSee('Simples assim')->assertSee('Passo editado');
    }

    public function test_base_seed_contains_no_fake_catalog_or_demo_data(): void
    {
        $this->assertSame(0, Service::count());
        $this->assertSame(0, Barber::count());
        $this->assertSame(0, MembershipPlan::count());
        $this->assertSame(0, Review::count());
        $this->assertSame(0, Page::published()->count(), 'Termos/políticas nascem como rascunho');
        $this->assertSame(0, Faq::published()->count(), 'FAQ nasce sem respostas');

        $this->get('/')->assertOk()
            ->assertDontSee('[DEMO]')
            ->assertSee('Tabela de serviços em atualização')
            ->assertSee('Os planos do Clube Zero 1 estão sendo preparados');
    }

    public function test_only_published_plans_with_their_rules_are_listed(): void
    {
        $service = Service::factory()->create(['name' => 'Corte Teste', 'is_plan_eligible' => true]);
        $published = MembershipPlan::factory()->create(['name' => 'Plano Visível', 'price_cents' => 12000, 'max_future_bookings' => 2]);
        $published->services()->attach($service->id);
        MembershipPlan::factory()->create(['name' => 'Plano Rascunho', 'is_published' => false]);

        $this->get('/')->assertOk()
            ->assertSee('Plano Visível')->assertSee('R$ 120,00')
            ->assertSee('Até 2 agendamento(s) futuro(s) por vez')
            ->assertSee('liberados após a confirmação do pagamento', false)
            ->assertDontSee('Plano Rascunho');
    }

    public function test_inactive_or_hidden_services_and_barbers_are_not_shown(): void
    {
        Service::factory()->create(['name' => 'Serviço Ativo']);
        Service::factory()->create(['name' => 'Serviço Inativo', 'is_active' => false]);
        Service::factory()->create(['name' => 'Serviço Oculto', 'is_public' => false]);
        Barber::factory()->create(['display_name' => 'Profissional Público']);
        Barber::factory()->create(['display_name' => 'Profissional Desligado', 'status' => 'inactive']);

        $this->get('/')->assertSee('Serviço Ativo')->assertDontSee('Serviço Inativo')->assertDontSee('Serviço Oculto')
            ->assertSee('Profissional Público')->assertDontSee('Profissional Desligado');
    }

    public function test_reviews_need_authorization_and_faqs_need_answers(): void
    {
        Review::create(['author_name' => 'Autorizado', 'content' => 'Texto A', 'is_published' => true, 'authorization_confirmed_at' => now()]);
        Review::create(['author_name' => 'SemAutorizacao', 'content' => 'Texto B', 'is_published' => true]);
        Faq::create(['question' => 'Pergunta respondida?', 'answer' => 'Sim.', 'is_published' => true]);

        $this->get('/')->assertSee('Autorizado')->assertDontSee('SemAutorizacao')
            ->assertSee('Pergunta respondida?')->assertDontSee('Como cancelo minha assinatura?');
    }

    public function test_draft_pages_are_private_and_published_pages_render_safe_markdown(): void
    {
        $page = Page::where('slug', 'privacidade')->first();
        $this->get('/p/privacidade')->assertNotFound();

        $manager = $this->userWithRole(Role::Manager);
        $this->actingAs($manager)->get('/p/privacidade')->assertOk()->assertSee('Pré-visualização');

        $page->update(['is_published' => true, 'body' => "## Título\n\n<script>alert(1)</script>[x](javascript:alert(1))"]);
        auth()->logout();
        $this->get('/p/privacidade')->assertOk()->assertSee('<h2>Título</h2>', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('javascript:alert', false);
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('pages.show', 'privacidade'));
    }

    public function test_robots_blocks_indexing_outside_production(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
        $this->get('/')->assertSee('noindex', false);
    }

    public function test_security_headers_and_csp_nonce(): void
    {
        $this->withVite();
        $res = $this->get('/');
        $res->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $csp = $res->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]+'/", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
    }

    public function test_cta_entry_points_require_account_for_confirmation(): void
    {
        $this->get('/agendar')->assertOk()->assertSee('Agende seu horário');
        $plan = MembershipPlan::factory()->create(['is_published' => false]);
        $this->get(route('plans.subscribe', $plan))->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole(Role::Customer))->get(route('plans.subscribe', $plan))->assertNotFound();
    }
}
