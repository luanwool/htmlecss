@php $policy = app(\App\Services\Booking\BookingPolicy::class); $canChange = $a->status->isOpen() && $a->starts_at->gt(now()->addHours($policy->cancelMinHours())); @endphp
<x-layouts.account title="Agendamento">
    @if (session('just_booked'))
        <div class="mb-6 rounded-3xl bg-ink p-6 text-paper">
            <p class="eyebrow text-accent">Confirmado</p>
            <p class="mt-2 font-display text-3xl">Até {{ ucfirst($a->localStart()->locale('pt_BR')->translatedFormat('l')) }}, {{ $a->localStart()->format('d/m') }} às {{ $a->localStart()->format('H:i') }}!</p>
            <p class="mt-2 text-sm text-smoke">Enviamos a confirmação por e-mail. Adicione ao seu calendário:</p>
        </div>
    @endif
    @error('booking')<div class="mb-6 rounded-xl bg-red-50 p-4 text-sm text-red-900" role="alert">{{ $message }}</div>@enderror
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <span class="{{ $a->status->badge() }}">{{ $a->status->label() }}</span>
            @if ($a->plan_payment_issue)<p class="mt-3 rounded-xl bg-red-50 p-3 text-sm text-red-900">O mês do plano que cobre este horário não está pago. <a class="underline" href="{{ route('account.subscription') }}">Regularize</a> ou fale com a barbearia.</p>@endif
            <h2 class="mt-4 font-display text-3xl">{{ $a->service_name_snapshot }}</h2>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-graphite/60">Data e hora</dt><dd class="font-semibold">{{ ucfirst($a->localStart()->locale('pt_BR')->translatedFormat('l, d/m/Y')) }}, {{ $a->localStart()->format('H:i') }}–{{ $a->localEnd()->format('H:i') }}</dd></div>
                <div><dt class="text-graphite/60">Profissional</dt><dd class="font-semibold">{{ $a->barber->display_name }}</dd></div>
                <div><dt class="text-graphite/60">Modalidade</dt><dd class="font-semibold">{{ $a->isPlan() ? 'Plano (1 uso do mês)' : 'Avulso — '.\App\Support\Money::format($a->price_cents_snapshot) }}</dd></div>
                @if ($address = $site->fullAddress())<div><dt class="text-graphite/60">Local</dt><dd>{{ $address }}</dd></div>@endif
            </dl>
            @if ($a->status->isOpen())
                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="{{ $googleUrl }}" target="_blank" rel="noopener" class="btn-dark min-h-10 px-4 text-xs"><x-icon name="calendar" class="size-4" /> Google Agenda</a>
                    <a href="{{ route('account.appointments.ics', $a->uid) }}" class="btn-ghost-dark min-h-10 px-4 text-xs">Baixar .ics (Apple/Outlook)</a>
                    @if ($dir = $site->directionsUrl())<a href="{{ $dir }}" target="_blank" rel="noopener" class="btn-ghost-dark min-h-10 px-4 text-xs"><x-icon name="map-pin" class="size-4" /> Rota</a>@endif
                </div>
                <p class="mt-3 text-xs text-graphite/60">O botão do Google Agenda cria o evento uma vez; se você reagendar ou cancelar, baixe o .ics novamente ou atualize o evento manualmente.</p>
            @endif
        </div>
        <div class="card space-y-3">
            <h2 class="font-semibold">Alterar</h2>
            @if ($canChange)
                <a href="{{ route('account.appointments.reschedule', $a->uid) }}" class="btn-ghost-dark w-full">Reagendar</a>
                <form method="POST" action="{{ route('account.appointments.cancel', $a->uid) }}" data-confirm="Cancelar este agendamento?">
                    @csrf<button class="btn w-full border border-red-600/30 text-red-700">Cancelar agendamento</button>
                </form>
                <p class="text-xs text-graphite/60">Permitido até {{ $policy->cancelMinHours() }}h antes do horário.</p>
            @elseif ($a->status->isOpen())
                <p class="text-sm text-graphite/70">O prazo para alterações online terminou.@if ($a->isPlan() && $policy->lateCancelForfeits()) Cancelar agora consome o atendimento do plano.@endif</p>
                <form method="POST" action="{{ route('account.appointments.cancel', $a->uid) }}" data-confirm="Cancelar fora do prazo?">@csrf<button class="text-sm font-semibold text-red-700 underline">Cancelar mesmo assim</button></form>
                @if ($wa = $site->whatsappUrl())<a href="{{ $wa }}" class="btn-ghost-dark w-full" target="_blank" rel="noopener">Falar com a barbearia</a>@endif
            @else
                <p class="text-sm text-graphite/70">Este agendamento não pode ser alterado.</p>
            @endif
        </div>
    </div>
</x-layouts.account>
