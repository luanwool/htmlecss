<x-layouts.admin title="Termos e políticas">
    <p class="mb-5 max-w-3xl text-sm text-stone">Os textos iniciais são rascunhos com marcações [A definir]. Revise com assessoria jurídica antes de publicar. Cada alteração fica registrada na auditoria.</p>
    <div class="card divide-y divide-black/[.06] p-0">
        @foreach ($pages as $p)
            <div class="flex items-center justify-between gap-4 px-5 py-4">
                <span><span class="font-medium">{{ $p->title }}</span><span class="block text-xs text-stone">/p/{{ $p->slug }} · atualizado {{ \App\Support\BusinessTime::format($p->updated_at, 'd/m/Y H:i') }}</span></span>
                <span class="flex items-center gap-3">
                    <span class="{{ $p->is_published ? 'badge-green' : 'badge-amber' }}">{{ $p->is_published ? 'Publicada' : 'Rascunho' }}</span>
                    <a href="{{ route('pages.show', $p) }}" target="_blank" class="text-sm underline">Ver</a>
                    <a href="{{ route('admin.pages.edit', $p) }}" class="text-sm font-semibold underline">Editar</a>
                </span>
            </div>
        @endforeach
    </div>
</x-layouts.admin>
