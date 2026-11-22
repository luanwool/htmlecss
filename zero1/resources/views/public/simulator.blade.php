<x-layouts.public title="Checkout simulado" :noindex="true" body-class="bg-[#eef2f6] text-ink" :sticky-cta="false">
    <section class="container-site max-w-md py-12">
        <div class="rounded-3xl bg-white p-6 shadow-xl">
            <p class="rounded-lg bg-amber-100 px-3 py-2 text-center text-xs font-bold text-amber-950">SIMULADOR DE PAGAMENTO — ambiente de testes</p>
            <h1 class="mt-5 text-xl font-semibold">{{ $sub->term('name') }}</h1>
            <p class="text-sm text-graphite/70">Mensalidade {{ $cycle->sequence }} · {{ $cycle->period_start->format('d/m/Y') }} a {{ $cycle->lastDay()->format('d/m/Y') }}</p>
            <p class="mt-4 text-3xl font-bold">{{ \App\Support\Money::format($cycle->amount_cents) }}</p>
            <p class="mt-1 text-sm">{{ $sub->payment_method === 'card' ? 'Cartão de crédito (recorrente)' : 'Pix (cobrança do mês)' }}</p>
            <p class="mt-4 rounded-xl bg-paper p-3 text-xs text-graphite/80">Esta tela substitui o checkout oficial do Mercado Pago nesta versão. Nenhum dado de cartão é pedido. Escolha o resultado a simular.</p>
            <form method="POST" action="{{ route('simulator.pay', $sub->gateway_subscription_id) }}" class="mt-5 space-y-3">
                @csrf
                <input type="hidden" name="ref" value="{{ $cycle->externalReference() }}">
                <button name="outcome" value="approved" class="btn w-full bg-emerald-600 text-white hover:bg-emerald-700">Aprovar pagamento</button>
                <button name="outcome" value="rejected" class="btn w-full bg-red-600 text-white hover:bg-red-700">Recusar pagamento</button>
                @if ($sub->payment_method === 'pix')
                    <button name="outcome" value="pending" class="btn-ghost-dark w-full">Gerar Pix e pagar depois</button>
                @endif
                <label class="flex items-center gap-2 text-xs text-graphite/70"><input type="checkbox" name="lose_webhook" value="1" class="form-check"> Simular perda do webhook (recuperável pela conciliação)</label>
            </form>
        </div>
    </section>
</x-layouts.public>
