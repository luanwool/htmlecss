<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo comercial dos planos do Clube Zero 1.
 * As condições vendidas são congeladas em snapshot na assinatura (Fase 3);
 * por isso o plano guarda uma "version" incrementada a cada alteração comercial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('price_cents');
            $table->char('currency', 3)->default('BRL');
            $table->string('billing_interval', 20)->default('monthly');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(false)->index();
            // limited_global: N usos no ciclo, somando serviços; limited_per_service: franquia por serviço;
            // unlimited: ilimitado sujeito aos limites razoáveis abaixo.
            $table->string('usage_mode', 30)->default('limited_global');
            $table->unsignedSmallInteger('uses_per_cycle')->nullable();
            $table->unsignedSmallInteger('max_future_bookings')->nullable();
            $table->unsignedSmallInteger('max_per_day')->nullable();
            $table->unsignedSmallInteger('max_per_week')->nullable();
            $table->unsignedSmallInteger('min_days_between_uses')->nullable();
            $table->unsignedInteger('min_advance_minutes')->nullable();
            $table->unsignedSmallInteger('max_advance_days')->nullable();
            $table->json('allowed_weekdays')->nullable(); // [1..6] ISO; null = todos
            $table->time('allowed_time_start')->nullable();
            $table->time('allowed_time_end')->nullable();
            $table->boolean('all_barbers')->default(true);
            $table->unsignedSmallInteger('min_commitment_months')->nullable();
            $table->unsignedSmallInteger('grace_days')->nullable(); // carência
            $table->text('guest_policy')->nullable();
            $table->text('no_show_policy')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->json('benefits')->nullable();
            $table->text('terms')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('plan_service_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('uses_per_cycle')->nullable(); // usado em limited_per_service
            $table->unique(['membership_plan_id', 'service_id']);
        });

        Schema::create('barber_membership_plan', function (Blueprint $table) {
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->primary(['membership_plan_id', 'barber_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barber_membership_plan');
        Schema::dropIfExists('plan_service_rules');
        Schema::dropIfExists('membership_plans');
    }
};
