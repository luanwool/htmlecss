@props(['period', 'options' => \App\Support\AgendaPeriod::OPTIONS, 'param' => 'periodo'])
@php $q = request()->except([$param, 'page']); @endphp
<nav {{ $attributes->merge(['class' => '-mx-4 flex gap-1 overflow-x-auto px-4 sm:mx-0 sm:px-0']) }} aria-label="Período">
    @foreach ($options as $key => $label)
        <a href="{{ request()->url().'?'.http_build_query($q + [$param => $key]) }}" @if ((string) $period === (string) $key) aria-current="page" @endif
           @class(['whitespace-nowrap rounded-full px-4 py-2 text-sm font-medium transition', 'bg-ink text-paper' => (string) $period === (string) $key, 'bg-white text-graphite hover:bg-black/5 border border-black/10' => (string) $period !== (string) $key])>{{ $label }}</a>
    @endforeach
</nav>
