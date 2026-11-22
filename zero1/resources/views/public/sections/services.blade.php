<section id="servicos" class="relative overflow-hidden bg-graphite py-20 md:py-28" aria-labelledby="servicos-title">
    <div aria-hidden="true" class="fx-grid pointer-events-none absolute inset-0 opacity-60"></div>
    <div class="container-site relative">
        <div class="grid gap-10 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <div class="lg:sticky lg:top-28">
                    @if ($section->eyebrow)<p class="eyebrow reveal text-accent">{{ $section->eyebrow }}</p>@endif
                    <h2 id="servicos-title" class="display-lg reveal mt-4 text-paper">{{ $section->title }}</h2>
                    @if ($section->subtitle)<p class="reveal mt-5 max-w-sm text-smoke">{{ $section->subtitle }}</p>@endif
                </div>
            </div>

            <div class="lg:col-span-8">
                @forelse ($services->groupBy(fn ($s) => $s->category?->name ?? '') as $category => $items)
                    <div class="{{ ! $loop->first ? 'mt-12' : '' }}">
                        @if ($category !== '')
                            <h3 class="hud reveal mb-3 flex items-center gap-3"><span class="text-accent">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $category }}</h3>
                        @endif
                        <ul class="grid gap-2" data-stagger>
                            @foreach ($items as $service)
                                <li class="reveal glass spotlight grid grid-cols-[1fr_auto] items-center gap-x-5 gap-y-3 rounded-2xl p-5 sm:grid-cols-[1fr_auto_auto]">
                                    <div class="min-w-0">
                                        <p class="text-lg font-semibold text-paper sm:text-xl">{{ $service->name }}</p>
                                        @if ($service->description)<p class="mt-1 max-w-lg text-sm text-smoke">{{ $service->description }}</p>@endif
                                        <p class="mt-2 flex flex-wrap items-center gap-2">
                                            <span class="hud inline-flex items-center gap-1.5"><x-icon name="clock" class="size-3.5" />{{ $service->formattedDuration() }}</span>
                                            @if ($service->is_plan_eligible && $plans->isNotEmpty())<span class="rounded-full border border-accent/40 px-2 py-0.5 font-mono text-[0.65rem] uppercase tracking-wider text-accent">no plano</span>@endif
                                        </p>
                                    </div>
                                    <p class="text-right font-mono text-lg text-paper whitespace-nowrap sm:text-xl">
                                        @if ($service->price_is_from && $service->price_cents !== null)<span class="hud block text-[0.6rem]">a partir de</span>@endif
                                        {{ \App\Support\Money::format($service->price_cents) }}
                                    </p>
                                    @if ($service->is_bookable_standalone)
                                        <a href="{{ route('booking.start', ['servico' => $service->id]) }}" class="btn-light col-span-2 min-h-10 px-5 text-xs sm:col-span-1" aria-label="Agendar {{ $service->name }}">Agendar</a>
                                    @else
                                        <span class="hud col-span-2 sm:col-span-1 sm:text-right">Só no plano</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    @if ($loop->last)
                        <div class="reveal mt-6 flex flex-col gap-3 rounded-2xl border border-dashed border-white/15 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-smoke">Não sabe qual escolher? Veja os horários livres e decida com calma.</p>
                            <a href="{{ route('booking.start') }}" class="btn-ghost-light shrink-0">Ver horários livres</a>
                        </div>
                    @endif
                @empty
                    <div class="reveal glass rounded-3xl p-8">
                        <p class="text-xl font-semibold text-paper">Tabela de serviços em atualização.</p>
                        <p class="mt-2 text-smoke">Consulte valores e disponibilidade diretamente com a barbearia.</p>
                        @if ($wa = $site->whatsappUrl('Olá! Gostaria de saber os serviços e valores.'))
                            <a href="{{ $wa }}" target="_blank" rel="noopener" class="btn-light mt-5">Perguntar no WhatsApp</a>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</section>
