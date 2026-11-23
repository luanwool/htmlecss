<x-layouts.public title="Status do pagamento" :noindex="true" body-class="bg-paper text-ink">
    <section class="container-site max-w-xl py-16 text-center">
        @if (! $subscription)
            <h1 class="display-md">Nenhuma assinatura encontrada.</h1>
        @elseif (in_array($subscription->status->value, ['active', 'cancel_requested']))
            <p class="eyebrow text-emerald-700">Pagamento confirmado</p>
            <h1 class="display-md mt-3">Bem-vindo ao Clube Zero 1!</h1>
            <p class="mt-3 text-graphite/75">Seus atendimentos do mês estão liberados.</p>
            <a href="{{ route('booking.start') }}" class="btn-dark mt-8">Agendar agora</a>
        @else
            <p class="eyebrow text-amber-700">{{ $subscription->status->label() }}</p>
            <h1 class="display-md mt-3">Aguardando a confirmação do pagamento</h1>
            <p class="mt-3 text-graphite/75">Retornar do pagamento não confirma a assinatura por si só. Assim que o provedor nos notificar a aprovação, seu plano é liberado automaticamente. Atualize esta página em instantes.</p>
            <div class="mt-8 flex justify-center gap-3">
                <a href="{{ route('subscription.return') }}" class="btn-dark">Atualizar status</a>
                <a href="{{ route('account.subscription') }}" class="btn-ghost-dark">Minha assinatura</a>
            </div>
        @endif
    </section>
</x-layouts.public>
