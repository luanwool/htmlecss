<x-layouts.account :title="'Olá, '.\Illuminate\Support\Str::of($user->name)->before(' ')">
    @unless ($user->hasVerifiedEmail())
        <div class="mb-6 flex flex-col gap-3 rounded-2xl bg-amber-100 p-5 text-sm text-amber-950 sm:flex-row sm:items-center sm:justify-between">
            <p>Confirme seu e-mail para assinar planos e receber avisos dos seus horários.</p>
            <form method="POST" action="{{ route('verification.send') }}">@csrf<button class="btn-dark min-h-10 px-4 text-xs">Reenviar confirmação</button></form>
        </div>
    @endunless
    <div class="grid gap-5 md:grid-cols-3">
        <div class="card md:col-span-2">
            <div class="flex items-center justify-between">
                <p class="eyebrow text-graphite/60">Próximos horários</p>
                <a href="{{ route('booking.start') }}" class="btn-dark min-h-10 px-4 text-xs">Agendar</a>
            </div>
            @forelse ($upcoming as $a)
                <a href="{{ route('account.appointments.show', $a->uid) }}" class="mt-4 flex items-center justify-between gap-4 rounded-2xl border border-black/10 p-4 hover:border-ink">
                    <span>
                        <span class="block font-display text-2xl">{{ ucfirst($a->localStart()->locale('pt_BR')->translatedFormat('D, d/m')) }} · {{ $a->localStart()->format('H:i') }}</span>
                        <span class="text-sm text-graphite/70">{{ $a->service_name_snapshot }} com {{ $a->barber->display_name }}</span>
                    </span>
                    <span class="flex flex-col items-end gap-1"><span class="{{ $a->isPlan() ? 'badge-ink' : 'badge-gray' }}">{{ $a->isPlan() ? 'Plano' : 'Avulso' }}</span>
                        @if ($a->plan_payment_issue)<span class="badge-red">Regularizar plano</span>@endif</span>
                </a>
            @empty
                <p class="mt-4 font-display text-2xl">Nenhum horário agendado.</p>
            @endforelse
        </div>
        <div class="card">
            <p class="eyebrow text-graphite/60">Clube Zero 1</p>
            @if ($sub)
                <p class="mt-3 font-display text-2xl">{{ $sub->term('name') }}</p>
                <span class="{{ $sub->status->badge() }} mt-2">{{ $sub->status->label() }}</span>
                @if ($usage)
                    <p class="mt-4 text-sm">Este mês (até {{ $cycle->lastDay()->format('d/m') }}):</p>
                    <p class="font-display text-3xl">{{ $usage['available'] ?? '∞' }} <span class="text-sm font-sans text-graphite/60">disponível(is)</span></p>
                    <p class="text-xs text-graphite/60">{{ $usage['consumed'] }} concluído(s) · {{ $usage['reserved'] }} reservado(s)</p>
                @endif
                <a href="{{ route('account.subscription') }}" class="btn-ghost-dark mt-5">Detalhes</a>
            @else
                <p class="mt-3 font-display text-2xl">Sem assinatura</p>
                <a href="{{ route('home') }}#clube" class="btn-ghost-dark mt-5">Ver planos</a>
            @endif
        </div>
    </div>
</x-layouts.account>
