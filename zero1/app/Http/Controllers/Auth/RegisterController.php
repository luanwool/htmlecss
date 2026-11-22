<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^[\d\s()+\-]{10,20}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'accept_terms' => ['accepted'],
            'marketing_consent' => ['nullable', 'boolean'],
        ], [
            'accept_terms.accepted' => 'É preciso aceitar os termos de uso e a política de privacidade.',
            'phone.regex' => 'Informe um telefone válido com DDD.',
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
                'terms_accepted_at' => now(),
                'privacy_accepted_at' => now(),
                'marketing_consent_at' => $request->boolean('marketing_consent') ? now() : null,
            ])->save();

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('account.dashboard'))
            ->with('status', 'Conta criada! Enviamos um link de confirmação para o seu e-mail.');
    }
}
