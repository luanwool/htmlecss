{{-- Funil de 3 passos (padrão de conversão para agendamento): explica o fluxo real e reduz atrito antes do CTA. --}}
@php
    $steps = collect([1, 2, 3])->map(fn ($i) => ['title' => $section->field("step_{$i}_title"), 'text' => $section->field("step_{$i}_text")])
        ->filter(fn ($s) => $s['title'])->values();
@endphp
@if ($steps->isNotEmpty())
<section id="como-funciona" class="relative bg-ink py-16 md:py-24" aria-labelledby="steps-title">
    <div class="container-site">
        <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
            <div class="max-w-2xl">
                @if ($section->eyebrow)<p class="eyebrow reveal text-accent">{{ $section->eyebrow }}</p>@endif
                <h2 id="steps-title" class="display-md reveal mt-3 text-paper">{{ $section->title }}</h2>
                @if ($section->subtitle)<p class="reveal mt-4 text-smoke">{{ $section->subtitle }}</p>@endif
            </div>
            <a href="{{ route('booking.start') }}" class="btn-accent reveal self-start md:self-auto">
                {{ $section->field('primary_cta', 'Agendar horário') }} <x-icon name="arrow-right" class="size-4" />
            </a>
        </div>

        <div class="relative mt-12">
            <div aria-hidden="true" class="absolute left-8 right-8 top-[2.15rem] hidden h-px bg-white/10 md:block">
                <div data-steps-line class="h-full origin-left bg-gradient-to-r from-accent via-paper to-ion"></div>
            </div>
            <ol data-stagger="cards" class="relative grid gap-4 md:gap-5 {{ [1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3'][$steps->count()] }}">
                @foreach ($steps as $i => $step)
                    <li class="reveal glass spotlight rounded-3xl p-6">
                        <span class="relative z-10 flex size-11 items-center justify-center rounded-full border border-white/15 bg-ink font-mono text-sm text-paper" aria-hidden="true">0{{ $i + 1 }}</span>
                        <h3 class="mt-6 text-lg font-semibold text-paper text-balance"><span class="sr-only">Passo {{ $i + 1 }}: </span>{{ $step['title'] }}</h3>
                        @if ($step['text'])<p class="mt-2 text-pretty text-smoke">{{ $step['text'] }}</p>@endif
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</section>
@endif
