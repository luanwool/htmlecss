<x-layouts.public :title="$page->title" :description="$page->meta_description" :noindex="! $page->is_published" body-class="bg-paper text-ink">
    <article class="container-site max-w-3xl py-16 md:py-24">
        @unless ($page->is_published)
            <p class="mb-6 rounded-xl bg-amber-100 px-4 py-3 text-sm text-amber-900">Pré-visualização: esta página ainda não está publicada.</p>
        @endunless
        <p class="eyebrow text-graphite/70">{{ $site->businessName() }}</p>
        <h1 class="display-lg mt-4">{{ $page->title }}</h1>
        <p class="mt-4 text-sm text-graphite/70">Atualizado em {{ \App\Support\BusinessTime::format($page->updated_at, 'd/m/Y') }}</p>
        <div class="prose-legal mt-10">{{ $page->renderedBody() }}</div>
    </article>
</x-layouts.public>
