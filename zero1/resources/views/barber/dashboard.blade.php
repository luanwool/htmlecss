<x-layouts.public title="Painel do barbeiro" :noindex="true" body-class="bg-paper text-ink">
    <div class="container-site max-w-3xl py-10">
        <div class="flex items-center justify-between">
            <div>
                <p class="eyebrow text-graphite/60">Painel do barbeiro</p>
                <h1 class="display-md mt-2">{{ $barber?->display_name ?? auth()->user()->name }}</h1>
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-ghost-dark min-h-10 px-4 text-xs">Sair</button></form>
        </div>
        @unless ($barber)
            <p class="mt-6 rounded-xl bg-amber-100 p-4 text-sm text-amber-950">Seu usuário ainda não está vinculado a um perfil de profissional. Peça ao gestor para vincular em Profissionais.</p>
        @endunless
        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <div class="card">
                <p class="eyebrow text-graphite/60">Minha agenda de hoje</p>
                <p class="mt-3 text-sm text-graphite/70">Disponível na Fase 2 (motor de agenda).</p>
            </div>
            <div class="card">
                <p class="eyebrow text-graphite/60">Registrar atendimento</p>
                <p class="mt-3 text-sm text-graphite/70">Disponível na Fase 4 (lançamentos de balcão e comissões).</p>
            </div>
        </div>
        @if ($barber && $barber->services->isNotEmpty())
            <div class="card mt-4">
                <p class="eyebrow text-graphite/60">Serviços habilitados</p>
                <ul class="mt-3 flex flex-wrap gap-2">@foreach ($barber->services as $s)<li class="badge-gray">{{ $s->name }}</li>@endforeach</ul>
            </div>
        @endif
    </div>
</x-layouts.public>
