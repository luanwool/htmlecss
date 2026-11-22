<x-layouts.admin :title="'Assinatura · '.$s->customer->name">
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card lg:col-span-2">
            <div class="flex flex-wrap items-center gap-2"><h2 class="font-display text-3xl">{{ $s->term('name') }}</h2><span class="{{ $s->status->badge() }}">{{ $s->status->label() }}</span>@if ($s->fraud_blocked)<span class="badge-red">Bloqueada (contestação)</span>@endif</div>
            <p class="mt-2 text-sm text-stone">{{ \App\Support\Money::format($s->price_cents) }}/mês · {{ $s->payment_method === 'card' ? 'Cartão recorrente' : 'Pix mensal' }} · gateway {{ $s->gateway }} · ID {{ $s->gateway_subscription_id }} · condições v{{ $s->plan_version }} (congeladas na adesão)</p>
            @if ($s->cancel_effective_on)<p class="mt-2 text-sm">Cancelamento solicitado em {{ \App\Support\BusinessTime::format($s->cancel_requested_at) }} · efetivo em {{ $s->cancel_effective_on->format('d/m/Y') }} @if ($s->cancel_reason)· {{ $s->cancel_reason }}@endif</p>@endif
            <h3 class="mt-6 font-semibold">Ciclos de cobrança</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="table-admin">
                    <thead><tr><th>#</th><th>Período</th><th>Valor</th><th>Taxa</th><th>Líquido</th><th>Situação</th><th>Uso</th>@if ($simulated)<th>Simular</th>@endif</tr></thead>
                    <tbody>
                        @foreach ($s->cycles->sortByDesc('sequence') as $c)
                            @php $u = $usage[$c->id]; @endphp
                            <tr class="align-top">
                                <td>{{ $c->sequence }}</td>
                                <td class="whitespace-nowrap">{{ $c->period_start->format('d/m/y') }}–{{ $c->lastDay()->format('d/m/y') }}</td>
                                <td>{{ \App\Support\Money::format($c->amount_cents) }}</td>
                                <td>{{ $c->fee_cents !== null ? \App\Support\Money::format($c->fee_cents) : '—' }}</td>
                                <td>{{ $c->net_cents !== null ? \App\Support\Money::format($c->net_cents) : '—' }}</td>
                                <td><span class="{{ $c->status->badge() }}">{{ $c->status->label() }}</span><span class="block text-xs text-stone">{{ $c->gateway_payment_id }}</span></td>
                                <td class="whitespace-nowrap text-xs">{{ $u['consumed'] }} feitos · {{ $u['reserved'] }} reserv. · {{ $u['available'] ?? '∞' }} livres</td>
                                @if ($simulated)
                                    <td>
                                        <form method="POST" action="{{ route('admin.subscriptions.simulate', $s) }}" class="flex flex-wrap gap-1">
                                            @csrf<input type="hidden" name="cycle_id" value="{{ $c->id }}">
                                            @if (in_array($c->status->value, ['pending', 'failed']))
                                                <button name="action" value="pay" class="badge-green">Aprovar</button>
                                                <button name="action" value="reject" class="badge-red">Recusar</button>
                                            @elseif ($c->status->value === 'paid')
                                                <button name="action" value="refund" class="badge-amber">Estornar</button>
                                                <button name="action" value="chargeback" class="badge-red">Chargeback</button>
                                            @endif
                                            <label class="flex items-center gap-1 text-[0.65rem] text-stone"><input type="checkbox" name="lose_webhook" value="1"> perder webhook</label>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <h3 class="mt-6 font-semibold">Ledger de franquia (últimos movimentos)</h3>
            <ul class="mt-2 space-y-1 text-xs">
                @forelse ($ledger as $l)
                    <li><span class="text-stone">{{ \App\Support\BusinessTime::format($l->created_at, 'd/m H:i') }}</span> · <code>{{ $l->kind }}</code> · reserva {{ sprintf('%+d', $l->reserved_delta) }} · uso {{ sprintf('%+d', $l->consumed_delta) }} · agend. #{{ $l->appointment_id }} {{ $l->note ? '· '.$l->note : '' }}</li>
                @empty
                    <li class="text-stone">Sem movimentos.</li>
                @endforelse
            </ul>
        </section>
        <aside class="space-y-4">
            <div class="card space-y-3 text-sm">
                <p><span class="text-stone">Cliente:</span> <a class="underline" href="{{ route('admin.customers.show', $s->customer) }}">{{ $s->customer->name }}</a></p>
                <p><span class="text-stone">Última conciliação:</span> {{ \App\Support\BusinessTime::format($s->last_reconciled_at) }}</p>
                <form method="POST" action="{{ route('admin.subscriptions.reconcile', $s) }}">@csrf<button class="btn-ghost-dark w-full min-h-10 text-xs">Conciliar com o gateway agora</button></form>
            </div>
            @if (in_array($s->status->value, ['active', 'past_due', 'awaiting_payment', 'pending', 'suspended']))
                <form method="POST" action="{{ route('admin.subscriptions.cancel', $s) }}" class="card space-y-2" data-confirm="Cancelar a assinatura? A renovação será interrompida.">
                    @csrf<h3 class="font-semibold">Cancelar</h3>
                    <input name="reason" required placeholder="Motivo" class="form-input min-h-10 text-sm" aria-label="Motivo">
                    <button class="w-full text-sm font-semibold text-red-700 underline">Cancelar assinatura</button>
                </form>
            @endif
            <div class="card text-sm">
                <h3 class="font-semibold">Pagamentos registrados</h3>
                <ul class="mt-2 space-y-1 text-xs">@forelse ($transactions as $t)<li>{{ $t->gateway_payment_id }} · {{ $t->status }} · {{ \App\Support\Money::format($t->amount_cents) }}</li>@empty<li class="text-stone">Nenhum.</li>@endforelse</ul>
            </div>
            <div class="card text-sm">
                <h3 class="font-semibold">Agendamentos pelo plano</h3>
                <ul class="mt-2 space-y-1 text-xs">@forelse ($appointments as $a)<li><a class="underline" href="{{ route('admin.agenda.show', $a) }}">{{ $a->localStart()->format('d/m H:i') }}</a> · {{ $a->barber->display_name }} · {{ $a->status->label() }}</li>@empty<li class="text-stone">Nenhum.</li>@endforelse</ul>
            </div>
        </aside>
    </div>
</x-layouts.admin>
