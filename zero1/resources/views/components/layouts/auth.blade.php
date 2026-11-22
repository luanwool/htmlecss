@props(['title', 'subtitle' => null])
<x-layouts.public :title="$title" :noindex="true" body-class="bg-ink text-paper">
    <section class="container-site grid min-h-[calc(100svh-5rem)] items-center gap-12 py-12 lg:grid-cols-2">
        <div class="hidden lg:block">
            <p class="font-display text-[9rem] leading-none numeral-outline" aria-hidden="true">01</p>
            <p class="mt-6 max-w-sm text-smoke">Agende, acompanhe seus horários e gerencie sua assinatura do Clube Zero 1 em um só lugar.</p>
        </div>
        <div class="mx-auto w-full max-w-md rounded-3xl bg-paper p-6 text-ink shadow-2xl sm:p-9">
            <h1 class="font-display text-3xl">{{ $title }}</h1>
            @if ($subtitle)<p class="mt-2 text-sm text-graphite/70">{{ $subtitle }}</p>@endif
            <x-flash class="mt-5" />
            <div class="mt-6">{{ $slot }}</div>
        </div>
    </section>
</x-layouts.public>
