<x-layouts.admin :title="$review->exists ? 'Editar depoimento' : 'Novo depoimento'">
    <form method="POST" action="{{ $review->exists ? route('admin.reviews.update', $review) : route('admin.reviews.store') }}" class="grid max-w-5xl gap-6 lg:grid-cols-3" data-loading>
        @csrf @if ($review->exists) @method('PUT') @endif
        <div class="card space-y-5 lg:col-span-2">
            <x-form.input name="author_name" label="Nome do autor (como autorizado)" :value="$review->author_name" required />
            <x-form.textarea name="content" label="Depoimento (texto original)" :value="$review->content" rows="5" required data-count="1200" maxlength="1200" />
            <div class="grid gap-5 sm:grid-cols-3">
                <x-form.select name="rating" label="Nota" :value="$review->rating" placeholder="—" :options="[5 => '5', 4 => '4', 3 => '3', 2 => '2', 1 => '1']" />
                <x-form.select name="source" label="Origem" :value="$review->source" placeholder="—" :options="['google' => 'Google', 'presencial' => 'Presencial', 'instagram' => 'Instagram', 'whatsapp' => 'WhatsApp', 'outro' => 'Outro']" />
                <x-form.input name="reviewed_on" type="date" label="Data" :value="$review->reviewed_on?->format('Y-m-d')" />
            </div>
        </div>
        <div class="space-y-6">
            <div class="card space-y-4">
                <x-form.checkbox name="authorization_confirmed" label="Autor autorizou a publicação" :checked="(bool) $review->authorization_confirmed_at"
                                 :hint="$review->authorization_confirmed_at ? 'Registrado em '.\App\Support\BusinessTime::format($review->authorization_confirmed_at) : null" />
                <x-form.input name="authorization_note" label="Como foi autorizado" :value="$review->authorization_note" placeholder="Ex.: WhatsApp em 10/11" />
                <x-form.checkbox name="is_published" label="Publicar no site" :checked="$review->is_published" />
                <x-form.input name="sort_order" type="number" min="0" label="Ordem" :value="$review->sort_order ?? 0" />
            </div>
            <button class="btn-dark w-full">Salvar</button>
        </div>
    </form>
    @if ($review->exists)
        <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" class="mt-8" data-confirm="Excluir depoimento?">@csrf @method('DELETE')<button class="text-sm font-semibold text-red-700 underline">Excluir</button></form>
    @endif
</x-layouts.admin>
