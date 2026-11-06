<?php

namespace Tests\Feature;

use App\Repositories\AdminAssinaturaRepository;
use App\Repositories\AdminMatriculaRepository;
use App\Repositories\AdminPlanoCicloRepository;
use App\Repositories\AdminPlanoRepository;
use App\Repositories\MatriculaRepository;
use App\Repositories\TurmaRepository;
use App\Repositories\UsuarioRepository;
use App\Services\AcessoHibridoService;
use App\Services\Admin\AdminMatriculaService;
use App\Services\Admin\AdminPacoteService;
use App\Services\Admin\AdminPagamentoPlanoService;
use App\Services\Admin\AdminPlanoCicloService;
use App\Services\Admin\AdminTurmaFixaService;
use App\Services\JwtService;
use App\Services\MatriculaMigracaoAptidaoService;
use App\Services\PagamentoPlanoService;
use App\Services\ReajustePrecoService;
use App\Services\Recorrencia\RecorrenciaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\HybridFixture;
use Tests\TestCase;

class AcessoHibridoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        HybridFixture::create();
        HybridFixture::migrate();
        HybridFixture::seed();
    }

    public function test_ausencias_preservam_dez_vagas_fixas_e_apenas_duas_livres(): void
    {
        $acesso = app(AcessoHibridoService::class);
        $aula = HybridFixture::aula();
        $this->assertSame(10, $acesso->vagas($aula, 3)['vagas_fixas']);
        $this->assertSame(2, $acesso->vagas($aula, 3)['vagas_livres']);
        foreach ([11, 12] as $aluno) {
            $this->assertNull($acesso->erroCheckin($aula, 3, $acesso->matriculaParaAula($aluno, 3, $aula)));
            DB::table('checkins')->insert(['tenant_id' => 3, 'turma_id' => 1, 'aluno_id' => $aluno]);
        }
        $this->assertNotNull($acesso->erroCheckin($aula, 3, $acesso->matriculaParaAula(13, 3, $aula)));
        $this->assertNull($acesso->erroCheckin($aula, 3, $acesso->matriculaParaAula(1, 3, $aula)));
        DB::table('checkins')->insert(['tenant_id' => 3, 'turma_id' => 1, 'aluno_id' => 1]);
        $this->assertSame(0, $acesso->vagas($aula, 3)['vagas_livres']);
        $this->assertSame(2, $acesso->vagas($aula, 3)['vagas_livres_ocupadas']);
    }

    public function test_tenant_turma_data_plano_e_permissao_livre(): void
    {
        $acesso = app(AcessoHibridoService::class);
        $aula = HybridFixture::aula();
        $this->assertNull($acesso->matriculaParaAula(1, 4, $aula));
        $this->assertNull($acesso->matriculaParaAula(1, 3, array_replace($aula, ['turma_fixa_id' => 2])));
        $this->assertNull($acesso->matriculaParaAula(1, 3, array_replace($aula, ['dia_data' => '2000-01-01'])));
        $this->assertNull($acesso->matriculaParaAula(1, 3, array_replace($aula, ['modalidade_id' => 2])));
        $this->assertNotNull($acesso->erroCheckin(array_replace($aula, ['checkin_livre' => false]), 3, $acesso->matriculaParaAula(11, 3, $aula)));
        $this->expectException(ValidationException::class);
        $acesso->validarEscolha(3, ['tipo_acesso' => 'turma_fixa', 'turma_fixa_id' => 1, 'plano_id' => 3]);
    }

    public function test_pendente_nao_reserva_e_ativacao_sem_vaga_preserva_pagamento_e_valor(): void
    {
        DB::table('turmas_fixas')->where('id', 1)->update(['capacidade' => 10]);
        DB::table('matriculas')->where('id', 11)->update(['tipo_acesso' => 'turma_fixa', 'turma_fixa_id' => 1, 'status_id' => 2]);
        $acesso = app(AcessoHibridoService::class);
        $this->assertSame(10, $acesso->vagas(HybridFixture::aula(), 3)['vagas_fixas']);
        $status = $acesso->aplicarStatus(3, 11, 1, ['status_id' => 1]);
        $this->assertSame('aguardando_vaga', DB::table('status_matricula')->where('id', $status)->value('codigo'));
        $this->assertSame($status, DB::table('matriculas')->where('id', 11)->value('status_id'));
        $this->assertEquals(100, DB::table('matriculas')->where('id', 11)->value('valor'));
        DB::table('matriculas')->where('id', 1)->update(['status_id' => 3]);
        $this->assertSame(1, $acesso->aplicarStatus(3, 11, 1));
    }

    public function test_ativacao_considera_vagas_livres_ja_ocupadas(): void
    {
        foreach ([11, 12] as $aluno) {
            DB::table('checkins')->insert(['tenant_id' => 3, 'turma_id' => 1, 'aluno_id' => $aluno]);
        }
        DB::table('matriculas')->where('id', 13)->update(['tipo_acesso' => 'turma_fixa', 'turma_fixa_id' => 1, 'status_id' => 2]);
        $status = app(AcessoHibridoService::class)->aplicarStatus(3, 13, 1);
        $this->assertSame('aguardando_vaga', DB::table('status_matricula')->where('id', $status)->value('codigo'));
    }

    public function test_preco_de_tabela_nao_modifica_contratos_e_vinculo_nao_muda_valor(): void
    {
        DB::table('planos')->where('id', 1)->update(['valor' => 120]);
        $this->assertEquals(120, DB::table('planos')->where('id', 1)->value('valor'));
        $this->assertEquals(100, DB::table('matriculas')->where('id', 1)->value('valor'));
        app(AdminTurmaFixaService::class)->vincularMatricula(3, 1, ['tipo_acesso' => 'horario_livre']);
        $this->assertSame('horario_livre', DB::table('matriculas')->where('id', 1)->value('tipo_acesso'));
        $this->assertEquals(100, DB::table('matriculas')->where('id', 1)->value('valor'));
    }

    public function test_novas_rotas_exigem_login_e_administrador(): void
    {
        $this->getJson('/v2/admin/turmas-fixas')->assertUnauthorized();
        $this->postJson('/v2/admin/turmas-fixas', [], ['Authorization' => 'Bearer '.$this->tokenParaPapel(1)])->assertForbidden();
        $this->getJson('/v2/admin/turmas-fixas', ['Authorization' => 'Bearer '.$this->tokenParaPapel(3)])
            ->assertOk()->assertJsonPath('turmas.0.nome', 'Turma Aqua');
    }

    public function test_editar_preco_so_afeta_nova_contratacao_e_cobranca(): void
    {
        $ciclo = ['id' => 1, 'plano_id' => 1, 'valor' => 100, 'tipo_meses' => 1, 'tipo_codigo' => 'mensal',
            'plano_valor' => 100, 'plano_duracao_dias' => 30, 'permite_recorrencia' => 0, 'permite_reposicao' => 0, 'ativo' => 1];
        $ciclos = \Mockery::mock(AdminPlanoCicloRepository::class);
        $ciclos->shouldReceive('findCiclo')->andReturn($ciclo);
        $ciclos->shouldReceive('update')->once()->andReturnUsing(function ($id, $tenant, $dados) use (&$ciclo) {
            $ciclo['valor'] = $dados['valor'];
        });
        $ciclos->shouldReceive('listarPorPlano')->andReturnUsing(fn () => [$ciclo + ['meses' => 1]]);
        $ciclos->shouldReceive('updateDesconto');
        $planos = \Mockery::mock(AdminPlanoRepository::class);
        $planos->shouldReceive('updateValor')->andReturnUsing(fn ($id, $tenant, $valor) => DB::table('planos')->where('id', $id)->update(['valor' => $valor]));
        $reajuste = \Mockery::mock(ReajustePrecoService::class);
        $reajuste->shouldNotReceive('reajustarCiclo');
        $recorrencia = \Mockery::mock(RecorrenciaService::class);
        $recorrencia->shouldNotReceive('reajustarContratos');
        $this->app->instance(ReajustePrecoService::class, $reajuste);
        $this->app->instance(RecorrenciaService::class, $recorrencia);
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('matricula_id');
            $t->decimal('valor', 10, 2);
            $t->integer('status_pagamento_id');
        });
        DB::table('pagamentos_plano')->insert(['tenant_id' => 3, 'matricula_id' => 1, 'valor' => 100, 'status_pagamento_id' => 1]);
        $result = (new AdminPlanoCicloService($ciclos, $planos))->atualizar(1, 1, 3, ['valor' => 120]);
        $this->assertSame(200, $result['status']);
        $this->assertEquals(120, DB::table('planos')->where('id', 1)->value('valor'));
        $this->assertEquals(100, DB::table('matriculas')->where('id', 1)->value('valor'));
        $this->assertEquals(100, DB::table('pagamentos_plano')->where('matricula_id', 1)->value('valor'));
        $repo = \Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findUsuarioIdPorAluno')->andReturn(15);
        $repo->shouldReceive('findUsuarioAluno', 'findUsuarioAlunoNoTenant')->andReturn(['id' => 15]);
        $repo->shouldReceive('ensureVinculoAlunoTenant', 'inserirHistoricoPlanos');
        $repo->shouldReceive('findPlano')->andReturnUsing(fn () => ((array) DB::table('planos')->where('id', 1)->first()) + ['duracao_dias' => 30]);
        $repo->shouldReceive('findPlanoCicloAtivo')->andReturnUsing(fn () => $ciclo + ['meses' => 1]);
        $repo->shouldReceive('matriculasAtivasValidas')->andReturn([]);
        $repo->shouldReceive('buscarMatriculaDuplicadaMesmoPlanoCiclo', 'ultimaMatriculaNaModalidade')->andReturn(null);
        $repo->shouldReceive('statusIdPorCodigo')->andReturn(2);
        $repo->shouldReceive('motivoIdPorCodigo')->andReturn(1);
        $repo->shouldReceive('inserirMatricula')->once()->andReturnUsing(fn ($payload) => DB::table('matriculas')->insertGetId(array_intersect_key($payload, array_flip(Schema::getColumnListing('matriculas')))));
        $repo->shouldReceive('inserirPagamentoPlano')->once()->andReturnUsing(fn ($payload) => DB::table('pagamentos_plano')->insertGetId(array_intersect_key($payload, array_flip(Schema::getColumnListing('pagamentos_plano')))));
        $repo->shouldReceive('findMatriculaCriada')->andReturnUsing(fn ($id) => (array) DB::table('matriculas')->where('id', $id)->first());
        $repo->shouldReceive('listarPagamentosCriar')->andReturnUsing(fn ($id) => DB::table('pagamentos_plano')->where('matricula_id', $id)->get()->map(fn ($p) => (array) $p)->all());
        $service = new AdminMatriculaService($repo, \Mockery::mock(PagamentoPlanoService::class),
            \Mockery::mock(MatriculaRepository::class), \Mockery::mock(AdminPacoteService::class),
            \Mockery::mock(AdminPagamentoPlanoService::class), \Mockery::mock(AdminAssinaturaRepository::class),
            \Mockery::mock(MatriculaMigracaoAptidaoService::class));
        $novo = $service->criar(3, 5, ['aluno_id' => 15, 'plano_id' => 1, 'plano_ciclo_id' => 1, 'tipo_acesso' => 'horario_livre']);
        $this->assertSame(201, $novo['status'], json_encode($novo));
        $this->assertEquals(120, $novo['body']['matricula']['valor']);
        $this->assertEquals(120, $novo['body']['pagamentos'][0]['valor']);
        $this->assertEquals(100, DB::table('matriculas')->where('id', 1)->value('valor'));
        $this->assertEquals(100, DB::table('pagamentos_plano')->where('matricula_id', 1)->value('valor'));
    }

    public function test_cadastro_recorrente_materializa_dias_sem_recriar_aulas_e_preserva_contratos(): void
    {
        Schema::create('professores', function (Blueprint $t) {
            $t->integer('id')->primary();
        });
        DB::table('professores')->insert(['id' => 2]);
        $turmas = \Mockery::mock(TurmaRepository::class)->makePartial();
        $turmas->shouldReceive('professorPertenceAoTenant')->andReturn(true);
        $turmas->shouldReceive('verificarHorarioOcupado')->andReturn([]);
        $this->app->instance(TurmaRepository::class, $turmas);
        $headers = ['Authorization' => 'Bearer '.$this->tokenParaPapel(3)];
        $dados = ['nome' => 'Turma tarde', 'professor_id' => 2, 'modalidade_id' => 1,
            'dias_semana' => [1, 3], 'horario_inicio' => '15:00', 'horario_fim' => '16:00',
            'capacidade' => 8, 'planos' => [2], 'checkin_livre' => true];
        $created = $this->postJson('/v2/admin/turmas-fixas', $dados, $headers)->assertCreated();
        $id = $created->json('id');
        $aulas = DB::table('turmas as t')->join('dias as d', 'd.id', '=', 't.dia_id')->where('t.turma_fixa_id', $id)->get(['d.data']);
        $this->assertGreaterThan(20, count($aulas));
        foreach ($aulas as $aula) {
            $this->assertContains((int) (new \DateTime($aula->data))->format('N'), [1, 3]);
        }
        $this->assertSame(0, app(AdminTurmaFixaService::class)->gerarAulas(3, $id));
        $this->getJson('/v2/admin/turmas-fixas', $headers)->assertOk()->assertJsonCount(2, 'turmas');
        $this->putJson("/v2/admin/turmas-fixas/{$id}", array_replace($dados, ['capacidade' => 9]), $headers)->assertOk();
        $this->assertEquals(9, DB::table('turmas_fixas')->where('id', $id)->value('capacidade'));
        $this->assertEquals(100, DB::table('matriculas')->where('id', 1)->value('valor'));
        $this->putJson('/v2/admin/turmas-fixas/1', array_replace($dados, ['planos' => [1], 'capacidade' => 9]), $headers)->assertUnprocessable();
        $this->assertEquals(12, DB::table('turmas_fixas')->where('id', 1)->value('capacidade'));
    }

    public function test_reajuste_legado_fica_preservado_mas_exige_autorizacao(): void
    {
        Schema::table('matriculas', function (Blueprint $t) {
            $t->decimal('valor_renovacao', 10, 2)->nullable();
        });
        DB::table('matriculas')->where('id', 1)->update(['valor_renovacao' => 120]);
        $reajuste = app(ReajustePrecoService::class);
        $this->assertNull($reajuste->valorRenovacao(3, 1));
        $this->assertNull($reajuste->iniciarCicloNovo(3, 1));
        $this->assertEquals(100, DB::table('matriculas')->where('id', 1)->value('valor'));
        $this->assertEquals(120, DB::table('matriculas')->where('id', 1)->value('valor_renovacao'));
        DB::table('matriculas')->where('id', 1)->update(['reajuste_autorizado_em' => now()]);
        $this->assertEquals(120, $reajuste->iniciarCicloNovo(3, 1));
        $this->assertNull(DB::table('matriculas')->where('id', 1)->value('reajuste_autorizado_em'));
    }

    public function test_reajuste_explicito_simula_sem_persistir_e_confirma_sem_mudar_parcela_paga(): void
    {
        Schema::table('matriculas', function (Blueprint $t) {
            $t->decimal('valor_renovacao', 10, 2)->nullable();
            $t->integer('plano_ciclo_id')->default(1);
            $t->integer('pacote_contrato_id')->nullable();
            $t->string('tipo_cobranca')->default('avulso');
        });
        Schema::create('plano_ciclos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->decimal('valor', 10, 2);
        });
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('matricula_id');
            $t->decimal('valor', 10, 2);
            $t->integer('status_pagamento_id');
            $t->date('data_vencimento');
            $t->date('data_pagamento')->nullable();
            $t->string('observacoes')->nullable();
            $t->decimal('desconto', 10, 2)->default(0);
            $t->timestamps();
        });
        DB::table('plano_ciclos')->insert(['id' => 1, 'tenant_id' => 3, 'valor' => 120]);
        foreach ([1, 2] as $status) {
            DB::table('pagamentos_plano')->insert(['tenant_id' => 3, 'matricula_id' => 1, 'valor' => 100,
                'status_pagamento_id' => $status, 'data_vencimento' => '2099-01-01']);
        }
        DB::table('matriculas')->where('id', 1)->update(['valor_renovacao' => 110]);
        $opcoes = ['--tenant' => 3, '--ciclo' => 1, '--valor-anterior' => 100];
        $this->artisan('planos:reajustar-contratos', $opcoes)->assertSuccessful();
        $this->assertEquals(110, DB::table('matriculas')->where('id', 1)->value('valor_renovacao'));
        $this->assertNull(DB::table('matriculas')->where('id', 1)->value('reajuste_autorizado_em'));
        $this->assertEquals(100, DB::table('pagamentos_plano')->where('id', 1)->value('valor'));
        $this->artisan('planos:reajustar-contratos', $opcoes + ['--confirmar' => true])->assertSuccessful();
        $this->assertEquals(120, DB::table('matriculas')->where('id', 1)->value('valor_renovacao'));
        $this->assertNotNull(DB::table('matriculas')->where('id', 1)->value('reajuste_autorizado_em'));
        $this->assertEquals(100, DB::table('matriculas')->where('id', 1)->value('valor'));
        $this->assertEquals(120, DB::table('pagamentos_plano')->where('id', 1)->value('valor'));
        $this->assertEquals(100, DB::table('pagamentos_plano')->where('id', 2)->value('valor'));
    }

    public function test_ativacao_e_prorrogacao_manualmente_respeitam_a_capacidade(): void
    {
        DB::table('turmas_fixas')->where('id', 1)->update(['capacidade' => 10]);
        DB::table('matriculas')->where('id', 11)->update(['tipo_acesso' => 'turma_fixa', 'turma_fixa_id' => 1, 'status_id' => 2]);
        $repo = app(AdminMatriculaRepository::class);
        $repo->ativarMatriculaSePendente(11);
        $esperando = DB::table('status_matricula')->where('codigo', 'aguardando_vaga')->value('id');
        $this->assertEquals($esperando, DB::table('matriculas')->where('id', 11)->value('status_id'));
        $this->assertTrue($repo->atualizarProximaDataVencimento(11, 3, '2099-01-01', 1, false)['ok']);
        $this->assertEquals($esperando, DB::table('matriculas')->where('id', 11)->value('status_id'));
        DB::table('matriculas')->where('id', 11)->update(['status_id' => 1, 'proxima_data_vencimento' => '2000-01-01']);
        $repo->atualizarProximaDataVencimentoSimples(11, '2099-01-01');
        $this->assertEquals($esperando, DB::table('matriculas')->where('id', 11)->value('status_id'));
        $this->assertEquals(100, DB::table('matriculas')->where('id', 11)->value('valor'));
    }

    public function test_vinculo_nao_aceita_plano_falso_para_contornar_a_oferta(): void
    {
        DB::table('turma_fixa_planos')->where('turma_fixa_id', 1)->update(['plano_id' => 2]);
        $this->expectException(ValidationException::class);
        app(AdminTurmaFixaService::class)->vincularMatricula(3, 1, ['tipo_acesso' => 'turma_fixa', 'turma_fixa_id' => 1, 'plano_id' => 2]);
    }

    public function test_migracao_automatica_nao_sobrepoe_vinculo_fixo_alterado_pela_secretaria(): void
    {
        DB::transaction(fn () => app(AcessoHibridoService::class)->validarMigracaoAutomatica(3, 11));
        $this->expectException(ValidationException::class);
        DB::transaction(fn () => app(AcessoHibridoService::class)->validarMigracaoAutomatica(3, 1));
    }

    private function tokenParaPapel(int $papel): string
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);
        $usuarios = \Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')->with(5)->andReturn([
            'id' => 5, 'nome' => 'Admin', 'email' => 'admin@example.test', 'tenant_id' => 3, 'papel_id' => $papel,
        ]);
        $this->app->instance(UsuarioRepository::class, $usuarios);

        return app(JwtService::class)->encode(['user_id' => 5, 'tenant_id' => 3, 'email' => 'admin@example.test']);
    }
}
