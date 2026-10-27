<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central de Comunicação: notificações, comunicados, mensagens e enquetes para os alunos.
 *
 * - comunicacoes.status guarda rascunho | publicada | encerrada | cancelada; "agendada"/"ativa"/"encerrada"
 *   da API saem da vigência (inicio_em/termino_em) no momento da consulta, sem depender de agendador.
 * - comunicacao_destinatarios: público congelado na publicação (uma linha por aluno). confirmada_em é a
 *   leitura confirmada; concluida_em é a enquete enviada por inteiro (separada das respostas).
 * - enquete_respostas: uma linha por pergunta (e por alternativa na múltipla escolha). Em enquete anônima
 *   aluno_id fica nulo e não há horário: a participação fica só no destinatário.
 * - Comunicação obrigatória vigente e não resolvida bloqueia o check-in (PendenciaComunicacaoService).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comunicacoes')) {
            Schema::create('comunicacoes', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('tenant_id');
                $t->string('tipo', 20)->comment('notificacao | comunicado | mensagem | enquete');
                $t->string('titulo', 160);
                $t->string('descricao', 500)->nullable();
                $t->text('conteudo')->nullable();
                $t->string('icone', 40)->nullable();
                $t->string('classificacao', 12)->default('info')->comment('info | alerta | sucesso | urgente');
                $t->unsignedTinyInteger('prioridade')->default(1)->comment('0 baixa, 1 normal, 2 alta, 3 urgente');
                $t->boolean('obrigatoria')->default(false);
                $t->boolean('exibir_modal')->default(false);
                $t->boolean('manter_historico')->default(true);
                $t->dateTime('inicio_em')->nullable();
                $t->dateTime('termino_em')->nullable();
                $t->string('status', 12)->default('rascunho')->comment('rascunho | publicada | encerrada | cancelada');
                $t->json('publico')->nullable()->comment('{tipo: todos|ativos|segmento|alunos, modalidades, planos, turmas, dias_checkin, inadimplentes, alunos, matriculas}');
                $t->json('configuracoes')->nullable()->comment('links, anexos, imagem_url, enquete {permitir_alterar, anonima, exibir_resultados, exibir_participantes}, canais');
                $t->dateTime('publicada_em')->nullable();
                $t->unsignedInteger('publicada_por')->nullable();
                $t->dateTime('encerrada_em')->nullable();
                $t->unsignedInteger('criado_por')->nullable();
                $t->unsignedInteger('atualizado_por')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['tenant_id', 'status', 'tipo']);
                $t->index(['tenant_id', 'inicio_em', 'termino_em']);
            });
        }

        if (! Schema::hasTable('comunicacao_destinatarios')) {
            Schema::create('comunicacao_destinatarios', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('tenant_id');
                $t->unsignedBigInteger('comunicacao_id');
                $t->unsignedInteger('aluno_id');
                $t->dateTime('disponibilizada_em');
                $t->dateTime('visualizada_em')->nullable();
                $t->dateTime('confirmada_em')->nullable();
                $t->unsignedInteger('confirmada_por_usuario_id')->nullable()->comment('quem confirmou (aluno; futuramente o responsável)');
                $t->dateTime('concluida_em')->nullable()->comment('enquete enviada por inteiro');
                $t->timestamps();
                $t->unique(['tenant_id', 'comunicacao_id', 'aluno_id'], 'uniq_com_dest');
                $t->index(['tenant_id', 'aluno_id'], 'idx_com_dest_aluno');
                $t->foreign('comunicacao_id')->references('id')->on('comunicacoes')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('enquete_perguntas')) {
            Schema::create('enquete_perguntas', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('tenant_id');
                $t->unsignedBigInteger('comunicacao_id');
                $t->string('pergunta', 300);
                $t->string('tipo_resposta', 12)->comment('unica | multipla | sim_nao | avaliacao | aberta');
                $t->boolean('obrigatoria')->default(true);
                $t->unsignedSmallInteger('ordem')->default(0);
                $t->timestamps();
                $t->index(['tenant_id', 'comunicacao_id']);
                $t->foreign('comunicacao_id')->references('id')->on('comunicacoes')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('enquete_alternativas')) {
            Schema::create('enquete_alternativas', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('tenant_id');
                $t->unsignedBigInteger('pergunta_id');
                $t->string('texto', 200);
                $t->unsignedSmallInteger('ordem')->default(0);
                $t->index(['tenant_id', 'pergunta_id']);
                $t->foreign('pergunta_id')->references('id')->on('enquete_perguntas')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('enquete_respostas')) {
            Schema::create('enquete_respostas', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('tenant_id');
                $t->unsignedBigInteger('comunicacao_id');
                $t->unsignedBigInteger('pergunta_id');
                $t->unsignedInteger('aluno_id')->nullable()->comment('nulo em enquete anônima');
                $t->unsignedBigInteger('alternativa_id')->nullable();
                $t->text('valor_texto')->nullable();
                $t->unsignedTinyInteger('valor_numero')->nullable()->comment('avaliação 1–5');
                $t->dateTime('respondida_em')->nullable()->comment('nulo em enquete anônima');
                $t->index(['tenant_id', 'comunicacao_id', 'pergunta_id'], 'idx_enq_resp_perg');
                $t->index(['tenant_id', 'comunicacao_id', 'aluno_id'], 'idx_enq_resp_aluno');
                $t->foreign('pergunta_id')->references('id')->on('enquete_perguntas')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('comunicacao_auditoria')) {
            Schema::create('comunicacao_auditoria', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('tenant_id');
                $t->unsignedBigInteger('comunicacao_id');
                $t->string('acao', 30)->comment('criada | editada | publicada | encerrada | cancelada | destinatarios_incluidos | leitura_confirmada | enquete_respondida');
                $t->unsignedInteger('usuario_id')->nullable();
                $t->unsignedInteger('aluno_id')->nullable();
                $t->json('dados')->nullable();
                $t->dateTime('created_at');
                $t->index(['tenant_id', 'comunicacao_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comunicacao_auditoria');
        Schema::dropIfExists('enquete_respostas');
        Schema::dropIfExists('enquete_alternativas');
        Schema::dropIfExists('enquete_perguntas');
        Schema::dropIfExists('comunicacao_destinatarios');
        Schema::dropIfExists('comunicacoes');
    }
};
