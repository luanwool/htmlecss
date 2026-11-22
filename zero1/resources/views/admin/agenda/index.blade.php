<x-layouts.admin title="Agenda">
    <x-slot:actions>
        <a href="{{ route('admin.agenda.create') }}" class="btn-dark min-h-10 px-4 text-xs">Novo agendamento</a>
    </x-slot:actions>
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <x-period-tabs :period="$period->key" />
        @if ($period->key === 'hoje')
            <div class="flex gap-1 text-sm">
                <a href="{{ request()->fullUrlWithQuery(['visao' => 'lista']) }}" @class(['rounded-full px-4 py-2', 'bg-ink text-paper' => $view === 'lista', 'border border-black/10 bg-white' => $view !== 'lista'])>Lista</a>
                <a href="{{ request()->fullUrlWithQuery(['visao' => 'colunas']) }}" @class(['rounded-full px-4 py-2', 'bg-ink text-paper' => $view === 'colunas', 'border border-black/10 bg-white' => $view !== 'colunas'])>Por profissional</a>
            </div>
        @endif
    </div>
    <form method="GET" class="mt-4 grid gap-2 sm:grid-cols-3 lg:grid-cols-6">
        <input type="hidden" name="periodo" value="{{ $period->key }}"><input type="hidden" name="visao" value="{{ $view }}">
        <input type="date" name="inicio" value="{{ $period->from->toDateString() }}" class="form-input" aria-label="A partir de">
        <select name="profissional" class="form-input" aria-label="Profissional"><option value="">Todos os profissionais</option>@foreach ($barbers as $b)<option value="{{ $b->id }}" @selected(request('profissional') == $b->id)>{{ $b->display_name }}</option>@endforeach</select>
        <select name="servico" class="form-input" aria-label="Serviço"><option value="">Todos os serviços</option>@foreach ($services as $s)<option value="{{ $s->id }}" @selected(request('servico') == $s->id)>{{ $s->name }}</option>@endforeach</select>
        <select name="status" class="form-input" aria-label="Situação"><option value="">Ativos (sem cancelados)</option>@foreach (\App\Enums\AppointmentStatus::cases() as $st)<option value="{{ $st->value }}" @selected(request('status') === $st->value)>{{ $st->label() }}</option>@endforeach</select>
        <select name="modalidade" class="form-input" aria-label="Modalidade"><option value="">Assinante e avulso</option><option value="plan" @selected(request('modalidade') === 'plan')>Assinante</option><option value="avulso" @selected(request('modalidade') === 'avulso')>Avulso</option></select>
        <div class="flex gap-2"><input type="search" name="cliente" value="{{ request('cliente') }}" placeholder="Cliente" class="form-input" aria-label="Cliente"><button class="btn-ghost-dark min-h-11 px-4">OK</button></div>
    </form>
    <p class="mt-3 text-sm text-stone">{{ ucfirst($period->from->locale('pt_BR')->translatedFormat('d \d\e F')) }} – {{ $period->to->subDay()->locale('pt_BR')->translatedFormat('d \d\e F') }} · {{ $appointments->count() }} agendamento(s)</p>

    @if ($blocks->isNotEmpty())
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($blocks as $b)<span class="badge-amber">Bloqueio {{ $b->barber?->display_name ?? 'salão' }}: {{ \App\Support\BusinessTime::format($b->starts_at, 'd/m H:i') }}–{{ \App\Support\BusinessTime::format($b->ends_at, 'd/m H:i') }}{{ $b->reason ? ' · '.$b->reason : '' }}</span>@endforeach
        </div>
    @endif

    <div class="mt-5">
        @if ($view === 'colunas')
            <div class="grid gap-4 overflow-x-auto" style="grid-template-columns: repeat({{ max(1, $barbers->count()) }}, minmax(15rem, 1fr));">
                @foreach ($barbers as $b)
                    <section class="rounded-2xl bg-white p-3" aria-label="{{ $b->display_name }}">
                        <h3 class="mb-1 font-semibold">{{ $b->display_name }}</h3>
                        <p class="mb-3 text-xs text-stone">
                            @forelse ($windows[$b->id] ?? [] as [$s, $e]){{ \App\Support\BusinessTime::format($s, 'H:i') }}–{{ \App\Support\BusinessTime::format($e, 'H:i') }}@if (! $loop->last) · @endif @empty Sem expediente hoje @endforelse
                        </p>
                        <ul class="space-y-2">
                            @forelse ($appointments->where('barber_id', $b->id) as $a)
                                <li><a href="{{ route('admin.agenda.show', $a) }}" class="block rounded-xl border-l-4 bg-paper p-3 text-sm {{ $a->isPlan() ? 'border-ink' : 'border-accent' }}">
                                    <span class="font-semibold tabular-nums">{{ $a->localStart()->format('H:i') }}–{{ $a->localEnd()->format('H:i') }}</span>
                                    <span class="block">{{ $a->customer->name }}</span>
                                    <span class="block text-xs text-stone">{{ $a->service_name_snapshot }} · {{ $a->status->label() }}</span></a></li>
                            @empty
                                <li class="text-sm text-stone">Livre</li>
                            @endforelse
                        </ul>
                    </section>
                @endforeach
            </div>
        @else
            <x-agenda-list :by-day="$byDay" :link="fn ($a) => route('admin.agenda.show', $a)" />
        @endif
    </div>
</x-layouts.admin>
