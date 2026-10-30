<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurações de pagamento: em quantas vezes o aluno pode dividir no cartão.
 * Padrão 6x: no parcelado o aluno paga a antecipação de cada parcela, que encarece muito em 12x.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_payment_credentials')
            && ! Schema::hasColumn('tenant_payment_credentials', 'max_parcelas_cartao')) {
            Schema::table('tenant_payment_credentials', function (Blueprint $t) {
                $t->unsignedTinyInteger('max_parcelas_cartao')->default(6)->after('enable_credit_card')
                    ->comment('Máximo de parcelas no cartão (1 a 12)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenant_payment_credentials', 'max_parcelas_cartao')) {
            Schema::table('tenant_payment_credentials', fn (Blueprint $t) => $t->dropColumn('max_parcelas_cartao'));
        }
    }
};
