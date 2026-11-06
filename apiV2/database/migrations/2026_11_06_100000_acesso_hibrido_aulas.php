<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turmas_fixas', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('tenant_id');
            $t->string('nome');
            $t->unsignedInteger('professor_id');
            $t->unsignedInteger('modalidade_id');
            $t->json('dias_semana');
            $t->time('horario_inicio');
            $t->time('horario_fim');
            $t->unsignedInteger('capacidade');
            $t->boolean('checkin_livre')->default(false);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->index(['tenant_id', 'ativo']);
        });
        Schema::create('turma_fixa_planos', function (Blueprint $t) {
            $t->unsignedBigInteger('turma_fixa_id');
            $t->unsignedInteger('plano_id');
            $t->primary(['turma_fixa_id', 'plano_id']);
        });
        Schema::table('matriculas', function (Blueprint $t) {
            $t->string('tipo_acesso', 20)->default('horario_livre');
            $t->unsignedBigInteger('turma_fixa_id')->nullable();
            $t->timestamp('reajuste_autorizado_em')->nullable();
            $t->index(['tenant_id', 'turma_fixa_id', 'status_id'], 'matricula_turma_fixa_status');
        });
        Schema::table('turmas', function (Blueprint $t) {
            $t->unsignedBigInteger('turma_fixa_id')->nullable();
            $t->boolean('checkin_livre')->default(true);
            $t->unique(['turma_fixa_id', 'dia_id'], 'turma_fixa_ocorrencia');
        });
        // Preserva pagamentos recebidos quando a ativação não pode reservar uma vaga.
        DB::table('status_matricula')->insert([
            'codigo' => 'aguardando_vaga', 'nome' => 'Pago - aguardando vaga',
            'cor' => '#FFA500', 'permite_checkin' => 0, 'ativo' => 1,
        ]);
        $categoria = DB::table('tipos_parametro')->where('codigo', 'matriculas')->value('id');
        if (! $categoria) {
            $categoria = DB::table('tipos_parametro')->insertGetId([
                'codigo' => 'matriculas', 'nome' => 'Matrículas', 'ordem' => 6, 'ativo' => 1,
            ]);
        }
        DB::table('parametros')->insert([
            'tipo_parametro_id' => $categoria, 'codigo' => 'tipo_acesso_padrao',
            'nome' => 'Tipo padrão para novas matrículas',
            'descricao' => 'Pré-seleção no painel. Não altera matrículas existentes nem o cadastro público.',
            'tipo_valor' => 'select', 'valor_padrao' => 'horario_livre',
            'opcoes_select' => json_encode([
                ['value' => 'horario_livre', 'label' => 'Horário livre'],
                ['value' => 'turma_fixa', 'label' => 'Turma fixa'],
            ]),
            'ordem' => 1, 'ativo' => 1, 'visivel_tenant' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        throw new RuntimeException('Migração de acesso híbrido irreversível: preserve matrículas e aulas. Para restauração, utilize backup validado.');
    }
};
