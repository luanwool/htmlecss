<x-layouts.admin title="Auditoria">
    <form method="GET" class="mb-5 flex gap-3">
        <input name="action" value="{{ request('action') }}" placeholder="Filtrar ação (ex.: plan., service., auth.)" class="form-input max-w-sm" aria-label="Ação">
        <button class="btn-ghost-dark min-h-11">Filtrar</button>
    </form>
    <div class="card overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Quando</th><th>Quem</th><th>Ação</th><th>Registro</th><th>Alterações</th><th>IP</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr class="align-top">
                        <td class="whitespace-nowrap">{{ \App\Support\BusinessTime::format($log->created_at, 'd/m/Y H:i:s') }}</td>
                        <td>{{ $log->user?->name ?? 'sistema' }}</td>
                        <td><code class="text-xs">{{ $log->action }}</code></td>
                        <td class="text-xs text-stone">{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</td>
                        <td class="max-w-md text-xs">
                            @if ($log->new_values)
                                <details><summary class="text-stone underline">{{ count($log->new_values) }} campo(s)</summary>
                                    <dl class="mt-2 space-y-1">
                                        @foreach ($log->new_values as $k => $v)
                                            <div><dt class="font-semibold">{{ $k }}</dt>
                                                <dd class="break-words text-stone">{{ \Illuminate\Support\Str::limit(is_scalar($log->old_values[$k] ?? null) ? (string) $log->old_values[$k] : json_encode($log->old_values[$k] ?? null, JSON_UNESCAPED_UNICODE), 120) }} → {{ \Illuminate\Support\Str::limit(is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), 120) }}</dd></div>
                                        @endforeach
                                    </dl>
                                </details>
                            @else — @endif
                        </td>
                        <td class="text-xs text-stone">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-stone">Sem registros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.admin>
