<x-layouts.admin :title="'Editar: '.$page->title">
    <form method="POST" action="{{ route('admin.pages.update', $page) }}" class="card max-w-4xl space-y-5" data-loading>
        @csrf @method('PUT')
        <x-form.input name="title" label="Título" :value="$page->title" required />
        <x-form.textarea name="body" label="Conteúdo (Markdown)" :value="$page->body" rows="22" required class="font-mono text-sm"
                         hint="Use ## para títulos, - para listas, **negrito**. HTML não é permitido." />
        <x-form.input name="meta_description" label="Descrição para buscadores" :value="$page->meta_description" maxlength="300" />
        <x-form.checkbox name="is_published" label="Publicada" :checked="$page->is_published" hint="Confirme que o texto foi revisado e aprovado pela barbearia." />
        <div class="flex gap-3"><button class="btn-dark">Salvar</button><a href="{{ route('pages.show', $page) }}" target="_blank" class="btn-ghost-dark">Pré-visualizar</a></div>
    </form>
</x-layouts.admin>
