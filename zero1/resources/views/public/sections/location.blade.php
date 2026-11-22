@php $today = \App\Support\BusinessTime::now()->dayOfWeekIso; @endphp
<section id="localizacao" class="bg-ink py-20 md:py-28" aria-labelledby="location-title">
    <div class="container-site grid gap-10 lg:grid-cols-12 lg:gap-16">
        <div class="lg:col-span-5">
            @if ($section->eyebrow)<p class="eyebrow reveal text-accent">{{ $section->eyebrow }}</p>@endif
            <h2 id="location-title" class="display-lg reveal mt-4">{{ $section->title }}</h2>

            @if ($address = $site->fullAddress())
                <address class="reveal mt-8 flex gap-3 not-italic text-lg text-paper">
                    <x-icon name="map-pin" class="mt-1 size-5 shrink-0 text-accent" />
                    <span>{{ $site->businessName() }}<br><span class="text-smoke">{{ $address }}</span></span>
                </address>
            @endif

            <div class="reveal mt-8">
                <p class="eyebrow flex items-center gap-2 text-stone"><x-icon name="clock" class="size-4" /> Horário de funcionamento</p>
                <table class="mt-4 w-full max-w-sm text-sm">
                    <caption class="sr-only">Horário de funcionamento da barbearia</caption>
                    <tbody>
                        @foreach ($hours as $h)
                            <tr class="border-b border-white/[.07] {{ $h->weekday === $today ? 'text-paper' : 'text-smoke' }}">
                                <th scope="row" class="py-2 text-left font-normal">{{ $h->dayName() }}@if ($h->weekday === $today) <span class="ml-1 text-xs text-accent">hoje</span>@endif</th>
                                <td class="py-2 text-right tabular-nums">{{ $h->label() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if (! $site->get('business.hours_confirmed'))
                    <p class="mt-3 text-xs text-stone">Horário conforme cadastro público, sujeito a confirmação. Os horários disponíveis para agendamento seguem a escala de cada profissional.</p>
                @elseif ($note = $site->get('business.hours_note'))
                    <p class="mt-3 text-xs text-stone">{{ $note }}</p>
                @endif
            </div>

            <div class="reveal mt-9 flex flex-wrap gap-3">
                @if ($dir = $site->directionsUrl())<a href="{{ $dir }}" target="_blank" rel="noopener" class="btn-accent"><x-icon name="map-pin" class="size-4" /> Traçar rota</a>@endif
                @if ($wa = $site->whatsappUrl())<a href="{{ $wa }}" target="_blank" rel="noopener" class="btn-ghost-light"><x-icon name="whatsapp" class="size-4" /> WhatsApp</a>@endif
                @if ($tel = $site->phoneHref())<a href="{{ $tel }}" class="btn-ghost-light"><x-icon name="phone" class="size-4" /> Ligar</a>@endif
            </div>
        </div>

        <div class="reveal lg:col-span-7">
            @if ($embed = $site->mapEmbedUrl())
                <div class="relative aspect-[4/3] overflow-hidden rounded-3xl border border-white/10 bg-graphite-2 lg:aspect-auto lg:h-full lg:min-h-[32rem]">
                    <iframe src="{{ $embed }}" title="Mapa: {{ $site->businessName() }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                            class="absolute inset-0 h-full w-full grayscale-[.85] invert-[.92] hue-rotate-180 contrast-[.9]" allowfullscreen></iframe>
                </div>
            @endif
        </div>
    </div>
</section>
