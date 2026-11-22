<x-layouts.admin :title="$user->exists ? 'Editar usuário' : 'Novo usuário'">
    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="card max-w-2xl space-y-5" data-loading>
        @csrf @if ($user->exists) @method('PUT') @endif
        <x-form.input name="name" label="Nome" :value="$user->name" required />
        <x-form.input name="email" type="email" label="E-mail" :value="$user->email" required />
        <x-form.input name="phone" type="tel" label="Telefone" :value="$user->phone" />
        <x-form.select name="role" label="Cargo" :value="$currentRole" required
                       :options="collect($roles)->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all()"
                       hint="Barbeiro: vê só a própria agenda. Gestor: conforme permissões. Proprietário: acesso total." />
        <x-form.checkbox name="is_active" label="Acesso ativo" :checked="$user->is_active" />
        @unless ($user->exists)
            <p class="rounded-xl bg-paper p-3 text-sm text-graphite">O usuário receberá por e-mail um link para definir a própria senha.</p>
        @endunless
        <div class="flex gap-3"><button class="btn-dark">Salvar</button><a href="{{ route('admin.users.index') }}" class="btn-ghost-dark">Cancelar</a></div>
    </form>
    @if ($user->exists && ! $user->isOwner() && ! $user->is(auth()->user()))
        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="mt-6" data-confirm="Remover o acesso deste usuário?">@csrf @method('DELETE')<button class="text-sm font-semibold text-red-700 underline">Remover acesso</button></form>
    @endif
</x-layouts.admin>
