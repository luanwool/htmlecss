@php $days = collect(range(0, app(\App\Services\Booking\BookingPolicy::class)->maxAdvanceDays()))->map(fn ($i) => \App\Support\BusinessTime::now()->startOfDay()->addDays($i)); @endphp
<x-layouts.account title="Reagendar">
    <p class="text-sm text-graphite/70">{{ $a->service_name_snapshot }} · atual: {{ $a->localStart()->format('d/m H:i') }} com {{ $a->barber->display_name }}</p>
    @error('booking')<div class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-900" role="alert">{{ $message }}</div>@enderror
    <div class="-mx-4 mt-6 flex gap-2 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0">
        @foreach ($days as $d)
            <a href="{{ route('account.appointments.reschedule', [$a->uid, 'data' => $d->toDateString(), 'profissional' => $barberId]) }}"
               @class(['flex w-16 shrink-0 flex-col items-center rounded-2xl border py-3', 'border-ink bg-ink text-paper' => $d->isSameDay($date), 'border-black/10 bg-white' => ! $d->isSameDay($date)])>
                <span class="text-[0.65rem] uppercase">{{ $d->locale('pt_BR')->translatedFormat('D') }}</span><span class="font-display text-2xl">{{ $d->format('d') }}</span>
            </a>
        @endforeach
    </div>
    <div class="mt-6">
        @if (count($slots))
            <form method="POST" action="{{ route('account.appointments.reschedule.store', $a->uid) }}" class="space-y-4" data-loading>
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <input type="hidden" name="barber_id" value="{{ $barberId === 'qualquer' ? '' : $barberId }}">
                <div class="grid grid-cols-4 gap-2 sm:grid-cols-8">
                    @foreach ($slots as $s)
                        <label class="cursor-pointer"><input type="radio" name="time" value="{{ $s['time'] }}" class="peer sr-only" required>
                            <span class="flex min-h-11 items-center justify-center rounded-xl border border-black/15 bg-white text-sm font-semibold peer-checked:border-ink peer-checked:bg-ink peer-checked:text-paper peer-focus-visible:ring-2 peer-focus-visible:ring-accent">{{ $s['time'] }}</span></label>
                    @endforeach
                </div>
                <button class="btn-dark">Confirmar novo horário</button>
            </form>
        @else
            <p class="card text-sm text-graphite/70">Sem horários nesta data.</p>
        @endif
    </div>
</x-layouts.account>
