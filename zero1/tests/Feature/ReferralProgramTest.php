<?php

namespace Tests\Feature;

use App\Enums\ReferralStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralCredit;
use App\Services\Operations\VisitService;
use App\Services\Referrals\ReferralProgram;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class ReferralProgramTest extends TestCase
{
    use BuildsScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->freezeAt();
        app(SiteSettings::class)->set([
            'referral.enabled' => true,
            'referral.referrer_percent' => 10,
            'referral.referred_percent' => 30,
            'referral.credit_valid_days' => 90,
            'referral.max_percent_per_visit' => 50,
        ]);
    }

    public function test_link_sets_session_and_signup_links_the_referral(): void
    {
        $alice = $this->customer('alice@example.com');
        $code = app(ReferralProgram::class)->codeFor($alice);
        // O link grava o código (na sessão da cadeia de requests do teste); o cadastro na sequência o consome.
        $this->get('/i/'.$code->code)->assertRedirect('/')->assertSessionHas(ReferralProgram::COOKIE, $code->code);
        $this->assertSame(1, $code->fresh()->visits_count);

        // Cadastro na mesma sessão
        $this->post('/cadastro', [
            'name' => 'Bob', 'email' => 'bob@example.com', 'phone' => '21999990000',
            'password' => 'senhaForte1', 'password_confirmation' => 'senhaForte1', 'accept_terms' => '1',
        ])->assertRedirect(route('account.dashboard'));

        $bob = Customer::where('email', 'bob@example.com')->firstOrFail();
        $this->assertSame($alice->id, $bob->referred_by_customer_id);
        $this->assertSame(ReferralStatus::Invited, $bob->referralReceived->status);
        $this->assertSame(1, $code->fresh()->signups_count);
    }

    public function test_first_paid_visit_issues_credits_for_both_parties(): void
    {
        $alice = $this->customer();
        $bob = $this->customer();
        app(ReferralProgram::class)->codeFor($alice);
        app(ReferralProgram::class)->linkFromCookie($bob, ReferralCode::where('customer_id', $alice->id)->value('code'));

        $corte = $this->service('Corte', 30, attrs: ['price_cents' => 5000]);
        $x = $this->barber('X', [$corte]);
        $a = $this->book($bob, $corte, $x, '2026-03-05 10:00');
        $this->freezeAt('2026-03-05 10:30');
        app(VisitService::class)->complete($a->fresh(), ['price_cents' => 5000, 'payment_method' => 'pix', 'payment_status' => 'paid'], null);

        $this->assertSame(ReferralStatus::Rewarded, Referral::firstOrFail()->status);
        $this->assertSame(1500, ReferralCredit::where('customer_id', $bob->id)->first()->amount_cents); // 30%
        $this->assertSame(500, ReferralCredit::where('customer_id', $alice->id)->first()->amount_cents); // 10%
    }

    public function test_second_visit_applies_the_credit_respecting_cap(): void
    {
        $alice = $this->customer();
        $bob = $this->customer();
        app(ReferralProgram::class)->codeFor($alice);
        app(ReferralProgram::class)->linkFromCookie($bob, ReferralCode::where('customer_id', $alice->id)->value('code'));

        $corte = $this->service('Corte', 30, attrs: ['price_cents' => 5000]);
        $x = $this->barber('X', [$corte]);
        $a1 = $this->book($bob, $corte, $x, '2026-03-05 10:00');
        $this->freezeAt('2026-03-05 10:30');
        app(VisitService::class)->complete($a1->fresh(), ['price_cents' => 5000, 'payment_method' => 'pix', 'payment_status' => 'paid'], null);

        $this->assertSame(1500, app(ReferralProgram::class)->balance($bob));

        $a2 = $this->book($bob, $corte, $x, '2026-03-12 10:00');
        $this->freezeAt('2026-03-12 10:30');
        $v2 = app(VisitService::class)->complete($a2->fresh(), ['price_cents' => 5000, 'payment_method' => 'pix', 'payment_status' => 'paid'], null);

        // Teto 50% de 5000 = 2500; crédito disponível era 1500 → aplica 1500
        $this->assertSame(1500, $v2->discount_cents);
        $this->assertSame(3500, $v2->final_cents);
        $this->assertSame(0, app(ReferralProgram::class)->balance($bob));
    }

    public function test_program_disabled_does_not_create_or_apply_credits(): void
    {
        app(SiteSettings::class)->set(['referral.enabled' => false]);
        $alice = $this->customer();
        $bob = $this->customer();
        app(ReferralProgram::class)->codeFor($alice);
        app(ReferralProgram::class)->linkFromCookie($bob, ReferralCode::where('customer_id', $alice->id)->value('code'));
        $this->assertNull($bob->fresh()->referred_by_customer_id);
        $this->assertSame(0, Referral::count());
    }

    public function test_cannot_self_refer_or_double_refer(): void
    {
        $alice = $this->customer();
        $code = app(ReferralProgram::class)->codeFor($alice);
        $this->assertNull(app(ReferralProgram::class)->linkFromCookie($alice, $code->code));

        $bob = $this->customer();
        $this->assertNotNull(app(ReferralProgram::class)->linkFromCookie($bob, $code->code));
        // Segunda vinculação do mesmo bob tem que ser rejeitada (bob->fresh() lê o referral já criado)
        $other = $this->customer();
        $otherCode = app(ReferralProgram::class)->codeFor($other);
        $this->assertNull(app(ReferralProgram::class)->linkFromCookie($bob->fresh(), $otherCode->code));
        $this->assertSame(1, Referral::count());
    }

    public function test_admin_sees_referrals_list(): void
    {
        $this->actingAs($this->userWithRole(Role::Owner))->get('/painel/indicacoes')->assertOk()->assertSee('Programa de indicações');
    }

    public function test_customer_referral_page_shows_code_and_link(): void
    {
        $u = $this->userWithRole(Role::Customer);
        $res = $this->actingAs($u)->get('/minha-conta/indicar')->assertOk()->assertSee('Seu código');
        $code = ReferralCode::firstOrFail();
        $res->assertSee(url('/i/'.$code->code));
    }
}
