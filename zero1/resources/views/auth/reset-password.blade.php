<x-layouts.auth title="Nova senha">
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5" data-loading>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-form.input name="email" type="email" label="E-mail" :value="$email" autocomplete="email" required />
        <x-form.input name="password" type="password" label="Nova senha" autocomplete="new-password" required hint="Mínimo de 8 caracteres, com letras e números." />
        <x-form.input name="password_confirmation" type="password" label="Confirme a nova senha" autocomplete="new-password" required />
        <button type="submit" class="btn-dark w-full">Salvar nova senha</button>
    </form>
</x-layouts.auth>
