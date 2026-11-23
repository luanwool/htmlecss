@if ($a->status->isOpen())
    <details class="group w-full sm:w-auto">
        <summary class="btn-dark min-h-9 px-4 text-xs">Ações</summary>
        <div class="mt-3 space-y-3 rounded-2xl border border-black/10 bg-white p-4 text-sm sm:absolute sm:right-4 sm:z-20 sm:w-80 sm:shadow-xl">
            @if ($a->status->value === 'confirmed')
                <form method="POST" action="{{ route('barber.appointments.action', [$a, 'iniciar']) }}">@csrf<button class="btn-ghost-dark w-full min-h-10 text-xs">Iniciar atendimento</button></form>
            @endif
            <form method="POST" action="{{ route('barber.appointments.action', [$a, 'concluir']) }}" class="space-y-3" data-loading>
                @csrf
                @unless ($a->isPlan())
                    <x-visit-fields :payment-methods="\App\Models\ServiceVisit::PAYMENT_METHODS" :price="\App\Support\Money::format($a->price_cents_snapshot, false)" />
                @endunless
                <button class="btn-dark w-full min-h-10 text-xs">Concluir{{ $a->isPlan() ? ' (consome 1 uso do plano)' : '' }}</button>
            </form>
            <form method="POST" action="{{ route('barber.appointments.action', [$a, 'ausente']) }}" data-confirm="Marcar que o cliente não compareceu?">@csrf<button class="w-full text-xs font-semibold text-red-700 underline">Cliente não compareceu</button></form>
        </div>
    </details>
@endif
