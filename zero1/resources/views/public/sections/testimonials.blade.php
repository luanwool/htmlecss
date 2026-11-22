@php $mapsUrl = $site->get('business.maps_url'); @endphp
@if ($reviews->isNotEmpty() || $mapsUrl)
<section id="depoimentos" class="bg-paper py-20 text-ink md:py-28" aria-labelledby="reviews-title">
    <div class="container-site">
        <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
            <div>
                @if ($section->eyebrow)<p class="eyebrow reveal text-graphite/70">{{ $section->eyebrow }}</p>@endif
                <h2 id="reviews-title" class="display-lg reveal mt-4 max-w-2xl">{{ $section->title }}</h2>
            </div>
            @if ($mapsUrl)
                <a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="btn-ghost-dark reveal self-start md:self-auto">
                    {{ $section->field('google_cta', 'Ver avaliações no Google') }} <x-icon name="external" class="size-4" />
                </a>
            @endif
        </div>

        @if ($reviews->isNotEmpty())
            <div data-stagger="cards" class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($reviews as $review)
                    <figure class="reveal flex flex-col rounded-3xl bg-white p-7 shadow-[0_1px_0_rgba(0,0,0,.04)]">
                        <span class="font-display text-6xl leading-[0.5] text-accent" style="filter:brightness(.8)" aria-hidden="true">“</span>
                        <blockquote class="mt-4 flex-1 text-pretty text-[1.02rem] leading-relaxed text-graphite">{{ $review->content }}</blockquote>
                        <figcaption class="mt-6 flex items-center justify-between gap-3 border-t border-ink/10 pt-4 text-sm">
                            <span class="font-semibold">{{ $review->author_name }}</span>
                            <span class="flex items-center gap-2 text-xs text-graphite/70">
                                @if ($review->rating)<span aria-label="{{ $review->rating }} de 5 estrelas">{{ str_repeat('★', $review->rating) }}</span>@endif
                                @if ($review->source === 'google')· Google @endif
                            </span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endif
