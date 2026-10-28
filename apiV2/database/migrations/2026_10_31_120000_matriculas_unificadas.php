<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uma matrícula por aluno e modalidade: o comando matriculas:unificar junta os ciclos que viraram
 * linhas separadas na matrícula atual. Aqui fica o id antigo → atual (webhooks que ainda chegarem
 * com MAT-{id antigo} acham a matrícula) e uma cópia da linha removida, para consulta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('matriculas_unificadas')) {
            return;
        }
        Schema::create('matriculas_unificadas', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('tenant_id');
            $t->unsignedInteger('matricula_antiga_id')->unique();
            $t->unsignedInteger('matricula_id')->index()->comment('Matrícula que ficou');
            $t->json('dados')->comment('Linha de matriculas removida');
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matriculas_unificadas');
    }
};
