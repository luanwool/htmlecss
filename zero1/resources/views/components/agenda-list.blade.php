{{-- Lista cronológica por dia: data/hora e profissional; horários simultâneos aparecem separados (nunca deduplicados). --}}
@props(['byDay', 'link' => null, 'showBarber' => true, 'actionsView' => null])
@forelse ($byDay as $day => $items)
    @php $d = \Carbon\CarbonImmutable::parse($day, \App\Support\BusinessTime::tz()); @endphp
    <section class="mb-6" aria-label="{{ $d->locale('pt_BR')->translatedFormat('l, d \d\e F') }}">
        <h3 class="sticky top-16 z-10 -mx-1 mb-2 bg-[#f6f5f1]/95 px-1 py-2 font-semibold backdrop-blur">
            {{ ucfirst($d->locale('pt_BR')->translatedFormat('l')) }}, dia {{ $d->format('d') }}
            <span class="font-normal text-stone">· {{ $d->locale('pt_BR')->translatedFormat('F') }} · {{ $items->count() }} atendimento(s)</span>
        </h3>
        <ul class="space-y-2">
            @foreach ($items as $a)
                <li class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <a @if ($link) href="{{ $link($a) }}" @endif class="flex min-w-0 flex-1 flex-wrap items-baseline gap-x-3 gap-y-1">
                        <span class="font-display text-2xl tabular-nums">{{ $a->localStart()->format('H:i') }}</span>
                        <span class="text-xs text-stone tabular-nums">até {{ $a->localEnd()->format('H:i') }}</span>
                        @if ($showBarber)<span class="font-semibold">— {{ $a->barber->display_name }}</span>@endif
                        <span class="w-full sm:w-auto">{{ $a->customer->name }} · {{ $a->service_name_snapshot }} · {{ $a->duration_minutes_snapshot }} min</span>
                    </a>
                    <span class="flex flex-wrap items-center gap-1.5">
                        <span class="{{ $a->isPlan() ? 'badge-ink' : 'badge-gray' }}">{{ $a->isPlan() ? 'Assinatura' : 'Avulso' }}</span>
                        <span class="{{ $a->status->badge() }}">{{ $a->status->label() }}</span>
                        @if ($a->plan_payment_issue)<span class="badge-red">Plano não pago</span>@endif
                        @if ($actionsView)@include($actionsView, ['a' => $a])@endif
                    </span>
                </li>
            @endforeach
        </ul>
    </section>
@empty
    <div class="card text-center text-sm text-stone">Nenhum atendimento no período.</div>
@endforelse
