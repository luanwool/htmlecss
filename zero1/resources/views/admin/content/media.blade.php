<x-layouts.admin title="Galeria de fotos">
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.media.store') }}" class="card mb-8 space-y-4" data-loading>
        @csrf
        <h2 class="font-semibold">Enviar fotos</h2>
        <input type="file" name="files[]" multiple accept="image/jpeg,image/png,image/webp" required class="block w-full text-sm file:mr-3 file:rounded-full file:border-0 file:bg-ink file:px-4 file:py-2 file:text-paper">
        @error('files')<p class="form-error">{{ $message }}</p>@enderror @error('files.*')<p class="form-error">{{ $message }}</p>@enderror
        <x-form.select name="category" label="Categoria" :options="$categories" placeholder="Sem categoria" class="max-w-xs" />
        <x-form.checkbox name="confirm_own" label="Confirmo que as fotos são da própria Zero 1 Barbearia e que pessoas retratadas autorizaram o uso." />
        <p class="form-hint">Até 12 fotos por envio. As imagens são convertidas para WebP em vários tamanhos e os metadados (incluindo localização) são removidos.</p>
        <button class="btn-dark">Enviar</button>
    </form>

    @if ($items->isEmpty())
        <p class="text-sm text-stone">Nenhuma foto na galeria. A seção fica oculta no site até haver fotos publicadas.</p>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($items as $m)
                <div class="card p-3">
                    <img src="{{ $m->url(480) }}" alt="{{ $m->alt_text }}" class="aspect-[4/3] w-full rounded-xl object-cover" loading="lazy">
                    <form method="POST" action="{{ route('admin.media.update', $m) }}" class="mt-3 space-y-2">
                        @csrf @method('PUT')
                        <input name="alt_text" value="{{ $m->alt_text }}" placeholder="Texto alternativo (acessibilidade)" class="form-input min-h-9 text-sm" aria-label="Texto alternativo">
                        <input name="caption" value="{{ $m->caption }}" placeholder="Legenda (opcional)" class="form-input min-h-9 text-sm" aria-label="Legenda">
                        <div class="flex items-center gap-2">
                            <select name="category" class="form-input min-h-9 text-sm" aria-label="Categoria">
                                <option value="">Sem categoria</option>
                                @foreach ($categories as $k => $l)<option value="{{ $k }}" @selected($m->category === $k)>{{ $l }}</option>@endforeach
                            </select>
                            <input name="sort_order" type="number" min="0" value="{{ $m->sort_order }}" class="form-input min-h-9 w-20 text-sm" aria-label="Ordem">
                        </div>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_published" value="1" class="form-check" @checked($m->is_published)> Publicada</label>
                        <button class="btn-ghost-dark min-h-9 w-full text-xs">Salvar</button>
                    </form>
                    <form method="POST" action="{{ route('admin.media.destroy', $m) }}" data-confirm="Excluir esta foto?" class="mt-2 text-center">
                        @csrf @method('DELETE')<button class="text-xs font-semibold text-red-700 underline">Excluir</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.admin>
