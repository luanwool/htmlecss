<x-layouts.admin title="Permissões do gestor">
    <form method="POST" action="{{ route('admin.roles.update') }}" class="card max-w-3xl" data-loading>
        @csrf @method('PUT')
        <p class="text-sm text-stone">O proprietário sempre tem acesso completo. Barbeiros só acessam a própria agenda e lançamentos.</p>
        <div class="mt-5 grid gap-x-8 sm:grid-cols-2">
            @foreach ($permissions as $p)
                <x-form.checkbox name="permissions[]" :value="$p->value" :label="e($p->label())" class="mt-3" :checked="in_array($p->value, $granted)"
                                 :hint="in_array($p, \App\Enums\Permission::ownerOnly(), true) ? 'Sensível — recomendado só para o proprietário' : null" />
            @endforeach
        </div>
        <button class="btn-dark mt-6">Salvar permissões</button>
    </form>
</x-layouts.admin>
