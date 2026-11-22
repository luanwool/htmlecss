<x-layouts.admin :title="'Seção: '.$section->label()">
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.sections.update', $section) }}" class="grid max-w-5xl gap-6 lg:grid-cols-3" data-loading>
        @csrf @method('PUT')
        <div class="card space-y-5 lg:col-span-2">
            <x-form.input name="eyebrow" label="Sobretítulo" :value="$section->eyebrow" maxlength="80" />
            <x-form.input name="title" label="Título" :value="$section->title" maxlength="160" />
            <x-form.textarea name="subtitle" label="Texto de apoio" :value="$section->subtitle" rows="3" data-count="600" maxlength="600" />
            @foreach ($extraFields as $field => $label)
                @if (str_ends_with($field, '_text'))
                    <x-form.textarea :name="'content['.$field.']'" :label="$label" :value="$section->field($field)" rows="2" maxlength="180" />
                @else
                    <x-form.input :name="'content['.$field.']'" :label="$label" :value="$section->field($field)" maxlength="60" />
                @endif
            @endforeach
        </div>
        <div class="space-y-6">
            <div class="card space-y-4">
                <x-form.checkbox name="is_visible" label="Seção visível" :checked="$section->is_visible" />
                <x-form.input name="sort_order" type="number" min="0" label="Ordem na página" :value="$section->sort_order" />
            </div>
            @if ($section->key === 'hero')
                <div class="card space-y-3">
                    <p class="form-label">Imagem de fundo</p>
                    <img id="media-preview" @if ($section->media) src="{{ $section->media->url(960) }}" @else hidden @endif alt="" class="w-full rounded-xl object-cover">
                    <input type="file" name="media" accept="image/jpeg,image/png,image/webp" data-preview="media-preview" class="block w-full text-sm file:mr-3 file:rounded-full file:border-0 file:bg-ink file:px-4 file:py-2 file:text-paper">
                    <p class="form-hint">Somente fotos reais da Zero 1. Sem imagem, o hero usa composição tipográfica.</p>
                    @error('media')<p class="form-error">{{ $message }}</p>@enderror
                    <x-form.input name="media_alt" label="Texto alternativo" :value="$section->media?->alt_text" />
                    @if ($section->media)<x-form.checkbox name="remove_media" label="Remover imagem" />@endif
                </div>
            @endif
            <button class="btn-dark w-full">Salvar seção</button>
        </div>
    </form>
</x-layouts.admin>
