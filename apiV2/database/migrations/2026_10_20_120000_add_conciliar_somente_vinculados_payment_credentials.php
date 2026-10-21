<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conciliação bancária: com o flag ligado, os recebimentos do provedor sem matrícula ligada
 * (sem vínculo) ficam fora da tela e dos totais. Liga para o Mercado Pago, onde a conta
 * costuma receber também vendas que não são do Check-in.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_payment_credentials')
            || Schema::hasColumn('tenant_payment_credentials', 'conciliar_somente_vinculados')) {
            return;
        }

        Schema::table('tenant_payment_credentials', function (Blueprint $table) {
            $table->boolean('conciliar_somente_vinculados')->default(false)
                ->comment('Conciliação mostra só recebimentos ligados a matrícula');
        });

        DB::table('tenant_payment_credentials')
            ->where('provider', 'mercadopago')
            ->update(['conciliar_somente_vinculados' => 1]);
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_payment_credentials')
            && Schema::hasColumn('tenant_payment_credentials', 'conciliar_somente_vinculados')) {
            Schema::table('tenant_payment_credentials', function (Blueprint $table) {
                $table->dropColumn('conciliar_somente_vinculados');
            });
        }
    }
};
