@php
    $service = $serviceId ? \App\Models\Service::publiclyListed()->find($serviceId) : null;
    $barber = $barberId ? \App\Models\Barber::visibleOnSite()->find($barberId) : null;
    $msg = $mode === 'subscribe'
        ? 'Olá! Tenho interesse no plano '.$plan->name.' do Clube Zero 1.'
        : 'Olá! Gostaria de agendar'.($service ? ' '.$service->name : ' um horário').($barber ? ' com '.$barber->display_name : '').'.';
@endphp
<x-layouts.public :title="$mode === 'subscribe' ? 'Assinar '.$plan->name : 'Agendar horário'" :noindex="true" body-class="bg-paper text-ink">
    <section class="container-site max-w-2xl py-16 md:py-24">
        <p class="eyebrow text-graphite/60">{{ $mode === 'subscribe' ? 'Clube Zero 1' : 'Agendamento' }}</p>
        <h1 class="display-lg mt-4">{{ $mode === 'subscribe' ? $plan->name : 'Agende seu horário' }}</h1>

        @if ($mode === 'subscribe')
            <p class="mt-4 text-lg">{{ $plan->formattedPrice() }}<span class="text-graphite/60">/mês · {{ $plan->usageSummary() }}</span></p>
        @elseif ($service || $barber)
            <p class="mt-4 text-graphite/75">Selecionado: {{ collect([$service?->name, $barber?->display_name])->filter()->implode(' com ') }}</p>
        @endif

        <div class="mt-10 rounded-3xl bg-white p-7 shadow-sm">
            <p class="font-semibold">{{ $mode === 'subscribe' ? 'A assinatura online estará disponível em breve.' : 'O agendamento online estará disponível em breve.' }}</p>
            <p class="mt-2 text-graphite/75">Por enquanto, fale com a barbearia pelos canais abaixo.
                {{ $mode === 'subscribe' ? 'O pagamento online será feito exclusivamente pelo checkout do Mercado Pago.' : '' }}</p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                @if ($wa = $site->whatsappUrl($msg))<a href="{{ $wa }}" target="_blank" rel="noopener" class="btn-dark"><x-icon name="whatsapp" class="size-4" /> Chamar no WhatsApp</a>@endif
                @if ($tel = $site->phoneHref())<a href="{{ $tel }}" class="btn-ghost-dark"><x-icon name="phone" class="size-4" /> {{ $site->get('business.phone') }}</a>@endif
            </div>
        </div>
        @guest
            <p class="mt-8 text-sm text-graphite/70">Já quer deixar sua conta pronta? <a href="{{ route('register') }}" class="font-semibold underline">Crie seu cadastro</a>.</p>
        @endguest
    </section>
</x-layouts.public>
