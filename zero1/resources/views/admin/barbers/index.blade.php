<x-layouts.admin title="Profissionais">
    <x-slot:actions><a href="{{ route('admin.barbers.create') }}" class="btn-dark min-h-10 px-4 text-xs">Novo profissional</a></x-slot:actions>
    @if ($barbers->isEmpty())
        <div class="card text-center">
            <p class="font-semibold">Nenhum profissional cadastrado.</p>
            <p class="mt-1 text-sm text-stone">Cadastre os barbeiros reais (nome, foto autorizada e apresentação).</p>
            <a href="{{ route('admin.barbers.create') }}" class="btn-dark mt-4">Cadastrar</a>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($barbers as $b)
                <a href="{{ route('admin.barbers.edit', $b) }}" class="card flex items-center gap-4 transition hover:border-black/20">
                    @if ($b->photo)
                        <img src="{{ $b->photo->url(480) }}" alt="" class="size-16 rounded-2xl object-cover">
                    @else
                        <span class="flex size-16 items-center justify-center rounded-2xl bg-ink font-display text-xl text-paper">{{ $b->initials() }}</span>
                    @endif
                    <span class="min-w-0">
                        <span class="block truncate font-semibold">{{ $b->display_name }}</span>
                        <span class="mt-1 flex flex-wrap gap-1">
                            <span class="{{ ['active' => 'badge-green', 'away' => 'badge-amber', 'inactive' => 'badge-gray'][$b->status->value] }}">{{ $b->status->label() }}</span>
                            @unless ($b->is_public)<span class="badge-gray">Oculto</span>@endunless
                            @unless ($b->user_id)<span class="badge-red">Sem login</span>@endunless
                        </span>
                        <span class="mt-1 block text-xs text-stone">{{ $b->services_count }} serviço(s)</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.admin>
