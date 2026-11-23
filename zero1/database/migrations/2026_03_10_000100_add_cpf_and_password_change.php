<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - customers.cpf: CPF guardado apenas em dígitos (11 chars). Único entre clientes ativos.
 *   Um CPF ficou cadastrado antes do soft delete? A unicidade continua valendo — o ADM precisa
 *   restaurar/mesclar o cliente em vez de criar outro.
 * - users.must_change_password + password_set_at: ADM gera senha temporária; usuário é obrigado
 *   a trocar na próxima entrada antes de acessar qualquer página do sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->char('cpf', 11)->nullable()->after('phone');
            $table->unique('cpf');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->timestamp('password_set_at')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['cpf']);
            $table->dropColumn('cpf');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['must_change_password', 'password_set_at']);
        });
    }
};
