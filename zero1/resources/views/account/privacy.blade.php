<x-layouts.account title="Privacidade e dados">
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card">
            <h2 class="font-semibold">Baixar meus dados</h2>
            <p class="mt-2 text-sm text-graphite/70">Arquivo JSON com os dados pessoais associados à sua conta.</p>
            <a href="{{ route('account.privacy.export') }}" class="btn-dark mt-5">Baixar cópia</a>
        </div>
        <form method="POST" action="{{ route('account.privacy.request') }}" class="card space-y-4" data-loading>
            @csrf
            <h2 class="font-semibold">Solicitar correção ou exclusão</h2>
            <p class="text-sm text-graphite/70">Alguns dados podem precisar ser mantidos por obrigação legal (por exemplo, registros financeiros). A barbearia responderá informando o que foi feito.</p>
            <x-form.select name="type" label="Tipo de solicitação" :options="\App\Models\DataRequest::TYPES" required />
            <x-form.textarea name="message" label="Detalhes (opcional)" rows="3" />
            <button class="btn-dark">Enviar solicitação</button>
        </form>
    </div>
    @if ($requests->isNotEmpty())
        <div class="card mt-6 overflow-x-auto">
            <h2 class="font-semibold">Minhas solicitações</h2>
            <table class="table-admin mt-4">
                <thead><tr><th>Data</th><th>Tipo</th><th>Situação</th><th>Resposta</th></tr></thead>
                <tbody>
                    @foreach ($requests as $r)
                        <tr><td>{{ \App\Support\BusinessTime::format($r->created_at) }}</td><td>{{ \App\Models\DataRequest::TYPES[$r->type] }}</td>
                            <td>{{ \App\Models\DataRequest::STATUSES[$r->status] }}</td><td>{{ $r->resolution_note ?? '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.account>
