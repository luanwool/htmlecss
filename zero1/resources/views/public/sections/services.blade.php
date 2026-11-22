<section id="servicos" class="bg-paper py-20 text-ink md:py-28" aria-labelledby="servicos-title">
    <div class="container-site">
        <div class="grid gap-10 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <div class="lg:sticky lg:top-28">
                    @if ($section->eyebrow)<p class="eyebrow reveal text-graphite/60">{{ $section->eyebrow }}</p>@endif
                    <h2 id="servicos-title" class="display-lg reveal mt-4">{{ $section->title }}</h2>
                    @if ($section->subtitle)<p class="reveal mt-5 max-w-sm text-graphite/75">{{ $section->subtitle }}</p>@endif
                </div>
            </div>

            <div class="lg:col-span-8">
                @forelse ($services->groupBy(fn ($s) => $s->category?->name ?? '') as $category => $items)
                    <div class="{{ ! $loop->first ? 'mt-14' : '' }}">
                        @if ($category !== '')
                            <h3 class="eyebrow reveal mb-2 flex items-center gap-3 text-graphite/60">
                                <span class="font-display text-base normal-case tracking-normal text-accent" style="filter:brightness(.75)">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $category }}
                            </h3>
                        @endif
                        <ul class="divide-y divide-ink/10 border-y border-ink/10" data-stagger>
                            @foreach ($items as $service)
                                <li class="reveal group grid grid-cols-[1fr_auto] items-start gap-x-6 gap-y-2 py-6 sm:grid-cols-[1fr_auto_auto] sm:items-center">
                                    <div class="min-w-0">
                                        <p class="font-display text-2xl leading-tight sm:text-[1.7rem]">{{ $service->name }}</p>
                                        @if ($service->description)<p class="mt-1.5 max-w-lg text-sm text-graphite/70">{{ $service->description }}</p>@endif
                                        <p class="mt-2 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wider text-graphite/55">
                                            <x-icon name="clock" class="size-3.5" />{{ $service->formattedDuration() }}
                                            @if ($service->is_plan_eligible && $plans->isNotEmpty())<span class="ml-2 rounded-full bg-ink px-2 py-0.5 text-[0.65rem] text-paper">Incluso em planos</span>@endif
                                        </p>
                                    </div>
                                    <p class="text-right font-display text-xl whitespace-nowrap sm:text-2xl">
                                        @if ($service->price_is_from && $service->price_cents !== null)<span class="block text-[0.65rem] font-sans uppercase tracking-wider text-graphite/55">a partir de</span>@endif
                                        {{ \App\Support\Money::format($service->price_cents) }}
                                    </p>
                                    @if ($service->is_bookable_standalone)
                                        <a href="{{ route('booking.start', ['servico' => $service->id]) }}" class="btn-dark col-span-2 min-h-10 px-5 text-xs sm:col-span-1"
                                           aria-label="Agendar {{ $service->name }}">Agendar</a>
                                    @else
                                        <span class="col-span-2 text-xs text-graphite/55 sm:col-span-1 sm:w-24 sm:text-right">Somente no plano</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <div class="reveal rounded-2xl border border-dashed border-ink/20 p-8 text-graphite/75">
                        <p class="font-display text-2xl text-ink">Tabela de serviços em atualização.</p>
                        <p class="mt-2">Consulte valores e disponibilidade diretamente com a barbearia.</p>
                        @if ($wa = $site->whatsappUrl('Olá! Gostaria de saber os serviços e valores.'))
                            <a href="{{ $wa }}" target="_blank" rel="noopener" class="btn-dark mt-5">Perguntar no WhatsApp</a>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</section>
