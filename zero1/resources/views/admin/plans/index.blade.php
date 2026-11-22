<x-layouts.admin title="Planos do Clube">
    <x-slot:actions><a href="{{ route('admin.plans.create') }}" class="btn-dark min-h-10 px-4 text-xs">Novo plano</a></x-slot:actions>
    <p class="mb-5 max-w-3xl text-sm text-stone">Alterações comerciais geram nova versão do plano. Assinaturas já vendidas mantêm as condições da adesão; migrações exigem consentimento do cliente (Fase 3).</p>
    @if ($plans->isEmpty())
        <div class="card text-center">
            <p class="font-semibold">Nenhum plano cadastrado.</p>
            <a href="{{ route('admin.plans.create') }}" class="btn-dark mt-4">Criar plano</a>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($plans as $p)
                <a href="{{ route('admin.plans.edit', $p) }}" class="card block transition hover:border-black/20">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-semibold">{{ $p->name }}</p>
                        <span class="{{ $p->is_published ? 'badge-green' : 'badge-gray' }}">{{ $p->is_published ? 'Publicado' : 'Rascunho' }}</span>
                    </div>
                    <p class="mt-3 font-display text-3xl">{{ $p->formattedPrice() }}<span class="text-sm text-stone">/mês</span></p>
                    <p class="mt-1 text-sm text-stone">{{ $p->usageSummary() }} · {{ $p->services_count }} serviço(s) · v{{ $p->version }}</p>
                    @if ($p->is_featured)<span class="badge-ink mt-3">Destaque</span>@endif
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.admin>
