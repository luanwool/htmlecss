<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceVisit extends Model
{
    public const PAYMENT_METHODS = [
        'pix' => 'Pix', 'dinheiro' => 'Dinheiro', 'cartao_credito' => 'Cartão de crédito',
        'cartao_debito' => 'Cartão de débito', 'cortesia' => 'Cortesia', 'outro' => 'Outro',
    ];

    public const ORIGINS = ['balcao' => 'Balcão', 'telefone' => 'Telefone', 'whatsapp' => 'WhatsApp', 'online' => 'Online', 'painel' => 'Painel'];

    protected $fillable = [
        'appointment_id', 'customer_id', 'barber_id', 'service_id', 'service_name_snapshot', 'performed_at', 'mode',
        'price_cents', 'discount_cents', 'final_cents', 'payment_method', 'payment_status', 'origin', 'subscription_id',
        'billing_cycle_id', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['performed_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function commission(): HasOne
    {
        return $this->hasOne(CommissionLedgerEntry::class);
    }
}
