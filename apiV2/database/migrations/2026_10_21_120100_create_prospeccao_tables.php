<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Prospecção → Recuperação de Alunos (mini-CRM sobre matrículas canceladas).
 *
 * Nenhum dado pessoal é copiado: nome, telefone e foto vêm de `alunos`; modalidade e plano
 * vêm da matrícula de referência. Regras em App\Services\Admin\Prospeccao e App\Support\ProspeccaoStatus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prospeccao_oportunidades')) {
            Schema::create('prospeccao_oportunidades', function (Blueprint $t) {
                $t->increments('id');
                $t->integer('tenant_id');
                $t->integer('aluno_id');
                // Matrícula cancelada mais recente do aluno no momento em que a oportunidade foi aberta/atualizada
                $t->integer('matricula_referencia_id');
                $t->date('data_cancelamento_referencia');
                $t->boolean('data_cancelamento_estimada')->default(false)->comment('1 = sem data_cancelamento; usada a data da última atualização da matrícula');
                $t->string('status', 30)->default('nao_contatado');
                $t->integer('responsavel_id')->nullable();
                $t->string('motivo_categoria', 30)->nullable()->comment('Categoria do motivo informada pela equipe (ProspeccaoStatus::MOTIVOS)');
                $t->string('interesse', 255)->nullable();
                $t->text('condicoes_apresentadas')->nullable();
                $t->text('observacoes')->nullable();
                $t->dateTime('ultimo_contato_em')->nullable();
                $t->integer('ultimo_contato_por')->nullable();
                $t->dateTime('proximo_contato_em')->nullable()->comment('Cache do agendamento pendente mais próximo');
                $t->unsignedTinyInteger('score')->nullable();
                $t->unsignedTinyInteger('score_confianca')->nullable()->comment('% dos pesos com dado disponível');
                $t->json('score_detalhes')->nullable();
                $t->dateTime('score_calculado_em')->nullable();
                $t->integer('matricula_conversao_id')->nullable();
                $t->dateTime('convertida_em')->nullable();
                $t->dateTime('encerrada_em')->nullable()->comment('Preenchida em recuperado / sem_interesse / nao_contatar');
                $t->timestamps();
                // = aluno_id enquanto aberta, NULL quando encerrada (mantido pelo ProspeccaoRepository).
                // Garante uma oportunidade aberta por aluno no UNIQUE (NULL não conflita). Não é coluna gerada
                // porque o MySQL recusa FK com CASCADE em coluna-base de coluna gerada.
                $t->integer('aluno_aberto')->nullable();

                $t->unique(['tenant_id', 'matricula_referencia_id'], 'uk_prosp_op_matricula_ref');
                $t->unique(['tenant_id', 'aluno_aberto'], 'uk_prosp_op_aluno_aberto');
                $t->index(['tenant_id', 'status'], 'idx_prosp_op_status');
                $t->index(['tenant_id', 'aluno_id'], 'idx_prosp_op_aluno');
                $t->index(['tenant_id', 'score'], 'idx_prosp_op_score');
                $t->index(['tenant_id', 'proximo_contato_em'], 'idx_prosp_op_proximo');
                $t->index(['tenant_id', 'data_cancelamento_referencia'], 'idx_prosp_op_cancelamento');
                $t->index(['tenant_id', 'convertida_em'], 'idx_prosp_op_convertida');

                $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $t->foreign('aluno_id')->references('id')->on('alunos')->cascadeOnDelete();
                $t->foreign('matricula_referencia_id')->references('id')->on('matriculas')->cascadeOnDelete();
                $t->foreign('matricula_conversao_id')->references('id')->on('matriculas')->nullOnDelete();
                $t->foreign('responsavel_id')->references('id')->on('usuarios')->nullOnDelete();
                $t->foreign('ultimo_contato_por')->references('id')->on('usuarios')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('prospeccao_historico_status')) {
            Schema::create('prospeccao_historico_status', function (Blueprint $t) {
                $t->increments('id');
                $t->integer('tenant_id');
                $t->unsignedInteger('oportunidade_id');
                $t->string('status_anterior', 30)->nullable();
                $t->string('status_novo', 30);
                $t->integer('usuario_id')->nullable()->comment('NULL = sistema (sincronização)');
                $t->string('observacao', 500)->nullable();
                $t->timestamp('created_at')->useCurrent();

                $t->index(['oportunidade_id', 'created_at'], 'idx_prosp_hist_op');
                $t->index(['tenant_id', 'status_novo', 'created_at'], 'idx_prosp_hist_status');
                $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $t->foreign('oportunidade_id')->references('id')->on('prospeccao_oportunidades')->cascadeOnDelete();
                $t->foreign('usuario_id')->references('id')->on('usuarios')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('prospeccao_modelos_mensagem')) {
            Schema::create('prospeccao_modelos_mensagem', function (Blueprint $t) {
                $t->increments('id');
                $t->integer('tenant_id');
                $t->string('nome', 100);
                $t->string('etapa', 30)->default('outro');
                $t->text('conteudo');
                $t->boolean('ativo')->default(true);
                $t->integer('criado_por')->nullable();
                $t->integer('atualizado_por')->nullable();
                $t->timestamps();

                $t->unique(['tenant_id', 'nome'], 'uk_prosp_modelo_nome');
                $t->index(['tenant_id', 'ativo'], 'idx_prosp_modelo_ativo');
                $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $t->foreign('criado_por')->references('id')->on('usuarios')->nullOnDelete();
                $t->foreign('atualizado_por')->references('id')->on('usuarios')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('prospeccao_interacoes')) {
            Schema::create('prospeccao_interacoes', function (Blueprint $t) {
                $t->increments('id');
                $t->integer('tenant_id');
                $t->unsignedInteger('oportunidade_id');
                $t->integer('usuario_id')->nullable();
                $t->string('canal', 20);
                $t->string('resultado', 30);
                $t->unsignedInteger('modelo_mensagem_id')->nullable();
                $t->text('mensagem')->nullable()->comment('Texto usado na abordagem, quando houver');
                $t->text('observacao')->nullable();
                $t->dateTime('ocorrido_em');
                $t->timestamp('created_at')->useCurrent();

                $t->index(['oportunidade_id', 'ocorrido_em'], 'idx_prosp_int_op');
                $t->index(['tenant_id', 'ocorrido_em'], 'idx_prosp_int_data');
                $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $t->foreign('oportunidade_id')->references('id')->on('prospeccao_oportunidades')->cascadeOnDelete();
                $t->foreign('usuario_id')->references('id')->on('usuarios')->nullOnDelete();
                $t->foreign('modelo_mensagem_id')->references('id')->on('prospeccao_modelos_mensagem')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('prospeccao_agendamentos')) {
            Schema::create('prospeccao_agendamentos', function (Blueprint $t) {
                $t->increments('id');
                $t->integer('tenant_id');
                $t->unsignedInteger('oportunidade_id');
                $t->integer('responsavel_id')->nullable();
                $t->dateTime('agendado_para');
                $t->string('tipo_contato', 20);
                $t->string('observacao', 500)->nullable();
                $t->string('situacao', 20)->default('pendente');
                $t->unsignedInteger('reagendado_de_id')->nullable();
                $t->integer('criado_por')->nullable();
                $t->integer('finalizado_por')->nullable();
                $t->dateTime('finalizado_em')->nullable();
                $t->timestamps();

                $t->index(['tenant_id', 'situacao', 'agendado_para'], 'idx_prosp_ag_agenda');
                $t->index(['oportunidade_id', 'situacao'], 'idx_prosp_ag_op');
                $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $t->foreign('oportunidade_id')->references('id')->on('prospeccao_oportunidades')->cascadeOnDelete();
                $t->foreign('reagendado_de_id')->references('id')->on('prospeccao_agendamentos')->nullOnDelete();
                $t->foreign('responsavel_id')->references('id')->on('usuarios')->nullOnDelete();
                $t->foreign('criado_por')->references('id')->on('usuarios')->nullOnDelete();
                $t->foreign('finalizado_por')->references('id')->on('usuarios')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('prospeccao_restricoes_contato')) {
            // Oposição ao contato (LGPD) é do aluno no tenant, não de uma oportunidade: vale para ciclos futuros.
            Schema::create('prospeccao_restricoes_contato', function (Blueprint $t) {
                $t->increments('id');
                $t->integer('tenant_id');
                $t->integer('aluno_id');
                $t->string('motivo', 500)->nullable();
                $t->dateTime('solicitado_em');
                $t->integer('registrado_por')->nullable();
                $t->dateTime('removido_em')->nullable();
                $t->integer('removido_por')->nullable();
                $t->string('motivo_remocao', 500)->nullable();
                $t->timestamps();
                $t->integer('aluno_ativo')->nullable()->comment('= aluno_id enquanto vigente, NULL quando removida (UNIQUE: uma vigente por aluno)');

                $t->unique(['tenant_id', 'aluno_ativo'], 'uk_prosp_restricao_ativa');
                $t->index(['tenant_id', 'aluno_id'], 'idx_prosp_restricao_aluno');
                $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $t->foreign('aluno_id')->references('id')->on('alunos')->cascadeOnDelete();
                $t->foreign('registrado_por')->references('id')->on('usuarios')->nullOnDelete();
                $t->foreign('removido_por')->references('id')->on('usuarios')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prospeccao_restricoes_contato');
        Schema::dropIfExists('prospeccao_agendamentos');
        Schema::dropIfExists('prospeccao_interacoes');
        Schema::dropIfExists('prospeccao_modelos_mensagem');
        Schema::dropIfExists('prospeccao_historico_status');
        Schema::dropIfExists('prospeccao_oportunidades');
    }
};
