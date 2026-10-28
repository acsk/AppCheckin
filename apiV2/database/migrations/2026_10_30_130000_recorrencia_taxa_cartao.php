<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assinatura no cartão: a taxa do cartão (tarifa Asaas + sobretaxa de antecipação à vista das
 * Configurações de pagamento) é repassada ao aluno, como no checkout à vista. O Asaas cobra
 * valor_mensal_vigente + taxa_cartao_mensal; os valores do contrato (regularização) não incluem a taxa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contratos_recorrencia') && ! Schema::hasColumn('contratos_recorrencia', 'taxa_cartao_mensal')) {
            Schema::table('contratos_recorrencia', function (Blueprint $t) {
                $t->decimal('taxa_cartao_mensal', 10, 2)->default(0)->after('valor_mensal_vigente')
                    ->comment('taxa do cartão repassada ao aluno, somada à mensalidade no gateway');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contratos_recorrencia', 'taxa_cartao_mensal')) {
            Schema::table('contratos_recorrencia', fn (Blueprint $t) => $t->dropColumn('taxa_cartao_mensal'));
        }
    }
};
