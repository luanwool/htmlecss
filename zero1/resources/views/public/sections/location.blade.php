@php $today = \App\Support\BusinessTime::now()->dayOfWeekIso; @endphp
<section id="localizacao" class="relative bg-graphite py-20 md:py-28" aria-labelledby="location-title">
    <div class="container-site grid gap-8 lg:grid-cols-12 lg:gap-12">
        <div class="lg:col-span-5">
            @if ($section->eyebrow)<p class="eyebrow reveal text-accent">{{ $section->eyebrow }}</p>@endif
            <h2 id="location-title" class="display-lg reveal mt-4 text-paper">{{ $section->title }}</h2>

            @if ($address = $site->fullAddress())
                <address class="reveal mt-8 flex gap-3 not-italic text-lg text-paper">
                    <x-icon name="map-pin" class="mt-1 size-5 shrink-0 text-accent" />
                    <span>{{ $site->businessName() }}<br><span class="text-smoke">{{ $address }}</span></span>
                </address>
            @endif

            <div class="reveal glass mt-8 rounded-2xl p-5">
                <p class="hud flex items-center gap-2"><x-icon name="clock" class="size-4" /> Horário de funcionamento</p>
                <table class="mt-3 w-full text-sm">
                    <caption class="sr-only">Horário de funcionamento da barbearia</caption>
                    <tbody>
                        @foreach ($hours as $h)
                            <tr class="border-b border-white/[.06] last:border-0 {{ $h->weekday === $today ? 'text-paper' : 'text-smoke' }}">
                                <th scope="row" class="py-2 text-left font-normal">{{ $h->dayName() }}@if ($h->weekday === $today) <span class="hud ml-1 text-accent">hoje</span>@endif</th>
                                <td class="py-2 text-right font-mono">{{ $h->label() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if (! $site->get('business.hours_confirmed'))
                    <p class="mt-3 text-xs text-stone">Horário conforme cadastro público, sujeito a confirmação. Os horários de agendamento seguem a escala de cada profissional.</p>
                @elseif ($note = $site->get('business.hours_note'))
                    <p class="mt-3 text-xs text-stone">{{ $note }}</p>
                @endif
            </div>

            <div class="reveal mt-6 flex flex-wrap gap-3">
                @if ($dir = $site->directionsUrl())<a href="{{ $dir }}" target="_blank" rel="noopener" class="btn-accent"><x-icon name="map-pin" class="size-4" /> Traçar rota</a>@endif
                @if ($wa = $site->whatsappUrl())<a href="{{ $wa }}" target="_blank" rel="noopener" class="btn-ghost-light"><x-icon name="whatsapp" class="size-4" /> WhatsApp</a>@endif
                @if ($tel = $site->phoneHref())<a href="{{ $tel }}" class="btn-ghost-light"><x-icon name="phone" class="size-4" /> Ligar</a>@endif
            </div>
        </div>

        <div class="reveal lg:col-span-7">
            @if ($embed = $site->mapEmbedUrl())
                <div class="glass relative aspect-[4/3] overflow-hidden rounded-3xl p-1.5 lg:aspect-auto lg:h-full lg:min-h-[32rem]">
                    <div class="relative h-full w-full overflow-hidden rounded-[1.25rem]">
                        <iframe src="{{ $embed }}" title="Mapa: {{ $site->businessName() }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                                class="absolute inset-0 h-full w-full grayscale-[.85] invert-[.9] hue-rotate-180 contrast-[.9]" allowfullscreen></iframe>
                    </div>
                    @if ($site->get('business.latitude') && $site->get('business.longitude'))
                        <span class="hud pointer-events-none absolute bottom-4 left-4 rounded-full bg-ink/80 px-3 py-1 backdrop-blur">{{ $site->get('business.latitude') }}, {{ $site->get('business.longitude') }}</span>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
