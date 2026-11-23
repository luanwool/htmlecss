<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.profile', ['user' => $request->user()->load('customer')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'lowercase', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['required', 'string', 'regex:/^[\d\s()+\-]{10,20}$/'],
            'marketing_consent' => ['nullable', 'boolean'],
        ]);
        $data['phone'] = preg_replace('/\D/', '', $data['phone']);

        $user->fill(collect($data)->only(['name', 'email', 'phone'])->all());
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();
        if ($user->wasChanged('email')) {
            $user->sendEmailVerificationNotification();
        }

        if ($customer = $user->customer) {
            $customer->fill(['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone]);
            $wantsMarketing = $request->boolean('marketing_consent');
            if ($wantsMarketing && ! $customer->marketing_consent_at) {
                $customer->marketing_consent_at = now();
            } elseif (! $wantsMarketing) {
                $customer->marketing_consent_at = null;
            }
            $customer->save();
        }

        return back()->with('status', 'Dados atualizados.');
    }

    public function password(Request $request, AuditLogger $audit): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $request->user()->update([
            'password' => $request->password,
            'must_change_password' => false,
            'password_set_at' => now(),
        ]);
        $audit->log('auth.password_changed', $request->user());

        return back()->with('status', 'Senha alterada.');
    }

    /**
     * Tela separada para trocar a senha temporária gerada pela barbearia.
     *
     * Diferente da troca normal, aqui não pedimos a senha atual (o cliente perdeu o acesso;
     * já passou pelo login com a senha provisória). Enquanto `must_change_password` for true,
     * o middleware `ForcePasswordChange` manda toda navegação para cá, então essa é a única
     * tela acessível antes de redefinir.
     */
    public function showForcedChange(): \Illuminate\View\View
    {
        return view('account.password-change');
    }

    public function updateForcedChange(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->must_change_password, 403);
        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $request->user()->update([
            'password' => $request->password,
            'must_change_password' => false,
            'password_set_at' => now(),
        ]);
        $audit->log('auth.password_reset_completed', $request->user());

        return redirect()->intended($request->user()->homeRoute())
            ->with('status', 'Senha redefinida com sucesso.');
    }
}
