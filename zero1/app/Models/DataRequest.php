<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataRequest extends Model
{
    public const TYPES = ['deletion' => 'Exclusão de dados', 'correction' => 'Correção de dados', 'export' => 'Cópia dos dados'];

    public const STATUSES = ['open' => 'Aberta', 'in_progress' => 'Em andamento', 'done' => 'Concluída', 'rejected' => 'Recusada (justificada)'];

    protected $fillable = ['user_id', 'type', 'message', 'status', 'resolution_note', 'handled_by', 'handled_at'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
