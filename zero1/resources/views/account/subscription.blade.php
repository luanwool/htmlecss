<x-layouts.account title="Minha assinatura">
    @if (! $sub)
        <div class="card"><p>Você não possui assinatura.</p><a href="{{ route('home') }}#clube" class="btn-dark mt-4">Conhecer os planos</a></div>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="card lg:col-span-2">
                <div class="flex flex-wrap items-center gap-3"><h2 class="font-display text-3xl">{{ $sub->term('name') }}</h2><span class="{{ $sub->status->badge() }}">{{ $sub->status->label() }}</span></div>
                <p class="mt-2 text-sm text-graphite/70">{{ \App\Support\Money::format($sub->price_cents) }}/mês · {{ $sub->payment_method === 'card' ? 'Cartão (recorrente)' : 'Pix (pagamento mensal)' }} · condições da versão {{ $sub->plan_version }}</p>
                @if ($sub->status->value === 'cancel_requested')
                    <p class="mt-4 rounded-xl bg-amber-100 p-3 text-sm text-amber-950">Cancelamento registrado. Não haverá novas cobranças; você pode usar o período pago até {{ $sub->cancel_effective_on->copy()->subDay()->format('d/m/Y') }}.</p>
                @endif
                @if ($payable)
                    <div class="mt-4 flex flex-col gap-3 rounded-xl bg-red-50 p-4 text-sm text-red-900 sm:flex-row sm:items-center sm:justify-between">
                        <span>Mensalidade de {{ $payable->period_start->format('d/m') }} a {{ $payable->lastDay()->format('d/m') }}: <strong>{{ $payable->status->label() }}</strong>. Benefícios desse período ficam bloqueados até a confirmação.</span>
                        <form method="POST" action="{{ route('account.subscription.pay', $payable) }}">@csrf<button class="btn-dark min-h-10 px-4 text-xs">Pagar agora</button></form>
                    </div>
                @endif
                @if ($usage)
                    <div class="mt-6 grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-2xl bg-paper p-4"><p class="font-display text-3xl">{{ $usage['available'] ?? '∞' }}</p><p class="text-xs text-graphite/60">disponíveis</p></div>
                        <div class="rounded-2xl bg-paper p-4"><p class="font-display text-3xl">{{ $usage['reserved'] }}</p><p class="text-xs text-graphite/60">reservados</p></div>
                        <div class="rounded-2xl bg-paper p-4"><p class="font-display text-3xl">{{ $usage['consumed'] }}</p><p class="text-xs text-graphite/60">concluídos</p></div>
                    </div>
                    <p class="mt-2 text-xs text-graphite/60">Ciclo atual: {{ $current->period_start->format('d/m/Y') }} a {{ $current->lastDay()->format('d/m/Y') }}</p>
                @endif
                <h3 class="mt-8 font-semibold">Histórico de cobranças</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="table-admin">
                        <thead><tr><th>Ciclo</th><th>Período</th><th>Valor</th><th>Situação</th><th>Pago em</th><th>ID externo</th></tr></thead>
                        <tbody>
                            @foreach ($sub->cycles->sortByDesc('sequence') as $c)
                                <tr><td>#{{ $c->sequence }}</td><td class="whitespace-nowrap">{{ $c->period_start->format('d/m/Y') }} – {{ $c->lastDay()->format('d/m/Y') }}</td>
                                    <td>{{ \App\Support\Money::format($c->amount_cents) }}</td><td><span class="{{ $c->status->badge() }}">{{ $c->status->label() }}</span></td>
                                    <td>{{ \App\Support\BusinessTime::format($c->paid_at, 'd/m/Y') }}</td><td class="text-xs text-stone">{{ $c->gateway_payment_id ?? '—' }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="space-y-6">
                <div class="card text-sm">
                    <h3 class="font-semibold">Serviços cobertos</h3>
                    <ul class="mt-2 list-disc pl-5">@foreach ($sub->term('service_names', []) as $name)<li>{{ $name }}</li>@endforeach</ul>
                    <a href="{{ route('booking.start') }}" class="btn-dark mt-4 w-full">Agendar pelo plano</a>
                </div>
                @if (in_array($sub->status->value, ['active', 'past_due', 'awaiting_payment', 'pending', 'suspended']))
                    <form method="POST" action="{{ route('account.subscription.cancel') }}" class="card space-y-3 text-sm" data-confirm="Confirmar cancelamento da assinatura?">
                        @csrf
                        <h3 class="font-semibold">Cancelar assinatura</h3>
                        <p class="text-graphite/70">O cancelamento impede a próxima renovação. O período já pago continua válido até o fim do ciclo.</p>
                        <x-form.input name="reason" label="Motivo (opcional)" />
                        <x-form.checkbox name="confirm" label="Entendo as condições acima" />
                        <button class="btn w-full border border-red-600/30 text-red-700">Solicitar cancelamento</button>
                    </form>
                @endif
            </div>
        </div>
    @endif
</x-layouts.account>
