<x-layouts.admin title="Categorias de serviço">
    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('admin.categories.store') }}" class="card space-y-4">
            @csrf
            <h2 class="font-semibold">Nova categoria</h2>
            <x-form.input name="name" label="Nome" required />
            <x-form.input name="sort_order" type="number" min="0" label="Ordem" value="0" />
            <button class="btn-dark w-full">Adicionar</button>
        </form>
        <div class="card lg:col-span-2">
            @forelse ($categories as $c)
                <div class="flex flex-col gap-3 border-b border-black/[.06] py-3 sm:flex-row sm:items-end">
                    <form method="POST" action="{{ route('admin.categories.update', $c) }}" class="flex flex-1 flex-wrap items-end gap-3">
                        @csrf @method('PUT')
                        <label class="flex-1"><span class="sr-only">Nome</span><input name="name" value="{{ $c->name }}" class="form-input" required></label>
                        <label class="w-24"><span class="sr-only">Ordem</span><input name="sort_order" type="number" value="{{ $c->sort_order }}" class="form-input"></label>
                        <button class="btn-ghost-dark min-h-11 px-4 text-xs">Salvar</button>
                    </form>
                    <form method="POST" action="{{ route('admin.categories.destroy', $c) }}" data-confirm="Remover a categoria? Os {{ $c->services_count }} serviço(s) ficarão sem categoria.">
                        @csrf @method('DELETE')<button class="text-xs font-semibold text-red-700 underline">Remover</button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-stone">Nenhuma categoria.</p>
            @endforelse
        </div>
    </div>
</x-layouts.admin>
