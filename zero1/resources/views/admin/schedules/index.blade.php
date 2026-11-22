<x-layouts.admin title="Escalas e bloqueios">
    <p class="mb-6 max-w-3xl text-sm text-stone">Os horários oferecidos no site saem destas escalas — independentes do horário público do salão. Até 3 períodos por dia (o intervalo entre eles funciona como almoço/pausa).</p>
    <div class="space-y-6">
        @forelse ($barbers as $b)
            @php $byDay = $b->schedules->groupBy('weekday'); @endphp
            <form method="POST" action="{{ route('admin.schedules.weekly', $b) }}" class="card" data-loading>
                @csrf @method('PUT')
                <div class="flex items-center justify-between gap-3"><h2 class="font-semibold">{{ $b->display_name }}</h2><span class="{{ $b->status->value === 'active' ? 'badge-green' : 'badge-amber' }}">{{ $b->status->label() }}</span></div>
                @error('days.*')<p class="form-error">{{ $message }}</p>@enderror
                <div class="mt-4 divide-y divide-black/[.06]">
                    @foreach (range(1, 7) as $d)
                        <div class="grid items-center gap-2 py-2 sm:grid-cols-[9rem_1fr]">
                            <span class="text-sm font-medium">{{ \App\Support\BusinessTime::weekdayName($d) }}</span>
                            <div class="flex flex-wrap gap-3">
                                @foreach (range(0, 2) as $i)
                                    @php $p = ($byDay[$d] ?? collect())->values()[$i] ?? null; @endphp
                                    <span class="flex items-center gap-1">
                                        <input type="time" name="days[{{ $d }}][{{ $i }}][start]" value="{{ $p ? substr($p->starts_at, 0, 5) : '' }}" class="form-input min-h-9 w-28 py-1 text-sm" aria-label="Início período {{ $i + 1 }}">
                                        <span class="text-stone">–</span>
                                        <input type="time" name="days[{{ $d }}][{{ $i }}][end]" value="{{ $p ? substr($p->ends_at, 0, 5) : '' }}" class="form-input min-h-9 w-28 py-1 text-sm" aria-label="Fim período {{ $i + 1 }}">
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <button class="btn-dark mt-4 min-h-10 px-5 text-xs">Salvar escala</button>
            </form>
        @empty
            <p class="card text-sm text-stone">Cadastre profissionais primeiro.</p>
        @endforelse
    </div>

    <div class="mt-10 grid gap-6 lg:grid-cols-2">
        <section class="card">
            <h2 class="font-semibold">Folgas, feriados e aberturas extraordinárias</h2>
            <form method="POST" action="{{ route('admin.schedules.exceptions.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                @csrf
                <x-form.select name="barber_id" label="Profissional" :options="$barbers->pluck('display_name', 'id')->all()" placeholder="Salão inteiro" />
                <x-form.input name="date" type="date" label="Data" required />
                <x-form.select name="type" label="Tipo" :options="['closed' => 'Fechado (folga/feriado)', 'open' => 'Horário especial do profissional']" />
                <x-form.input name="reason" label="Motivo" />
                <div data-show-when="type=open" class="grid gap-3 sm:col-span-2 sm:grid-cols-2">
                    <x-form.input name="starts_at" type="time" label="Início" />
                    <x-form.input name="ends_at" type="time" label="Fim" />
                </div>
                <button class="btn-dark min-h-10 sm:col-span-2">Adicionar</button>
            </form>
            <ul class="mt-6 divide-y divide-black/[.06] text-sm">
                @forelse ($exceptions as $e)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <span>{{ $e->date->format('d/m/Y') }} · {{ $e->barber?->display_name ?? 'Salão' }} · {{ $e->is_closed ? 'Fechado' : substr($e->starts_at, 0, 5).'–'.substr($e->ends_at, 0, 5) }} {{ $e->reason ? '· '.$e->reason : '' }}</span>
                        <form method="POST" action="{{ route('admin.schedules.exceptions.destroy', $e) }}">@csrf @method('DELETE')<button class="text-xs text-red-700 underline">Remover</button></form>
                    </li>
                @empty
                    <li class="py-2 text-stone">Nenhuma exceção futura.</li>
                @endforelse
            </ul>
        </section>
        <section class="card">
            <h2 class="font-semibold">Bloqueios (férias, ausências, bloqueio manual)</h2>
            <form method="POST" action="{{ route('admin.schedules.blocks.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                @csrf
                <x-form.select name="barber_id" label="Profissional" :options="$barbers->pluck('display_name', 'id')->all()" placeholder="Salão inteiro" class="sm:col-span-2" />
                <x-form.input name="start_date" type="date" label="De (data)" required />
                <x-form.input name="start_time" type="time" label="Hora" value="00:00" required />
                <x-form.input name="end_date" type="date" label="Até (data)" required />
                <x-form.input name="end_time" type="time" label="Hora" value="23:59" required />
                <x-form.input name="reason" label="Motivo" class="sm:col-span-2" />
                <x-form.checkbox name="force" label="Bloquear mesmo havendo atendimentos confirmados" class="sm:col-span-2" />
                <button class="btn-dark min-h-10 sm:col-span-2">Bloquear</button>
            </form>
            <ul class="mt-6 divide-y divide-black/[.06] text-sm">
                @forelse ($blocks as $b)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <span>{{ $b->barber?->display_name ?? 'Salão' }} · {{ \App\Support\BusinessTime::format($b->starts_at) }} → {{ \App\Support\BusinessTime::format($b->ends_at) }} {{ $b->reason ? '· '.$b->reason : '' }}</span>
                        <form method="POST" action="{{ route('admin.schedules.blocks.destroy', $b) }}">@csrf @method('DELETE')<button class="text-xs text-red-700 underline">Remover</button></form>
                    </li>
                @empty
                    <li class="py-2 text-stone">Nenhum bloqueio ativo.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.admin>
