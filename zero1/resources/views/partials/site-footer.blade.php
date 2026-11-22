<footer class="border-t border-white/10 bg-ink pb-28 pt-16 text-sm text-smoke md:pb-12" data-hide-sticky>
    <div class="container-site grid gap-12 md:grid-cols-12">
        <div class="md:col-span-5">
            @if ($logo)
                <img src="{{ $logo->url(480) }}" alt="{{ $site->businessName() }}" class="h-12 w-auto" loading="lazy">
            @else
                <x-wordmark size="lg" />
            @endif
            @if ($tagline = $site->get('brand.tagline'))
                <p class="mt-4 max-w-sm">{{ $tagline }}</p>
            @endif
        </div>
        <div class="md:col-span-4">
            <p class="eyebrow text-stone">Contato</p>
            <ul class="mt-4 space-y-2">
                @if ($address = $site->fullAddress())<li>{{ $address }}</li>@endif
                @if ($phone = $site->get('business.phone'))<li><a class="link-underline hover:text-paper" href="{{ $site->phoneHref() }}">{{ $phone }}</a></li>@endif
                @if ($wa = $site->whatsappUrl())<li><a class="link-underline hover:text-paper" href="{{ $wa }}" target="_blank" rel="noopener">WhatsApp</a></li>@endif
                @if ($email = $site->get('business.email'))<li><a class="link-underline hover:text-paper" href="mailto:{{ $email }}">{{ $email }}</a></li>@endif
                @if ($ig = $site->get('business.instagram'))<li><a class="link-underline hover:text-paper" href="{{ $ig }}" target="_blank" rel="noopener">Instagram</a></li>@endif
            </ul>
        </div>
        <div class="md:col-span-3">
            <p class="eyebrow text-stone">Institucional</p>
            <ul class="mt-4 space-y-2">
                @foreach ($legalPages as $p)
                    <li><a class="link-underline hover:text-paper" href="{{ route('pages.show', $p->slug) }}">{{ $p->title }}</a></li>
                @endforeach
                @if ($site->get('marketing.ga4_id') || $site->get('marketing.meta_pixel_id'))
                    <li><button type="button" data-consent-reopen class="link-underline hover:text-paper">Preferências de cookies</button></li>
                @endif
                <li><a class="link-underline hover:text-paper" href="{{ route('login') }}">Área do cliente</a></li>
            </ul>
        </div>
    </div>
    <div class="container-site mt-14 flex flex-col gap-2 border-t border-white/10 pt-6 text-xs text-stone sm:flex-row sm:justify-between">
        <p>© {{ now()->year }} {{ $site->get('business.legal_name') ?: $site->businessName() }}@if ($doc = $site->get('business.document')) · CNPJ {{ $doc }}@endif</p>
        <p>Pagamentos online processados pelo Mercado Pago. Não armazenamos dados de cartão.</p>
    </div>
</footer>
