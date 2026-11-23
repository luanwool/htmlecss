<x-layouts.account title="Indicar e ganhar">
    @if (! $code)
        <div class="card text-sm">
            <p>O programa de indicação não está disponível no momento.</p>
            <a href="{{ route('home') }}" class="btn-ghost-dark mt-3">Voltar</a>
        </div>
    @else
        @php $url = $code->url(); @endphp
        <div class="grid gap-6 lg:grid-cols-3">
            <section class="card lg:col-span-2 bg-ink text-paper" style="background-image:radial-gradient(40rem 20rem at 100% 0%, color-mix(in oklab, var(--color-accent) 20%, transparent), transparent 60%)">
                <p class="hud text-accent">Indique e ganhe</p>
                <h2 class="mt-2 font-display text-3xl text-paper">Seu código: <span class="font-mono tracking-widest text-accent">{{ $code->code }}</span></h2>
                <p class="mt-3 text-smoke">Compartilhe o link abaixo. Quem fizer o primeiro atendimento com ele ganha
                    <strong class="text-paper">{{ $cfg['referred_percent'] }}%</strong> de desconto,
                    e você ganha <strong class="text-paper">{{ $cfg['referrer_percent'] }}%</strong> de crédito
                    @if ($cfg['credit_valid_days']) (vale por {{ $cfg['credit_valid_days'] }} dias) @endif.</p>
                <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                    <input id="ref-url" value="{{ $url }}" readonly class="form-input flex-1 font-mono text-sm" onclick="this.select()">
                    <button type="button" class="btn-dark" data-copy="ref-url" data-ok="Link copiado!">Copiar link</button>
                </div>
                <div class="mt-4 grid grid-cols-3 gap-3 text-center text-xs text-smoke">
                    <div class="rounded-lg bg-white/5 p-3"><p class="font-mono text-2xl text-paper">{{ $code->visits_count }}</p>cliques no link</div>
                    <div class="rounded-lg bg-white/5 p-3"><p class="font-mono text-2xl text-paper">{{ $code->signups_count }}</p>novas contas</div>
                    <div class="rounded-lg bg-white/5 p-3"><p class="font-mono text-2xl text-paper">{{ $referrals->where('status', \App\Enums\ReferralStatus::Rewarded)->count() }}</p>recompensas</div>
                </div>
            </section>
            <aside class="card">
                <p class="hud">Seu saldo</p>
                <p class="kpi-value mt-2 font-display text-4xl">{{ \App\Support\Money::format($balance) }}</p>
                <p class="mt-1 text-sm text-stone">Aplicado automaticamente no próximo atendimento avulso (teto {{ $cfg['max_percent_per_visit'] }}% do valor).</p>
                @if ($credits->isNotEmpty())
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($credits as $c)
                            <li class="flex justify-between gap-3 border-t border-black/[.06] pt-2">
                                <span>
                                    {{ $c->note ?: ($c->role === 'referrer' ? 'Indicação' : 'Bônus') }}
                                    @if ($c->expires_on)<br><span class="text-xs text-stone">vence {{ $c->expires_on->format('d/m/Y') }}</span>@endif
                                </span>
                                <span class="font-mono text-right">
                                    {{ \App\Support\Money::format($c->balance()) }}
                                    @if ($c->used_cents)<br><span class="text-xs text-stone">(usados {{ \App\Support\Money::format($c->used_cents) }})</span>@endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </aside>
        </div>

        @if ($referrals->isNotEmpty())
            <section class="card mt-6">
                <h2 class="font-semibold">Seus convidados</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($referrals as $r)
                        <li class="flex items-center justify-between gap-3 border-t border-black/[.06] pt-2">
                            <span>{{ $r->referred?->name ? \Illuminate\Support\Str::of($r->referred->name)->before(' ').' '.\Illuminate\Support\Str::of($r->referred->name)->after(' ')->substr(0, 1).'.' : '—' }}</span>
                            <span class="{{ $r->status->badge() }}">{{ $r->status->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
</x-layouts.account>
