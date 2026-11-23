@php
    $q = fn (array $over) => route('booking.start', array_filter(array_merge([
        'servico' => $service?->id, 'profissional' => $barberId ?? 'qualquer', 'data' => $date?->toDateString(), 'modo' => $mode,
    ], $over), fn ($v) => $v !== null));
    $chosenBarber = $barberId ? $barbers->firstWhere('id', $barberId) : null;
@endphp
<x-layouts.public title="Agendar horário" :noindex="true" body-class="bg-paper text-ink">
    <section class="container-site max-w-4xl py-10 md:py-16">
        <p class="eyebrow text-graphite/60">Agendamento</p>
        <h1 class="display-lg mt-3">Agende seu horário</h1>

        @if ($subscription && ! $planUsable)
            <div class="mt-6 rounded-2xl bg-amber-100 p-4 text-sm text-amber-950">
                Seu plano está <strong>{{ $subscription->status->label() }}</strong>: agendamentos pelo plano ficam bloqueados até a confirmação do pagamento.
                <a href="{{ route('account.subscription') }}" class="font-semibold underline">Ver assinatura</a>. Você ainda pode agendar como avulso.
            </div>
        @endif
        @error('booking')
            <div class="mt-6 rounded-2xl border border-red-600/20 bg-red-50 p-4 text-sm text-red-900" role="alert">
                <p class="font-semibold">{{ $message }}</p>
                @if ($alts = session('alternatives'))
                    <p class="mt-2">Horários próximos disponíveis:</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($alts as $alt)<a href="{{ $q(['hora' => $alt['time']]) }}" class="rounded-full bg-white px-3 py-1 font-semibold">{{ $alt['time'] }}</a>@endforeach
                    </div>
                @endif
            </div>
        @enderror

        <ol class="mt-10 space-y-10">
            {{-- 1. Serviço --}}
            <li>
                <h2 class="flex items-center gap-3 font-semibold"><span class="flex size-7 items-center justify-center rounded-full bg-ink text-xs text-paper">1</span> Serviço</h2>
                @if ($services->isEmpty())
                    <p class="mt-4 text-sm text-graphite/70">Nenhum serviço disponível para agendamento online no momento.
                        @if ($wa = $site->whatsappUrl())<a class="underline" href="{{ $wa }}" target="_blank" rel="noopener">Fale pelo WhatsApp</a>.@endif</p>
                @else
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        @foreach ($services as $s)
                            @php $isCovered = $planUsable && in_array($s->id, $covered); @endphp
                            <a href="{{ route('booking.start', ['servico' => $s->id]) }}" @if ($service?->id === $s->id) aria-current="true" @endif
                               @class(['flex items-center justify-between gap-4 rounded-2xl border p-4 transition', 'border-ink bg-ink text-paper' => $service?->id === $s->id, 'border-black/10 bg-white hover:border-ink' => $service?->id !== $s->id])>
                                <span><span class="font-semibold">{{ $s->name }}</span><span class="block text-xs opacity-70">{{ $s->formattedDuration() }}</span></span>
                                <span class="text-right text-sm">@if ($isCovered)<span class="badge bg-accent text-accent-ink">No plano</span>@else{{ $s->formattedPrice() }}@endif</span>
                            </a>
                        @endforeach
                    </div>
                @endif
                @if ($service && $planUsable && in_array($service->id, $covered))
                    <div class="mt-3 flex gap-2 text-sm" role="group" aria-label="Forma de uso">
                        <a href="{{ $q(['modo' => 'plan', 'hora' => null]) }}" @class(['rounded-full px-4 py-2', 'bg-ink text-paper' => $mode === 'plan', 'border border-black/15' => $mode !== 'plan'])>Usar meu plano</a>
                        @if ($service->is_bookable_standalone)
                            <a href="{{ $q(['modo' => 'avulso', 'hora' => null]) }}" @class(['rounded-full px-4 py-2', 'bg-ink text-paper' => $mode === 'avulso', 'border border-black/15' => $mode !== 'avulso'])>Pagar avulso ({{ $service->formattedPrice() }})</a>
                        @endif
                    </div>
                @endif
            </li>

            {{-- 2. Profissional --}}
            @if ($service)
                <li>
                    <h2 class="flex items-center gap-3 font-semibold"><span class="flex size-7 items-center justify-center rounded-full bg-ink text-xs text-paper">2</span> Profissional</h2>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ $q(['profissional' => 'qualquer', 'hora' => null]) }}" @class(['rounded-full px-4 py-2.5 text-sm font-medium', 'bg-ink text-paper' => ! $barberId, 'border border-black/15 bg-white' => $barberId])>Qualquer barbeiro disponível</a>
                        @foreach ($barbers as $b)
                            <a href="{{ $q(['profissional' => $b->id, 'hora' => null]) }}" @class(['rounded-full px-4 py-2.5 text-sm font-medium', 'bg-ink text-paper' => $barberId === $b->id, 'border border-black/15 bg-white' => $barberId !== $b->id])>{{ $b->display_name }}</a>
                        @endforeach
                    </div>
                </li>

                {{-- 3. Data --}}
                <li>
                    <h2 class="flex items-center gap-3 font-semibold"><span class="flex size-7 items-center justify-center rounded-full bg-ink text-xs text-paper">3</span> Data</h2>
                    <div class="-mx-4 mt-4 flex gap-2 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0">
                        @foreach ($days as $d)
                            @php $sel = $date && $date->isSameDay($d); @endphp
                            <a href="{{ $q(['data' => $d->toDateString(), 'hora' => null]) }}" @if ($sel) aria-current="date" @endif
                               @class(['flex w-16 shrink-0 flex-col items-center rounded-2xl border py-3 text-center', 'border-ink bg-ink text-paper' => $sel, 'border-black/10 bg-white hover:border-ink' => ! $sel])>
                                <span class="text-[0.65rem] uppercase tracking-wider opacity-70">{{ $d->locale('pt_BR')->translatedFormat('D') }}</span>
                                <span class="font-display text-2xl">{{ $d->format('d') }}</span>
                                <span class="text-[0.65rem] opacity-70">{{ $d->locale('pt_BR')->translatedFormat('M') }}</span>
                            </a>
                        @endforeach
                    </div>
                </li>
            @endif

            {{-- 4. Horário --}}
            @if ($service && $date)
                <li>
                    <h2 class="flex items-center gap-3 font-semibold"><span class="flex size-7 items-center justify-center rounded-full bg-ink text-xs text-paper">4</span> Horário · {{ $date->locale('pt_BR')->translatedFormat('l, d/m') }}</h2>
                    <div class="mt-4">
                        <x-slot-grid :slots="$slots" :selected="$selected['time'] ?? null" :href="fn ($s) => $q(['hora' => $s['time']]).'#confirmar'" />
                    </div>
                </li>
            @endif

            {{-- 5. Confirmação --}}
            @if ($selected)
                <li id="confirmar" class="scroll-mt-24">
                    <h2 class="flex items-center gap-3 font-semibold"><span class="flex size-7 items-center justify-center rounded-full bg-accent text-xs text-accent-ink">5</span> Confirme</h2>
                    <div class="mt-4 rounded-3xl bg-white p-6 shadow-sm">
                        <dl class="grid gap-3 text-sm sm:grid-cols-2">
                            <div><dt class="text-graphite/60">Serviço</dt><dd class="font-semibold">{{ $service->name }} · {{ $service->formattedDuration() }}</dd></div>
                            <div><dt class="text-graphite/60">Quando</dt><dd class="font-semibold">{{ ucfirst($date->locale('pt_BR')->translatedFormat('l, d/m/Y')) }} às {{ $selected['time'] }}</dd></div>
                            <div><dt class="text-graphite/60">Profissional</dt><dd class="font-semibold">{{ $chosenBarber?->display_name ?? 'Qualquer disponível (definido na confirmação)' }}</dd></div>
                            <div><dt class="text-graphite/60">Pagamento</dt><dd class="font-semibold">{{ $mode === 'plan' ? 'Usa 1 atendimento do seu plano' : $service->formattedPrice().' — pago na barbearia' }}</dd></div>
                            @if ($address = $site->fullAddress())<div class="sm:col-span-2"><dt class="text-graphite/60">Local</dt><dd>{{ $address }}</dd></div>@endif
                        </dl>
                        <p class="mt-4 text-xs text-graphite/60">Cancelamentos e reagendamentos até {{ app(\App\Services\Booking\BookingPolicy::class)->cancelMinHours() }}h antes, pela sua área do cliente.
                            @if ($mode === 'plan' && app(\App\Services\Booking\BookingPolicy::class)->lateCancelForfeits()) Cancelamentos fora do prazo e faltas consomem o atendimento do plano.@endif</p>
                        @auth
                            @if (auth()->user()->isStaff())
                                <p class="mt-5 text-sm text-graphite/70">Contas da equipe agendam pelo <a class="underline" href="{{ route('admin.agenda.create') }}">painel</a>.</p>
                            @else
                                <form method="POST" action="{{ route('booking.store') }}" class="mt-5" data-loading>
                                    @csrf
                                    <input type="hidden" name="service_id" value="{{ $service->id }}">
                                    <input type="hidden" name="barber_id" value="{{ $barberId }}">
                                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                                    <input type="hidden" name="time" value="{{ $selected['time'] }}">
                                    <input type="hidden" name="mode" value="{{ $mode }}">
                                    <x-form.input name="notes" label="Observação (opcional)" maxlength="300" />
                                    <button class="btn-dark mt-4 w-full sm:w-auto" data-loading-text="Confirmando…">Confirmar agendamento</button>
                                </form>
                            @endif
                        @else
                            <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                                <a href="{{ route('login') }}" class="btn-dark">Entrar para confirmar</a>
                                <a href="{{ route('register') }}" class="btn-ghost-dark">Criar conta</a>
                            </div>
                        @endauth
                    </div>
                </li>
            @endif
        </ol>
    </section>
</x-layouts.public>
