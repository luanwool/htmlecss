@props(['title', 'actions' => null])
@php
    $u = auth()->user();
    $groups = [
        '' => [
            ['admin.dashboard', [], 'Visão geral', 'home', 'dashboard.view', 'admin.dashboard'],
        ],
        'Operação' => [
            ['admin.agenda.index', [], 'Agenda', 'calendar', 'agenda.view_all', 'admin.agenda.*'],
            ['admin.visits.index', [], 'Atendimentos', 'scissors', 'agenda.manage', 'admin.visits.*'],
            ['admin.schedules.index', [], 'Escalas e bloqueios', 'clock', 'schedules.manage', 'admin.schedules.*'],
            ['admin.schedule-requests.index', [], 'Pedidos de escala', 'list', 'schedule.review', 'admin.schedule-requests.*'],
        ],
        'Catálogo' => [
            ['admin.services.index', [], 'Serviços', 'list', 'services.manage', 'admin.services.*'],
            ['admin.plans.index', [], 'Planos', 'star', 'plans.manage', 'admin.plans.*'],
            ['admin.barbers.index', [], 'Profissionais', 'users', 'barbers.manage', 'admin.barbers.*'],
        ],
        'Clientes' => [
            ['admin.customers.index', [], 'Clientes', 'users', 'customers.view', 'admin.customers.*'],
            ['admin.subscriptions.index', [], 'Assinaturas', 'card', 'subscriptions.manage', 'admin.subscriptions.*'],
            ['admin.referrals.index', [], 'Indicações', 'users', 'referrals.manage', 'admin.referrals.*'],
        ],
        'Gestão' => [
            ['admin.finance.index', [], 'Financeiro', 'wallet', 'finance.view', 'admin.finance.*'],
            ['admin.reports.index', [], 'Relatórios', 'chart', 'reports.view', 'admin.reports.*'],
        ],
        'Site e sistema' => [
            ['admin.content.index', [], 'Conteúdo do site', 'image', 'content.manage', 'admin.content.*|admin.sections.*|admin.media.*|admin.reviews.*|admin.faqs.*|admin.pages.*'],
            ['admin.settings.edit', [], 'Configurações', 'settings', 'settings.manage', 'admin.settings.*'],
            ['admin.users.index', [], 'Equipe e acessos', 'shield', 'users.manage', 'admin.users.*|admin.roles.*'],
            ['admin.data-requests.index', [], 'LGPD', 'shield', 'privacy.manage', 'admin.data-requests.*'],
            ['admin.audit.index', [], 'Auditoria', 'list', 'audit.view', 'admin.audit.*'],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="pt-BR" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · Painel {{ $site->businessName() }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <style>:root{--accent: {{ $site->accentColor() }};--accent-ink: {{ $site->accentInk() }};}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-dark min-h-dvh bg-ink text-paper antialiased" style="background-image:radial-gradient(40rem 26rem at 90% -10%, color-mix(in oklab, var(--color-accent) 10%, transparent), transparent 60%),radial-gradient(34rem 24rem at -10% 110%, color-mix(in oklab, var(--color-ion) 10%, transparent), transparent 60%);background-attachment:fixed">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-full focus:bg-ink focus:px-4 focus:py-2 focus:text-paper">Pular para o conteúdo</a>
    <div class="lg:flex">
        <aside id="admin-nav" class="fixed inset-y-0 left-0 z-50 hidden w-72 flex-col overflow-y-auto border-r border-white/5 bg-graphite/80 text-smoke backdrop-blur-xl data-[open]:flex lg:sticky lg:top-0 lg:flex lg:h-dvh lg:w-64">
            <div class="flex h-16 items-center justify-between px-5">
                <a href="{{ route('admin.dashboard') }}"><x-wordmark /></a>
                <button type="button" data-close class="inline-flex size-10 items-center justify-center rounded-full lg:hidden" aria-label="Fechar menu"><x-icon name="close" /></button>
            </div>
            @if (config('zero1.demo_mode'))
                <p class="mx-5 mb-2 rounded-lg bg-accent/15 px-3 py-1.5 text-xs text-accent">Modo demonstração</p>
            @endif
            @if (config('zero1.payments.driver') === 'simulated')
                <p class="mx-5 mb-2 rounded-lg bg-amber-500/15 px-3 py-1.5 text-xs text-amber-300">Pagamentos simulados</p>
            @endif
            <nav class="flex-1 px-3 pb-6 text-sm" aria-label="Painel">
                @foreach ($groups as $group => $items)
                    @php $visible = collect($items)->filter(fn ($i) => $u->can($i[4])); @endphp
                    @if ($visible->isNotEmpty())
                        @if ($group)<p class="eyebrow mt-6 mb-2 px-3 text-[0.62rem] text-stone/70">{{ $group }}</p>@endif
                        @foreach ($visible as $item)
                            @php
                                $active = request()->routeIs(...explode('|', $item[5]));
                            @endphp
                            <a href="{{ route($item[0], $item[1]) }}" @if ($active) aria-current="page" @endif @style(['box-shadow: inset 2px 0 0 var(--color-accent)' => $active])
                               @class(['relative flex items-center gap-3 rounded-lg px-3 py-2 transition', 'bg-white/[.06] text-paper' => $active, 'hover:bg-white/[.04] hover:text-paper' => ! $active])>
                                <x-icon :name="$item[3]" class="size-[1.1rem] opacity-80" />
                                <span class="flex-1">{{ $item[2] }}</span>
                            </a>
                        @endforeach
                    @endif
                @endforeach
            </nav>
            <div class="border-t border-white/10 p-4 text-xs">
                <p class="truncate text-paper">{{ $u->name }}</p>
                <p class="text-stone">{{ $u->roles->map(fn ($r) => \App\Enums\Role::from($r->name)->label())->implode(', ') }}</p>
                <div class="mt-3 flex gap-2">
                    <a href="{{ route('home') }}" target="_blank" class="inline-flex items-center gap-1 rounded-md px-2 py-1 hover:bg-white/5"><x-icon name="external" class="size-3.5" /> Ver site</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="inline-flex items-center gap-1 rounded-md px-2 py-1 hover:bg-white/5"><x-icon name="logout" class="size-3.5" /> Sair</button></form>
                </div>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-white/5 bg-ink/70 px-4 backdrop-blur-xl sm:px-6 lg:px-10">
                <button type="button" data-toggle="admin-nav" data-lock-scroll aria-controls="admin-nav" aria-expanded="false"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-black/10 lg:hidden" aria-label="Abrir menu"><x-icon name="menu" /></button>
                <h1 class="truncate text-lg font-semibold">{{ $title }}</h1>
                @if ($actions)<div class="ml-auto flex items-center gap-2">{{ $actions }}</div>@endif
            </header>
            <main id="conteudo" class="px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
                <x-flash class="mb-6" />
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
