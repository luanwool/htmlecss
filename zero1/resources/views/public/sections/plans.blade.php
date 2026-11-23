<section id="clube" class="relative overflow-hidden bg-ink py-20 md:py-28" aria-labelledby="clube-title">
    <div aria-hidden="true" class="fx-glow pointer-events-none absolute inset-0 opacity-80"></div>
    <div class="container-site relative">
        <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
            <div class="max-w-2xl">
                @if ($section->eyebrow)<p class="eyebrow reveal text-accent">{{ $section->eyebrow }}</p>@endif
                <h2 id="clube-title" class="display-lg reveal mt-4 text-paper">{{ $section->title }}</h2>
            </div>
            @if ($section->subtitle)<p class="reveal max-w-md text-smoke">{{ $section->subtitle }}</p>@endif
        </div>

        @if ($plans->isNotEmpty())
            <div data-stagger="cards" class="mt-14 grid gap-5 {{ $plans->count() === 1 ? 'max-w-xl' : ($plans->count() === 2 ? 'max-w-5xl md:grid-cols-2' : 'md:grid-cols-2 xl:grid-cols-3') }}">
                @foreach ($plans as $plan)
                    <article class="reveal glass spotlight relative flex flex-col rounded-3xl p-7 sm:p-9 {{ $plan->is_featured ? 'glow-border' : '' }}" aria-labelledby="plan-{{ $plan->id }}">
                        @if ($plan->is_featured)
                            <span class="hud absolute right-6 top-6 rounded-full bg-accent px-3 py-1 text-[0.6rem] text-accent-ink">Destaque</span>
                        @endif
                        <h3 id="plan-{{ $plan->id }}" class="font-future text-2xl font-semibold tracking-tight text-paper">{{ $plan->name }}</h3>
                        @if ($plan->description)<p class="mt-3 text-sm text-smoke">{{ $plan->description }}</p>@endif

                        <p class="mt-7 flex items-baseline gap-2">
                            <span class="font-mono text-5xl tracking-tight text-paper">{{ $plan->formattedPrice() }}</span>
                            <span class="hud">/mês</span>
                        </p>
                        <p class="mt-2 font-mono text-sm text-accent">{{ $plan->usageSummary() }}</p>

                        <div class="hairline my-6"></div>
                        <p class="hud">Serviços cobertos</p>
                        <ul class="mt-3 space-y-2 text-sm text-paper">
                            @foreach ($plan->services as $s)
                                <li class="flex gap-2.5"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-accent" />
                                    <span>{{ $s->name }}@if ($s->pivot->uses_per_cycle) <span class="font-mono text-stone">· {{ $s->pivot->uses_per_cycle }}x/mês</span>@endif</span>
                                </li>
                            @endforeach
                        </ul>

                        @if ($plan->benefits)
                            <p class="hud mt-6">Vantagens</p>
                            <ul class="mt-3 space-y-2 text-sm text-paper">
                                @foreach ($plan->benefits as $b)
                                    <li class="flex gap-2.5"><x-icon name="star" class="mt-0.5 size-4 shrink-0 text-ion" /><span>{{ $b }}</span></li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($rules = $plan->ruleLines())
                            <details class="group mt-6 rounded-xl border border-white/10 px-4 py-3 text-sm">
                                <summary class="flex min-h-6 items-center justify-between text-smoke">Regras de uso
                                    <x-icon name="arrow-right" class="size-4 transition group-open:rotate-90" /></summary>
                                <ul class="mt-3 list-disc space-y-1 pl-5 text-smoke">
                                    @foreach ($rules as $r)<li>{{ $r }}</li>@endforeach
                                    @if ($plan->cancellation_policy)<li>Cancelamento: {{ $plan->cancellation_policy }}</li>@endif
                                    @if ($plan->no_show_policy)<li>Faltas: {{ $plan->no_show_policy }}</li>@endif
                                </ul>
                            </details>
                        @endif

                        <div class="mt-auto pt-8">
                            <a href="{{ route('plans.subscribe', $plan) }}" class="{{ $plan->is_featured ? 'btn-accent' : 'btn-light' }} w-full">Assinar agora</a>
                            <p class="mt-3 text-center text-xs text-stone">Renovação mensal. Os usos são liberados após a confirmação do pagamento de cada mês.</p>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="reveal glass mt-12 max-w-2xl rounded-3xl p-8">
                <p class="text-xl font-semibold text-paper">Os planos do Clube Zero 1 estão sendo preparados.</p>
                <p class="mt-2 text-smoke">Em breve você poderá assinar online. Enquanto isso, agende normalmente seu horário.</p>
                <a href="{{ route('booking.start') }}" class="btn-accent mt-6">Agendar horário</a>
            </div>
        @endif
    </div>
</section>
