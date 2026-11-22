@php
    $today = \App\Support\BusinessTime::now()->dayOfWeekIso;
    $todayHours = $hours->firstWhere('weekday', $today);
    $media = $section->media;
@endphp
<section data-hero data-hide-sticky class="grain relative -mt-16 overflow-hidden bg-ink pt-16 md:-mt-20 md:pt-20" aria-labelledby="hero-title">
    @if ($media)
        <div class="absolute inset-0 -z-10">
            <img src="{{ $media->url(1600) }}" srcset="{{ $media->srcset() }}" sizes="100vw" alt="{{ $media->alt_text }}"
                 class="h-full w-full object-cover opacity-45" data-hero-media fetchpriority="high" width="{{ $media->width }}" height="{{ $media->height }}">
            <div class="absolute inset-0 bg-gradient-to-t from-ink via-ink/60 to-ink/30"></div>
        </div>
    @else
        {{-- Sem foto oficial: composição tipográfica (não usa imagens de terceiros). --}}
        <div aria-hidden="true" data-hero-numeral class="pointer-events-none absolute -right-[6vw] top-1/2 -z-10 -translate-y-1/2 select-none font-display leading-none numeral-outline"
             style="font-size: clamp(22rem, 58vw, 54rem); font-weight: 400;">01</div>
        <div aria-hidden="true" class="absolute inset-y-0 left-[58%] -z-10 hidden w-px bg-gradient-to-b from-transparent via-white/10 to-transparent lg:block"></div>
    @endif

    <div class="container-site flex flex-col pb-12 pt-6 md:min-h-[92svh] md:justify-end md:pb-16 md:pt-24">
        <div class="max-w-4xl">
            @if ($section->eyebrow)
                <p class="eyebrow flex items-center gap-3 text-accent" data-hero-fade><span class="h-px w-8 bg-accent" data-hero-rule></span>{{ $section->eyebrow }}</p>
            @endif
            <h1 id="hero-title" class="display-xl mt-6 text-balance" data-hero-title>
                {{ $section->title ?: $site->businessName() }}
            </h1>
            <p class="sr-only">{{ $site->businessName() }}</p>
            @if ($section->subtitle)
                <p class="mt-6 max-w-xl text-pretty text-base text-smoke sm:text-lg" data-hero-fade>{{ $section->subtitle }}</p>
            @endif
            <div class="mt-9 flex flex-col gap-3 sm:flex-row" data-hero-fade>
                <a href="{{ route('booking.start') }}" class="btn-accent h-13 px-8 text-[0.95rem]">
                    {{ $section->field('primary_cta', 'Agendar horário') }} <x-icon name="arrow-right" class="size-4" />
                </a>
                <a href="#clube" class="btn-ghost-light h-13 px-8 text-[0.95rem]">{{ $section->field('secondary_cta', 'Conhecer planos') }}</a>
            </div>
        </div>

        <dl data-hero-facts class="mt-14 grid grid-cols-1 gap-px overflow-hidden rounded-2xl border border-white/10 bg-white/10 text-sm sm:grid-cols-3">
            <div class="bg-ink/80 p-4 backdrop-blur sm:p-5">
                <dt class="eyebrow text-stone">Endereço</dt>
                <dd class="mt-2 text-paper">{{ $site->addressLine() ?: 'Em breve' }}</dd>
            </div>
            <div class="bg-ink/80 p-4 backdrop-blur sm:p-5">
                <dt class="eyebrow text-stone">Hoje</dt>
                <dd class="mt-2 text-paper">{{ $todayHours?->label() ?? '—' }}
                    @unless ($site->get('business.hours_confirmed'))<span class="ml-1 text-xs text-stone">(horário a confirmar)</span>@endunless
                </dd>
            </div>
            <div class="bg-ink/80 p-4 backdrop-blur sm:p-5">
                <dt class="eyebrow text-stone">Contato</dt>
                <dd class="mt-2 flex flex-wrap gap-x-4 text-paper">
                    @if ($phone = $site->get('business.phone'))<a href="{{ $site->phoneHref() }}" class="link-underline">{{ $phone }}</a>@endif
                    @if ($wa = $site->whatsappUrl())<a href="{{ $wa }}" class="link-underline text-accent" target="_blank" rel="noopener">WhatsApp</a>@endif
                </dd>
            </div>
        </dl>
    </div>
</section>
