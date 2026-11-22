@php
    $nav = [
        ['#servicos', 'Serviços'],
        ['#clube', 'Clube de Planos'],
        ['#profissionais', 'Profissionais'],
        ['#localizacao', 'Localização'],
    ];
    $onHome = request()->routeIs('home');
@endphp
<header data-site-header class="group/header sticky top-0 z-50 border-b border-transparent transition-colors duration-500 data-[scrolled]:border-white/10 data-[scrolled]:bg-ink/85 data-[scrolled]:backdrop-blur-md">
    <div class="container-site flex h-16 items-center justify-between gap-6 md:h-20">
        <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="{{ $site->businessName() }} — início">
            @if ($logo)
                <img src="{{ $logo->url(480) }}" alt="{{ $site->businessName() }}" class="h-9 w-auto md:h-11" width="{{ $logo->width }}" height="{{ $logo->height }}">
            @else
                <x-wordmark />
            @endif
        </a>

        <nav class="hidden items-center gap-8 text-sm text-smoke lg:flex" aria-label="Principal">
            @foreach ($nav as [$href, $label])
                <a href="{{ $onHome ? $href : route('home').$href }}" class="link-underline hover:text-paper">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2 sm:gap-3">
            @auth
                <a href="{{ auth()->user()->homeRoute() }}" class="hidden text-sm text-smoke hover:text-paper sm:inline">Minha área</a>
            @else
                <a href="{{ route('login') }}" class="hidden text-sm text-smoke hover:text-paper sm:inline">Entrar</a>
            @endauth
            <a href="{{ route('booking.start') }}" class="btn-accent hidden min-h-10 px-5 md:inline-flex">Agendar</a>
            <button type="button" data-toggle="mobile-nav" data-lock-scroll aria-controls="mobile-nav" aria-expanded="false"
                    class="inline-flex size-11 items-center justify-center rounded-full border border-white/15 lg:hidden" aria-label="Abrir menu">
                <x-icon name="menu" class="size-5" />
            </button>
        </div>
    </div>

    <div id="mobile-nav" class="fixed inset-0 top-16 z-40 hidden bg-ink data-[open]:block lg:hidden">
        <nav class="container-site flex h-full flex-col gap-1 py-8" aria-label="Menu móvel">
            @foreach ($nav as $i => [$href, $label])
                <a href="{{ $onHome ? $href : route('home').$href }}" class="flex items-baseline gap-4 border-b border-white/10 py-4">
                    <span class="font-display text-sm text-stone">0{{ $i + 1 }}</span>
                    <span class="font-display text-3xl">{{ $label }}</span>
                </a>
            @endforeach
            <div class="mt-auto grid gap-3 pb-6">
                <a href="{{ route('booking.start') }}" class="btn-accent">Agendar horário</a>
                @auth
                    <a href="{{ auth()->user()->homeRoute() }}" class="btn-ghost-light">Minha área</a>
                @else
                    <a href="{{ route('login') }}" class="btn-ghost-light">Entrar / Criar conta</a>
                @endauth
            </div>
        </nav>
    </div>
</header>
