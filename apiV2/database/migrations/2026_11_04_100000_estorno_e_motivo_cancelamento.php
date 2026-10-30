<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estorno de parcela (status 5 + valor/data/motivo, total ou parcial) e motivo de cancelamento
 * estruturado na matrícula (código de App\Support\MotivoCancelamento; o texto livre continua).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('status_pagamento') && ! DB::table('status_pagamento')->where('id', 5)->exists()) {
            DB::table('status_pagamento')->insert([
                'id' => 5, 'nome' => 'Estornado', 'descricao' => 'Pagamento devolvido ao aluno',
                'dias_tolerancia' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('pagamentos_plano') && ! Schema::hasColumn('pagamentos_plano', 'valor_estornado')) {
            Schema::table('pagamentos_plano', function (Blueprint $t) {
                $t->decimal('valor_estornado', 10, 2)->nullable()->after('data_pagamento')
                    ->comment('Total devolvido; igual ao valor = estorno total (status 5)');
                $t->date('data_estorno')->nullable()->after('valor_estornado');
                $t->string('motivo_estorno', 255)->nullable()->after('data_estorno');
                $t->unsignedInteger('estornado_por')->nullable()->after('motivo_estorno');
                $t->index(['tenant_id', 'data_estorno'], 'idx_pp_tenant_estorno');
            });
        }

        if (Schema::hasTable('matriculas') && ! Schema::hasColumn('matriculas', 'motivo_cancelamento_codigo')) {
            Schema::table('matriculas', function (Blueprint $t) {
                $t->string('motivo_cancelamento_codigo', 30)->nullable()->after('motivo_cancelamento')
                    ->comment('App\Support\MotivoCancelamento');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('matriculas', 'motivo_cancelamento_codigo')) {
            Schema::table('matriculas', fn (Blueprint $t) => $t->dropColumn('motivo_cancelamento_codigo'));
        }
        if (Schema::hasColumn('pagamentos_plano', 'valor_estornado')) {
            Schema::table('pagamentos_plano', function (Blueprint $t) {
                $t->dropIndex('idx_pp_tenant_estorno');
                $t->dropColumn(['valor_estornado', 'data_estorno', 'motivo_estorno', 'estornado_por']);
            });
        }
    }
};
