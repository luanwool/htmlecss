<x-layouts.admin title="Uso dos planos">
    <p class="mb-4 text-sm text-stone">Ciclo pago vigente de cada assinatura: permitidos, reservados, concluídos e saldo — com sinais de uso irregular.</p>
    <div class="card overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Cliente</th><th>Plano</th><th>Situação</th><th>Ciclo</th><th>Permitidos</th><th>Reservados</th><th>Concluídos</th><th>Saldo</th><th>Alertas</th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td><a class="underline" href="{{ route('admin.subscriptions.show', $r['s']) }}">{{ $r['s']->customer->name }}</a></td>
                        <td>{{ $r['s']->term('name') }}</td>
                        <td><span class="{{ $r['s']->status->badge() }}">{{ $r['s']->status->label() }}</span></td>
                        <td class="whitespace-nowrap">{{ $r['cycle'] ? $r['cycle']->period_start->format('d/m').'–'.$r['cycle']->lastDay()->format('d/m') : 'sem ciclo pago' }}</td>
                        <td>{{ $r['u'] ? ($r['u']['allowance'] ?? '∞') : '—' }}</td><td>{{ $r['u']['reserved'] ?? '—' }}</td><td>{{ $r['u']['consumed'] ?? '—' }}</td><td>{{ $r['u'] ? ($r['u']['available'] ?? '∞') : '—' }}</td>
                        <td>@foreach ($r['flags'] as $fl)<span class="badge-red">{{ $fl }}</span> @endforeach</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-8 text-center text-stone">Nenhuma assinatura viva.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
