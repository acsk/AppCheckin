<?php

namespace Tests\Feature;

use App\Services\Admin\Prospeccao\ProspeccaoSincronizacaoService;
use App\Support\Papel;
use App\Support\ProspeccaoStatus;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ProspeccaoSchema;
use Tests\TestCase;

/**
 * Regras críticas do módulo Prospecção → Recuperação de Alunos (SQLite).
 */
class ProspeccaoRecuperacaoTest extends TestCase
{
    use ProspeccaoSchema;

    private int $t1;

    private int $admin;

    private int $plano;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaProspeccao();
        $this->t1 = $this->tenant('Academia Um');
        $this->admin = $this->usuario($this->t1, Papel::ADMIN, 'Admin');
        $this->plano = $this->plano($this->t1);
    }

    private function sincronizar(int $tenantId): array
    {
        return app(ProspeccaoSincronizacaoService::class)->sincronizar($tenantId);
    }

    /** @return list<object> */
    private function oportunidades(int $tenantId): array
    {
        return DB::table('prospeccao_oportunidades')->where('tenant_id', $tenantId)->orderBy('id')->get()->all();
    }

    // ------------------------------------------------------------ elegibilidade e duplicidade

    public function test_cria_uma_oportunidade_por_aluno_usando_a_cancelada_mais_recente(): void
    {
        $aluno = $this->aluno($this->t1);
        $this->matricula($this->t1, $aluno, $this->plano, 'cancelada', $this->dia(-90));
        $recente = $this->matricula($this->t1, $aluno, $this->plano, 'cancelada', $this->dia(-10));

        $r = $this->sincronizar($this->t1);
        $ops = $this->oportunidades($this->t1);

        $this->assertSame(1, $r['criadas']);
        $this->assertCount(1, $ops);
        $this->assertSame($recente, (int) $ops[0]->matricula_referencia_id);
        $this->assertSame($this->dia(-10), $ops[0]->data_cancelamento_referencia);
        $this->assertSame(ProspeccaoStatus::NAO_CONTATADO, $ops[0]->status);
        $this->assertNotNull($ops[0]->score_calculado_em);
    }

    public function test_sincronizacao_e_idempotente(): void
    {
        $this->matricula($this->t1, $this->aluno($this->t1), $this->plano, 'cancelada', $this->dia(-5));

        $this->sincronizar($this->t1);
        $segunda = $this->sincronizar($this->t1);

        $this->assertSame(0, $segunda['criadas']);
        $this->assertCount(1, $this->oportunidades($this->t1));
        $this->assertSame(1, DB::table('prospeccao_historico_status')->count());
    }

    public function test_ignora_diaria_aluno_ativo_matricula_pendente_e_cancelamento_antigo(): void
    {
        $diaria = $this->plano($this->t1, 1, 'Diária');
        $this->matricula($this->t1, $this->aluno($this->t1, 'DIARISTA'), $diaria, 'cancelada', $this->dia(-3), ['tipo_cobranca' => 'avulso']);

        $trocouPlano = $this->aluno($this->t1, 'TROCOU PLANO');
        $this->matricula($this->t1, $trocouPlano, $this->plano, 'cancelada', $this->dia(-3));
        $this->matricula($this->t1, $trocouPlano, $this->plano, 'ativa');

        $pendente = $this->aluno($this->t1, 'PENDENTE');
        $this->matricula($this->t1, $pendente, $this->plano, 'cancelada', $this->dia(-3));
        $this->matricula($this->t1, $pendente, $this->plano, 'pendente');

        $this->matricula($this->t1, $this->aluno($this->t1, 'ANTIGO'), $this->plano, 'cancelada', $this->dia(-800));
        $this->matricula($this->t1, $this->aluno($this->t1, 'FINALIZADA'), $this->plano, 'finalizada', $this->dia(-3));

        $this->assertSame(0, $this->sincronizar($this->t1)['criadas']);
    }

    public function test_sem_data_de_cancelamento_usa_data_estimada(): void
    {
        $m = $this->matricula($this->t1, $this->aluno($this->t1), $this->plano, 'cancelada', null);
        DB::table('matriculas')->where('id', $m)->update(['updated_at' => $this->dia(-7).' 10:00:00']);

        $this->sincronizar($this->t1);
        $op = $this->oportunidades($this->t1)[0];

        $this->assertSame($this->dia(-7), $op->data_cancelamento_referencia);
        $this->assertSame(1, (int) $op->data_cancelamento_estimada);
    }

    public function test_banco_impede_duas_oportunidades_abertas_do_mesmo_aluno(): void
    {
        $aluno = $this->aluno($this->t1);
        $m1 = $this->matricula($this->t1, $aluno, $this->plano, 'cancelada', $this->dia(-30));
        $m2 = $this->matricula($this->t1, $aluno, $this->plano, 'cancelada', $this->dia(-20));
        $repo = app(\App\Repositories\ProspeccaoRepository::class);
        $base = ['tenant_id' => $this->t1, 'aluno_id' => $aluno, 'data_cancelamento_referencia' => $this->dia(-30), 'status' => 'nao_contatado'];
        $repo->inserirOportunidade($base + ['matricula_referencia_id' => $m1]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $repo->inserirOportunidade($base + ['matricula_referencia_id' => $m2]);
    }

    // ------------------------------------------------------------ recuperação

    public function test_matricula_pendente_nao_muda_a_oportunidade_e_ativa_marca_recuperado(): void
    {
        $aluno = $this->aluno($this->t1);
        $this->matricula($this->t1, $aluno, $this->plano, 'cancelada', $this->dia(-30));
        $this->sincronizar($this->t1);
        $op = $this->oportunidades($this->t1)[0];
        DB::table('prospeccao_oportunidades')->where('id', $op->id)->update(['status' => ProspeccaoStatus::INTERESSADO]);

        $nova = $this->matricula($this->t1, $aluno, $this->plano, 'pendente', null, ['data_inicio' => $this->dia(0), 'data_matricula' => $this->dia(0)]);
        $this->sincronizar($this->t1);
        $this->assertSame(ProspeccaoStatus::INTERESSADO, DB::table('prospeccao_oportunidades')->where('id', $op->id)->value('status'));

        DB::table('matriculas')->where('id', $nova)->update(['status_id' => 1]); // ativa
        $r = $this->sincronizar($this->t1);
        $op = DB::table('prospeccao_oportunidades')->where('id', $op->id)->first();

        $this->assertSame(1, $r['recuperadas']);
        $this->assertSame(ProspeccaoStatus::RECUPERADO, $op->status);
        $this->assertSame($nova, (int) $op->matricula_conversao_id);
        $this->assertNotNull($op->encerrada_em);
        $this->assertNull($op->aluno_aberto);
        $this->assertTrue(DB::table('prospeccao_historico_status')->where('oportunidade_id', $op->id)->where('status_novo', 'recuperado')->whereNull('usuario_id')->exists());
    }

    public function test_recuperado_que_cancela_de_novo_abre_novo_ciclo_e_preserva_o_historico(): void
    {
        $aluno = $this->aluno($this->t1);
        $this->matricula($this->t1, $aluno, $this->plano, 'cancelada', $this->dia(-60));
        $this->sincronizar($this->t1);
        $nova = $this->matricula($this->t1, $aluno, $this->plano, 'ativa', null, ['data_inicio' => $this->dia(-40), 'data_matricula' => $this->dia(-40)]);
        $this->sincronizar($this->t1);

        DB::table('matriculas')->where('id', $nova)->update(['status_id' => 3, 'data_cancelamento' => $this->dia(-1)]);
        $this->sincronizar($this->t1);
        $ops = $this->oportunidades($this->t1);

        $this->assertCount(2, $ops);
        $this->assertSame(ProspeccaoStatus::RECUPERADO, $ops[0]->status);
        $this->assertSame(ProspeccaoStatus::NAO_CONTATADO, $ops[1]->status);
        $this->assertSame($nova, (int) $ops[1]->matricula_referencia_id);
    }

    public function test_alteracao_manual_para_recuperado_e_recusada(): void
    {
        $this->matricula($this->t1, $this->aluno($this->t1), $this->plano, 'cancelada', $this->dia(-5));
        $this->sincronizar($this->t1);
        $id = $this->oportunidades($this->t1)[0]->id;

        $this->patchJson("/v2/admin/prospeccao/oportunidades/{$id}/status", ['status' => 'recuperado'], $this->auth($this->admin, $this->t1))
            ->assertStatus(422);
    }

    // ------------------------------------------------------------ funil, contato e LGPD

    public function test_contato_avanca_funil_registra_historico_e_bloqueia_duplicidade_de_outro_atendente(): void
    {
        $this->matricula($this->t1, $this->aluno($this->t1), $this->plano, 'cancelada', $this->dia(-5));
        $this->sincronizar($this->t1);
        $id = $this->oportunidades($this->t1)[0]->id;
        $comercial = $this->usuario($this->t1, Papel::COMERCIAL, 'Comercial');

        $this->postJson("/v2/admin/prospeccao/oportunidades/{$id}/interacoes", ['canal' => 'whatsapp', 'resultado' => 'mensagem_enviada'], $this->auth($comercial, $this->t1))
            ->assertCreated()
            ->assertJsonPath('oportunidade.status', 'contatado')
            ->assertJsonPath('interacoes.0.resultado', 'mensagem_enviada');

        $this->postJson("/v2/admin/prospeccao/oportunidades/{$id}/interacoes", ['canal' => 'telefone', 'resultado' => 'conversou'], $this->auth($this->admin, $this->t1))
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONTATO_RECENTE');

        $this->postJson("/v2/admin/prospeccao/oportunidades/{$id}/interacoes", ['canal' => 'telefone', 'resultado' => 'interessado', 'confirmar_duplicado' => true], $this->auth($this->admin, $this->t1))
            ->assertCreated()
            ->assertJsonPath('oportunidade.status', 'interessado');

        $hist = DB::table('prospeccao_historico_status')->where('oportunidade_id', $id)->orderBy('id')->get();
        $this->assertSame(['nao_contatado', 'contatado', 'interessado'], $hist->pluck('status_novo')->all());
        $this->assertSame($comercial, (int) $hist[1]->usuario_id);
    }

    public function test_nao_contatar_cria_restricao_que_vale_para_ciclos_futuros_e_so_sai_com_motivo(): void
    {
        $aluno = $this->aluno($this->t1);
        $this->matricula($this->t1, $aluno, $this->plano, 'cancelada', $this->dia(-5));
        $this->sincronizar($this->t1);
        $id = $this->oportunidades($this->t1)[0]->id;
        $h = $this->auth($this->admin, $this->t1);

        $this->patchJson("/v2/admin/prospeccao/oportunidades/{$id}/status", ['status' => 'nao_contatar', 'motivo_restricao' => 'Pediu por telefone'], $h)
            ->assertOk()->assertJsonPath('oportunidade.nao_contatar', true);
        $this->postJson("/v2/admin/prospeccao/oportunidades/{$id}/interacoes", ['canal' => 'whatsapp', 'resultado' => 'mensagem_enviada'], $h)
            ->assertStatus(409);
        $this->patchJson("/v2/admin/prospeccao/oportunidades/{$id}/status", ['status' => 'contatado'], $h)
            ->assertStatus(422);

        $this->assertTrue(DB::table('prospeccao_restricoes_contato')->where('aluno_id', $aluno)->whereNull('removido_em')->exists());

        $this->postJson("/v2/admin/prospeccao/oportunidades/{$id}/remover-restricao", ['motivo' => 'x'], $h)->assertStatus(422);
        $this->postJson("/v2/admin/prospeccao/oportunidades/{$id}/remover-restricao", ['motivo' => 'Aluno voltou a autorizar no balcão'], $h)
            ->assertOk()
            ->assertJsonPath('oportunidade.status', 'nao_contatado')
            ->assertJsonPath('oportunidade.nao_contatar', false);
    }

    public function test_restricao_ativa_faz_nova_oportunidade_nascer_em_nao_contatar(): void
    {
        $aluno = $this->aluno($this->t1);
        app(\App\Repositories\ProspeccaoRepository::class)->inserirRestricao($this->t1, $aluno, 'LGPD', $this->admin, $this->dia(0).' 10:00:00');
        $this->matricula($this->t1, $aluno, $this->plano, 'cancelada', $this->dia(-5));

        $this->sincronizar($this->t1);
        $op = $this->oportunidades($this->t1)[0];

        $this->assertSame(ProspeccaoStatus::NAO_CONTATAR, $op->status);
        $this->assertNotNull($op->encerrada_em);
    }

    public function test_agendamento_atualiza_proximo_contato_e_status_e_aparece_na_agenda(): void
    {
        $this->matricula($this->t1, $this->aluno($this->t1), $this->plano, 'cancelada', $this->dia(-5));
        $this->sincronizar($this->t1);
        $id = $this->oportunidades($this->t1)[0]->id;
        $h = $this->auth($this->admin, $this->t1);
        $quando = $this->dia(2).' 15:00:00';

        $ag = $this->postJson('/v2/admin/prospeccao/agendamentos', ['oportunidade_id' => $id, 'agendado_para' => $quando, 'tipo_contato' => 'whatsapp'], $h)
            ->assertCreated()->json('agendamento');
        $this->assertSame($quando, DB::table('prospeccao_oportunidades')->where('id', $id)->value('proximo_contato_em'));
        $this->assertSame('retorno_agendado', DB::table('prospeccao_oportunidades')->where('id', $id)->value('status'));
        $this->getJson('/v2/admin/prospeccao/agendamentos', $h)->assertOk()->assertJsonCount(1, 'proximos');

        $this->patchJson('/v2/admin/prospeccao/agendamentos/'.$ag['id'], ['acao' => 'concluir'], $h)->assertOk();
        $this->assertNull(DB::table('prospeccao_oportunidades')->where('id', $id)->value('proximo_contato_em'));
        $this->postJson('/v2/admin/prospeccao/agendamentos', ['oportunidade_id' => $id, 'agendado_para' => $this->dia(-2).' 10:00:00', 'tipo_contato' => 'whatsapp'], $h)
            ->assertStatus(422);
    }

    // ------------------------------------------------------------ isolamento e permissões

    public function test_um_tenant_nao_acessa_oportunidades_de_outro(): void
    {
        $t2 = $this->tenant('Academia Dois');
        $admin2 = $this->usuario($t2, Papel::ADMIN, 'Admin 2');
        $plano2 = $this->plano($t2);
        $this->matricula($t2, $this->aluno($t2, 'ALUNO DO OUTRO'), $plano2, 'cancelada', $this->dia(-5));
        $this->sincronizar($t2);
        $idOutro = $this->oportunidades($t2)[0]->id;
        $h = $this->auth($this->admin, $this->t1);

        $this->getJson('/v2/admin/prospeccao/oportunidades?status=todas', $h)->assertOk()->assertJsonPath('paginacao.total', 0);
        $this->getJson("/v2/admin/prospeccao/oportunidades/{$idOutro}", $h)->assertNotFound();
        $this->patchJson("/v2/admin/prospeccao/oportunidades/{$idOutro}/status", ['status' => 'contatado'], $h)->assertNotFound();
        $this->postJson("/v2/admin/prospeccao/oportunidades/{$idOutro}/interacoes", ['canal' => 'whatsapp', 'resultado' => 'conversou'], $h)->assertNotFound();
        $this->postJson('/v2/admin/prospeccao/agendamentos', ['oportunidade_id' => $idOutro, 'agendado_para' => $this->dia(1).' 10:00:00', 'tipo_contato' => 'whatsapp'], $h)->assertNotFound();

        // Admin de A com token apontando para B (onde não tem papel) é barrado
        $this->getJson('/v2/admin/prospeccao/oportunidades', $this->auth($this->admin, $t2))->assertForbidden();
        $this->getJson('/v2/admin/prospeccao/oportunidades', $this->auth($admin2, $t2))->assertOk()->assertJsonPath('paginacao.total', 1);
    }

    public function test_permissoes_por_papel(): void
    {
        $comercial = $this->usuario($this->t1, Papel::COMERCIAL, 'Comercial');
        $professor = $this->usuario($this->t1, Papel::PROFESSOR, 'Professor');
        $aluno = $this->usuario($this->t1, Papel::ALUNO, 'Aluno');

        $this->getJson('/v2/admin/prospeccao/oportunidades')->assertUnauthorized();
        $this->getJson('/v2/admin/prospeccao/oportunidades', $this->auth($aluno, $this->t1))->assertForbidden();
        $this->getJson('/v2/admin/prospeccao/oportunidades', $this->auth($professor, $this->t1))->assertForbidden();
        $this->getJson('/v2/admin/prospeccao/oportunidades', $this->auth($comercial, $this->t1))->assertOk();
        $this->getJson('/v2/admin/prospeccao/contexto', $this->auth($comercial, $this->t1))->assertOk()->assertJsonPath('admin', false);

        // Comercial não gerencia equipe nem score, e não entra no painel admin existente
        $this->getJson('/v2/admin/prospeccao/equipe', $this->auth($comercial, $this->t1))->assertForbidden();
        $this->putJson('/v2/admin/prospeccao/score/config', ['pesos' => ['frequencia' => 10]], $this->auth($comercial, $this->t1))->assertForbidden();
        $this->getJson('/v2/admin/dashboard', $this->auth($comercial, $this->t1))->assertForbidden();
        $this->getJson('/v2/admin/prospeccao/equipe', $this->auth($this->admin, $this->t1))->assertOk()->assertJsonCount(1, 'comerciais');
    }

    public function test_comercial_nao_ve_valores_financeiros_e_nao_pode_matricular(): void
    {
        $this->matricula($this->t1, $this->aluno($this->t1), $this->plano, 'cancelada', $this->dia(-5));
        $this->sincronizar($this->t1);
        $id = $this->oportunidades($this->t1)[0]->id;
        $comercial = $this->usuario($this->t1, Papel::COMERCIAL, 'Comercial');

        $ficha = $this->getJson("/v2/admin/prospeccao/oportunidades/{$id}", $this->auth($comercial, $this->t1))->assertOk()->json();
        $this->assertArrayNotHasKey('valor_pago', $ficha['financeiro']);
        $this->assertArrayHasKey('valor_pago', $this->getJson("/v2/admin/prospeccao/oportunidades/{$id}", $this->auth($this->admin, $this->t1))->json('financeiro'));

        $this->getJson("/v2/admin/prospeccao/oportunidades/{$id}/recuperacao", $this->auth($comercial, $this->t1))
            ->assertOk()->assertJsonPath('pode_matricular', false);
        $this->getJson("/v2/admin/prospeccao/oportunidades/{$id}/recuperacao", $this->auth($this->admin, $this->t1))
            ->assertOk()->assertJsonPath('pode_matricular', true);
    }

    public function test_admin_que_tambem_e_comercial_continua_admin(): void
    {
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => $this->t1, 'usuario_id' => $this->admin, 'papel_id' => Papel::COMERCIAL, 'ativo' => 1]);

        $ctx = app(\App\Repositories\UsuarioRepository::class)->findAuthContext($this->admin);
        $this->assertSame(Papel::ADMIN, (int) $ctx['papel_id']);
        $this->assertSame(Papel::ADMIN, app(\App\Repositories\UsuarioRepository::class)->getPapeis($this->admin)[0]['id']);
        $this->getJson('/v2/admin/prospeccao/contexto', $this->auth($this->admin, $this->t1))->assertJsonPath('admin', true);
    }

    public function test_comercial_nao_herda_permissao_de_gerenciar_turma(): void
    {
        $comercial = $this->usuario($this->t1, Papel::COMERCIAL, 'Comercial');

        $servico = app(\App\Services\TurmaCheckinBloqueioService::class);
        $this->assertFalse($servico->usuarioPodeGerenciarTurma($comercial, $this->t1, 999, null));
        // Mobile passa o papel do contexto de autenticação (5 para quem é só comercial)
        $this->assertFalse($servico->usuarioPodeGerenciarTurma($comercial, $this->t1, 999, ['id' => Papel::COMERCIAL]));
    }

    // ------------------------------------------------------------ listagem

    public function test_listagem_pagina_filtra_e_ordena_no_backend(): void
    {
        foreach (['ANA' => -10, 'BRUNO' => -40, 'CARLA' => -100] as $nome => $dias) {
            $this->matricula($this->t1, $this->aluno($this->t1, $nome, '8298888'.abs($dias)), $this->plano, 'cancelada', $this->dia($dias));
        }
        $h = $this->auth($this->admin, $this->t1);

        $this->getJson('/v2/admin/prospeccao/oportunidades?ordem=nome&direcao=asc&por_pagina=2', $h)
            ->assertOk()
            ->assertJsonPath('paginacao.total', 3)
            ->assertJsonPath('paginacao.paginas', 2)
            ->assertJsonCount(2, 'oportunidades')
            ->assertJsonPath('oportunidades.0.aluno_nome', 'ANA');
        $this->getJson('/v2/admin/prospeccao/oportunidades?busca=bru', $h)->assertJsonPath('paginacao.total', 1);
        $this->getJson('/v2/admin/prospeccao/oportunidades?busca=(82) 98888-40', $h)->assertJsonPath('oportunidades.0.aluno_nome', 'BRUNO');
        $this->getJson('/v2/admin/prospeccao/oportunidades?inatividade_min=30&inatividade_max=60', $h)->assertJsonPath('paginacao.total', 1);
        $this->getJson('/v2/admin/prospeccao/oportunidades?ordem=nome;DROP', $h)->assertOk();
        $this->getJson('/v2/admin/prospeccao/oportunidades?status=xyz', $h)->assertStatus(422);
    }
}
