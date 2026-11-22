<section class="grain relative overflow-hidden bg-accent py-20 text-accent-ink md:py-24" data-hide-sticky aria-labelledby="cta-title">
    <div class="container-site flex flex-col items-start gap-8 md:flex-row md:items-end md:justify-between">
        <div class="max-w-3xl">
            @if ($section->eyebrow)<p class="eyebrow reveal opacity-70">{{ $section->eyebrow }}</p>@endif
            <h2 id="cta-title" class="display-lg reveal text-balance">{{ $section->title }}</h2>
            @if ($section->subtitle)<p class="reveal mt-4 max-w-xl opacity-80">{{ $section->subtitle }}</p>@endif
        </div>
        <a href="{{ route('booking.start') }}" class="btn-dark reveal h-14 shrink-0 px-9 text-base">
            {{ $section->field('primary_cta', 'Agendar agora') }} <x-icon name="arrow-right" class="size-4" />
        </a>
    </div>
</section>
