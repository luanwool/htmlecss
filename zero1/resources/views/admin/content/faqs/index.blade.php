<x-layouts.admin title="Perguntas frequentes">
    <x-slot:actions><a href="{{ route('admin.faqs.create') }}" class="btn-dark min-h-10 px-4 text-xs">Nova pergunta</a></x-slot:actions>
    <p class="mb-5 max-w-3xl text-sm text-stone">As respostas refletem as regras da barbearia (cobrança, cancelamento, reagendamento, franquia, atraso e no-show). Só aparecem no site depois de respondidas e publicadas.</p>
    <div class="card divide-y divide-black/[.06] p-0">
        @forelse ($faqs as $f)
            <a href="{{ route('admin.faqs.edit', $f) }}" class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-paper/50">
                <span class="font-medium">{{ $f->question }}</span>
                <span class="shrink-0">@if (blank($f->answer))<span class="badge-amber">Sem resposta</span>@else<span class="{{ $f->is_published ? 'badge-green' : 'badge-gray' }}">{{ $f->is_published ? 'Publicada' : 'Rascunho' }}</span>@endif</span>
            </a>
        @empty
            <p class="px-5 py-8 text-center text-sm text-stone">Nenhuma pergunta.</p>
        @endforelse
    </div>
</x-layouts.admin>
