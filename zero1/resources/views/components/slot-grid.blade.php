{{-- Grade de horários disponíveis (cada um é um link GET para a etapa de confirmação). --}}
@props(['slots', 'href', 'selected' => null, 'tone' => 'light'])
@if (count($slots))
    <ul class="grid grid-cols-4 gap-2 sm:grid-cols-6 lg:grid-cols-8" role="list">
        @foreach ($slots as $s)
            <li>
                <a href="{{ $href($s) }}" @if ($selected === $s['time']) aria-current="true" @endif
                   @class(['flex min-h-11 items-center justify-center rounded-xl border text-sm font-semibold tabular-nums transition',
                       'border-ink bg-ink text-paper' => $selected === $s['time'],
                       'border-black/15 bg-white hover:border-ink' => $selected !== $s['time'] && $tone === 'light',
                       'border-white/15 hover:border-accent hover:text-accent' => $selected !== $s['time'] && $tone === 'dark'])>{{ $s['time'] }}</a>
            </li>
        @endforeach
    </ul>
@else
    <p {{ $attributes->merge(['class' => 'rounded-xl border border-dashed border-current/20 p-5 text-sm opacity-80']) }}>Sem horários disponíveis nesta data. Escolha outro dia{{ isset($noBarberHint) ? '' : ' ou outro profissional' }}.</p>
@endif
