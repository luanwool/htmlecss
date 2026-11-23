<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programa "Indique e ganhe".
 *
 * - referral_codes: código único por cliente (gerado na primeira vez que ele acessa a página de indicação).
 * - referrals: registra quem indicou quem. Cada cliente só pode ter sido indicado uma vez (unique referred_customer_id).
 * - referral_credits: créditos emitidos em centavos, com saldo (amount - used) e validade opcional.
 * - referral_credit_uses: log de aplicação do crédito num atendimento (unique service_visit_id para evitar reuso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 24)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('visits_count')->default(0); // cliques no link
            $table->unsignedInteger('signups_count')->default(0);
            $table->timestamps();
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('referred_customer_id')->unique()->constrained('customers')->cascadeOnDelete();
            $table->string('code', 24);
            // invited: cadastro feito; qualified: primeiro atendimento concluído do indicado; rewarded: créditos emitidos;
            // blocked: anulado por fraude (ex.: no_show repetido, estorno).
            $table->string('status', 20)->default('invited')->index();
            $table->timestamp('first_visit_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->json('snapshot'); // regras no momento da adesão (percentuais, limites)
            $table->timestamps();
        });

        Schema::create('referral_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referral_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 10); // referrer | referred
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('used_cents')->default(0);
            $table->date('expires_on')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'expires_on']);
        });

        Schema::create('referral_credit_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_credit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_visit_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['service_visit_id', 'referral_credit_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('referred_by_customer_id')->nullable()->after('created_by')->constrained('customers')->nullOnDelete();
            $table->string('referral_cookie_code', 24)->nullable()->after('referred_by_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['referred_by_customer_id']);
            $table->dropColumn(['referred_by_customer_id', 'referral_cookie_code']);
        });
        Schema::dropIfExists('referral_credit_uses');
        Schema::dropIfExists('referral_credits');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('referral_codes');
    }
};
