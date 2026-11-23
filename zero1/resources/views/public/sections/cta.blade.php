<section class="relative overflow-hidden bg-ink py-20 md:py-28" data-hide-sticky aria-labelledby="cta-title">
    <div class="container-site">
        <div class="glass relative overflow-hidden rounded-[2rem] px-6 py-14 sm:px-12 md:py-20">
            <div aria-hidden="true" class="fx-glow pointer-events-none absolute inset-0"></div>
            <div aria-hidden="true" class="pole absolute inset-x-0 top-0 h-1.5"></div>
            <div class="relative flex flex-col items-start gap-8 md:flex-row md:items-end md:justify-between">
                <div class="max-w-3xl">
                    @if ($section->eyebrow)<p class="eyebrow reveal text-accent">{{ $section->eyebrow }}</p>@endif
                    <h2 id="cta-title" class="display-lg chrome-text reveal">{{ $section->title }}</h2>
                    @if ($section->subtitle)<p class="reveal mt-4 max-w-xl text-smoke">{{ $section->subtitle }}</p>@endif
                </div>
                <a href="{{ route('booking.start') }}" class="btn-accent reveal h-14 shrink-0 px-9 text-base">
                    {{ $section->field('primary_cta', 'Agendar agora') }} <x-icon name="arrow-right" class="size-4" />
                </a>
            </div>
        </div>
    </div>
</section>
