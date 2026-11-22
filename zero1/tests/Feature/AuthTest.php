<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $over = []): array
    {
        return array_merge([
            'name' => 'Cliente Novo', 'email' => 'novo@example.com', 'phone' => '(21) 99999-0000',
            'password' => 'senhaForte1', 'password_confirmation' => 'senhaForte1', 'accept_terms' => '1',
        ], $over);
    }

    public function test_customer_registration_creates_user_customer_role_and_consents(): void
    {
        Notification::fake();
        $this->post('/cadastro', $this->registration())->assertRedirect(route('account.dashboard'));

        $user = User::where('email', 'novo@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(Role::Customer->value));
        $this->assertSame('21999990000', $user->phone);
        $this->assertNotNull($user->customer->terms_accepted_at);
        $this->assertNull($user->customer->marketing_consent_at);
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_requires_terms_acceptance_and_strong_password(): void
    {
        $this->post('/cadastro', $this->registration(['accept_terms' => null]))->assertSessionHasErrors('accept_terms');
        $this->post('/cadastro', $this->registration(['password' => '12345678', 'password_confirmation' => '12345678']))->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_registration_links_existing_walk_in_customer_instead_of_duplicating(): void
    {
        Customer::create(['name' => 'Balcão', 'email' => 'novo@example.com', 'origin' => 'balcao']);
        $this->post('/cadastro', $this->registration());
        $this->assertSame(1, Customer::where('email', 'novo@example.com')->count());
        $this->assertNotNull(Customer::first()->user_id);
    }

    public function test_login_redirects_by_role(): void
    {
        $owner = $this->userWithRole(Role::Owner);
        $this->post('/entrar', ['email' => $owner->email, 'password' => 'password123'])->assertRedirect(route('admin.dashboard'));
        $this->post('/sair');

        $barber = $this->userWithRole(Role::Barber);
        $this->post('/entrar', ['email' => $barber->email, 'password' => 'password123'])->assertRedirect(route('barber.dashboard'));
        $this->post('/sair');

        $customer = $this->userWithRole(Role::Customer);
        $this->post('/entrar', ['email' => $customer->email, 'password' => 'password123'])->assertRedirect(route('account.dashboard'));
        $this->assertNotNull($customer->fresh()->last_login_at);
    }

    public function test_wrong_password_and_brute_force_lockout(): void
    {
        $user = $this->userWithRole(Role::Customer);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/entrar', ['email' => $user->email, 'password' => 'errada'])->assertSessionHasErrors('email');
        }
        // Mesmo com a senha certa, fica bloqueado após 5 falhas.
        $this->post('/entrar', ['email' => $user->email, 'password' => 'password123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_login_and_active_session_is_terminated(): void
    {
        $user = $this->userWithRole(Role::Manager, ['is_active' => false]);
        $this->post('/entrar', ['email' => $user->email, 'password' => 'password123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $active = $this->userWithRole(Role::Manager);
        $this->actingAs($active);
        $active->update(['is_active' => false]);
        $this->get('/painel')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_reset_flow_is_neutral_and_works(): void
    {
        Notification::fake();
        $user = $this->userWithRole(Role::Customer);

        $this->post('/esqueci-a-senha', ['email' => 'naoexiste@example.com'])->assertSessionHas('status');
        $this->post('/esqueci-a-senha', ['email' => $user->email])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function ($n) use ($user) {
            $this->post('/redefinir-senha', [
                'token' => $n->token, 'email' => $user->email,
                'password' => 'novaSenha9', 'password_confirmation' => 'novaSenha9',
            ])->assertRedirect(route('login'));

            return true;
        });
        $this->assertTrue(Hash::check('novaSenha9', $user->fresh()->password));
    }
}
