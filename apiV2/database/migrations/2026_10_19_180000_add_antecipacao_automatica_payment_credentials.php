<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Antecipação automática do cartão no Asaas (liga/desliga na conta da academia).
 * A sobretaxa de antecipação só vale com ela ligada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_payment_credentials')
            || Schema::hasColumn('tenant_payment_credentials', 'enable_antecipacao_automatica')) {
            return;
        }

        Schema::table('tenant_payment_credentials', function (Blueprint $table) {
            $col = $table->boolean('enable_antecipacao_automatica')->default(false)
                ->comment('Antecipação automática do cartão ligada na conta Asaas');
            if (Schema::hasColumn('tenant_payment_credentials', 'enable_credit_card')) {
                $col->after('enable_credit_card');
            }
        });

        if (Schema::hasColumn('tenant_payment_credentials', 'enable_sobretaxa_antecipacao')) {
            DB::table('tenant_payment_credentials')
                ->where('provider', 'asaas')
                ->where('enable_sobretaxa_antecipacao', 1)
                ->update(['enable_antecipacao_automatica' => 1]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_payment_credentials')
            && Schema::hasColumn('tenant_payment_credentials', 'enable_antecipacao_automatica')) {
            Schema::table('tenant_payment_credentials', function (Blueprint $table) {
                $table->dropColumn('enable_antecipacao_automatica');
            });
        }
    }
};
