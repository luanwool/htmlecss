<x-layouts.admin :title="$title">
    <div class="card max-w-2xl">
        <span class="badge-amber">Fase {{ $phase }}</span>
        <h2 class="mt-4 font-display text-3xl">{{ $title }}</h2>
        <p class="mt-3 text-graphite/80">{{ $description }}</p>
        <p class="mt-4 text-sm text-stone">Este módulo ainda não está ativo. Nenhum dado é simulado nesta tela.</p>
    </div>
</x-layouts.admin>
