@php $mapsUrl = $site->get('business.maps_url'); @endphp
@if ($reviews->isNotEmpty() || $mapsUrl)
<section id="depoimentos" class="relative bg-ink py-20 md:py-28" aria-labelledby="reviews-title">
    <div class="container-site">
        <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
            <div>
                @if ($section->eyebrow)<p class="eyebrow reveal text-accent">{{ $section->eyebrow }}</p>@endif
                <h2 id="reviews-title" class="display-lg reveal mt-4 max-w-2xl text-paper">{{ $section->title }}</h2>
            </div>
            @if ($mapsUrl)
                <a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="btn-ghost-light reveal self-start md:self-auto">
                    {{ $section->field('google_cta', 'Ver avaliações no Google') }} <x-icon name="external" class="size-4" />
                </a>
            @endif
        </div>

        @if ($reviews->isNotEmpty())
            <div data-stagger="cards" class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($reviews as $review)
                    <figure class="reveal glass spotlight flex flex-col rounded-3xl p-7">
                        @if ($review->rating)<p class="font-mono text-sm tracking-widest text-accent" aria-label="{{ $review->rating }} de 5 estrelas">{{ str_repeat('★', $review->rating) }}</p>@endif
                        <blockquote class="mt-4 flex-1 text-pretty text-[1.02rem] leading-relaxed text-paper">“{{ $review->content }}”</blockquote>
                        <figcaption class="mt-6 flex items-center justify-between gap-3 border-t border-white/10 pt-4 text-sm">
                            <span class="font-semibold text-paper">{{ $review->author_name }}</span>
                            @if ($review->source === 'google')<span class="hud">Google</span>@endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endif
