<x-layouts.admin :title="$service->exists ? 'Editar serviço' : 'Novo serviço'">
    <form method="POST" action="{{ $service->exists ? route('admin.services.update', $service) : route('admin.services.store') }}" class="grid gap-6 xl:grid-cols-3" data-loading>
        @csrf
        @if ($service->exists) @method('PUT') @endif

        <div class="space-y-6 xl:col-span-2">
            <section class="card space-y-5">
                <h2 class="font-semibold">Informações</h2>
                <x-form.input name="name" label="Nome" :value="$service->name" required maxlength="120" />
                <x-form.select name="service_category_id" label="Categoria" :value="$service->service_category_id" placeholder="Sem categoria"
                               :options="$categories->pluck('name', 'id')->all()" />
                <x-form.textarea name="description" label="Descrição curta" :value="$service->description" rows="2" data-count="500" maxlength="500" />
            </section>
            <section class="card space-y-5">
                <h2 class="font-semibold">Preço e tempo</h2>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="price" label="Preço avulso (R$)" inputmode="decimal" placeholder="0,00"
                                  :value="$service->price_cents !== null ? \App\Support\Money::format($service->price_cents, false) : null"
                                  hint="Deixe vazio para exibir “Sob consulta”." />
                    <x-form.checkbox name="price_is_from" label="Exibir como “a partir de”" :checked="$service->price_is_from" class="sm:pt-8" />
                    <x-form.input name="duration_minutes" type="number" min="5" max="600" step="5" label="Duração do serviço (min)" :value="$service->duration_minutes" required />
                    <x-form.input name="buffer_minutes" type="number" min="0" max="240" step="5" label="Intervalo de preparação/limpeza (min)" :value="$service->buffer_minutes"
                                  hint="Ocupa a agenda após o serviço. Duração efetiva = serviço + intervalo." />
                    <x-form.input name="min_advance_minutes" type="number" min="0" label="Antecedência mínima (min)" :value="$service->min_advance_minutes"
                                  hint="Vazio = política geral da barbearia." />
                    <x-form.input name="sort_order" type="number" min="0" label="Ordem de exibição" :value="$service->sort_order ?? 0" />
                </div>
            </section>
        </div>

        <div class="space-y-6">
            <section class="card space-y-4">
                <h2 class="font-semibold">Disponibilidade</h2>
                <x-form.checkbox name="is_active" label="Ativo" :checked="$service->is_active" hint="Inativo não pode ser agendado." />
                <x-form.checkbox name="is_public" label="Visível no site" :checked="$service->is_public" />
                <x-form.checkbox name="is_bookable_standalone" label="Vendável/agendável avulso" :checked="$service->is_bookable_standalone" hint="Desmarque para serviços exclusivos de plano." />
                <x-form.checkbox name="is_plan_eligible" label="Elegível para planos" :checked="$service->is_plan_eligible" />
            </section>
            <section class="card">
                <h2 class="font-semibold">Profissionais habilitados</h2>
                @forelse ($barbers as $b)
                    <x-form.checkbox name="barbers[]" :value="$b->id" :label="e($b->display_name)" class="mt-3"
                                     :checked="$service->exists ? $service->barbers->contains($b) : false" />
                @empty
                    <p class="mt-2 text-sm text-stone">Nenhum profissional cadastrado.</p>
                @endforelse
            </section>
            <div class="flex gap-3">
                <button type="submit" class="btn-dark flex-1">Salvar</button>
                <a href="{{ route('admin.services.index') }}" class="btn-ghost-dark">Cancelar</a>
            </div>
        </div>
    </form>

    @if ($service->exists)
        <form method="POST" action="{{ route('admin.services.destroy', $service) }}" class="mt-8" data-confirm="Remover este serviço? O histórico será preservado.">
            @csrf @method('DELETE')
            <button class="text-sm font-semibold text-red-700 underline">Remover serviço</button>
        </form>
    @endif
</x-layouts.admin>
