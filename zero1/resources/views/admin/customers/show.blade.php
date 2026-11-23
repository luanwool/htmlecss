<x-layouts.admin :title="$customer->name">
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card space-y-3 text-sm">
            <h2 class="font-semibold">Contato</h2>
            <p><span class="text-stone">E-mail:</span> {{ $customer->email ?? '—' }}</p>
            <p><span class="text-stone">Telefone:</span> {{ $customer->phone ?? '—' }}</p>
            <p><span class="text-stone">CPF:</span> {{ \App\Rules\Cpf::format($customer->cpf) ?? '—' }}</p>
            <p><span class="text-stone">Origem:</span> {{ ucfirst($customer->origin) }}</p>
            <p><span class="text-stone">Termos aceitos:</span> {{ \App\Support\BusinessTime::format($customer->terms_accepted_at) }}</p>
            <p><span class="text-stone">Marketing:</span> {{ $customer->marketing_consent_at ? 'consentiu em '.\App\Support\BusinessTime::format($customer->marketing_consent_at, 'd/m/Y') : 'não' }}</p>
            @if ($customer->user_id)
                @if (session('temp_password'))
                    <div class="rounded-xl border border-amber-500/40 bg-amber-100/40 p-3 text-xs">
                        <p class="font-semibold text-ink">Senha temporária (mostrada só agora):</p>
                        <p class="mt-1 select-all font-mono text-base tracking-wide text-ink">{{ session('temp_password') }}</p>
                        <p class="mt-2 text-stone">Passe ao cliente por WhatsApp/pessoalmente. Na próxima entrada, ele será levado à tela de nova senha.</p>
                    </div>
                @endif
                <form method="POST" action="{{ route('admin.customers.reset-password', $customer) }}" class="pt-2" onsubmit="return confirm('Gerar uma nova senha temporária para {{ $customer->name }}? A senha atual deixará de valer.')">
                    @csrf
                    <button class="btn-dark min-h-9 w-full px-4 text-xs" type="submit">Gerar senha temporária</button>
                </form>
            @else
                <p class="text-xs text-stone">Sem login de cliente ainda — nada a redefinir.</p>
            @endif
        </section>
        <section class="card space-y-6 lg:col-span-2">
            @php
                $subs = $customer->subscriptions()->latest('id')->get();
                $appts = $customer->appointments()->with('barber')->latest('starts_at')->limit(20)->get();
                $visits = $customer->visits()->latest('performed_at')->get();
            @endphp
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-xl bg-paper p-3"><p class="font-display text-2xl">{{ $visits->count() }}</p><p class="text-xs text-stone">atendimentos</p></div>
                <div class="rounded-xl bg-paper p-3"><p class="font-display text-2xl">{{ $visits->first() ? \App\Support\BusinessTime::format($visits->first()->performed_at, 'd/m/y') : '—' }}</p><p class="text-xs text-stone">última visita</p></div>
                <div class="rounded-xl bg-paper p-3"><p class="font-display text-2xl">{{ $customer->appointments()->where('status', 'no_show')->count() }}</p><p class="text-xs text-stone">faltas</p></div>
            </div>
            <div>
                <h2 class="font-semibold">Assinaturas</h2>
                <ul class="mt-2 text-sm">@forelse ($subs as $s)<li><a class="underline" href="{{ route('admin.subscriptions.show', $s) }}">{{ $s->term('name') }}</a> · <span class="{{ $s->status->badge() }}">{{ $s->status->label() }}</span></li>@empty<li class="text-stone">Nenhuma.</li>@endforelse</ul>
            </div>
            <div>
                <div class="flex items-center justify-between"><h2 class="font-semibold">Agendamentos</h2>@can('agenda.manage')<a href="{{ route('admin.agenda.create', ['cliente_id' => $customer->id]) }}" class="btn-dark min-h-9 px-4 text-xs">Agendar</a>@endcan</div>
                <ul class="mt-2 divide-y divide-black/[.06] text-sm">@forelse ($appts as $a)<li class="flex justify-between py-1.5"><a class="underline" href="{{ route('admin.agenda.show', $a) }}">{{ $a->localStart()->format('d/m/Y H:i') }} · {{ $a->service_name_snapshot }} · {{ $a->barber->display_name }}</a><span class="{{ $a->status->badge() }}">{{ $a->status->label() }}</span></li>@empty<li class="text-stone">Nenhum.</li>@endforelse</ul>
            </div>
        </section>
    </div>
</x-layouts.admin>
