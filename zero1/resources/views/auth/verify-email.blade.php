<x-layouts.auth title="Confirme seu e-mail" subtitle="Enviamos um link de confirmação. Ele é necessário para assinar planos e receber avisos dos seus horários.">
    <form method="POST" action="{{ route('verification.send') }}" data-loading>
        @csrf
        <button type="submit" class="btn-dark w-full">Reenviar link</button>
    </form>
</x-layouts.auth>
