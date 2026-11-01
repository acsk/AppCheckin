<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crédito do aluno reservado na compra de plano pelo app. A parcela cobra só a diferença; quando é paga, o
 * crédito é consumido (vira credito_aplicado/credito_id). Parcela cancelada não consome nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pagamentos_plano') && ! Schema::hasColumn('pagamentos_plano', 'credito_reservado')) {
            Schema::table('pagamentos_plano', function (Blueprint $t) {
                $t->decimal('credito_reservado', 10, 2)->nullable()->after('credito_aplicado')
                    ->comment('Crédito do aluno a consumir quando a parcela for paga (compra pelo app)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pagamentos_plano', 'credito_reservado')) {
            Schema::table('pagamentos_plano', fn (Blueprint $t) => $t->dropColumn('credito_reservado'));
        }
    }
};
