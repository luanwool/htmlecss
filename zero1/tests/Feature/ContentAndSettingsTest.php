<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Faq;
use App\Models\MediaAsset;
use App\Models\OpeningHour;
use App\Models\PageSection;
use App\Models\Review;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->userWithRole(Role::Manager));
    }

    public function test_review_cannot_be_published_without_authorization(): void
    {
        $this->post('/painel/conteudo/depoimentos', ['author_name' => 'João', 'content' => 'Ótimo', 'is_published' => '1'])
            ->assertSessionHasErrors('authorization_confirmed');
        $this->post('/painel/conteudo/depoimentos', ['author_name' => 'João', 'content' => 'Ótimo', 'is_published' => '1', 'authorization_confirmed' => '1'])
            ->assertRedirect();
        $r = Review::firstOrFail();
        $this->assertNotNull($r->authorization_confirmed_at);
        $this->assertSame(auth()->id(), $r->authorization_confirmed_by);
    }

    public function test_faq_needs_answer_to_publish(): void
    {
        $faq = Faq::first();
        $this->put("/painel/conteudo/faq/{$faq->id}", ['question' => $faq->question, 'is_published' => '1'])->assertSessionHasErrors('answer');
        $this->put("/painel/conteudo/faq/{$faq->id}", ['question' => $faq->question, 'answer' => 'Resposta real.', 'is_published' => '1'])->assertRedirect();
        $this->get('/')->assertSee('Resposta real.');
    }

    public function test_hero_section_edit_reflects_on_home(): void
    {
        $hero = PageSection::where('key', 'hero')->first();
        $this->put("/painel/conteudo/secoes/{$hero->id}", ['title' => 'Headline nova', 'subtitle' => 'Sub nova', 'is_visible' => '1',
            'content' => ['primary_cta' => 'Reservar já', 'secondary_cta' => 'Planos']])->assertRedirect();
        $this->get('/')->assertSee('Headline nova')->assertSee('Reservar já');
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.section_updated']);
    }

    public function test_hidden_section_is_not_rendered(): void
    {
        $loc = PageSection::where('key', 'location')->first();
        $this->put("/painel/conteudo/secoes/{$loc->id}", ['title' => 'Como chegar']);
        $this->get('/')->assertDontSee('id="localizacao"', false);
    }

    public function test_gallery_upload_requires_ownership_confirmation(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('espaco.jpg', 1600, 1000);
        $this->post('/painel/conteudo/midia', ['files' => [$file]])->assertSessionHasErrors('confirm_own');
        $this->post('/painel/conteudo/midia', ['files' => [$file], 'confirm_own' => '1'])->assertRedirect();
        $m = MediaAsset::firstOrFail();
        $this->put("/painel/conteudo/midia/{$m->id}", ['alt_text' => 'Cadeiras do salão', 'is_published' => '1']);
        $this->get('/')->assertSee('Cadeiras do salão');
        $this->delete("/painel/conteudo/midia/{$m->id}");
        Storage::disk('public')->assertMissing($m->path);
    }

    private function settingsPayload(array $over = []): array
    {
        $hours = [];
        foreach (range(1, 7) as $d) {
            $hours[$d] = $d === 7 ? ['is_closed' => '1'] : ['opens_at' => '09:00', 'closes_at' => '19:00'];
        }

        return array_replace_recursive([
            's' => ['business_name' => 'Zero 1 Barbearia', 'business_whatsapp' => '5521973496399', 'brand_accent_color' => '#AA3322',
                'business_instagram' => 'https://instagram.com/zero1'],
            'hours' => $hours, 'business_hours_confirmed' => '1', 'seo_indexable' => '1',
        ], $over);
    }

    public function test_settings_update_hours_and_cache(): void
    {
        $this->put('/painel/configuracoes', $this->settingsPayload(['hours' => [6 => ['opens_at' => '08:00', 'closes_at' => '14:00']]]))
            ->assertSessionHasNoErrors();
        $settings = app(SiteSettings::class);
        $this->assertSame('#AA3322', $settings->accentColor());
        $this->assertTrue($settings->get('business.hours_confirmed'));
        $this->assertSame('08:00', substr(OpeningHour::where('weekday', 6)->value('opens_at'), 0, 5));
        $this->get('/')->assertSee('--accent: #AA3322', false)->assertDontSee('horário a confirmar')->assertSee('instagram.com/zero1');
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_settings_validation(): void
    {
        $this->put('/painel/configuracoes', $this->settingsPayload(['s' => ['business_whatsapp' => '21 9999', 'brand_accent_color' => 'red',
            'business_instagram' => 'javascript:alert(1)']]))->assertSessionHasErrors(['s.business_whatsapp', 's.brand_accent_color', 's.business_instagram']);
        $this->put('/painel/configuracoes', $this->settingsPayload(['hours' => [1 => ['opens_at' => '19:00', 'closes_at' => '09:00']]]))
            ->assertSessionHasErrors('hours.1.opens_at');
    }
}
