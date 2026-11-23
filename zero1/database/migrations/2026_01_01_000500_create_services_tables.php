<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('price_cents')->nullable(); // null = sob consulta
            $table->boolean('price_is_from')->default(false); // "a partir de"
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('buffer_minutes')->default(0); // preparação/limpeza
            $table->unsignedInteger('min_advance_minutes')->nullable(); // null = política geral
            $table->boolean('is_public')->default(true);
            $table->boolean('is_bookable_standalone')->default(true); // vendável/agendável avulso
            $table->boolean('is_plan_eligible')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('barber_service', function (Blueprint $table) {
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->primary(['barber_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barber_service');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
    }
};
