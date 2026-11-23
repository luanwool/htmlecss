<x-layouts.admin :title="$barber->exists ? 'Editar profissional' : 'Novo profissional'">
    <form method="POST" enctype="multipart/form-data" action="{{ $barber->exists ? route('admin.barbers.update', $barber) : route('admin.barbers.store') }}" class="grid gap-6 xl:grid-cols-3" data-loading>
        @csrf
        @if ($barber->exists) @method('PUT') @endif
        <div class="space-y-6 xl:col-span-2">
            <section class="card space-y-5">
                <h2 class="font-semibold">Perfil público</h2>
                <x-form.input name="display_name" label="Nome de exibição" :value="$barber->display_name" required maxlength="80" />
                <x-form.textarea name="bio" label="Apresentação" :value="$barber->bio" rows="4" data-count="1000" maxlength="1000" />
                <x-form.input name="specialties" label="Especialidades" :value="implode(', ', $barber->specialties ?? [])" hint="Separe por vírgula." />
                <div>
                    <p class="form-label">Foto</p>
                    <div class="flex items-start gap-4">
                        <img id="photo-preview" @if ($barber->photo) src="{{ $barber->photo->url(480) }}" @else hidden @endif alt="" class="size-28 rounded-2xl object-cover">
                        <div class="flex-1 space-y-3">
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-preview="photo-preview" class="block w-full text-sm file:mr-3 file:rounded-full file:border-0 file:bg-ink file:px-4 file:py-2 file:text-paper">
                            <p class="form-hint">Somente foto real e autorizada pelo profissional. JPG/PNG/WebP, mín. 300×300.</p>
                            @error('photo')<p class="form-error">{{ $message }}</p>@enderror
                            <x-form.input name="photo_alt" label="Texto alternativo da foto" :value="$barber->photo?->alt_text" />
                            @if ($barber->photo)<x-form.checkbox name="remove_photo" label="Remover foto atual" />@endif
                        </div>
                    </div>
                </div>
            </section>
            <section class="card">
                <h2 class="font-semibold">Serviços que realiza</h2>
                <div class="mt-2 grid gap-x-6 sm:grid-cols-2">
                    @forelse ($services as $s)
                        <x-form.checkbox name="services[]" :value="$s->id" :label="e($s->name)" class="mt-3" :checked="$barber->exists && $barber->services->contains($s)" />
                    @empty
                        <p class="text-sm text-stone">Cadastre serviços primeiro.</p>
                    @endforelse
                </div>
            </section>
        </div>
        <div class="space-y-6">
            <section class="card space-y-5">
                <h2 class="font-semibold">Situação</h2>
                <x-form.select name="status" label="Status" :value="$barber->status" required
                               :options="collect(\App\Enums\BarberStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()"
                               hint="Afastado: aparece no site, mas sem agendamento. Desativado: some do site." />
                <x-form.checkbox name="is_public" label="Exibir no site" :checked="$barber->is_public" />
                <x-form.checkbox name="accepts_any_assignment" label="Participa de “Qualquer barbeiro disponível”" :checked="$barber->accepts_any_assignment" />
                <x-form.input name="sort_order" type="number" min="0" label="Ordem" :value="$barber->sort_order ?? 0" />
            </section>
            <section class="card space-y-3">
                <h2 class="font-semibold">Acesso ao painel do barbeiro</h2>
                <x-form.select name="user_id" label="Usuário vinculado" :value="$barber->user_id" placeholder="Sem acesso"
                               :options="$users->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.$u->email.')'])->all()" />
                <p class="form-hint">Crie o usuário com o cargo Barbeiro em <a class="underline" href="{{ route('admin.users.create') }}">Equipe e acessos</a>.</p>
            </section>
            <div class="flex gap-3">
                <button class="btn-dark flex-1">Salvar</button>
                <a href="{{ route('admin.barbers.index') }}" class="btn-ghost-dark">Cancelar</a>
            </div>
        </div>
    </form>
    @if ($barber->exists)
        <form method="POST" action="{{ route('admin.barbers.destroy', $barber) }}" class="mt-8" data-confirm="Remover o profissional do cadastro ativo? O histórico de atendimentos é preservado.">
            @csrf @method('DELETE')<button class="text-sm font-semibold text-red-700 underline">Remover profissional</button>
        </form>
    @endif
</x-layouts.admin>
