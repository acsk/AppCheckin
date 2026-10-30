<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repasse da antecipação: folga sobre o plano (para a academia nunca receber menos) e a opção de usar
 * a taxa mensal que o Asaas informa para a conta em vez da digitada no painel.
 * Desligada por padrão para não mudar o preço de quem já configurou a taxa à mão.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_payment_credentials')) {
            return;
        }
        if (! Schema::hasColumn('tenant_payment_credentials', 'sobretaxa_folga_percentual')) {
            Schema::table('tenant_payment_credentials', function (Blueprint $t) {
                $t->decimal('sobretaxa_folga_percentual', 5, 2)->default(1.00)
                    ->comment('Folga (%) sobre o plano no repasse da antecipação');
            });
        }
        if (! Schema::hasColumn('tenant_payment_credentials', 'usar_taxa_antecipacao_asaas')) {
            Schema::table('tenant_payment_credentials', function (Blueprint $t) {
                $t->boolean('usar_taxa_antecipacao_asaas')->default(false)
                    ->comment('Usa a taxa de antecipação informada pelo Asaas (campos do painel viram reserva)');
            });
        }
    }

    public function down(): void
    {
        foreach (['sobretaxa_folga_percentual', 'usar_taxa_antecipacao_asaas'] as $coluna) {
            if (Schema::hasColumn('tenant_payment_credentials', $coluna)) {
                Schema::table('tenant_payment_credentials', fn (Blueprint $t) => $t->dropColumn($coluna));
            }
        }
    }
};
