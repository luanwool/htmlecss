<x-layouts.admin title="Pedidos de alteração de escala">
    <div class="mb-5 flex flex-wrap gap-2 text-sm">
        @foreach (\App\Enums\ScheduleChangeStatus::cases() as $st)
            @php $n = $counts[$st->value] ?? 0; @endphp
            <a href="{{ route('admin.schedule-requests.index', ['status' => $st->value]) }}"
               @class(['rounded-full px-4 py-2', 'bg-white/10 text-paper' => $status === $st, 'border border-white/10 text-smoke hover:border-white/30' => $status !== $st])>
                {{ $st->label() }} <span class="ml-1 opacity-70">{{ $n }}</span>
            </a>
        @endforeach
    </div>

    @forelse ($items as ['request' => $r, 'conflicts' => $cs])
        <div class="card mb-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="hud text-stone">{{ $r->kind->label() }}</p>
                    <h3 class="mt-1 text-lg font-semibold text-paper">{{ $r->barber?->display_name }}</h3>
                    <p class="mt-1 text-smoke">{{ $r->summary() }}</p>
                    @if ($r->reason)<p class="mt-2 rounded-xl bg-white/[.03] p-3 text-sm italic text-smoke">“{{ $r->reason }}”</p>@endif
                    <p class="hud mt-3 text-stone">Enviado {{ \App\Support\BusinessTime::format($r->created_at, 'd/m H:i') }}</p>
                </div>
                <span class="{{ $r->status->badge() }}">{{ $r->status->label() }}</span>
            </div>

            @if (! empty($cs))
                <div class="mt-4 rounded-xl border border-amber-400/30 bg-amber-400/10 p-3 text-sm text-amber-100">
                    <p class="font-semibold">{{ count($cs) }} atendimento(s) confirmado(s) na janela:</p>
                    <ul class="mt-2 space-y-0.5 text-xs">
                        @foreach ($cs as $c)
                            <li>{{ \App\Support\BusinessTime::format($c->starts_at, 'd/m H:i') }} — {{ $c->service_name_snapshot }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-xs">Se aprovar, reagende/avise esses clientes antes.</p>
                </div>
            @endif

            @if ($r->status === \App\Enums\ScheduleChangeStatus::Pending)
                <div class="mt-5 grid gap-3 lg:grid-cols-2">
                    <form method="POST" action="{{ route('admin.schedule-requests.approve', $r) }}" class="flex gap-2" data-confirm="Aprovar e aplicar à agenda?">
                        @csrf
                        <input name="review_note" placeholder="Observação (opcional)" class="form-input min-h-10" aria-label="Observação">
                        <button class="btn-dark min-h-10 shrink-0 px-4 text-xs">Aprovar</button>
                    </form>
                    <form method="POST" action="{{ route('admin.schedule-requests.reject', $r) }}" class="flex gap-2">
                        @csrf
                        <input name="review_note" placeholder="Motivo da recusa (obrigatório)" required class="form-input min-h-10" aria-label="Motivo da recusa">
                        <button class="btn-ghost-dark min-h-10 shrink-0 px-4 text-xs">Recusar</button>
                    </form>
                </div>
            @else
                <p class="mt-4 text-xs text-stone">Analisado por {{ $r->reviewer?->name ?? '—' }} em {{ \App\Support\BusinessTime::format($r->reviewed_at, 'd/m H:i') }}
                    @if ($r->review_note) · "{{ $r->review_note }}" @endif
                </p>
            @endif
        </div>
    @empty
        <div class="card text-center text-sm text-stone">Nenhum pedido nesta aba.</div>
    @endforelse

    <div class="mt-4">{{ $paginator->links() }}</div>
</x-layouts.admin>
