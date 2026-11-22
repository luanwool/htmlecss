<x-layouts.admin title="Serviços">
    <x-slot:actions>
        <a href="{{ route('admin.categories.index') }}" class="btn-ghost-dark min-h-10 px-4 text-xs">Categorias</a>
        <a href="{{ route('admin.services.create') }}" class="btn-dark min-h-10 px-4 text-xs">Novo serviço</a>
    </x-slot:actions>

    <form method="GET" class="mb-5 flex flex-col gap-3 sm:flex-row">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar serviço…" class="form-input sm:max-w-xs" aria-label="Buscar serviço">
        <select name="status" class="form-input sm:max-w-[12rem]" aria-label="Situação">
            <option value="">Todos</option>
            <option value="active" @selected(request('status') === 'active')>Ativos</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inativos</option>
        </select>
        <button class="btn-ghost-dark min-h-11">Filtrar</button>
    </form>

    @if ($services->isEmpty())
        <div class="card text-center">
            <p class="font-semibold">Nenhum serviço cadastrado.</p>
            <p class="mt-1 text-sm text-stone">Cadastre os serviços reais com preço e duração informados pela barbearia.</p>
            <a href="{{ route('admin.services.create') }}" class="btn-dark mt-4">Cadastrar serviço</a>
        </div>
    @else
        {{-- Cards no celular, tabela no desktop --}}
        <ul class="grid gap-3 md:hidden">
            @foreach ($services as $s)
                <li class="card p-4">
                    <a href="{{ route('admin.services.edit', $s) }}" class="flex items-start justify-between gap-3">
                        <span><span class="font-semibold">{{ $s->name }}</span><span class="block text-xs text-stone">{{ $s->category?->name ?? 'Sem categoria' }} · {{ $s->duration_minutes }} min @if ($s->buffer_minutes)+ {{ $s->buffer_minutes }} intervalo @endif</span></span>
                        <span class="text-right text-sm font-semibold">{{ $s->formattedPrice() }}</span>
                    </a>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <span class="{{ $s->is_active ? 'badge-green' : 'badge-gray' }}">{{ $s->is_active ? 'Ativo' : 'Inativo' }}</span>
                        @if ($s->is_plan_eligible)<span class="badge-ink">Plano</span>@endif
                        @unless ($s->is_public)<span class="badge-gray">Oculto no site</span>@endunless
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="card hidden overflow-x-auto p-0 md:block">
            <table class="table-admin">
                <thead><tr><th>Serviço</th><th>Categoria</th><th>Preço</th><th>Duração</th><th>Profissionais</th><th>Situação</th><th class="text-right">Ações</th></tr></thead>
                <tbody>
                    @foreach ($services as $s)
                        <tr>
                            <td class="font-medium">{{ $s->name }}</td>
                            <td class="text-stone">{{ $s->category?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap">{{ $s->formattedPrice() }}</td>
                            <td class="whitespace-nowrap">{{ $s->duration_minutes }} min @if ($s->buffer_minutes)<span class="text-stone">+{{ $s->buffer_minutes }}</span>@endif</td>
                            <td>{{ $s->barbers_count }}</td>
                            <td class="space-x-1 whitespace-nowrap">
                                <span class="{{ $s->is_active ? 'badge-green' : 'badge-gray' }}">{{ $s->is_active ? 'Ativo' : 'Inativo' }}</span>
                                @if ($s->is_plan_eligible)<span class="badge-ink">Plano</span>@endif
                                @unless ($s->is_public)<span class="badge-gray">Oculto</span>@endunless
                                @unless ($s->is_bookable_standalone)<span class="badge-amber">Só plano</span>@endunless
                            </td>
                            <td class="text-right"><a href="{{ route('admin.services.edit', $s) }}" class="font-semibold underline">Editar</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $services->links() }}</div>
    @endif
</x-layouts.admin>
