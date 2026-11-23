<x-layouts.barber title="Minha produção">
    <x-period-tabs :period="$period->key" />
    <div class="mt-4 grid grid-cols-3 gap-3">
        <div class="card p-4"><p class="text-xs text-stone">Atendimentos</p><p class="font-display text-3xl">{{ $visits->count() }}</p><p class="text-xs text-stone">{{ $visits->where('mode', 'plan')->count() }} de plano</p></div>
        <div class="card p-4"><p class="text-xs text-stone">Avulsos</p><p class="whitespace-nowrap font-display text-xl sm:text-2xl">{{ \App\Support\Money::format($visits->where('mode', 'avulso')->sum('final_cents')) }}</p></div>
        <div class="card p-4"><p class="text-xs text-stone">Comissão</p><p class="whitespace-nowrap font-display text-xl sm:text-2xl">{{ \App\Support\Money::format($visits->sum(fn ($v) => $v->commission?->amount_cents ?? 0)) }}</p></div>
    </div>
    <ul class="mt-6 space-y-2">
        @forelse ($visits as $v)
            <li class="card flex items-center justify-between gap-3 p-4 text-sm">
                <span><span class="font-semibold">{{ \App\Support\BusinessTime::format($v->performed_at, 'd/m H:i') }}</span> · {{ $v->customer->name }}<span class="block text-stone">{{ $v->service_name_snapshot }}</span></span>
                <span class="text-right"><span class="{{ $v->mode === 'plan' ? 'badge-ink' : 'badge-gray' }}">{{ $v->mode === 'plan' ? 'Plano' : \App\Support\Money::format($v->final_cents) }}</span>
                    @if ($v->payment_status === 'pending')<span class="badge-amber mt-1">Pendente</span>@endif</span>
            </li>
        @empty
            <li class="card text-center text-sm text-stone">Nenhum atendimento no período.</li>
        @endforelse
    </ul>
</x-layouts.barber>
