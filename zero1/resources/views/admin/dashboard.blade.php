@php
    $f = fn ($c) => \App\Support\Money::format((int) $c);
    $m = $metrics; // alias local
    $sum = fn (array $arr) => array_sum($arr);
    // Mini-gráfico de barras em SVG (sem biblioteca externa): cabe em qualquer card
    $spark = function (array $values, string $classes = '') {
        $w = 220; $h = 56; $n = max(1, count($values)); $max = max(1, ...$values);
        $bw = $w / $n; $out = '';
        foreach ($values as $i => $v) {
            $bh = $v > 0 ? max(2, ($v / $max) * ($h - 6)) : 2;
            $x = $i * $bw + 1; $y = $h - $bh;
            $cls = $v === 0 ? 'spark-bar muted' : $classes;
            $out .= "<rect class=\"{$cls}\" x=\"{$x}\" y=\"{$y}\" width=\"".($bw - 2)."\" height=\"{$bh}\" rx=\"2\"/>";
        }
        return "<svg viewBox=\"0 0 {$w} {$h}\" preserveAspectRatio=\"none\" class=\"mt-3 h-14 w-full\" aria-hidden=\"true\">{$out}</svg>";
    };
@endphp
<x-layouts.admin title="Visão geral">
    @if ($demoData)
        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-amber-400/30 bg-amber-400/10 p-4 text-sm text-amber-100">
            <x-icon name="shield" class="mt-0.5 size-4 shrink-0 text-amber-300" />
            <p>Há dados <strong>[DEMO]</strong> no banco. Nunca devem ir para produção — reimplante sem o <code class="font-mono text-xs">--demo</code>.</p>
        </div>
    @endif

    {{-- Linha de KPIs (números essenciais + mini-gráficos) --}}
    <section class="grid grid-cols-2 gap-4 lg:grid-cols-4" aria-label="Indicadores do mês">
        @if ($m)
            <div class="card">
                <p class="hud text-stone">Recebido no mês</p>
                <p class="kpi-value mt-2 text-3xl text-paper sm:text-4xl">{{ $f($m['income_paid']) }}</p>
                <p class="mt-1 text-xs text-smoke">Líquido estimado {{ $f($m['net_estimated']) }}</p>
                {!! $spark($m['series']['income'], 'spark-bar') !!}
            </div>
            <div class="card">
                <p class="hud text-stone">Assinaturas ativas</p>
                <p class="kpi-value mt-2 text-3xl text-paper sm:text-4xl">{{ $m['subs_active'] }}</p>
                <p class="mt-1 text-xs text-smoke">MRR {{ $f($m['mrr']) }} · {{ $m['subs_past_due'] }} em atraso</p>
                <p class="mt-6 flex gap-2 text-xs">
                    <span class="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-2 py-0.5 text-emerald-300">+{{ $m['subs_new_period'] }} novas</span>
                    <span class="rounded-full border border-white/10 bg-white/5 px-2 py-0.5 text-smoke">−{{ $m['subs_cancelled_period'] }} canceladas</span>
                </p>
            </div>
            <div class="card">
                <p class="hud text-stone">Atendimentos</p>
                <p class="kpi-value mt-2 text-3xl text-paper sm:text-4xl">{{ $m['visits_total'] }}</p>
                <p class="mt-1 text-xs text-smoke">{{ $m['visits_plan'] }} de plano · ticket médio {{ $f($m['avg_ticket_avulso']) }}</p>
                {!! $spark($m['series']['visits'], 'spark-bar alt') !!}
            </div>
            <div class="card">
                <p class="hud text-stone">Faltas (14 dias)</p>
                <p class="kpi-value mt-2 text-3xl text-paper sm:text-4xl">{{ $sum($m['series']['noShow']) }}</p>
                <p class="mt-1 text-xs text-smoke">Ocupação {{ $m['occupancy'] !== null ? $m['occupancy'].'%' : '—' }}</p>
                {!! $spark($m['series']['noShow'], 'spark-bar danger') !!}
            </div>
        @else
            @foreach ($stats as $label => $value)
                <div class="card"><p class="hud text-stone">{{ $label }}</p><p class="kpi-value mt-2 text-3xl text-paper sm:text-4xl">{{ $value }}</p></div>
            @endforeach
        @endif
    </section>

    {{-- Agenda de hoje + ações rápidas --}}
    <section class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="hud text-stone">Hoje</p>
                    <h2 class="kpi-value mt-1 text-2xl text-paper">{{ $todayAppointments->count() }} atendimento(s)</h2>
                </div>
                <span class="flex flex-wrap gap-2">
                    @can('agenda.manage')
                        <a href="{{ route('admin.agenda.create') }}" class="btn-dark min-h-10 px-4 text-xs">Agendar</a>
                        <a href="{{ route('admin.visits.create') }}" class="btn-ghost-dark min-h-10 px-4 text-xs">Balcão</a>
                    @endcan
                    <a href="{{ route('admin.agenda.index') }}" class="btn-ghost-dark min-h-10 px-4 text-xs">Ver agenda</a>
                </span>
            </div>
            @if ($todayAppointments->isEmpty())
                <div class="mt-5 flex flex-col items-center justify-center rounded-xl border border-dashed border-white/10 bg-white/[.02] px-4 py-10 text-center text-sm text-smoke">
                    <p>Nenhum atendimento programado para hoje.</p>
                    @can('agenda.manage')<a href="{{ route('admin.agenda.create') }}" class="btn-dark mt-4 min-h-9 px-4 text-xs">Criar agendamento</a>@endcan
                </div>
            @else
                <ol class="mt-5 space-y-2" role="list">
                    @foreach ($todayAppointments->take(14) as $a)
                        @php $ps = $a->localStart()->format('H:i'); @endphp
                        <li>
                            <a href="{{ route('admin.agenda.show', $a) }}" class="group grid grid-cols-[3.5rem_1fr_auto] items-center gap-3 rounded-xl border border-white/5 bg-white/[.02] px-3 py-2.5 transition hover:border-white/15 hover:bg-white/[.04]">
                                <span class="kpi-value text-lg text-paper">{{ $ps }}</span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm text-paper">{{ $a->customer->name }} · {{ $a->service_name_snapshot }}</span>
                                    <span class="hud mt-0.5 flex items-center gap-2 text-stone"><span class="size-1.5 rounded-full bg-accent"></span>{{ $a->barber->display_name }} · {{ $a->duration_minutes_snapshot }} min</span>
                                </span>
                                <span class="flex gap-1">
                                    <span class="{{ $a->isPlan() ? 'badge-ink' : 'badge-gray' }}">{{ $a->isPlan() ? 'Plano' : 'Avulso' }}</span>
                                    <span class="{{ $a->status->badge() }}">{{ $a->status->label() }}</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ol>
                @if ($todayAppointments->count() > 14)
                    <a href="{{ route('admin.agenda.index') }}" class="mt-3 inline-flex items-center gap-2 text-sm text-smoke hover:text-paper">+{{ $todayAppointments->count() - 14 }} atendimentos <x-icon name="arrow-right" class="size-4" /></a>
                @endif
            @endif
        </div>

        <div class="space-y-6">
            {{-- Status do gateway de pagamento --}}
            <div class="card">
                <p class="hud text-stone">Pagamentos</p>
                @php $simulated = config('zero1.payments.driver') === 'simulated'; @endphp
                <p class="kpi-value mt-2 text-2xl text-paper">{{ $simulated ? 'Modo simulado' : 'Mercado Pago' }}</p>
                <p class="mt-1 text-sm {{ $simulated ? 'text-amber-200' : 'text-emerald-300' }}">
                    <span class="live-dot inline-block {{ $simulated ? 'bg-amber-400' : '' }}" aria-hidden="true" style="{{ $simulated ? 'background:#fbbf24' : '' }}"></span>
                    {{ $simulated ? 'Nenhuma cobrança real acontece neste ambiente.' : 'Conectado — cobranças reais ativas.' }}
                </p>
                @can('integrations.manage')
                    <a href="{{ route('admin.settings.edit') }}#pagamentos" class="btn-ghost-dark mt-4 min-h-9 px-4 text-xs">{{ $simulated ? 'Conectar Mercado Pago' : 'Ver integração' }}</a>
                @endcan
            </div>
            {{-- Pendências reais --}}
            <div class="card">
                <div class="flex items-center justify-between gap-3">
                    <p class="hud text-stone">Pendências reais</p>
                    @if ($openDataRequests)<a href="{{ route('admin.data-requests.index') }}" class="badge-red">{{ $openDataRequests }} LGPD</a>@endif
                </div>
                @if ($pending)
                    <ul class="mt-4 space-y-3 text-sm">
                        @foreach ($pending as [$label, $url])
                            <li class="flex items-start justify-between gap-3">
                                <span class="flex gap-3"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-amber-400"></span>{{ $label }}</span>
                                @if ($url)<a href="{{ $url }}" class="shrink-0 text-xs font-semibold text-accent hover:underline">Resolver →</a>@endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-4 flex items-center gap-2 text-sm text-emerald-300"><x-icon name="check" class="size-4" /> Tudo em dia.</p>
                @endif
            </div>
        </div>
    </section>
</x-layouts.admin>
