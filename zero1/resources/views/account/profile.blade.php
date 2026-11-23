<x-layouts.account title="Meus dados">
    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('account.profile.update') }}" class="card space-y-5" data-loading>
            @csrf @method('PUT')
            <h2 class="font-semibold">Dados de contato</h2>
            <x-form.input name="name" label="Nome" :value="$user->name" required />
            <x-form.input name="email" type="email" label="E-mail" :value="$user->email" required hint="Ao trocar o e-mail, será preciso confirmá-lo novamente." />
            <x-form.input name="phone" type="tel" label="Celular" :value="$user->phone" required />
            <x-form.checkbox name="marketing_consent" label="Quero receber novidades e promoções" :checked="(bool) $user->customer?->marketing_consent_at" hint="Você pode retirar o consentimento quando quiser." />
            <button class="btn-dark">Salvar</button>
        </form>
        <form method="POST" action="{{ route('account.password.update') }}" class="card space-y-5" data-loading>
            @csrf @method('PUT')
            <h2 class="font-semibold">Alterar senha</h2>
            <x-form.input name="current_password" type="password" label="Senha atual" autocomplete="current-password" required />
            <x-form.input name="password" type="password" label="Nova senha" autocomplete="new-password" required />
            <x-form.input name="password_confirmation" type="password" label="Confirme a nova senha" autocomplete="new-password" required />
            <button class="btn-dark">Alterar senha</button>
        </form>
    </div>
</x-layouts.account>
