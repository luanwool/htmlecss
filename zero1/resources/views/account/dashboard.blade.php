<x-layouts.account :title="'Olá, '.\Illuminate\Support\Str::of($user->name)->before(' ')">
    @unless ($user->hasVerifiedEmail())
        <div class="mb-6 flex flex-col gap-3 rounded-2xl bg-amber-100 p-5 text-sm text-amber-950 sm:flex-row sm:items-center sm:justify-between">
            <p>Confirme seu e-mail para assinar planos e receber avisos dos seus horários.</p>
            <form method="POST" action="{{ route('verification.send') }}">@csrf<button class="btn-dark min-h-10 px-4 text-xs">Reenviar confirmação</button></form>
        </div>
    @endunless
    <div class="grid gap-5 md:grid-cols-3">
        <div class="card md:col-span-2">
            <p class="eyebrow text-graphite/60">Próximos horários</p>
            <p class="mt-4 font-display text-2xl">Nenhum horário agendado.</p>
            <p class="mt-2 text-sm text-graphite/70">O agendamento online com escolha de barbeiro e horário será liberado em breve.</p>
            <a href="{{ route('booking.start') }}" class="btn-dark mt-6">Agendar horário</a>
        </div>
        <div class="card">
            <p class="eyebrow text-graphite/60">Clube Zero 1</p>
            <p class="mt-4 font-display text-2xl">Sem assinatura</p>
            <p class="mt-2 text-sm text-graphite/70">Conheça os planos disponíveis.</p>
            <a href="{{ route('home') }}#clube" class="btn-ghost-dark mt-6">Ver planos</a>
        </div>
    </div>
</x-layouts.account>
