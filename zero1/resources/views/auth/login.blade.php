<x-layouts.auth title="Entrar" subtitle="Acesse sua conta da Zero 1 Barbearia.">
    <form method="POST" action="{{ route('login.store') }}" class="space-y-5" data-loading>
        @csrf
        <x-form.input name="email" type="email" label="E-mail" autocomplete="email" required autofocus />
        <x-form.input name="password" type="password" label="Senha" autocomplete="current-password" required />
        <div class="flex items-center justify-between gap-4">
            <x-form.checkbox name="remember" label="Manter conectado" />
            <a href="{{ route('password.request') }}" class="text-sm underline">Esqueci a senha</a>
        </div>
        <button type="submit" class="btn-dark w-full" data-loading-text="Entrando…">Entrar</button>
    </form>
    <p class="mt-6 text-center text-sm text-graphite/70">Ainda não tem conta? <a href="{{ route('register') }}" class="font-semibold text-ink underline">Criar cadastro</a></p>
</x-layouts.auth>
