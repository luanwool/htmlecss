<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinancialEntry extends Model
{
    public const EXPENSE_CATEGORIES = [
        'despesa:aluguel' => 'Aluguel', 'despesa:produtos' => 'Produtos', 'despesa:salarios' => 'Salários/comissões pagas',
        'despesa:contas' => 'Contas (luz, água, internet)', 'despesa:marketing' => 'Marketing', 'despesa:outros' => 'Outras despesas',
    ];

    protected $fillable = ['type', 'category', 'amount_cents', 'fee_cents', 'occurred_at', 'payment_method', 'status', 'source_type', 'source_id', 'description', 'created_by'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
