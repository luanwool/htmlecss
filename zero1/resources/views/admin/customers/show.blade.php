<x-layouts.admin :title="$customer->name">
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card space-y-3 text-sm">
            <h2 class="font-semibold">Contato</h2>
            <p><span class="text-stone">E-mail:</span> {{ $customer->email ?? '—' }}</p>
            <p><span class="text-stone">Telefone:</span> {{ $customer->phone ?? '—' }}</p>
            <p><span class="text-stone">Origem:</span> {{ ucfirst($customer->origin) }}</p>
            <p><span class="text-stone">Termos aceitos:</span> {{ \App\Support\BusinessTime::format($customer->terms_accepted_at) }}</p>
            <p><span class="text-stone">Marketing:</span> {{ $customer->marketing_consent_at ? 'consentiu em '.\App\Support\BusinessTime::format($customer->marketing_consent_at, 'd/m/Y') : 'não' }}</p>
        </section>
        <section class="card lg:col-span-2">
            <h2 class="font-semibold">Histórico</h2>
            <p class="mt-2 text-sm text-stone">Agendamentos, assinaturas e atendimentos aparecerão aqui a partir das Fases 2–4.</p>
        </section>
    </div>
</x-layouts.admin>
