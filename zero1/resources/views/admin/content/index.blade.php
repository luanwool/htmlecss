<x-layouts.admin title="Conteúdo do site">
    <x-slot:actions><a href="{{ route('home') }}" target="_blank" class="btn-ghost-dark min-h-10 px-4 text-xs">Ver site <x-icon name="external" class="size-3.5" /></a></x-slot:actions>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([['admin.media.index', 'Galeria de fotos', 'image'], ['admin.reviews.index', 'Depoimentos', 'star'], ['admin.faqs.index', 'Perguntas frequentes', 'list'], ['admin.pages.index', 'Termos e políticas', 'shield']] as [$r, $l, $i])
            <a href="{{ route($r) }}" class="card flex items-center gap-3 transition hover:border-black/20"><x-icon :name="$i" /> <span class="font-semibold">{{ $l }}</span></a>
        @endforeach
    </div>
    <h2 class="mt-10 mb-3 font-semibold">Seções da página inicial</h2>
    <div class="card divide-y divide-black/[.06] p-0">
        @foreach ($sections as $s)
            <a href="{{ route('admin.sections.edit', $s) }}" class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-paper/50">
                <span><span class="font-medium">{{ $s->label() }}</span><span class="block text-sm text-stone">{{ $s->title ?: '—' }}</span></span>
                <span class="flex items-center gap-3"><span class="{{ $s->is_visible ? 'badge-green' : 'badge-gray' }}">{{ $s->is_visible ? 'Visível' : 'Oculta' }}</span><span class="text-xs text-stone">#{{ $s->sort_order }}</span></span>
            </a>
        @endforeach
    </div>
</x-layouts.admin>
