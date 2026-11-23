<x-layouts.admin title="Equipe e acessos">
    <x-slot:actions>
        <a href="{{ route('admin.roles.edit') }}" class="btn-ghost-dark min-h-10 px-4 text-xs">Permissões do gestor</a>
        <a href="{{ route('admin.users.create') }}" class="btn-dark min-h-10 px-4 text-xs">Novo usuário</a>
    </x-slot:actions>
    <form method="GET" class="mb-5 flex flex-col gap-3 sm:flex-row">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nome ou e-mail" class="form-input sm:max-w-xs" aria-label="Buscar">
        <select name="papel" class="form-input sm:max-w-[14rem]" aria-label="Cargo">
            <option value="">Equipe (todos os cargos)</option>
            @foreach ($roles as $r)<option value="{{ $r->value }}" @selected(request('papel') === $r->value)>{{ $r->label() }}</option>@endforeach
        </select>
        <button class="btn-ghost-dark min-h-11">Filtrar</button>
    </form>
    <div class="card overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Nome</th><th>E-mail</th><th>Cargo</th><th>Situação</th><th>Último acesso</th><th></th></tr></thead>
            <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td class="font-medium">{{ $u->name }}</td>
                        <td class="text-stone">{{ $u->email }}</td>
                        <td>{{ $u->roles->map(fn ($r) => \App\Enums\Role::from($r->name)->label())->implode(', ') }}</td>
                        <td><span class="{{ $u->is_active ? 'badge-green' : 'badge-red' }}">{{ $u->is_active ? 'Ativo' : 'Desativado' }}</span></td>
                        <td class="whitespace-nowrap text-stone">{{ \App\Support\BusinessTime::format($u->last_login_at) }}</td>
                        <td class="text-right"><a href="{{ route('admin.users.edit', $u) }}" class="font-semibold underline">Editar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-stone">Nenhum usuário.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.admin>
