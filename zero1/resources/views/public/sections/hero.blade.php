@php
    $today = \App\Support\BusinessTime::now()->dayOfWeekIso;
    $todayHours = $hours->firstWhere('weekday', $today);
    $media = $section->media;
@endphp
<section data-hero data-hide-sticky class="relative -mt-16 overflow-hidden bg-ink pt-16 md:-mt-20 md:pt-20" aria-labelledby="hero-title">
    {{-- Fundo: foto real (se enviada) ou grade técnica + brilhos difusos --}}
    @if ($media)
        <div class="absolute inset-0 -z-0">
            <img src="{{ $media->url(1600) }}" srcset="{{ $media->srcset() }}" sizes="100vw" alt="{{ $media->alt_text }}"
                 class="h-full w-full object-cover opacity-40" fetchpriority="high" width="{{ $media->width }}" height="{{ $media->height }}" data-hero-media>
            <div class="absolute inset-0 bg-gradient-to-t from-ink via-ink/70 to-ink/40"></div>
        </div>
    @endif
    <div aria-hidden="true" class="fx-glow pointer-events-none absolute inset-0"></div>
    <div aria-hidden="true" class="fx-grid pointer-events-none absolute inset-0" data-hero-numeral></div>

    <div class="container-site relative grid gap-10 pb-12 pt-8 md:pt-24 lg:grid-cols-12 lg:items-end lg:gap-12 lg:pb-20">
        <div class="lg:col-span-7">
            @if ($section->eyebrow)
                <p class="hud flex items-center gap-3 text-smoke" data-hero-fade>
                    <span class="h-px w-8 bg-accent" data-hero-rule></span>{{ $section->eyebrow }}
                </p>
            @endif
            <h1 id="hero-title" class="display-xl chrome-text mt-6" data-hero-title>{{ $section->title ?: $site->businessName() }}</h1>
            @if ($section->subtitle)
                <p class="mt-6 max-w-xl text-pretty text-base leading-relaxed text-smoke sm:text-lg" data-hero-fade>{{ $section->subtitle }}</p>
            @endif
            <div class="mt-9 flex flex-col gap-3 sm:flex-row" data-hero-fade>
                <a href="{{ route('booking.start') }}" class="btn-accent h-13 px-8 text-[0.95rem]">
                    {{ $section->field('primary_cta', 'Agendar horário') }} <x-icon name="arrow-right" class="size-4" />
                </a>
                <a href="#clube" class="btn-ghost-light h-13 px-8 text-[0.95rem]">{{ $section->field('secondary_cta', 'Conhecer planos') }}</a>
            </div>
        </div>

        {{-- Painel "Agenda ao vivo": horários reais do motor de agenda --}}
        <aside class="lg:col-span-5" data-hero-fade aria-labelledby="live-title">
            <div class="glass spotlight relative overflow-hidden rounded-3xl p-5 sm:p-6">
                <span aria-hidden="true" class="pole absolute inset-y-0 left-0 w-1"></span>
                <div class="flex items-center justify-between gap-3 pl-2">
                    <h2 id="live-title" class="hud flex items-center gap-2 text-paper"><span class="live-dot" aria-hidden="true"></span> Agenda ao vivo</h2>
                    @if ($live && $live['count'] > 0)<span class="hud">{{ $live['day'] }}</span>@endif
                </div>
                @if ($live && $live['count'] > 0)
                    <p class="mt-4 pl-2 text-sm text-smoke">Próximos horários livres · <span class="text-paper">{{ $live['service'] }}</span> <span class="whitespace-nowrap font-mono text-xs">({{ $live['duration'] }})</span></p>
                    <ul class="mt-4 grid grid-cols-4 gap-2 pl-2" role="list">
                        @foreach ($live['times'] as $t)
                            <li><a href="{{ route('booking.start', ['servico' => $live['service_id'], 'profissional' => 'qualquer', 'data' => $live['date'], 'hora' => $t]) }}#confirmar"
                                   class="flex min-h-11 items-center justify-center rounded-xl border border-white/10 bg-white/[.04] font-mono text-sm text-paper transition duration-200 hover:border-accent hover:text-accent"
                                   aria-label="Agendar {{ $live['service'] }} às {{ $t }}">{{ $t }}</a></li>
                        @endforeach
                    </ul>
                    <a href="{{ route('booking.start', ['servico' => $live['service_id'], 'data' => $live['date']]) }}" class="mt-4 inline-flex items-center gap-2 pl-2 text-sm text-smoke transition hover:text-paper">
                        Ver os {{ $live['count'] }} horários do dia <x-icon name="arrow-right" class="size-4" />
                    </a>
                @else
                    <p class="mt-4 pl-2 text-sm text-smoke">A agenda online é atualizada em tempo real. Escolha serviço, profissional e horário em poucos toques.</p>
                    <a href="{{ route('booking.start') }}" class="btn-ghost-light mt-4 ml-2">Abrir agenda</a>
                @endif
                <div class="hairline my-5"></div>
                <dl class="grid gap-3 pl-2 text-sm sm:grid-cols-2" data-hero-facts>
                    <div>
                        <dt class="hud">Hoje</dt>
                        <dd class="mt-1 font-mono text-paper">{{ $todayHours?->label() ?? '—' }}
                            @unless ($site->get('business.hours_confirmed'))<span class="block font-sans text-xs text-stone">horário a confirmar</span>@endunless
                        </dd>
                    </div>
                    <div>
                        <dt class="hud">Contato</dt>
                        <dd class="mt-1 flex flex-wrap gap-x-3">
                            @if ($phone = $site->get('business.phone'))<a href="{{ $site->phoneHref() }}" class="font-mono text-paper link-underline">{{ $phone }}</a>@endif
                            @if ($wa = $site->whatsappUrl())<a href="{{ $wa }}" class="text-accent link-underline" target="_blank" rel="noopener">WhatsApp</a>@endif
                        </dd>
                    </div>
                    @if ($address = $site->addressLine())
                        <div class="sm:col-span-2">
                            <dt class="hud">Endereço</dt>
                            <dd class="mt-1 text-paper">{{ $address }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </aside>
    </div>
    <div class="hairline"></div>
</section>
