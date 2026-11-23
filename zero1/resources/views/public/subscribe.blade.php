<x-layouts.public :title="'Assinar '.$plan->name" :noindex="true" body-class="bg-paper text-ink">
    <section class="container-site grid max-w-5xl gap-10 py-10 md:py-16 lg:grid-cols-5">
        <div class="lg:col-span-3">
            <p class="eyebrow text-graphite/60">Clube Zero 1</p>
            <h1 class="display-lg mt-3">{{ $plan->name }}</h1>
            <p class="mt-4 font-display text-4xl">{{ $plan->formattedPrice() }}<span class="text-base text-graphite/60">/mês</span></p>
            <p class="mt-1 text-sm font-medium">{{ $plan->usageSummary() }}</p>
            @if ($plan->description)<p class="mt-4 text-graphite/80">{{ $plan->description }}</p>@endif
            <h2 class="mt-8 font-semibold">Serviços cobertos</h2>
            <ul class="mt-2 list-disc pl-5 text-sm">@foreach ($plan->services as $s)<li>{{ $s->name }}@if ($s->pivot->uses_per_cycle) — {{ $s->pivot->uses_per_cycle }}x/mês @endif</li>@endforeach</ul>
            @if ($rules = $plan->ruleLines())
                <h2 class="mt-6 font-semibold">Regras de uso</h2>
                <ul class="mt-2 list-disc pl-5 text-sm">@foreach ($rules as $r)<li>{{ $r }}</li>@endforeach</ul>
            @endif
            @foreach (['cancellation_policy' => 'Cancelamento e reagendamento', 'no_show_policy' => 'Faltas e atrasos', 'terms' => 'Condições específicas'] as $f => $label)
                @if ($plan->$f)<h2 class="mt-6 font-semibold">{{ $label }}</h2><p class="mt-2 whitespace-pre-line text-sm text-graphite/80">{{ $plan->$f }}</p>@endif
            @endforeach
        </div>

        <div class="lg:col-span-2">
            <div class="rounded-3xl bg-white p-6 shadow-sm lg:sticky lg:top-28">
                @if ($simulated)
                    <p class="mb-4 rounded-xl bg-amber-100 px-3 py-2 text-xs font-semibold text-amber-950">MODO DE TESTE — pagamento simulado. Nenhuma cobrança real será feita.</p>
                @endif
                @if ($existing && ! in_array($existing->status->value, ['pending', 'awaiting_payment']))
                    <p class="text-sm">Você já possui uma assinatura ({{ $existing->status->label() }}).</p>
                    <a href="{{ route('account.subscription') }}" class="btn-dark mt-4 w-full">Ver minha assinatura</a>
                @else
                    <x-flash class="mb-4" />
                    @error('subscription')<p class="form-error mb-3">{{ $message }}</p>@enderror
                    <form method="POST" action="{{ route('plans.subscribe.store', $plan) }}" class="space-y-5" data-loading>
                        @csrf
                        <fieldset>
                            <legend class="form-label">Forma de pagamento</legend>
                            <div class="space-y-2">
                                <label class="flex cursor-pointer gap-3 rounded-xl border border-black/10 p-4 has-[:checked]:border-ink">
                                    <input type="radio" name="method" value="card" class="mt-1 accent-ink" @checked(old('method', 'card') === 'card')>
                                    <span><span class="font-semibold">Cartão de crédito</span><span class="block text-xs text-graphite/70">Cobrança automática todo mês até você cancelar.</span></span>
                                </label>
                                <label class="flex cursor-pointer gap-3 rounded-xl border border-black/10 p-4 has-[:checked]:border-ink">
                                    <input type="radio" name="method" value="pix" class="mt-1 accent-ink" @checked(old('method') === 'pix')>
                                    <span><span class="font-semibold">Pix</span><span class="block text-xs text-graphite/70">
                                        @if ($automaticPix) Débito automático via Pix Automático, com sua autorização. @else Cobrança mensal: a cada mês você paga a mensalidade pelo Pix. <strong>A renovação não é automática.</strong> @endif
                                    </span></span>
                                </label>
                            </div>
                        </fieldset>
                        <x-form.checkbox name="accept_terms" :label="'Li e aceito as regras deste plano'.($termsPage ? ' e os <a class=&quot;underline&quot; target=&quot;_blank&quot; href=&quot;'.e(route('pages.show', $termsPage)).'&quot;>termos do Clube</a>' : '').'. Entendo que os benefícios são liberados somente após a confirmação do pagamento de cada mês e que o cancelamento impede a próxima renovação, mantendo o período já pago.'" />
                        <button class="btn-dark w-full" data-loading-text="Abrindo pagamento…">Ir para o pagamento</button>
                        <p class="text-center text-xs text-graphite/60">O pagamento é feito no ambiente do provedor. Não armazenamos dados de cartão.</p>
                    </form>
                @endif
            </div>
        </div>
    </section>
</x-layouts.public>
