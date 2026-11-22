<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique(); // UID estável do evento de calendário
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('barber_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');          // fim do serviço
            $table->dateTime('occupied_until');   // fim + intervalo operacional (usado nos conflitos)
            $table->unsignedSmallInteger('duration_minutes_snapshot');
            $table->unsignedSmallInteger('buffer_minutes_snapshot')->default(0);
            $table->string('service_name_snapshot');
            $table->unsignedInteger('price_cents_snapshot')->nullable();
            // confirmed, in_progress, completed, cancelled_by_customer, cancelled_by_shop, no_show, expired
            $table->string('status', 30)->default('confirmed');
            $table->string('mode', 10); // plan | avulso
            $table->string('origin', 20); // online, painel, barbeiro, balcao, telefone, whatsapp
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('billing_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('plan_payment_issue')->default(false);
            $table->string('notes', 500)->nullable();
            $table->unsignedInteger('ics_sequence')->default(0);
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamp('reminder_24h_sent_at')->nullable();
            $table->timestamp('reminder_2h_sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['barber_id', 'starts_at', 'occupied_until', 'status'], 'appointments_barber_interval_idx');
            $table->index(['customer_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });

        Schema::table('subscription_entitlement_ledger', function (Blueprint $table) {
            $table->foreign('appointment_id')->references('id')->on('appointments')->nullOnDelete();
        });

        Schema::create('appointment_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->json('changes')->nullable(); // reagendamentos: horários/barbeiro anteriores
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // Atendimento executado: UM registro por corte (agendado ou balcão), base de produção e financeiro.
        Schema::create('service_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('barber_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('service_name_snapshot');
            $table->dateTime('performed_at');
            $table->string('mode', 10); // plan | avulso
            $table->unsignedInteger('price_cents')->default(0);
            $table->unsignedInteger('discount_cents')->default(0);
            $table->unsignedInteger('final_cents')->default(0);
            $table->string('payment_method', 20)->nullable(); // pix, dinheiro, cartao_credito, cartao_debito, cortesia, plano
            $table->string('payment_status', 10)->default('paid'); // paid | pending
            $table->string('origin', 20);
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('billing_cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['barber_id', 'performed_at']);
            $table->index('performed_at');
        });

        Schema::create('financial_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10); // income | expense | refund
            $table->string('category', 40); // avulso, assinatura, despesa:*, estorno, outros
            $table->integer('amount_cents'); // bruto (positivo)
            $table->unsignedInteger('fee_cents')->default(0);
            $table->dateTime('occurred_at');
            $table->string('payment_method', 20)->nullable();
            $table->string('status', 10)->default('paid'); // paid | pending
            $table->nullableMorphs('source');
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['source_type', 'source_id', 'type'], 'financial_source_unique');
            $table->index('occurred_at');
        });

        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('percent', 5, 2)->default(0); // sobre o valor final do avulso
            $table->unsignedInteger('plan_visit_cents')->default(0); // valor fixo por atendimento de plano
            $table->timestamps();
            $table->unique(['barber_id', 'service_id']);
        });

        Schema::create('commission_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_visit_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->integer('amount_cents');
            $table->string('basis', 120)->nullable();
            $table->timestamp('paid_out_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('commission_ledger');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('financial_entries');
        Schema::dropIfExists('service_visits');
        Schema::dropIfExists('appointment_status_history');
        Schema::table('subscription_entitlement_ledger', fn (Blueprint $t) => $t->dropForeign(['appointment_id']));
        Schema::dropIfExists('appointments');
    }
};
