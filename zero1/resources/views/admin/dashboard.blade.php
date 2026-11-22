<x-layouts.admin title="Visão geral">
    @if ($demoData)
        <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
            Há dados de demonstração ([DEMO]) no banco. Eles nunca devem ir para produção — rode a implantação sem o DemoSeeder.
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ($stats as $label => $value)
            <div class="card">
                <p class="text-xs font-medium uppercase tracking-wider text-stone">{{ $label }}</p>
                <p class="mt-2 font-display text-4xl">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="card lg:col-span-2" aria-labelledby="today-title">
            <div class="flex items-center justify-between gap-3">
                <h2 id="today-title" class="font-semibold">Hoje · {{ $todayAppointments->count() }} atendimento(s)</h2>
                <span class="flex gap-2">
                    @can('agenda.manage')<a href="{{ route('admin.agenda.create') }}" class="btn-dark min-h-9 px-4 text-xs">Agendar</a>
                    <a href="{{ route('admin.visits.create') }}" class="btn-ghost-dark min-h-9 px-4 text-xs">Balcão</a>@endcan
                </span>
            </div>
            <ul class="mt-3 divide-y divide-black/[.06] text-sm">
                @forelse ($todayAppointments->take(12) as $a)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <a href="{{ route('admin.agenda.show', $a) }}" class="min-w-0"><strong class="tabular-nums">{{ $a->localStart()->format('H:i') }}</strong> — {{ $a->barber->display_name }} | {{ $a->customer->name }} | {{ $a->service_name_snapshot }} | {{ $a->duration_minutes_snapshot }} min</a>
                        <span class="flex shrink-0 gap-1"><span class="{{ $a->isPlan() ? 'badge-ink' : 'badge-gray' }}">{{ $a->isPlan() ? 'Assinatura' : 'Avulso' }}</span><span class="{{ $a->status->badge() }}">{{ $a->status->label() }}</span></span>
                    </li>
                @empty
                    <li class="py-3 text-stone">Nenhum atendimento hoje.</li>
                @endforelse
            </ul>
            @if ($todayAppointments->count() > 12)<a href="{{ route('admin.agenda.index') }}" class="mt-2 inline-block text-sm underline">Ver agenda completa</a>@endif
        </section>
        @if ($metrics)
            <section class="card" aria-labelledby="month-title">
                <h2 id="month-title" class="font-semibold">Este mês</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    @foreach (['Recebido' => \App\Support\Money::format($metrics['income_paid']), 'Líquido estimado' => \App\Support\Money::format($metrics['net_estimated']), 'MRR' => \App\Support\Money::format($metrics['mrr']), 'Assinaturas ativas' => $metrics['subs_active'], 'Inadimplentes' => $metrics['subs_past_due'], 'Atendimentos' => $metrics['visits_total'].' ('.$metrics['visits_plan'].' plano)', 'Faltas' => $metrics['no_show']] as $l => $v)
                        <div class="flex justify-between"><dt class="text-stone">{{ $l }}</dt><dd class="font-semibold">{{ $v }}</dd></div>
                    @endforeach
                </dl>
                <a href="{{ route('admin.reports.index') }}" class="mt-4 inline-block text-sm underline">Relatórios completos</a>
            </section>
        @endif
        <section class="card lg:col-span-2" aria-labelledby="pending-title">
            <h2 id="pending-title" class="font-semibold">Informações reais pendentes</h2>
            <p class="mt-1 text-sm text-stone">O site só exibe o que for cadastrado aqui. Nada é inventado.</p>
            @if ($pending)
                <ul class="mt-4 divide-y divide-black/[.06]">
                    @foreach ($pending as [$label, $url])
                        <li class="flex items-center justify-between gap-4 py-3 text-sm">
                            <span class="flex items-center gap-3"><span class="size-2 shrink-0 rounded-full bg-amber-500"></span>{{ $label }}</span>
                            @if ($url)<a href="{{ $url }}" class="shrink-0 font-semibold underline">Resolver</a>@endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-4 text-sm text-emerald-800">Tudo preenchido.</p>
            @endif
        </section>
        <section class="card" aria-labelledby="roadmap-title">
            <h2 id="roadmap-title" class="font-semibold">Implantação por fases</h2>
            <ol class="mt-4 space-y-3 text-sm">
                <li class="flex gap-3"><span class="badge-green">F1</span> Site, CMS, acessos, catálogo</li>
                <li class="flex gap-3"><span class="badge-green">F2</span> Agenda e disponibilidade</li>
                <li class="flex gap-3"><span class="badge-amber">F3</span> Clube (pagamento simulado)</li>
                <li class="flex gap-3"><span class="badge-green">F4</span> Atendimentos, financeiro, relatórios</li>
                <li class="flex gap-3"><span class="badge-gray">Próx.</span> Mercado Pago real + deploy</li>
            </ol>
            @if ($openDataRequests)
                <a href="{{ route('admin.data-requests.index') }}" class="mt-5 block rounded-xl bg-red-50 p-3 text-sm text-red-900">{{ $openDataRequests }} solicitação(ões) LGPD em aberto</a>
            @endif
        </section>
    </div>
</x-layouts.admin>
