<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Barber;
use App\Models\MembershipPlan;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->userWithRole(Role::Manager));
    }

    public function test_manager_creates_service_with_price_buffer_and_barbers(): void
    {
        $barber = Barber::factory()->create();
        $this->post('/painel/servicos', [
            'name' => 'Corte + barba', 'price' => '80,00', 'price_is_from' => '1', 'duration_minutes' => 60, 'buffer_minutes' => 10,
            'is_active' => '1', 'is_public' => '1', 'is_bookable_standalone' => '1', 'is_plan_eligible' => '1', 'barbers' => [$barber->id],
        ])->assertRedirect(route('admin.services.index'));

        $s = Service::firstOrFail();
        $this->assertSame(8000, $s->price_cents);
        $this->assertSame(70, $s->effectiveDurationMinutes());
        $this->assertSame('corte-barba', $s->slug);
        $this->assertTrue($s->barbers->contains($barber));
        $this->assertDatabaseHas('audit_logs', ['action' => 'service.created', 'auditable_id' => $s->id]);
    }

    public function test_service_validation(): void
    {
        $this->post('/painel/servicos', ['name' => '', 'duration_minutes' => 2, 'price' => 'abc'])
            ->assertSessionHasErrors(['name', 'duration_minutes', 'price']);
        $this->assertSame(0, Service::count());
    }

    public function test_service_update_is_audited_with_old_and_new_values_and_delete_is_soft(): void
    {
        $s = Service::factory()->create(['price_cents' => 5000, 'duration_minutes' => 30]);
        $this->put("/painel/servicos/{$s->id}", ['name' => $s->name, 'price' => '55', 'duration_minutes' => 45, 'is_active' => '1'])->assertRedirect();

        $log = AuditLog::where('action', 'service.updated')->firstOrFail();
        $this->assertSame(5000, $log->old_values['price_cents']);
        $this->assertSame(5500, $log->new_values['price_cents']);
        $this->assertSame(45, $log->new_values['duration_minutes']);

        $this->delete("/painel/servicos/{$s->id}")->assertRedirect();
        $this->assertSoftDeleted($s);
    }

    public function test_barber_photo_is_converted_to_responsive_webp(): void
    {
        Storage::fake('public');
        $this->post('/painel/profissionais', [
            'display_name' => 'Profissional Real', 'status' => 'active', 'is_public' => '1',
            'specialties' => 'Degradê, Barba', 'photo' => UploadedFile::fake()->image('foto.jpg', 1200, 1500),
        ])->assertRedirect(route('admin.barbers.index'));

        $b = Barber::with('photo')->firstOrFail();
        $this->assertSame(['Degradê', 'Barba'], $b->specialties);
        $this->assertSame('image/webp', $b->photo->mime_type);
        $this->assertArrayHasKey(480, $b->photo->variants);
        $this->assertArrayHasKey(960, $b->photo->variants);
        Storage::disk('public')->assertExists($b->photo->path);
    }

    public function test_plan_requires_services_and_real_price_to_publish(): void
    {
        $s = Service::factory()->create();
        $this->post('/painel/planos', ['name' => 'P', 'price' => '0', 'usage_mode' => 'limited_global', 'uses_per_cycle' => 4, 'is_published' => '1', 'services' => [$s->id]])
            ->assertSessionHasErrors('price');
        $this->post('/painel/planos', ['name' => 'P', 'price' => '99', 'usage_mode' => 'limited_global', 'uses_per_cycle' => 4])
            ->assertSessionHasErrors('services');
        $this->post('/painel/planos', ['name' => 'P', 'price' => '99', 'usage_mode' => 'limited_global', 'services' => [$s->id]])
            ->assertSessionHasErrors('uses_per_cycle');
        $this->post('/painel/planos', ['name' => 'P', 'price' => '99', 'usage_mode' => 'limited_per_service', 'services' => [$s->id]])
            ->assertSessionHasErrors('services');
        $this->assertSame(0, MembershipPlan::count());
    }

    public function test_commercial_changes_create_new_plan_version_but_cosmetic_ones_do_not(): void
    {
        $s1 = Service::factory()->create();
        $s2 = Service::factory()->create();
        $base = ['name' => 'Clube', 'price' => '99,90', 'usage_mode' => 'limited_global', 'uses_per_cycle' => 4,
            'services' => [$s1->id], 'all_barbers' => '1', 'allowed_time_start' => '09:00', 'allowed_time_end' => '18:00'];
        $this->post('/painel/planos', $base + ['is_published' => '1'])->assertRedirect();
        $plan = MembershipPlan::firstOrFail();
        $this->assertSame(1, $plan->version);
        $this->assertSame(9990, $plan->price_cents);

        $this->put("/painel/planos/{$plan->id}", array_merge($base, ['name' => 'Clube Renomeado', 'description' => 'nova copy']));
        $this->assertSame(1, $plan->fresh()->version, 'Mudança cosmética não altera a versão');

        $this->put("/painel/planos/{$plan->id}", array_merge($base, ['price' => '109,90']));
        $this->assertSame(2, $plan->fresh()->version);

        $this->put("/painel/planos/{$plan->id}", array_merge($base, ['price' => '109,90', 'services' => [$s1->id, $s2->id]]));
        $this->assertSame(3, $plan->fresh()->version, 'Mudança de serviços cobertos é comercial');
    }

    public function test_per_service_franchise_is_stored_on_pivot(): void
    {
        $s = Service::factory()->create();
        $this->post('/painel/planos', ['name' => 'Franquia', 'price' => '150', 'usage_mode' => 'limited_per_service',
            'services' => [$s->id], 'service_uses' => [$s->id => 2], 'all_barbers' => '1'])->assertRedirect();
        $this->assertSame(2, MembershipPlan::first()->services->first()->pivot->uses_per_cycle);
        $this->assertNull(MembershipPlan::first()->uses_per_cycle);
    }
}
