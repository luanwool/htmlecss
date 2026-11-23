@php
    $terms = \App\Models\Page::published()->where('slug', 'termos-de-assinatura')->exists();
    $privacy = \App\Models\Page::published()->where('slug', 'privacidade')->exists();
    $termsLabel = 'Li e aceito os '.($terms ? '<a class="underline" target="_blank" href="'.e(route('pages.show', 'termos-de-assinatura')).'">termos</a>' : 'termos de uso')
        .' e a '.($privacy ? '<a class="underline" target="_blank" href="'.e(route('pages.show', 'privacidade')).'">política de privacidade</a>' : 'política de privacidade').'.';
@endphp
<x-layouts.auth title="Criar conta" subtitle="Leva menos de um minuto. Pedimos só o necessário.">
    <form method="POST" action="{{ route('register.store') }}" class="space-y-5" data-loading>
        @csrf
        <x-form.input name="name" label="Nome" autocomplete="name" required autofocus />
        <x-form.input name="email" type="email" label="E-mail" autocomplete="email" required />
        <x-form.input name="phone" type="tel" label="Celular (WhatsApp)" autocomplete="tel" inputmode="tel" placeholder="(21) 90000-0000" required hint="Usado para confirmações e avisos do seu horário." />
        <x-form.input name="cpf" label="CPF" inputmode="numeric" placeholder="000.000.000-00" maxlength="14" required hint="Usamos para identificar sua conta. Uma conta por CPF." />
        <x-form.input name="password" type="password" label="Senha" autocomplete="new-password" required hint="Mínimo de 8 caracteres, com letras e números." />
        <x-form.input name="password_confirmation" type="password" label="Confirme a senha" autocomplete="new-password" required />
        <x-form.checkbox name="accept_terms" :label="$termsLabel" />
        <x-form.checkbox name="marketing_consent" label="Quero receber novidades e promoções (opcional)" />
        <button type="submit" class="btn-dark w-full" data-loading-text="Criando conta…">Criar conta</button>
    </form>
    <p class="mt-6 text-center text-sm text-graphite/70">Já tem conta? <a href="{{ route('login') }}" class="font-semibold text-ink underline">Entrar</a></p>
</x-layouts.auth>
