<x-layouts.admin :title="$faq->exists ? 'Editar pergunta' : 'Nova pergunta'">
    <form method="POST" action="{{ $faq->exists ? route('admin.faqs.update', $faq) : route('admin.faqs.store') }}" class="card max-w-3xl space-y-5" data-loading>
        @csrf @if ($faq->exists) @method('PUT') @endif
        <x-form.input name="question" label="Pergunta" :value="$faq->question" required />
        <x-form.textarea name="answer" label="Resposta" :value="$faq->answer" rows="6" />
        <div class="flex flex-wrap items-center gap-6">
            <x-form.checkbox name="is_published" label="Publicar" :checked="$faq->is_published" />
            <x-form.input name="sort_order" type="number" min="0" label="Ordem" :value="$faq->sort_order ?? 0" class="w-28" />
        </div>
        <div class="flex gap-3"><button class="btn-dark">Salvar</button><a href="{{ route('admin.faqs.index') }}" class="btn-ghost-dark">Cancelar</a></div>
    </form>
    @if ($faq->exists)
        <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" class="mt-6" data-confirm="Excluir pergunta?">@csrf @method('DELETE')<button class="text-sm font-semibold text-red-700 underline">Excluir</button></form>
    @endif
</x-layouts.admin>
