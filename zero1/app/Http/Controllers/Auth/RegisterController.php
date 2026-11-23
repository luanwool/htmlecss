<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\Referrals\ReferralProgram;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        // Normaliza CPF antes de validar para que a regra unique/algoritmo trabalhe nos 11 dígitos.
        $request->merge(['cpf' => Cpf::digits($request->input('cpf'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^[\d\s()+\-]{10,20}$/'],
            'cpf' => ['required', 'string', 'size:11', new Cpf, Rule::unique('customers', 'cpf')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'accept_terms' => ['accepted'],
            'marketing_consent' => ['nullable', 'boolean'],
        ], [
            'accept_terms.accepted' => 'É preciso aceitar os termos de uso e a política de privacidade.',
            'phone.regex' => 'Informe um telefone válido com DDD.',
            'cpf.unique' => 'Este CPF já tem uma conta. Entre em contato com a barbearia se precisou perder o acesso.',
            'cpf.size' => 'Informe um CPF válido.',
        ]);

        $user = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => preg_replace('/\D/', '', $data['phone']),
                'password' => $data['password'],
            ]);
            $user->assignRole(Role::Customer->value);

            // Vincula a um cadastro de balcão já existente com o mesmo e-mail, sem duplicar o cliente.
            $customer = Customer::query()->whereNull('user_id')->where('email', $data['email'])->first() ?? new Customer;
            $customer->fill([
                'user_id' => $user->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $user->phone,
                'cpf' => $data['cpf'],
                'terms_accepted_at' => now(),
                'privacy_accepted_at' => now(),
                'marketing_consent_at' => $request->boolean('marketing_consent') ? now() : null,
            ])->save();

            return $user;
        });

        // Vincula a indicação (se houve clique em /i/<code> antes do cadastro, por sessão ou cookie).
        $refCode = $request->session()->pull(ReferralProgram::COOKIE) ?: $request->cookie(ReferralProgram::COOKIE);
        if ($refCode) {
            app(ReferralProgram::class)->linkFromCookie($user->customer, $refCode);
        }

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('account.dashboard'))
            ->with('status', 'Conta criada! Enviamos um link de confirmação para o seu e-mail.');
    }
}
