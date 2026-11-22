@props(['size' => 'md', 'tone' => 'light'])
{{-- Marca textual provisória (somente o nome). Substituída automaticamente pelo logo oficial enviado no painel. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-baseline gap-2 leading-none '.($tone === 'dark' ? 'text-ink' : 'text-paper')]) }}>
    <span class="font-future {{ $size === 'lg' ? 'text-3xl' : 'text-xl md:text-[1.4rem]' }} font-semibold tracking-tight">ZERO<span class="text-accent">&thinsp;1</span></span>
    <span class="font-mono text-[0.6rem] uppercase tracking-[0.3em] {{ $tone === 'dark' ? 'text-graphite/70' : 'text-stone' }}">Barbearia</span>
</span>
