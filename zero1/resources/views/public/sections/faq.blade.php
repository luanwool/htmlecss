@if ($faqs->isNotEmpty())
<section id="faq" class="bg-ink pb-20 md:pb-28" aria-labelledby="faq-title">
    <div class="container-site grid gap-10 lg:grid-cols-12">
        <div class="lg:col-span-4">
            @if ($section->eyebrow)<p class="eyebrow reveal text-accent">{{ $section->eyebrow }}</p>@endif
            <h2 id="faq-title" class="display-md reveal mt-3 text-paper">{{ $section->title }}</h2>
            @if ($section->subtitle)<p class="reveal mt-4 text-smoke">{{ $section->subtitle }}</p>@endif
        </div>
        <div data-stagger class="grid gap-2 lg:col-span-8">
            @foreach ($faqs as $faq)
                <details class="group reveal glass rounded-2xl px-5" @if ($loop->first) open @endif>
                    <summary class="flex min-h-14 items-center justify-between gap-6 py-4 text-left font-medium text-paper">
                        {{ $faq->question }}
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full border border-white/15 text-smoke transition group-open:rotate-45 group-open:border-accent group-open:text-accent" aria-hidden="true">
                            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 5v14M5 12h14"/></svg>
                        </span>
                    </summary>
                    <div class="pb-5 pr-10 leading-relaxed text-smoke">{!! nl2br(e($faq->answer)) !!}</div>
                </details>
            @endforeach
        </div>
    </div>
</section>
@endif
