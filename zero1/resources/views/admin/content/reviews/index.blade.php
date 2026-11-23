<x-layouts.admin title="Depoimentos">
    <x-slot:actions><a href="{{ route('admin.reviews.create') }}" class="btn-dark min-h-10 px-4 text-xs">Novo depoimento</a></x-slot:actions>
    <p class="mb-5 max-w-3xl text-sm text-stone">Somente depoimentos reais. A publicação exige registrar a autorização do autor (ex.: mensagem de WhatsApp, avaliação pública no Google).</p>
    <div class="card divide-y divide-black/[.06] p-0">
        @forelse ($reviews as $r)
            <a href="{{ route('admin.reviews.edit', $r) }}" class="flex items-start justify-between gap-4 px-5 py-4 hover:bg-paper/50">
                <span class="min-w-0"><span class="font-medium">{{ $r->author_name }}</span><span class="block truncate text-sm text-stone">{{ $r->content }}</span></span>
                <span class="flex shrink-0 flex-col items-end gap-1">
                    <span class="{{ $r->is_published && $r->authorization_confirmed_at ? 'badge-green' : 'badge-gray' }}">{{ $r->is_published ? 'Publicado' : 'Rascunho' }}</span>
                    @unless ($r->authorization_confirmed_at)<span class="badge-amber">Sem autorização</span>@endunless
                </span>
            </a>
        @empty
            <p class="px-5 py-8 text-center text-sm text-stone">Nenhum depoimento cadastrado.</p>
        @endforelse
    </div>
</x-layouts.admin>
