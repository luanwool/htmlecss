@if ($barbers->isNotEmpty())
<section id="profissionais" class="bg-ink py-20 md:py-28" aria-labelledby="team-title">
    <div class="container-site">
        <div class="max-w-2xl">
            @if ($section->eyebrow)<p class="eyebrow reveal text-accent">{{ $section->eyebrow }}</p>@endif
            <h2 id="team-title" class="display-lg reveal mt-4">{{ $section->title }}</h2>
            @if ($section->subtitle)<p class="reveal mt-5 text-smoke">{{ $section->subtitle }}</p>@endif
        </div>

        <div class="mt-14 grid gap-x-6 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($barbers as $barber)
                <article class="reveal group" style="transition-delay: {{ $loop->index * 0.06 }}s">
                    <div class="relative aspect-[4/5] overflow-hidden rounded-3xl bg-graphite-2">
                        @if ($barber->photo)
                            <img src="{{ $barber->photo->url(960) }}" srcset="{{ $barber->photo->srcset() }}" sizes="(min-width:1024px) 30vw, (min-width:640px) 45vw, 92vw"
                                 alt="{{ $barber->photo->alt_text ?: 'Foto de '.$barber->display_name }}" loading="lazy" decoding="async"
                                 width="{{ $barber->photo->width }}" height="{{ $barber->photo->height }}"
                                 class="h-full w-full object-cover grayscale transition duration-700 group-hover:scale-[1.03] group-hover:grayscale-0">
                        @else
                            <div class="flex h-full items-center justify-center" aria-hidden="true">
                                <span class="font-display text-[7rem] leading-none numeral-outline">{{ $barber->initials() }}</span>
                            </div>
                        @endif
                        @if ($barber->status->value === 'away')
                            <span class="badge absolute left-4 top-4 bg-ink/80 text-smoke backdrop-blur">Temporariamente afastado</span>
                        @endif
                    </div>
                    <div class="mt-5 flex items-start justify-between gap-4">
                        <div>
                            <h3 class="font-display text-2xl">{{ $barber->display_name }}</h3>
                            @if ($barber->specialties)
                                <p class="mt-1 text-sm text-accent">{{ implode(' · ', $barber->specialties) }}</p>
                            @endif
                        </div>
                    </div>
                    @if ($barber->bio)<p class="mt-3 text-sm leading-relaxed text-smoke">{{ $barber->bio }}</p>@endif
                    @if ($barber->status->value === 'active')
                        <a href="{{ route('booking.start', ['profissional' => $barber->id]) }}" class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-paper link-underline">
                            Agendar com {{ $barber->display_name }}
                            <x-icon name="arrow-up-right" class="size-4 text-accent" />
                        </a>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif
