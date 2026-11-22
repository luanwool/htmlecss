@props(['title' => null, 'description' => null, 'canonical' => null, 'noindex' => false, 'schema' => null, 'bodyClass' => 'bg-ink text-paper'])
@php
    $siteName = $site->businessName();
    $pageTitle = $title ? $title.' · '.$siteName : ($site->get('seo.title') ?: $siteName);
    $pageDescription = $description ?: $site->get('seo.description');
    $ogMedia = ($ogId = $site->get('seo.og_media_id')) ? \App\Models\MediaAsset::find($ogId) : null;
    $logo = ($logoId = $site->get('brand.logo_media_id')) ? \App\Models\MediaAsset::find($logoId) : null;
@endphp
<!DOCTYPE html>
<html lang="pt-BR" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $pageTitle }}</title>
    @if ($pageDescription)<meta name="description" content="{{ $pageDescription }}">@endif
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    @if ($noindex || ! $site->get('seo.indexable', true) || ! app()->environment('production'))
        <meta name="robots" content="noindex, nofollow">
    @endif
    <meta name="theme-color" content="#0d0d0e">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    @if ($pageDescription)<meta property="og:description" content="{{ $pageDescription }}">@endif
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
    @if ($ogMedia)
        <meta property="og:image" content="{{ $ogMedia->url(1600) }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <style>:root{--accent: {{ $site->accentColor() }};--accent-ink: {{ $site->accentInk() }};}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($schema)
        <script type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endif
</head>
<body class="{{ $bodyClass }} min-h-dvh antialiased">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-full focus:bg-paper focus:px-4 focus:py-2 focus:text-ink">Pular para o conteúdo</a>

    @if (config('zero1.demo_mode'))
        <div class="relative z-[60] bg-accent px-4 py-1.5 text-center text-xs font-semibold text-accent-ink" role="note">
            Ambiente de demonstração — itens marcados com [DEMO] são fictícios e não representam a Zero 1 Barbearia.
        </div>
    @endif

    @include('partials.site-header', ['logo' => $logo])

    <main id="conteudo">
        {{ $slot }}
    </main>

    @include('partials.site-footer', ['logo' => $logo])

    {{-- CTA fixo no celular --}}
    <div data-sticky-cta class="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-ink/90 px-4 pb-[max(.75rem,env(safe-area-inset-bottom))] pt-3 backdrop-blur-md transition-transform duration-500 data-[hidden]:translate-y-full md:hidden">
        <div class="flex gap-2">
            <a href="{{ route('booking.start') }}" class="btn-accent flex-1">Agendar horário</a>
            @if ($wa = $site->whatsappUrl())
                <a href="{{ $wa }}" class="btn-ghost-light px-4" target="_blank" rel="noopener" aria-label="Falar no WhatsApp">
                    <x-icon name="whatsapp" class="size-5" />
                </a>
            @endif
        </div>
    </div>

    @if ($site->get('marketing.ga4_id') || $site->get('marketing.meta_pixel_id'))
        <div data-consent hidden data-ga4="{{ $site->get('marketing.ga4_id') }}" data-pixel="{{ $site->get('marketing.meta_pixel_id') }}"
             class="fixed inset-x-3 bottom-24 z-50 mx-auto max-w-lg rounded-2xl border border-white/10 bg-graphite p-5 text-sm text-smoke shadow-2xl md:bottom-6" role="dialog" aria-label="Preferências de cookies">
            <p>Usamos cookies de medição e marketing somente com o seu consentimento. Os cookies essenciais ao funcionamento do site estão sempre ativos.
                @if ($legalPages->firstWhere('slug', 'privacidade'))<a class="underline" href="{{ route('pages.show', 'privacidade') }}">Saiba mais</a>.@endif</p>
            <div class="mt-4 flex gap-2">
                <button type="button" data-consent-choice="granted" class="btn-accent min-h-10 px-5">Aceitar</button>
                <button type="button" data-consent-choice="denied" class="btn-ghost-light min-h-10 px-5">Recusar</button>
            </div>
        </div>
    @endif
</body>
</html>
