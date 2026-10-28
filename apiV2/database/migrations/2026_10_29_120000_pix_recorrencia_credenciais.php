<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurações de pagamento (Asaas): aceitar PIX na assinatura mensal dos planos com fidelização.
 * Desligado por padrão: no PIX o aluno precisa pagar cada mensalidade (não há débito automático),
 * então a assinatura fica só no cartão até a academia ligar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenant_payment_credentials', 'enable_pix_recorrencia')) {
            Schema::table('tenant_payment_credentials', function (Blueprint $t) {
                $t->boolean('enable_pix_recorrencia')->default(false)->after('enable_credit_card')
                    ->comment('PIX na assinatura mensal (fidelização): o aluno paga cada mês');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenant_payment_credentials', 'enable_pix_recorrencia')) {
            Schema::table('tenant_payment_credentials', fn (Blueprint $t) => $t->dropColumn('enable_pix_recorrencia'));
        }
    }
};
