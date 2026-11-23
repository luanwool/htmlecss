<x-layouts.admin title="Atendimentos">
    <x-slot:actions><a href="{{ route('admin.visits.create') }}" class="btn-dark min-h-10 px-4 text-xs">Registrar atendimento</a></x-slot:actions>
    <x-period-tabs :period="$period->key" />
    <form method="GET" class="mt-4 flex flex-wrap gap-2">
        <input type="hidden" name="periodo" value="{{ $period->key }}">
        <select name="profissional" class="form-input max-w-[14rem]" aria-label="Profissional"><option value="">Todos os profissionais</option>@foreach ($barbers as $b)<option value="{{ $b->id }}" @selected(request('profissional') == $b->id)>{{ $b->display_name }}</option>@endforeach</select>
        <select name="modalidade" class="form-input max-w-[12rem]" aria-label="Modalidade"><option value="">Plano e avulso</option><option value="plan" @selected(request('modalidade') === 'plan')>Plano</option><option value="avulso" @selected(request('modalidade') === 'avulso')>Avulso</option></select>
        <button class="btn-ghost-dark min-h-11">Filtrar</button>
    </form>
    <div class="card mt-4 overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Data</th><th>Profissional</th><th>Cliente</th><th>Serviço</th><th>Modalidade</th><th>Valor</th><th>Pagamento</th><th>Comissão</th></tr></thead>
            <tbody>
                @forelse ($visits as $v)
                    <tr>
                        <td class="whitespace-nowrap">{{ \App\Support\BusinessTime::format($v->performed_at, 'd/m H:i') }}</td>
                        <td>{{ $v->barber->display_name }}</td><td>{{ $v->customer->name }}</td><td>{{ $v->service_name_snapshot }}</td>
                        <td><span class="{{ $v->mode === 'plan' ? 'badge-ink' : 'badge-gray' }}">{{ $v->mode === 'plan' ? 'Plano' : 'Avulso' }}</span></td>
                        <td class="whitespace-nowrap">{{ $v->mode === 'plan' ? '—' : \App\Support\Money::format($v->final_cents) }}@if ($v->discount_cents)<span class="block text-xs text-stone">desc. {{ \App\Support\Money::format($v->discount_cents) }}</span>@endif</td>
                        <td>
                            @if ($v->payment_status === 'pending')
                                <form method="POST" action="{{ route('admin.visits.paid', $v) }}" class="flex gap-1">@csrf
                                    <select name="payment_method" class="form-input min-h-8 py-0 text-xs" aria-label="Forma">@foreach ($paymentMethods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                                    <button class="badge-amber">Receber</button></form>
                            @else
                                {{ $paymentMethods[$v->payment_method] ?? ($v->payment_method === 'plano' ? 'Plano' : '—') }}
                            @endif
                        </td>
                        <td>{{ $v->commission ? \App\Support\Money::format($v->commission->amount_cents) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-stone">Nenhum atendimento no período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $visits->links() }}</div>
</x-layouts.admin>
