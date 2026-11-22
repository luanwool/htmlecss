@php $m = fn ($c) => \App\Support\Money::format($c); @endphp
<x-layouts.admin title="Financeiro">
    <x-slot:actions>
        @can('reports.export')<a href="{{ route('admin.reports.export', ['dataset' => 'financeiro', 'periodo' => 'mes']) }}" class="btn-ghost-dark min-h-10 px-4 text-xs">Exportar CSV (mês)</a>@endcan
    </x-slot:actions>
    <x-period-tabs :period="$period->key" />
    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="card overflow-x-auto p-0 xl:col-span-2">
            <div class="flex items-center justify-between gap-3 p-5 pb-0">
                <h2 class="font-semibold">Lançamentos</h2>
                <div class="flex gap-1 text-xs">
                    @foreach (['' => 'Todos', 'income' => 'Entradas', 'expense' => 'Despesas', 'refund' => 'Estornos'] as $k => $l)
                        <a href="{{ request()->fullUrlWithQuery(['tipo' => $k ?: null]) }}" @class(['rounded-full px-3 py-1', 'bg-ink text-paper' => request('tipo', '') === $k, 'border border-black/10' => request('tipo', '') !== $k])>{{ $l }}</a>
                    @endforeach
                </div>
            </div>
            <table class="table-admin mt-3">
                <thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th class="text-right">Valor</th><th>Taxa</th><th>Situação</th><th></th></tr></thead>
                <tbody>
                    @forelse ($entries as $e)
                        <tr>
                            <td class="whitespace-nowrap">{{ \App\Support\BusinessTime::format($e->occurred_at, 'd/m H:i') }}</td>
                            <td>{{ $e->description }}</td>
                            <td><span class="badge-gray">{{ \App\Models\FinancialEntry::EXPENSE_CATEGORIES[$e->category] ?? $e->category }}</span></td>
                            <td class="text-right font-semibold whitespace-nowrap {{ $e->type === 'income' ? 'text-emerald-700' : 'text-red-700' }}">{{ $e->type === 'income' ? '+' : '−' }} {{ $m($e->amount_cents) }}</td>
                            <td class="text-xs text-stone">{{ $e->fee_cents ? $m($e->fee_cents) : '' }}</td>
                            <td><span class="{{ $e->status === 'paid' ? 'badge-green' : 'badge-amber' }}">{{ $e->status === 'paid' ? 'Efetivado' : 'A receber' }}</span></td>
                            <td>@if (! $e->source_type)<form method="POST" action="{{ route('admin.finance.entries.destroy', $e) }}" data-confirm="Excluir lançamento manual?">@csrf @method('DELETE')<button class="text-xs text-red-700 underline">Excluir</button></form>@else<span class="text-xs text-stone">automático</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-stone">Sem lançamentos no período.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-4">{{ $entries->links() }}</div>
        </section>
        <div class="space-y-6">
            <form method="POST" action="{{ route('admin.finance.expenses.store') }}" class="card space-y-3" data-loading>
                @csrf
                <h2 class="font-semibold">Novo lançamento manual</h2>
                <x-form.select name="kind" label="Tipo" :options="['expense' => 'Despesa', 'income' => 'Outra entrada']" />
                <div data-show-when="kind=expense"><x-form.select name="category" label="Categoria" :options="$categories" /></div>
                <x-form.input name="amount" label="Valor (R$)" inputmode="decimal" required />
                <x-form.input name="date" type="date" label="Data" :value="now()->toDateString()" required />
                <x-form.select name="payment_method" label="Forma" :options="$paymentMethods" placeholder="—" />
                <x-form.input name="description" label="Descrição" required />
                <button class="btn-dark w-full min-h-10">Lançar</button>
            </form>
            <section class="card">
                <h2 class="font-semibold">Comissões a pagar</h2>
                <ul class="mt-3 divide-y divide-black/[.06] text-sm">
                    @forelse ($commissions as $c)
                        <li class="flex items-center justify-between gap-2 py-2">
                            <span>{{ $c['barber']?->display_name }} <span class="text-xs text-stone">({{ $c['n'] }})</span></span>
                            <span class="flex items-center gap-2 font-semibold">{{ $m($c['total']) }}
                                <form method="POST" action="{{ route('admin.finance.payout', $c['barber']) }}" data-confirm="Marcar comissões como pagas e lançar despesa?">@csrf<button class="badge-green">Pagar</button></form></span>
                        </li>
                    @empty
                        <li class="py-2 text-stone">Nada pendente.</li>
                    @endforelse
                </ul>
            </section>
            <section class="card">
                <h2 class="font-semibold">Regras de comissão</h2>
                <p class="mt-1 text-xs text-stone">A regra mais específica vence (profissional + serviço → profissional → serviço → geral). Avulso: % do valor final. Plano: valor fixo por atendimento.</p>
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach ($rules as $r)
                        <li class="flex justify-between gap-2"><span>{{ $r->barber?->display_name ?? 'Todos' }} · {{ $r->service?->name ?? 'todos os serviços' }}: {{ rtrim(rtrim(number_format($r->percent, 2, ',', ''), '0'), ',') }}% / plano {{ $m($r->plan_visit_cents) }}</span>
                            <form method="POST" action="{{ route('admin.finance.rules.destroy', $r) }}">@csrf @method('DELETE')<button class="text-xs text-red-700 underline">x</button></form></li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('admin.finance.rules.store') }}" class="mt-4 grid gap-2 sm:grid-cols-2">
                    @csrf
                    <x-form.select name="barber_id" label="Profissional" :options="$barbers->pluck('display_name', 'id')->all()" placeholder="Todos" />
                    <x-form.select name="service_id" label="Serviço" :options="$services->pluck('name', 'id')->all()" placeholder="Todos" />
                    <x-form.input name="percent" type="number" step="0.01" min="0" max="100" label="% avulso" required />
                    <x-form.input name="plan_visit" label="R$ por atend. de plano" inputmode="decimal" />
                    <button class="btn-dark min-h-10 sm:col-span-2">Salvar regra</button>
                </form>
            </section>
        </div>
    </div>
</x-layouts.admin>
