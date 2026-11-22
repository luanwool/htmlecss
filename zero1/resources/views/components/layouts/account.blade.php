@props(['title'])
<x-layouts.public :title="$title" :noindex="true" body-class="bg-paper text-ink">
    <div class="container-site py-10 md:py-14">
        <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="eyebrow text-graphite/60">Área do cliente</p>
                <h1 class="display-md mt-2">{{ $title }}</h1>
            </div>
            <nav class="-mx-4 flex gap-1 overflow-x-auto px-4 text-sm md:mx-0 md:px-0" aria-label="Área do cliente">
                @foreach ([['account.dashboard', 'Início'], ['account.profile.edit', 'Meus dados'], ['account.privacy', 'Privacidade']] as [$r, $l])
                    <a href="{{ route($r) }}" @class(['whitespace-nowrap rounded-full px-4 py-2', 'bg-ink text-paper' => request()->routeIs($r), 'hover:bg-black/5' => ! request()->routeIs($r)])
                       @if (request()->routeIs($r)) aria-current="page" @endif>{{ $l }}</a>
                @endforeach
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="whitespace-nowrap rounded-full px-4 py-2 hover:bg-black/5">Sair</button></form>
            </nav>
        </div>
        <x-flash class="mt-6" />
        <div class="mt-8">{{ $slot }}</div>
    </div>
</x-layouts.public>
