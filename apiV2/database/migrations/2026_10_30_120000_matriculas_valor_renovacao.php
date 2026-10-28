<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reajuste de preço do ciclo: o valor novo vale a partir da próxima renovação da matrícula à vista.
 * `valor` continua sendo o do ciclo já pago (crédito de troca de plano e quitação dependem dele);
 * `valor_renovacao` guarda o preço do próximo ciclo até ele começar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('matriculas', 'valor_renovacao')) {
            Schema::table('matriculas', function (Blueprint $t) {
                $t->decimal('valor_renovacao', 10, 2)->nullable()->after('valor')
                    ->comment('Preço do próximo ciclo após reajuste do plano (null = mesmo valor)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('matriculas', 'valor_renovacao')) {
            Schema::table('matriculas', fn (Blueprint $t) => $t->dropColumn('valor_renovacao'));
        }
    }
};
