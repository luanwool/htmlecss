{{-- Formulário de atendimento de balcão (gestor e barbeiro). Usa o mesmo motor de agenda: precisa de horário livre. --}}
<div class="space-y-6">
    <section class="card space-y-4">
        <h2 class="font-semibold">Cliente</h2>
        @if ($customer)
            <input type="hidden" name="customer_id" value="{{ $customer->id }}">
            <p class="rounded-xl bg-paper p-3 text-sm"><strong>{{ $customer->name }}</strong> {{ $customer->phone }} · <a class="underline" href="{{ url()->current() }}">trocar</a></p>
        @else
            <div class="flex gap-2">
                <input type="search" name="busca" form="customer-search" value="{{ request('busca') }}" placeholder="Buscar cliente cadastrado (nome ou telefone)" class="form-input" aria-label="Buscar cliente">
                <button form="customer-search" class="btn-ghost-dark min-h-11 px-4 text-xs">Buscar</button>
            </div>
            @foreach ($customers as $c)
                <a href="{{ url()->current().'?cliente_id='.$c->id }}" class="block rounded-xl border border-black/10 p-3 text-sm hover:border-ink">{{ $c->name }} <span class="text-stone">{{ $c->phone }}</span></a>
            @endforeach
            <p class="text-xs text-stone">Ou cadastre um novo cliente (dados mínimos):</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="new_name" label="Nome" />
                <x-form.input name="new_phone" type="tel" label="Celular (opcional)" />
            </div>
            <x-form.checkbox name="consent" label="O cliente autorizou o cadastro destes dados para atendimento" />
        @endif
    </section>
    <section class="card space-y-4">
        <h2 class="font-semibold">Atendimento</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            @unless ($fixedBarber)
                <x-form.select name="barber_id" label="Profissional" :options="$barbers->pluck('display_name', 'id')->all()" required />
            @endunless
            <x-form.select name="service_id" label="Serviço" :options="$services->mapWithKeys(fn ($s) => [$s->id => $s->name.' — '.$s->formattedPrice()])->all()" required />
            <x-form.input name="date" type="date" label="Data" :value="$now->toDateString()" required />
            <x-form.input name="time" type="time" label="Início" :value="$now->format('H:').str_pad((string) (intdiv((int) $now->format('i'), 15) * 15), 2, '0', STR_PAD_LEFT)" step="300" required />
            <x-form.select name="origin" label="Origem" :options="$origins" value="balcao" />
        </div>
        <fieldset class="flex gap-2">
            <legend class="form-label">Modalidade</legend>
            <label class="cursor-pointer rounded-full border border-black/15 px-4 py-2 text-sm has-[:checked]:bg-ink has-[:checked]:text-paper"><input type="radio" name="mode" value="avulso" class="sr-only" @checked(old('mode', 'avulso') === 'avulso')> Avulso</label>
            <label class="cursor-pointer rounded-full border border-black/15 px-4 py-2 text-sm has-[:checked]:bg-ink has-[:checked]:text-paper"><input type="radio" name="mode" value="plan" class="sr-only" @checked(old('mode') === 'plan')> Assinante (usa o plano)</label>
        </fieldset>
        <x-visit-fields :payment-methods="$paymentMethods" show-when="mode=avulso" />
        @error('payment_method')<p class="form-error">{{ $message }}</p>@enderror
        <x-form.input name="notes" label="Observação" maxlength="300" />
    </section>
    <button class="btn-dark w-full sm:w-auto">Registrar atendimento</button>
</div>
