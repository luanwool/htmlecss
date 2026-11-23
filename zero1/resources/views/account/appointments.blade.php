<x-layouts.account title="Meus agendamentos">
    <div class="mb-4 flex justify-end"><a href="{{ route('booking.start') }}" class="btn-dark min-h-10 px-4 text-xs">Novo agendamento</a></div>
    <h2 class="font-semibold">Próximos</h2>
    <div class="mt-3 space-y-2">
        @forelse ($upcoming as $a)
            <a href="{{ route('account.appointments.show', $a->uid) }}" class="card flex items-center justify-between gap-4 p-4 hover:border-ink">
                <span><span class="font-semibold">{{ ucfirst($a->localStart()->locale('pt_BR')->translatedFormat('l, d/m')) }} às {{ $a->localStart()->format('H:i') }}</span>
                    <span class="block text-sm text-graphite/70">{{ $a->service_name_snapshot }} · {{ $a->barber->display_name }}</span></span>
                <span class="{{ $a->status->badge() }}">{{ $a->status->label() }}</span>
            </a>
        @empty
            <p class="card text-sm text-graphite/70">Nenhum agendamento futuro.</p>
        @endforelse
    </div>
    <h2 class="mt-10 font-semibold">Histórico</h2>
    <div class="card mt-3 overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Data</th><th>Serviço</th><th>Profissional</th><th>Situação</th></tr></thead>
            <tbody>
                @forelse ($past as $a)
                    <tr><td class="whitespace-nowrap">{{ $a->localStart()->format('d/m/Y H:i') }}</td><td>{{ $a->service_name_snapshot }}</td><td>{{ $a->barber->display_name }}</td><td><span class="{{ $a->status->badge() }}">{{ $a->status->label() }}</span></td></tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center text-stone">Sem histórico.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.account>
