@php $f = fn ($c) => \App\Support\Money::format((int) $c); @endphp
<x-layouts.admin title="Programa de indicações">
    <x-slot:actions><a href="{{ route('admin.settings.edit') }}#indicacoes" class="btn-ghost-dark min-h-10 px-4 text-xs">Configurar</a></x-slot:actions>

    <div class="mb-6 flex items-center gap-3 rounded-xl border border-white/10 bg-white/[.03] p-3 text-sm">
        <span class="size-2.5 rounded-full {{ $cfg['enabled'] ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>
        <span class="flex-1">
            {{ $cfg['enabled'] ? 'Programa ativo' : 'Programa desligado' }} ·
            indicado: <strong>{{ $cfg['referred_percent'] }}%</strong> ·
            indicador: <strong>{{ $cfg['referrer_percent'] }}%</strong>
            @if ($cfg['credit_valid_days']) · validade {{ $cfg['credit_valid_days'] }} dias @endif
        </span>
    </div>

    <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            'Códigos gerados' => $stats['codes'],
            'Cadastros por indicação' => $stats['invited'] + $stats['rewarded'],
            'Recompensados' => $stats['rewarded'],
            'Saldo ativo' => $f($stats['active_balance']),
        ] as $label => $value)
            <div class="card"><p class="hud text-stone">{{ $label }}</p><p class="kpi-value mt-2 text-3xl text-paper">{{ $value }}</p></div>
        @endforeach
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="card xl:col-span-2 overflow-x-auto p-0">
            <h2 class="p-5 pb-0 font-semibold">Histórico</h2>
            <table class="table-admin mt-3">
                <thead><tr><th>Quando</th><th>Indicador</th><th>Indicado</th><th>Código</th><th>Situação</th></tr></thead>
                <tbody>
                    @forelse ($referrals as $r)
                        <tr>
                            <td class="whitespace-nowrap">{{ \App\Support\BusinessTime::format($r->created_at, 'd/m/Y') }}</td>
                            <td>{{ $r->referrer?->name ?? '—' }}</td>
                            <td>{{ $r->referred?->name ?? '—' }}</td>
                            <td><code class="font-mono text-xs">{{ $r->code }}</code></td>
                            <td><span class="{{ $r->status->badge() }}">{{ $r->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-stone">Ninguém foi indicado ainda.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-4">{{ $referrals->links() }}</div>
        </section>
        <section class="card">
            <h2 class="font-semibold">Top indicadores</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($top as $t)
                    <li class="flex justify-between gap-3">
                        <span>{{ $t->customer?->name ?? '—' }}</span>
                        <span class="font-mono text-stone">{{ $t->signups_count }} cad. · {{ $t->visits_count }} cliques</span>
                    </li>
                @empty
                    <li class="text-stone">Sem dados ainda.</li>
                @endforelse
            </ul>
            <p class="mt-5 text-xs text-stone">
                Créditos totais concedidos: <strong>{{ $f($stats['credits_granted']) }}</strong><br>
                Já utilizados: <strong>{{ $f($stats['credits_used']) }}</strong>
            </p>
        </section>
    </div>
</x-layouts.admin>
