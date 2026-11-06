<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turmas_fixas', function (Blueprint $t) {
            $t->date('vigencia_inicio')->default('1900-01-01');
            $t->date('vigencia_fim')->nullable();
            $t->unsignedBigInteger('substituida_por_id')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::table('matricula_grade', function (Blueprint $t) {
            $t->dropUnique('matricula_grade_unica');
            $t->date('vigencia_inicio')->default('1900-01-01');
            $t->date('vigencia_fim')->nullable();
            $t->unique(['matricula_id', 'turma_fixa_id', 'dia_semana', 'vigencia_inicio'], 'matricula_grade_periodo_unico');
        });
        Schema::table('turmas', function (Blueprint $t) {
            $t->unsignedBigInteger('suspensao_turma_id')->nullable();
        });
        Schema::create('turma_fixa_pausas', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('tenant_id');
            $t->unsignedBigInteger('turma_fixa_id');
            $t->date('inicio');
            $t->date('fim');
            $t->string('motivo', 500)->nullable();
            $t->timestamp('cancelada_em')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'turma_fixa_id', 'inicio', 'fim'], 'turma_pausa_periodo');
        });
        Schema::create('turma_fixa_eventos', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('tenant_id');
            $t->unsignedBigInteger('turma_fixa_id');
            $t->unsignedInteger('usuario_id')->nullable();
            $t->string('acao', 40);
            $t->json('dados');
            $t->timestamps();
            $t->index(['tenant_id', 'turma_fixa_id']);
        });
        DB::table('turmas_fixas')->where('ativo', 0)->update(['vigencia_fim' => '1900-01-01']);
    }

    public function down(): void
    {
        throw new RuntimeException('Migração irreversível: períodos de grade e histórico devem ser preservados. Restaure por backup validado.');
    }
};
