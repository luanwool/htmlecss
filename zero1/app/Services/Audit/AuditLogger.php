<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Trilha de auditoria para ações sensíveis. Nunca registra senhas, tokens ou dados de cartão.
 */
class AuditLogger
{
    private const REDACTED = ['password', 'remember_token', 'token', 'secret', 'access_token', 'card', 'cvv'];

    public function log(string $action, ?Model $subject = null, array $old = [], array $new = [], array $context = []): AuditLog
    {
        $request = app()->runningInConsole() ? null : request();

        return AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $this->clean($old) ?: null,
            'new_values' => $this->clean($new) ?: null,
            'context' => $context ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : 'console',
        ]);
    }

    /** Registra somente os campos que mudaram em um model já salvo. */
    public function logChanges(string $action, Model $model, array $original): ?AuditLog
    {
        $changes = Arr::except($model->getChanges(), ['updated_at']);
        if (! $changes) {
            return null;
        }

        return $this->log($action, $model, Arr::only($original, array_keys($changes)), $changes);
    }

    private function clean(array $values): array
    {
        foreach ($values as $key => $value) {
            foreach (self::REDACTED as $needle) {
                if (str_contains(strtolower((string) $key), $needle)) {
                    $values[$key] = '[removido]';

                    continue 2;
                }
            }
        }

        return $values;
    }
}
