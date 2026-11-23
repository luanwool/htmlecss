<x-layouts.auth title="Recuperar senha" subtitle="Informe seu e-mail e enviaremos um link para criar uma nova senha.">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5" data-loading>
        @csrf
        <x-form.input name="email" type="email" label="E-mail" autocomplete="email" required autofocus />
        <button type="submit" class="btn-dark w-full">Enviar link</button>
    </form>
    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="underline">Voltar para o login</a></p>
</x-layouts.auth>
