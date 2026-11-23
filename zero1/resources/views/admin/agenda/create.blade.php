@php
    $q = fn (array $over) => route('admin.agenda.create', array_filter(array_merge(['cliente_id' => $customer?->id, 'servico' => $service?->id, 'profissional' => $barberId, 'data' => $date->toDateString(), 'modo' => $mode], $over), fn ($v) => $v !== null));
@endphp
<x-layouts.admin title="Novo agendamento">
    @error('booking')
        <div class="mb-4 rounded-xl bg-red-50 p-4 text-sm text-red-900" role="alert">{{ $message }}
            @if ($alts = session('alternatives'))<span class="mt-2 block">Livres: @foreach ($alts as $alt)<strong>{{ $alt['time'] }}</strong>@if (! $loop->last), @endif @endforeach</span>@endif
        </div>
    @enderror
    <div class="grid max-w-5xl gap-6 lg:grid-cols-2">
        <section class="card space-y-4">
            <h2 class="font-semibold">1. Cliente</h2>
            @if ($customer)
                <p class="rounded-xl bg-paper p-3 text-sm"><strong>{{ $customer->name }}</strong> {{ $customer->phone }} · <a class="underline" href="{{ $q(['cliente_id' => null]) }}">trocar</a></p>
                @if ($sub)
                    <p class="text-sm">Assinatura: <span class="{{ $sub->status->badge() }}">{{ $sub->status->label() }}</span></p>
                    <div class="flex gap-2 text-sm">
                        <a href="{{ $q(['modo' => 'avulso']) }}" @class(['rounded-full px-4 py-2', 'bg-ink text-paper' => $mode === 'avulso', 'border border-black/15' => $mode !== 'avulso'])>Avulso</a>
                        <a href="{{ $q(['modo' => 'plan']) }}" @class(['rounded-full px-4 py-2', 'bg-ink text-paper' => $mode === 'plan', 'border border-black/15' => $mode !== 'plan'])>Pelo plano</a>
                    </div>
                @endif
            @else
                <form method="GET" class="flex gap-2">
                    <input type="search" name="busca" value="{{ request('busca') }}" placeholder="Nome, telefone ou e-mail" class="form-input" aria-label="Buscar cliente">
                    <button class="btn-ghost-dark min-h-11 px-4 text-xs">Buscar</button>
                </form>
                @foreach ($customers as $c)<a href="{{ $q(['cliente_id' => $c->id]) }}" class="block rounded-xl border border-black/10 p-3 text-sm hover:border-ink">{{ $c->name }} <span class="text-stone">{{ $c->phone }} {{ $c->email }}</span></a>@endforeach
                <p class="text-xs text-stone">Cliente novo? Preencha os dados na confirmação.</p>
            @endif

            <h2 class="pt-2 font-semibold">2. Serviço e profissional</h2>
            <form method="GET" class="grid gap-3 sm:grid-cols-2">
                @foreach (['cliente_id' => $customer?->id, 'modo' => $mode] as $k => $val)@if ($val)<input type="hidden" name="{{ $k }}" value="{{ $val }}">@endif @endforeach
                <select name="servico" class="form-input" aria-label="Serviço" required><option value="">Serviço…</option>@foreach ($services as $s)<option value="{{ $s->id }}" @selected($service?->id === $s->id)>{{ $s->name }} ({{ $s->formattedDuration() }})</option>@endforeach</select>
                <select name="profissional" class="form-input" aria-label="Profissional"><option value="qualquer">Qualquer disponível</option>@foreach ($barbers as $b)<option value="{{ $b->id }}" @selected((string) $barberId === (string) $b->id)>{{ $b->display_name }}</option>@endforeach</select>
                <input type="date" name="data" value="{{ $date->toDateString() }}" class="form-input" aria-label="Data">
                <button class="btn-ghost-dark min-h-11">Ver horários</button>
            </form>
        </section>

        <section class="card space-y-4">
            <h2 class="font-semibold">3. Horário e confirmação</h2>
            @if (! $service)
                <p class="text-sm text-stone">Escolha o serviço para ver os horários livres.</p>
            @elseif (! count($slots))
                <p class="text-sm text-stone">Sem horários livres em {{ $date->format('d/m') }} — encaixe só é possível com disponibilidade real.</p>
            @else
                <form method="POST" action="{{ route('admin.agenda.store') }}" class="space-y-4" data-loading>
                    @csrf
                    <input type="hidden" name="service_id" value="{{ $service->id }}"><input type="hidden" name="barber_id" value="{{ $barberId === 'qualquer' ? '' : $barberId }}">
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}"><input type="hidden" name="mode" value="{{ $mode }}">
                    <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
                        @foreach ($slots as $s)
                            <label class="cursor-pointer"><input type="radio" name="time" value="{{ $s['time'] }}" class="peer sr-only" required @checked(old('time') === $s['time'])>
                                <span class="flex min-h-10 items-center justify-center rounded-lg border border-black/15 text-sm font-semibold peer-checked:bg-ink peer-checked:text-paper">{{ $s['time'] }}</span></label>
                        @endforeach
                    </div>
                    @if ($customer)
                        <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                    @else
                        <div class="grid gap-3 sm:grid-cols-3">
                            <x-form.input name="new_name" label="Nome do cliente" required />
                            <x-form.input name="new_phone" type="tel" label="Telefone" />
                            <x-form.input name="new_email" type="email" label="E-mail (para confirmação)" />
                        </div>
                    @endif
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-form.select name="origin" label="Canal" :options="['telefone' => 'Telefone', 'whatsapp' => 'WhatsApp', 'balcao' => 'Balcão', 'painel' => 'Painel']" value="telefone" />
                        <x-form.input name="notes" label="Observação" />
                    </div>
                    <button class="btn-dark w-full">Confirmar agendamento</button>
                </form>
            @endif
        </section>
    </div>
</x-layouts.admin>
