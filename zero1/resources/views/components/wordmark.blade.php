@props(['size' => 'md', 'tone' => 'light'])
{{-- Marca textual provisória (somente o nome). Substituída automaticamente pelo logo oficial enviado no painel. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-baseline gap-2 leading-none '.($tone === 'dark' ? 'text-ink' : 'text-paper')]) }}>
    <span class="font-display {{ $size === 'lg' ? 'text-4xl' : 'text-2xl md:text-[1.7rem]' }} font-semibold tracking-tight">Zero<span class="text-accent">&thinsp;1</span></span>
    <span class="eyebrow text-[0.6rem] {{ $tone === 'dark' ? 'text-graphite/60' : 'text-stone' }}">Barbearia</span>
</span>
