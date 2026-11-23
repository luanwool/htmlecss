<x-layouts.barber title="Minha escala">
    @error('request')<div class="mb-4 rounded-xl bg-red-50 p-4 text-sm text-red-900" role="alert">{{ $message }}</div>@enderror

    <section class="card">
        <h2 class="font-semibold">Como você atende hoje</h2>
        @php $byDay = $barber->schedules->groupBy('weekday'); @endphp
        <div class="mt-3 divide-y divide-black/[.06] text-sm">
            @foreach (range(1, 7) as $d)
                <div class="flex items-center justify-between gap-3 py-2">
                    <span class="font-medium">{{ \App\Support\BusinessTime::weekdayName($d) }}</span>
                    <span class="text-stone">
                        @forelse ($byDay[$d] ?? collect() as $s)
                            {{ substr($s->starts_at, 0, 5) }}–{{ substr($s->ends_at, 0, 5) }}@if (! $loop->last) · @endif
                        @empty — @endforelse
                    </span>
                </div>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-stone">A escala é definida com o ADM. Para mudar algo, envie um pedido abaixo; ele será analisado.</p>
    </section>

    <section class="card mt-6">
        <h2 class="font-semibold">Pedir alteração</h2>
        <form method="POST" action="{{ route('barber.schedule.store') }}" class="mt-4 space-y-5" data-loading>
            @csrf
            <fieldset class="grid gap-2 sm:grid-cols-3">
                <legend class="form-label">Tipo</legend>
                @foreach (\App\Enums\ScheduleChangeKind::cases() as $k)
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-black/10 p-3 has-[:checked]:border-ink has-[:checked]:bg-paper">
                        <input type="radio" name="kind" value="{{ $k->value }}" class="mt-0.5 accent-ink" @checked(old('kind', 'exception') === $k->value)>
                        <span class="text-sm font-medium">{{ $k->label() }}</span>
                    </label>
                @endforeach
            </fieldset>

            {{-- Escala semanal --}}
            <div data-show-when="kind=weekly" class="space-y-4">
                <x-form.select name="weekday" label="Dia da semana" :options="collect(range(1, 7))->mapWithKeys(fn ($d) => [$d => \App\Support\BusinessTime::weekdayName($d)])->all()" />
                <p class="form-label">Períodos (até 3). Deixe vazio o dia inteiro para pedir folga.</p>
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach (range(0, 2) as $i)
                        <div class="flex items-center gap-1">
                            <input type="time" name="periods[{{ $i }}][start]" class="form-input min-h-10" aria-label="Início {{ $i + 1 }}">
                            <span class="text-stone">–</span>
                            <input type="time" name="periods[{{ $i }}][end]" class="form-input min-h-10" aria-label="Fim {{ $i + 1 }}">
                        </div>
                    @endforeach
                </div>
                <p class="form-hint">Exemplo: "Terças só à tarde" = <strong>13:00 – 19:00</strong> (deixe os outros vazios).</p>
            </div>

            {{-- Exceção (data pontual) --}}
            <div data-show-when="kind=exception" class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-form.input name="date" type="date" label="Data" />
                    <x-form.select name="exception_type" label="Tipo" :options="['closed' => 'Folga (não vou atender)', 'open' => 'Atender em horário diferente']" />
                </div>
                <div data-show-when="exception_type=open" class="grid gap-3 sm:grid-cols-2">
                    <x-form.input name="starts_at" type="time" label="Início" />
                    <x-form.input name="ends_at" type="time" label="Fim" />
                </div>
            </div>

            {{-- Bloqueio por período --}}
            <div data-show-when="kind=block" class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-4">
                    <x-form.input name="start_date" type="date" label="De (data)" />
                    <x-form.input name="start_time" type="time" label="Hora" value="08:00" />
                    <x-form.input name="end_date" type="date" label="Até (data)" />
                    <x-form.input name="end_time" type="time" label="Hora" value="19:00" />
                </div>
                <p class="form-hint">Use para férias, consulta médica, viagem…</p>
            </div>

            <x-form.textarea name="reason" label="Motivo (opcional)" rows="2" />

            <button class="btn-dark min-h-11">Enviar pedido para análise</button>
        </form>
    </section>

    <section class="mt-6">
        <h2 class="mb-3 font-semibold">Seus pedidos</h2>
        @forelse ($requests as $r)
            <div class="card mb-2 flex flex-wrap items-start justify-between gap-3 p-4 text-sm">
                <div class="min-w-0">
                    <p class="font-medium">{{ $r->kind->label() }}</p>
                    <p class="mt-0.5 text-stone">{{ $r->summary() }}</p>
                    @if ($r->reason)<p class="mt-1 text-xs text-stone">Seu motivo: {{ $r->reason }}</p>@endif
                    @if ($r->review_note)<p class="mt-1 rounded-lg bg-paper p-2 text-xs">Resposta: {{ $r->review_note }}</p>@endif
                </div>
                <div class="flex shrink-0 flex-col items-end gap-2">
                    <span class="{{ $r->status->badge() }}">{{ $r->status->label() }}</span>
                    <span class="text-xs text-stone">{{ \App\Support\BusinessTime::format($r->created_at, 'd/m H:i') }}</span>
                    @if ($r->status === \App\Enums\ScheduleChangeStatus::Pending)
                        <form method="POST" action="{{ route('barber.schedule.cancel', $r) }}" data-confirm="Cancelar este pedido?">@csrf<button class="text-xs text-red-700 underline">Cancelar</button></form>
                    @endif
                </div>
            </div>
        @empty
            <p class="card text-center text-sm text-stone">Você ainda não enviou pedidos.</p>
        @endforelse
    </section>
</x-layouts.barber>
