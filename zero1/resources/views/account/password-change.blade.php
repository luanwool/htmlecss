<x-layouts.auth title="Defina sua nova senha">
    <p class="mb-5 text-sm text-graphite/80">
        Você entrou com uma senha temporária gerada pela barbearia. Para continuar, crie agora uma senha só sua.
    </p>
    @if (session('status'))
        <div class="mb-5 rounded-xl border border-ink/10 bg-paper/60 p-3 text-sm text-graphite">{{ session('status') }}</div>
    @endif
    <form method="POST" action="{{ route('password.change.update') }}" class="space-y-5" data-loading>
        @csrf
        @method('PUT')
        <x-form.input name="password" type="password" label="Nova senha" autocomplete="new-password" required hint="Mínimo de 8 caracteres, com letras e números." autofocus />
        <x-form.input name="password_confirmation" type="password" label="Confirme a nova senha" autocomplete="new-password" required />
        <button type="submit" class="btn-dark w-full" data-loading-text="Salvando…">Salvar nova senha</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
        @csrf
        <button type="submit" class="text-sm text-graphite/70 underline">Sair</button>
    </form>
</x-layouts.auth>
