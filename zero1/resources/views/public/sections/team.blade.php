@if ($barbers->isNotEmpty())
<section id="profissionais" class="relative bg-graphite py-20 md:py-28" aria-labelledby="team-title">
    <div class="container-site">
        <div class="max-w-2xl">
            @if ($section->eyebrow)<p class="eyebrow reveal text-accent">{{ $section->eyebrow }}</p>@endif
            <h2 id="team-title" class="display-lg reveal mt-4 text-paper">{{ $section->title }}</h2>
            @if ($section->subtitle)<p class="reveal mt-5 text-smoke">{{ $section->subtitle }}</p>@endif
        </div>

        <div data-stagger="cards" class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($barbers as $barber)
                <article class="reveal glass spotlight group flex flex-col overflow-hidden rounded-3xl">
                    <div class="relative aspect-[4/5] overflow-hidden bg-graphite-2">
                        @if ($barber->photo)
                            <img src="{{ $barber->photo->url(960) }}" srcset="{{ $barber->photo->srcset() }}" sizes="(min-width:1024px) 30vw, (min-width:640px) 45vw, 92vw"
                                 alt="{{ $barber->photo->alt_text ?: 'Foto de '.$barber->display_name }}" loading="lazy" decoding="async"
                                 width="{{ $barber->photo->width }}" height="{{ $barber->photo->height }}"
                                 class="h-full w-full object-cover grayscale transition duration-500 group-hover:scale-[1.03] group-hover:grayscale-0">
                        @else
                            <div class="fx-grid absolute inset-0" aria-hidden="true"></div>
                            <div class="relative flex h-full items-center justify-center" aria-hidden="true">
                                <span class="chrome-text font-future text-8xl font-semibold tracking-tight">{{ $barber->initials() }}</span>
                            </div>
                        @endif
                        <div class="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-ink/80 to-transparent"></div>
                        <span class="hud absolute left-4 top-4 flex items-center gap-2 rounded-full bg-ink/70 px-3 py-1 backdrop-blur">
                            @if ($barber->status->value === 'away')Afastado @else<span class="live-dot" aria-hidden="true"></span> Atendendo @endif
                        </span>
                    </div>
                    <div class="flex flex-1 flex-col p-6">
                        <h3 class="text-xl font-semibold text-paper">{{ $barber->display_name }}</h3>
                        @if ($barber->specialties)<p class="hud mt-1 text-accent">{{ implode(' · ', $barber->specialties) }}</p>@endif
                        @if ($barber->bio)<p class="mt-3 text-sm leading-relaxed text-smoke">{{ $barber->bio }}</p>@endif
                        @if ($barber->status->value === 'active')
                            <a href="{{ route('booking.start', ['profissional' => $barber->id]) }}" class="btn-ghost-light mt-5 self-start">
                                Agendar com {{ $barber->display_name }} <x-icon name="arrow-up-right" class="size-4" />
                            </a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif
