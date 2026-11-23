<x-layouts.admin title="Solicitações LGPD">
    <div class="space-y-4">
        @forelse ($requests as $r)
            <div class="card">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold">{{ \App\Models\DataRequest::TYPES[$r->type] }} — {{ $r->user?->name ?? 'usuário removido' }}</p>
                        <p class="text-sm text-stone">{{ $r->user?->email }} · {{ \App\Support\BusinessTime::format($r->created_at) }}</p>
                    </div>
                    <span class="{{ $r->status === 'open' ? 'badge-red' : ($r->status === 'done' ? 'badge-green' : 'badge-gray') }}">{{ \App\Models\DataRequest::STATUSES[$r->status] }}</span>
                </div>
                @if ($r->message)<p class="mt-3 rounded-xl bg-paper p-3 text-sm">{{ $r->message }}</p>@endif
                <form method="POST" action="{{ route('admin.data-requests.update', $r) }}" class="mt-4 grid gap-3 sm:grid-cols-[12rem_1fr_auto] sm:items-end">
                    @csrf @method('PUT')
                    <select name="status" class="form-input" aria-label="Situação">
                        @foreach (\App\Models\DataRequest::STATUSES as $k => $l)<option value="{{ $k }}" @selected($r->status === $k)>{{ $l }}</option>@endforeach
                    </select>
                    <input name="resolution_note" value="{{ $r->resolution_note }}" placeholder="Resposta ao titular / justificativa legal" class="form-input" aria-label="Resposta">
                    <button class="btn-dark min-h-11">Atualizar</button>
                </form>
            </div>
        @empty
            <p class="card text-sm text-stone">Nenhuma solicitação.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $requests->links() }}</div>
</x-layouts.admin>
