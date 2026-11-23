<x-layouts.barber :title="$barber?->display_name ?? auth()->user()->name">
    @unless ($barber)
        <p class="rounded-xl bg-amber-100 p-4 text-sm text-amber-950">Seu usuário ainda não está vinculado a um perfil de profissional. Peça ao gestor para vincular em Profissionais.</p>
    @else
        <div class="grid grid-cols-3 gap-3">
            <div class="card p-4"><p class="text-xs text-stone">Hoje</p><p class="font-display text-3xl">{{ $todayVisits->count() }}</p><p class="text-xs text-stone">atendimentos</p></div>
            <div class="card p-4"><p class="text-xs text-stone">Avulsos</p><p class="whitespace-nowrap font-display text-xl sm:text-2xl">{{ \App\Support\Money::format($todayVisits->where('mode', 'avulso')->sum('final_cents')) }}</p></div>
            <div class="card p-4"><p class="text-xs text-stone">Comissão</p><p class="whitespace-nowrap font-display text-xl sm:text-2xl">{{ $showCommission ? \App\Support\Money::format($todayVisits->sum(fn ($v) => $v->commission?->amount_cents ?? 0)) : '—' }}</p></div>
        </div>
        <x-period-tabs :period="$period->key" class="mt-6" />
        <div class="mt-4">
            <x-agenda-list :by-day="$byDay" :show-barber="false" actions-view="barber.partials.actions" />
        </div>
        <div class="mt-6 hidden sm:block"><a href="{{ route('barber.visits.create') }}" class="btn-accent">+ Registrar atendimento</a></div>
    @endunless
</x-layouts.barber>
