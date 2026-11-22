<x-layouts.admin title="Assinaturas">
    <x-slot:actions>
        <form method="POST" action="{{ route('admin.subscriptions.tick') }}">@csrf<button class="btn-ghost-dark min-h-10 px-4 text-xs" title="Executada automaticamente pelo cron a cada hora">Rodar rotina de renovação</button></form>
    </x-slot:actions>
    @if ($simulated)
        <p class="mb-4 rounded-xl bg-amber-100 p-3 text-sm text-amber-950"><strong>Pagamentos simulados.</strong> Nenhuma cobrança real acontece. Use a tela de cada assinatura para simular aprovação, recusa, estorno, chargeback e perda de webhook.</p>
    @endif
    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('admin.subscriptions.index') }}" @class(['rounded-full px-3 py-1.5', 'bg-ink text-paper' => ! request('status'), 'border border-black/10 bg-white' => request('status')])>Todas</a>
        @foreach (\App\Enums\SubscriptionStatus::cases() as $st)
            @if ($counts[$st->value] ?? 0)<a href="{{ route('admin.subscriptions.index', ['status' => $st->value]) }}" @class(['rounded-full px-3 py-1.5', 'bg-ink text-paper' => request('status') === $st->value, 'border border-black/10 bg-white' => request('status') !== $st->value])>{{ $st->label() }} ({{ $counts[$st->value] }})</a>@endif
        @endforeach
    </div>
    <div class="card overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Cliente</th><th>Plano</th><th>Valor</th><th>Método</th><th>Situação</th><th>Desde</th></tr></thead>
            <tbody>
                @forelse ($subs as $s)
                    <tr>
                        <td><a href="{{ route('admin.subscriptions.show', $s) }}" class="font-medium underline-offset-2 hover:underline">{{ $s->customer->name }}</a></td>
                        <td>{{ $s->term('name') }} <span class="text-xs text-stone">v{{ $s->plan_version }}</span></td>
                        <td>{{ \App\Support\Money::format($s->price_cents) }}</td><td>{{ $s->payment_method === 'card' ? 'Cartão' : 'Pix' }}</td>
                        <td><span class="{{ $s->status->badge() }}">{{ $s->status->label() }}</span>@if ($s->fraud_blocked) <span class="badge-red">Bloqueada</span>@endif</td>
                        <td>{{ \App\Support\BusinessTime::format($s->created_at, 'd/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-stone">Nenhuma assinatura.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $subs->links() }}</div>
</x-layouts.admin>
