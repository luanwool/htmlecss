@if ($gallery->isNotEmpty())
<section id="galeria" class="bg-ink pb-20 md:pb-28" aria-labelledby="gallery-title">
    <div class="container-site">
        <div class="flex items-end justify-between gap-6 border-t border-white/10 pt-16">
            <div>
                @if ($section->eyebrow)<p class="eyebrow reveal text-accent">{{ $section->eyebrow }}</p>@endif
                <h2 id="gallery-title" class="display-md reveal mt-3">{{ $section->title }}</h2>
            </div>
            @if ($ig = $site->get('business.instagram'))
                <a href="{{ $ig }}" target="_blank" rel="noopener" class="reveal hidden items-center gap-2 text-sm text-smoke hover:text-paper sm:inline-flex">Mais no Instagram <x-icon name="arrow-up-right" class="size-4" /></a>
            @endif
        </div>
        @if ($section->subtitle)<p class="reveal mt-4 max-w-xl text-smoke">{{ $section->subtitle }}</p>@endif

        <ul class="mt-10 columns-2 gap-3 sm:gap-4 md:columns-3">
            @foreach ($gallery as $item)
                <li class="reveal mb-3 break-inside-avoid sm:mb-4">
                    <figure class="overflow-hidden rounded-2xl bg-graphite-2">
                        <img src="{{ $item->url(960) }}" srcset="{{ $item->srcset() }}" sizes="(min-width:768px) 31vw, 46vw" alt="{{ $item->alt_text ?? '' }}"
                             loading="lazy" decoding="async" width="{{ $item->width }}" height="{{ $item->height }}" class="h-auto w-full">
                        @if ($item->caption)<figcaption class="px-3 py-2 text-xs text-stone">{{ $item->caption }}</figcaption>@endif
                    </figure>
                </li>
            @endforeach
        </ul>
    </div>
</section>
@endif
