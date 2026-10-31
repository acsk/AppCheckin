<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sobretaxa da assinatura (fidelização): percentual sobre a mensalidade + valor fixo, somados à taxa
 * do cartão. O aluno vê só o total de taxas; a composição fica nas Configurações de pagamento.
 * Zerada por padrão: não muda o preço de ninguém até a academia configurar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_payment_credentials')) {
            return;
        }
        if (! Schema::hasColumn('tenant_payment_credentials', 'sobretaxa_recorrencia_percentual')) {
            Schema::table('tenant_payment_credentials', function (Blueprint $t) {
                $t->decimal('sobretaxa_recorrencia_percentual', 5, 2)->default(0)
                    ->comment('Sobretaxa (%) sobre a mensalidade da assinatura');
                $t->decimal('sobretaxa_recorrencia_fixa', 10, 2)->default(0)
                    ->comment('Sobretaxa fixa (R$) por mensalidade da assinatura');
            });
        }
    }

    public function down(): void
    {
        foreach (['sobretaxa_recorrencia_percentual', 'sobretaxa_recorrencia_fixa'] as $coluna) {
            if (Schema::hasColumn('tenant_payment_credentials', $coluna)) {
                Schema::table('tenant_payment_credentials', fn (Blueprint $t) => $t->dropColumn($coluna));
            }
        }
    }
};
