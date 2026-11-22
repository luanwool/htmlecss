<x-layouts.admin :title="'Agendamento #'.$a->id">
    @error('booking')<div class="mb-4 rounded-xl bg-red-50 p-4 text-sm text-red-900" role="alert">{{ $message }}</div>@enderror
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card lg:col-span-2">
            <div class="flex flex-wrap gap-2">
                <span class="{{ $a->status->badge() }}">{{ $a->status->label() }}</span>
                <span class="{{ $a->isPlan() ? 'badge-ink' : 'badge-gray' }}">{{ $a->isPlan() ? 'Assinatura' : 'Avulso' }}</span>
                <span class="badge-gray">Origem: {{ $a->origin }}</span>
                @if ($a->plan_payment_issue)<span class="badge-red">Mês do plano não pago</span>@endif
            </div>
            <h2 class="mt-4 font-display text-3xl">{{ ucfirst($a->localStart()->locale('pt_BR')->translatedFormat('l, d/m/Y')) }} · {{ $a->localStart()->format('H:i') }}–{{ $a->localEnd()->format('H:i') }}</h2>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-stone">Cliente</dt><dd class="font-semibold"><a class="underline" href="{{ route('admin.customers.show', $a->customer) }}">{{ $a->customer->name }}</a> {{ $a->customer->phone }}</dd></div>
                <div><dt class="text-stone">Profissional</dt><dd class="font-semibold">{{ $a->barber->display_name }}</dd></div>
                <div><dt class="text-stone">Serviço</dt><dd>{{ $a->service_name_snapshot }} · {{ $a->duration_minutes_snapshot }} min @if ($a->buffer_minutes_snapshot)+ {{ $a->buffer_minutes_snapshot }} preparo @endif</dd></div>
                <div><dt class="text-stone">Valor</dt><dd>{{ $a->isPlan() ? 'Coberto pelo plano' : \App\Support\Money::format($a->price_cents_snapshot) }}</dd></div>
                @if ($usage)<div class="sm:col-span-2"><dt class="text-stone">Franquia do ciclo</dt><dd>{{ $usage['available'] ?? '∞' }} disponível(is) · {{ $usage['reserved'] }} reservado(s) · {{ $usage['consumed'] }} concluído(s)</dd></div>@endif
                @if ($a->notes)<div class="sm:col-span-2"><dt class="text-stone">Observação</dt><dd>{{ $a->notes }}</dd></div>@endif
                @if ($a->visit)<div class="sm:col-span-2"><dt class="text-stone">Atendimento registrado</dt><dd>{{ \App\Support\Money::format($a->visit->final_cents) }} · {{ $paymentMethods[$a->visit->payment_method] ?? $a->visit->payment_method }} · {{ $a->visit->payment_status === 'paid' ? 'pago' : 'pendente' }}</dd></div>@endif
            </dl>
            <h3 class="mt-8 font-semibold">Histórico</h3>
            <ol class="mt-3 space-y-2 border-l border-black/10 pl-4 text-sm">
                @foreach ($a->history as $h)
                    <li><span class="text-stone">{{ \App\Support\BusinessTime::format($h->created_at, 'd/m H:i') }}</span> — {{ \App\Enums\AppointmentStatus::tryFrom($h->to_status)?->label() ?? $h->to_status }}
                        {{ $h->note ? '· '.$h->note : '' }} {{ $h->user ? '· '.$h->user->name : '' }}
                        @if ($h->changes)<span class="block text-xs text-stone">antes: {{ \App\Support\BusinessTime::format(\Carbon\Carbon::parse($h->changes['de']['starts_at'] ?? null), 'd/m H:i') }}</span>@endif</li>
                @endforeach
            </ol>
        </section>
        <aside class="space-y-4">
            @if ($a->status->isOpen())
                <div class="card space-y-3">
                    <h2 class="font-semibold">Ações</h2>
                    @if ($a->status->value === 'confirmed')
                        <form method="POST" action="{{ route('admin.agenda.action', [$a, 'iniciar']) }}">@csrf<button class="btn-ghost-dark w-full min-h-10 text-xs">Iniciar atendimento</button></form>
                    @endif
                    <form method="POST" action="{{ route('admin.agenda.action', [$a, 'concluir']) }}" class="space-y-3 rounded-xl bg-paper p-3" data-loading>
                        @csrf
                        @unless ($a->isPlan())<x-visit-fields :payment-methods="$paymentMethods" :price="\App\Support\Money::format($a->price_cents_snapshot, false)" />@endunless
                        <button class="btn-dark w-full min-h-10 text-xs">Concluir atendimento</button>
                    </form>
                    <a href="{{ route('admin.agenda.reschedule', $a) }}" class="btn-ghost-dark w-full min-h-10 text-xs">Reagendar</a>
                    <form method="POST" action="{{ route('admin.agenda.action', [$a, 'ausente']) }}" data-confirm="Marcar ausência (no-show)?">@csrf<button class="btn-ghost-dark w-full min-h-10 text-xs">Cliente não compareceu</button></form>
                    <form method="POST" action="{{ route('admin.agenda.action', [$a, 'cancelar']) }}" class="space-y-2" data-confirm="Cancelar pela barbearia? O cliente será avisado e o crédito do plano devolvido.">
                        @csrf<input name="reason" required placeholder="Motivo do cancelamento" class="form-input min-h-10 text-sm" aria-label="Motivo"><button class="w-full text-xs font-semibold text-red-700 underline">Cancelar pela barbearia</button>
                    </form>
                </div>
            @endif
            @if ($wa = $a->customer->phone ? 'https://wa.me/55'.ltrim(preg_replace('/\D/', '', $a->customer->phone), '0') : null)
                <a href="{{ $wa }}" target="_blank" rel="noopener" class="btn-ghost-dark w-full"><x-icon name="whatsapp" class="size-4" /> WhatsApp do cliente</a>
            @endif
        </aside>
    </div>
</x-layouts.admin>
