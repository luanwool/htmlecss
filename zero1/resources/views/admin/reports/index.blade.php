@php $f = fn ($c) => \App\Support\Money::format((int) $c); @endphp
<x-layouts.admin title="Relatórios">
    <x-slot:actions><a href="{{ route('admin.reports.plans') }}" class="btn-ghost-dark min-h-10 px-4 text-xs">Uso dos planos</a></x-slot:actions>
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <x-period-tabs :period="$key" :options="\App\Http\Controllers\Admin\ReportController::RANGES" />
        <form method="GET" class="flex gap-2">
            <input type="hidden" name="periodo" value="custom">
            <input type="date" name="de" value="{{ $from->toDateString() }}" class="form-input min-h-10" aria-label="De">
            <input type="date" name="ate" value="{{ $to->toDateString() }}" class="form-input min-h-10" aria-label="Até">
            <button class="btn-ghost-dark min-h-10 px-4">Aplicar</button>
        </form>
    </div>
    <p class="mt-2 text-sm text-stone">{{ $from->format('d/m/Y') }} a {{ $to->format('d/m/Y') }}</p>

    <h2 class="mt-6 font-semibold">Dinheiro</h2>
    <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach (['Entradas recebidas' => $m['income_paid'], 'Líquido estimado' => $m['net_estimated'], 'A receber' => $m['receivable'], 'Taxas de pagamento' => $m['fees'], 'Mensalidades' => $m['subscription_income'], 'Avulsos' => $m['walk_in_income'], 'Estornos' => $m['refunds'], 'Despesas' => $m['expenses']] as $l => $v)
            <div class="card p-4"><p class="text-xs uppercase tracking-wider text-stone">{{ $l }}</p><p class="mt-1 font-display text-2xl">{{ $f($v) }}</p></div>
        @endforeach
    </div>
    <p class="mt-2 text-xs text-stone">Líquido estimado = entradas − taxas − estornos − despesas. Cortes cobertos por plano não entram como receita nova (a receita é a mensalidade).</p>

    <h2 class="mt-8 font-semibold">Clube</h2>
    <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-6">
        @foreach (['MRR' => $f($m['mrr']), 'Ativas' => $m['subs_active'], 'Inadimplentes' => $m['subs_past_due'], 'Novas no período' => $m['subs_new_period'], 'Cancelamentos' => $m['subs_cancelled_period'], 'Churn' => $m['churn_rate'].'%'] as $l => $v)
            <div class="card p-4"><p class="text-xs uppercase tracking-wider text-stone">{{ $l }}</p><p class="mt-1 font-display text-2xl">{{ $v }}</p></div>
        @endforeach
    </div>
    @if ($m['revenue_by_plan']->isNotEmpty())
        <ul class="card mt-3 text-sm">@foreach ($m['revenue_by_plan'] as $r)<li class="flex justify-between py-1"><span>{{ $r->plan }} ({{ $r->n }} pagamento(s))</span><strong>{{ $f($r->total) }}</strong></li>@endforeach</ul>
    @endif

    <h2 class="mt-8 font-semibold">Agenda e produção</h2>
    <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-6">
        @foreach (['Agendamentos' => $m['appointments_total'], 'Concluídos' => $m['completed'], 'Faltas' => $m['no_show'], 'Cancelados' => $m['cancelled'], 'Ocupação' => $m['occupancy'] !== null ? $m['occupancy'].'%' : '—', 'Ticket médio avulso' => $f($m['avg_ticket_avulso'])] as $l => $v)
            <div class="card p-4"><p class="text-xs uppercase tracking-wider text-stone">{{ $l }}</p><p class="mt-1 font-display text-2xl">{{ $v }}</p></div>
        @endforeach
    </div>
    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <section class="card">
            <h3 class="font-semibold">Por profissional</h3>
            <table class="table-admin mt-2"><thead><tr><th>Nome</th><th>Cortes</th><th>Plano</th><th>Avulso R$</th></tr></thead><tbody>
                @forelse ($m['by_barber'] as $r)<tr><td>{{ $r->name }}</td><td>{{ $r->n }}</td><td>{{ $r->plan }}</td><td>{{ $f($r->revenue) }}</td></tr>@empty<tr><td colspan="4" class="text-stone">—</td></tr>@endforelse
            </tbody></table>
            <p class="mt-2 text-xs text-stone">Comissão a pagar no período: <strong>{{ $f($m['commission_due']) }}</strong></p>
        </section>
        <section class="card">
            <h3 class="font-semibold">Por serviço</h3>
            <table class="table-admin mt-2"><thead><tr><th>Serviço</th><th>Total</th><th>Plano</th></tr></thead><tbody>
                @forelse ($m['by_service'] as $r)<tr><td>{{ $r->name }}</td><td>{{ $r->n }}</td><td>{{ $r->plan }}</td></tr>@empty<tr><td colspan="3" class="text-stone">—</td></tr>@endforelse
            </tbody></table>
        </section>
        <section class="card">
            <h3 class="font-semibold">Horários mais procurados</h3>
            <ul class="mt-2 space-y-1 text-sm">@forelse ($m['popular_hours'] as $h => $n)<li class="flex justify-between"><span>{{ $h }}</span><strong>{{ $n }}</strong></li>@empty<li class="text-stone">—</li>@endforelse</ul>
        </section>
    </div>

    @can('reports.export')
        <h2 class="mt-8 font-semibold">Exportar (CSV)</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach (['atendimentos' => 'Atendimentos', 'agendamentos' => 'Agendamentos', 'financeiro' => 'Financeiro', 'assinaturas' => 'Assinaturas'] as $ds => $l)
                <a href="{{ route('admin.reports.export', ['dataset' => $ds] + request()->only('periodo', 'de', 'ate')) }}" class="btn-ghost-dark min-h-10 px-4 text-xs">{{ $l }}</a>
            @endforeach
        </div>
    @endcan
</x-layouts.admin>
