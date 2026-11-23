<x-layouts.admin :title="'Reagendar #'.$a->id">
    <p class="text-sm text-stone">{{ $a->customer->name }} · {{ $a->service_name_snapshot }} · atual {{ $a->localStart()->format('d/m H:i') }} com {{ $a->barber->display_name }}</p>
    @error('booking')<div class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-900" role="alert">{{ $message }}</div>@enderror
    <form method="GET" class="mt-4 flex flex-wrap gap-2">
        <input type="date" name="data" value="{{ $date->toDateString() }}" class="form-input max-w-[12rem]" aria-label="Data">
        <select name="profissional" class="form-input max-w-[14rem]" aria-label="Profissional"><option value="qualquer">Qualquer disponível</option>@foreach ($barbers as $b)<option value="{{ $b->id }}" @selected((string) $barberId === (string) $b->id)>{{ $b->display_name }}</option>@endforeach</select>
        <button class="btn-ghost-dark min-h-11">Ver horários</button>
    </form>
    <form method="POST" action="{{ route('admin.agenda.reschedule.store', $a) }}" class="card mt-4 max-w-3xl space-y-4" data-loading>
        @csrf
        <input type="hidden" name="date" value="{{ $date->toDateString() }}"><input type="hidden" name="barber_id" value="{{ $barberId === 'qualquer' ? '' : $barberId }}">
        @if (count($slots))
            <div class="grid grid-cols-4 gap-2 sm:grid-cols-8">
                @foreach ($slots as $s)
                    <label class="cursor-pointer"><input type="radio" name="time" value="{{ $s['time'] }}" class="peer sr-only" required>
                        <span class="flex min-h-10 items-center justify-center rounded-lg border border-black/15 text-sm font-semibold peer-checked:bg-ink peer-checked:text-paper">{{ $s['time'] }}</span></label>
                @endforeach
            </div>
            <button class="btn-dark">Confirmar novo horário</button>
        @else
            <p class="text-sm text-stone">Sem horários livres nesta data.</p>
        @endif
    </form>
</x-layouts.admin>
