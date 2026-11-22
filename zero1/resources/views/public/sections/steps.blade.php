{{-- Funil de 3 passos (padrão de conversão para agendamento): explica o fluxo real e reduz atrito antes do CTA. --}}
@php
    $steps = collect([1, 2, 3])->map(fn ($i) => ['title' => $section->field("step_{$i}_title"), 'text' => $section->field("step_{$i}_text")])
        ->filter(fn ($s) => $s['title'])->values();
@endphp
@if ($steps->isNotEmpty())
<section id="como-funciona" class="border-t border-white/10 bg-ink py-16 md:py-24" aria-labelledby="steps-title">
    <div class="container-site">
        <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
            <div class="max-w-2xl">
                @if ($section->eyebrow)<p class="eyebrow reveal text-accent">{{ $section->eyebrow }}</p>@endif
                <h2 id="steps-title" class="display-md reveal mt-3">{{ $section->title }}</h2>
                @if ($section->subtitle)<p class="reveal mt-4 text-smoke">{{ $section->subtitle }}</p>@endif
            </div>
            <a href="{{ route('booking.start') }}" class="btn-accent reveal self-start md:self-auto">
                {{ $section->field('primary_cta', 'Agendar horário') }} <x-icon name="arrow-right" class="size-4" />
            </a>
        </div>

        <div class="relative mt-12">
            {{-- Linha que liga os passos (desenhada no scroll quando há animação) --}}
            <div aria-hidden="true" class="absolute left-0 right-0 top-[1.375rem] hidden h-px bg-white/10 md:block">
                <div data-steps-line class="h-full origin-left bg-accent"></div>
            </div>
            <ol data-stagger class="relative grid gap-8 md:gap-10 {{ [1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3'][$steps->count()] }}">
                @foreach ($steps as $i => $step)
                    <li class="reveal flex gap-5 md:block">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-full border border-accent/60 bg-ink font-display text-lg text-accent tabular-nums" aria-hidden="true">{{ $i + 1 }}</span>
                        <div class="md:mt-6">
                            <h3 class="text-lg font-semibold text-paper text-balance">
                                <span class="sr-only">Passo {{ $i + 1 }}: </span>{{ $step['title'] }}
                            </h3>
                            @if ($step['text'])<p class="mt-2 max-w-sm text-pretty text-smoke">{{ $step['text'] }}</p>@endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</section>
@endif
