@props(['title'])
<x-layouts.public :title="$title" :noindex="true" body-class="bg-[#f6f5f1] text-ink" :sticky-cta="false">
    <div class="container-site max-w-3xl pb-28 pt-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="eyebrow text-graphite/60">Painel do barbeiro</p>
                <h1 class="mt-1 font-display text-3xl">{{ $title }}</h1>
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-ghost-dark min-h-10 px-4 text-xs">Sair</button></form>
        </div>
        <nav class="mt-4 flex gap-1 text-sm" aria-label="Barbeiro">
            @foreach ([['barber.dashboard', 'Minha agenda'], ['barber.visits.index', 'Produção'], ['barber.visits.create', 'Registrar']] as [$r, $l])
                <a href="{{ route($r) }}" @class(['rounded-full px-4 py-2', 'bg-ink text-paper' => request()->routeIs($r), 'bg-white border border-black/10' => ! request()->routeIs($r)])>{{ $l }}</a>
            @endforeach
        </nav>
        <x-flash class="mt-4" />
        @error('booking')<div class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-900" role="alert">{{ $message }}</div>@enderror
        <div class="mt-6">{{ $slot }}</div>
    </div>
    <a href="{{ route('barber.visits.create') }}" class="btn-accent fixed inset-x-4 bottom-[max(1rem,env(safe-area-inset-bottom))] z-40 shadow-xl sm:hidden">+ Registrar atendimento</a>
</x-layouts.public>
