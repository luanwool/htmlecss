<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedidos do barbeiro para alterar a própria agenda (escala semanal, folga/abertura numa data ou bloqueio por período).
 * ADM aprova ou rejeita; ao aprovar, aplica no mesmo tipo de registro já usado (barber_work_schedules, schedule_exceptions, blocked_intervals).
 * O payload é um JSON com só os campos específicos do tipo, para cabermos tudo numa única tabela auditável.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20); // weekly | exception | block
            $table->json('payload');
            $table->string('status', 20)->default('pending')->index();
            $table->string('reason', 500)->nullable();
            $table->string('review_note', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_change_requests');
    }
};
