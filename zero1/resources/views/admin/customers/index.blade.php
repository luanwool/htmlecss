<x-layouts.admin title="Clientes">
    <form method="GET" class="mb-5 flex gap-3">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nome, e-mail ou telefone" class="form-input max-w-sm" aria-label="Buscar cliente">
        <button class="btn-ghost-dark min-h-11">Buscar</button>
    </form>
    <div class="card overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Nome</th><th>Contato</th><th>Origem</th><th>Conta</th><th>Desde</th></tr></thead>
            <tbody>
                @forelse ($customers as $c)
                    <tr>
                        <td><a class="font-medium underline-offset-2 hover:underline" href="{{ route('admin.customers.show', $c) }}">{{ $c->name }}</a></td>
                        <td class="text-stone">{{ $c->email ?? '—' }}<br>{{ $c->phone ?? '' }}</td>
                        <td>{{ ucfirst($c->origin) }}</td>
                        <td>@if ($c->user)<span class="{{ $c->user->email_verified_at ? 'badge-green' : 'badge-amber' }}">{{ $c->user->email_verified_at ? 'Verificada' : 'Não verificada' }}</span>@else<span class="badge-gray">Balcão</span>@endif</td>
                        <td class="whitespace-nowrap">{{ \App\Support\BusinessTime::format($c->created_at, 'd/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-stone">Nenhum cliente encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $customers->links() }}</div>
</x-layouts.admin>
