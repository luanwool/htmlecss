<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_plan_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('plan_version');
            $table->json('plan_snapshot'); // condições congeladas na adesão
            $table->unsignedInteger('price_cents');
            $table->string('status', 30)->index();
            $table->string('payment_method', 10); // card | pix
            $table->string('gateway', 30);
            $table->string('gateway_subscription_id')->nullable()->unique();
            $table->string('checkout_url', 500)->nullable();
            $table->timestamp('terms_accepted_at');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->date('cancel_effective_on')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->boolean('fraud_blocked')->default(false);
            $table->timestamp('last_reconciled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->date('period_start'); // inclusivo (data local)
            $table->date('period_end');   // exclusivo
            $table->date('due_on');
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('allowance')->nullable(); // usos do ciclo (null = ilimitado)
            // pending, paid, failed, refunded, charged_back, cancelled
            $table->string('status', 20)->default('pending')->index();
            $table->string('gateway_payment_id')->nullable()->unique();
            $table->unsignedInteger('fee_cents')->nullable();
            $table->unsignedInteger('net_cents')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->string('receipt_url', 500)->nullable();
            $table->timestamps();
            $table->unique(['subscription_id', 'sequence']);
            $table->index(['subscription_id', 'period_start', 'period_end']);
        });

        // Movimentos de franquia: nunca sobrescrevemos contadores.
        Schema::create('subscription_entitlement_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable(); // FK adicionada após appointments
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            // reserve | release | consume | forfeit | restore | adjust
            $table->string('kind', 20);
            $table->integer('reserved_delta')->default(0);
            $table->integer('consumed_delta')->default(0);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['appointment_id', 'kind'], 'ledger_appointment_kind_unique'); // impede consumo/reserva duplicados
            $table->index(['billing_cycle_id', 'service_id'], 'ledger_cycle_service_idx');
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('billing_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 30);
            $table->string('gateway_payment_id');
            $table->string('status', 20); // approved, pending, rejected, refunded, charged_back
            $table->string('method', 10)->nullable();
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('fee_cents')->nullable();
            $table->unsignedInteger('net_cents')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('details')->nullable(); // payload mínimo, sem dados de cartão
            $table->timestamps();
            $table->unique(['gateway', 'gateway_payment_id']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 30);
            $table->string('event_id');
            $table->string('topic', 60);
            $table->string('resource_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('result', 40)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'event_id']);
        });

        // Estado do gateway SIMULADO (faz o papel da "API do provedor" para consultas e conciliação).
        Schema::create('simulated_gateway_records', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20); // subscription | payment
            $table->string('external_id')->unique();
            $table->string('parent_id')->nullable()->index();
            $table->string('external_reference')->nullable()->index();
            $table->string('status', 20);
            $table->string('method', 10)->nullable();
            $table->unsignedInteger('amount_cents')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulated_gateway_records');
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('subscription_entitlement_ledger');
        Schema::dropIfExists('billing_cycles');
        Schema::dropIfExists('subscriptions');
    }
};
