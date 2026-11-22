<x-layouts.admin title="Visão geral">
    @if ($demoData)
        <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
            Há dados de demonstração ([DEMO]) no banco. Eles nunca devem ir para produção — rode a implantação sem o DemoSeeder.
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ($stats as $label => $value)
            <div class="card">
                <p class="text-xs font-medium uppercase tracking-wider text-stone">{{ $label }}</p>
                <p class="mt-2 font-display text-4xl">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="card lg:col-span-2" aria-labelledby="pending-title">
            <h2 id="pending-title" class="font-semibold">Informações reais pendentes</h2>
            <p class="mt-1 text-sm text-stone">O site só exibe o que for cadastrado aqui. Nada é inventado.</p>
            @if ($pending)
                <ul class="mt-4 divide-y divide-black/[.06]">
                    @foreach ($pending as [$label, $url])
                        <li class="flex items-center justify-between gap-4 py-3 text-sm">
                            <span class="flex items-center gap-3"><span class="size-2 shrink-0 rounded-full bg-amber-500"></span>{{ $label }}</span>
                            @if ($url)<a href="{{ $url }}" class="shrink-0 font-semibold underline">Resolver</a>@endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-4 text-sm text-emerald-800">Tudo preenchido.</p>
            @endif
        </section>
        <section class="card" aria-labelledby="roadmap-title">
            <h2 id="roadmap-title" class="font-semibold">Implantação por fases</h2>
            <ol class="mt-4 space-y-3 text-sm">
                <li class="flex gap-3"><span class="badge-green">F1</span> Site, CMS, acessos, catálogo</li>
                <li class="flex gap-3"><span class="badge-gray">F2</span> Agenda e disponibilidade</li>
                <li class="flex gap-3"><span class="badge-gray">F3</span> Clube + Mercado Pago</li>
                <li class="flex gap-3"><span class="badge-gray">F4</span> Atendimentos, financeiro, relatórios</li>
                <li class="flex gap-3"><span class="badge-gray">F5</span> Deploy e homologação</li>
            </ol>
            @if ($openDataRequests)
                <a href="{{ route('admin.data-requests.index') }}" class="mt-5 block rounded-xl bg-red-50 p-3 text-sm text-red-900">{{ $openDataRequests }} solicitação(ões) LGPD em aberto</a>
            @endif
        </section>
    </div>
</x-layouts.admin>
