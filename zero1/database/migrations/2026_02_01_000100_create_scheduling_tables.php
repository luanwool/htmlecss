<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda. Datas/horas absolutas em UTC; horários de escala (time) no fuso da barbearia.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Escala semanal: vários períodos por dia (o intervalo entre eles é o almoço/pausa).
        Schema::create('barber_work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // ISO 1..7
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();
            $table->index(['barber_id', 'weekday']);
        });

        // Exceções de data: folga/feriado (fechado) ou abertura extraordinária com horário próprio.
        // barber_id nulo = vale para o salão inteiro.
        Schema::create('schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_closed')->default(true);
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['date', 'barber_id']);
        });

        // Bloqueios por intervalo absoluto: férias, ausências, bloqueio manual (individual ou salão).
        Schema::create('blocked_intervals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->nullable()->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['barber_id', 'starts_at', 'ends_at']);
        });

        // Linha travada com SELECT ... FOR UPDATE para serializar reservas do mesmo barbeiro no mesmo dia.
        Schema::create('barber_day_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unique(['barber_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barber_day_locks');
        Schema::dropIfExists('blocked_intervals');
        Schema::dropIfExists('schedule_exceptions');
        Schema::dropIfExists('barber_work_schedules');
    }
};
