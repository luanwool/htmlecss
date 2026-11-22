@if ($faqs->isNotEmpty())
<section id="faq" class="bg-paper pb-20 text-ink md:pb-28" aria-labelledby="faq-title">
    <div class="container-site grid gap-10 border-t border-ink/10 pt-16 lg:grid-cols-12">
        <div class="lg:col-span-4">
            @if ($section->eyebrow)<p class="eyebrow reveal text-graphite/70">{{ $section->eyebrow }}</p>@endif
            <h2 id="faq-title" class="display-md reveal mt-3">{{ $section->title }}</h2>
            @if ($section->subtitle)<p class="reveal mt-4 text-graphite/70">{{ $section->subtitle }}</p>@endif
        </div>
        <div data-stagger class="divide-y divide-ink/10 border-y border-ink/10 lg:col-span-8">
            @foreach ($faqs as $faq)
                <details class="group reveal py-1" @if ($loop->first) open @endif>
                    <summary class="flex items-center justify-between gap-6 py-5 text-left text-lg font-medium">
                        {{ $faq->question }}
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full border border-ink/15 transition group-open:rotate-45" aria-hidden="true">
                            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 5v14M5 12h14"/></svg>
                        </span>
                    </summary>
                    <div class="pb-6 pr-12 leading-relaxed text-graphite/80">{!! nl2br(e($faq->answer)) !!}</div>
                </details>
            @endforeach
        </div>
    </div>
</section>
@endif
