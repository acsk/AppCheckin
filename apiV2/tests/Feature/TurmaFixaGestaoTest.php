<?php

namespace Tests\Feature;

use App\Repositories\TurmaRepository;
use App\Repositories\UsuarioRepository;
use App\Services\AcessoHibridoService;
use App\Services\Admin\AdminTurmaFixaGestaoService;
use App\Services\Admin\AdminTurmaFixaService;
use App\Services\Comunicacao\ComunicacaoService;
use App\Services\JwtService;
use App\Support\AcademyDateTime;
use App\Support\Permissao;
use App\Support\PermissaoRotas;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Fixtures\HybridFixture;
use Tests\TestCase;

class TurmaFixaGestaoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        HybridFixture::create();
        HybridFixture::migrate();
        HybridFixture::seed();
        (require database_path('migrations/2026_11_06_140000_grade_individual_reposicoes.php'))->up();
        (require database_path('migrations/2026_11_06_160000_ciclo_vida_turmas_fixas.php'))->up();
        Schema::create('professores', function (Blueprint $t) {
            $t->integer('id')->primary();
        });
        DB::table('professores')->insert([['id' => 1], ['id' => 2]]);
        $turmas = \Mockery::mock(TurmaRepository::class)->makePartial();
        $turmas->shouldReceive('professorPertenceAoTenant')->andReturnUsing(fn ($id, $tenant) => in_array($id, [1, 2], true) && $tenant === 3);
        $this->app->instance(TurmaRepository::class, $turmas);
        DB::table('matricula_grade')->where('matricula_id', '>', 1)->delete();
        DB::table('planos')->where('id', 1)->update(['checkins_semanais' => 3]);
    }

    private function executar(string $acao, array $dados = [], array $ids = [1]): array
    {
        return app(AdminTurmaFixaGestaoService::class)->executar(3, 90, ['acao' => $acao, 'ids' => $ids, 'dados' => $dados]);
    }

    private function vigenciaTeste(): string
    {
        return AcademyDateTime::now()->modify('+7 days')->format('Y-m-d');
    }

    public function test_criar_turma_sem_nome_gera_nome_a_partir_dos_dias_e_horario(): void
    {
        $turma = app(AdminTurmaFixaService::class)->criar(3, [
            'professor_id' => 2, 'modalidade_id' => 1, 'dias_semana' => [4, 2],
            'horario_inicio' => '17:00', 'horario_fim' => '18:00', 'capacidade' => 12,
        ]);
        $this->assertSame('Ter/Qui 17 às 18h', DB::table('turmas_fixas')->where('id', $turma['id'])->value('nome'));
    }

    public function test_mudanca_grade_preserva_ocorrencias_passadas_e_remapeia_so_dias_confirmados(): void
    {
        DB::table('matricula_grade')->where('dia_semana', '<>', 1)->delete();
        $from = $this->vigenciaTeste();
        $antes = HybridFixture::aula();
        $this->executar('grade', ['from' => $from, 'dias_semana' => [2, 4], 'horario_inicio' => '18:00',
            'horario_fim' => '19:00', 'professor_id' => 2, 'remapeamentos' => [['matricula_id' => 1, 'dias_semana' => [2]]]]);
        $novo = (int) DB::table('turmas_fixas')->where('id', 1)->value('substituida_por_id');
        $acesso = app(AcessoHibridoService::class);
        $this->assertEquals(1, $acesso->grade(3, 1)[0]['turma_fixa_id']);
        $this->assertSame([2], array_map('intval', array_column($acesso->grade(3, 1, $from), 'dia_semana')));
        $this->assertEquals($novo, $acesso->grade(3, 1, $from)[0]['turma_fixa_id']);
        $this->assertEquals($antes, HybridFixture::aula());
        $this->assertEquals(100, DB::table('matriculas')->where('id', 1)->value('valor'));
        $this->assertEquals(1, DB::table('matriculas')->where('id', 1)->value('plano_id'));
        $data = (new \DateTimeImmutable($from))->modify('next Tuesday')->format('Y-m-d');
        $aula = DB::table('turmas as t')->join('dias as d', 'd.id', '=', 't.dia_id')->where('t.turma_fixa_id', $novo)->where('d.data', $data)->first(['t.*', 'd.data as dia_data']);
        $this->assertNotNull($acesso->matriculaParaAula(1, 3, (array) $aula));
        $aula->dia_data = (new \DateTimeImmutable($data))->modify('+2 days')->format('Y-m-d');
        $this->assertNull($acesso->matriculaParaAula(1, 3, (array) $aula));
        $this->assertSame(1, DB::table('turma_fixa_eventos')->count());
    }

    public function test_editar_grade_aceita_nome_vazio_e_salva_dados_da_nova_versao(): void
    {
        $from = $this->vigenciaTeste();
        $this->executar('grade', [
            'from' => $from,
            'nome' => null,
            'dias_semana' => [2],
            'horario_inicio' => '18:00',
            'horario_fim' => '19:00',
            'professor_id' => 2,
            'capacidade' => 17,
            'checkin_livre' => false,
            'remapeamentos' => [['matricula_id' => 1, 'dias_semana' => [2]]],
        ]);

        $novaId = (int) DB::table('turmas_fixas')->where('id', 1)->value('substituida_por_id');
        $nova = DB::table('turmas_fixas')->where('id', $novaId)->first();
        $this->assertSame('Ter 18 às 19h', $nova->nome);
        $this->assertSame(17, (int) $nova->capacidade);
        $this->assertSame(0, (int) $nova->checkin_livre);
        $this->assertSame(17, (int) DB::table('turmas')->where('turma_fixa_id', $novaId)->value('limite_alunos'));
        $this->assertSame(0, (int) DB::table('turmas')->where('turma_fixa_id', $novaId)->value('checkin_livre'));
    }

    public function test_remapeamento_incompleto_ou_excesso_nao_aplica_nenhuma_alteracao(): void
    {
        $dados = ['from' => $this->vigenciaTeste(), 'dias_semana' => [2, 4], 'horario_inicio' => '18:00', 'horario_fim' => '19:00', 'professor_id' => 2, 'remapeamentos' => []];
        try {
            $this->executar('grade', $dados);
            $this->fail('Deveria exigir remapeamento.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('remapeamentos', $e->errors());
        }
        $this->assertSame(1, DB::table('turmas_fixas')->count());
        $this->assertNull(DB::table('turmas_fixas')->value('vigencia_fim'));
        DB::table('planos')->where('id', 1)->update(['checkins_semanais' => 1]);
        $dados['remapeamentos'] = [['matricula_id' => 1, 'dias_semana' => [2, 4]]];
        try {
            $this->executar('grade', $dados);
            $this->fail('Deveria limitar frequência.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('grade', $e->errors());
        }
        $this->assertSame(3, DB::table('matricula_grade')->count());
        $this->assertSame(0, DB::table('turma_fixa_eventos')->count());
    }

    public function test_professor_com_vigencia_preserva_dias_e_professor_anterior(): void
    {
        DB::table('matricula_grade')->insert(['tenant_id' => 3, 'matricula_id' => 2, 'turma_fixa_id' => 1,
            'dia_semana' => 1, 'vigencia_inicio' => AcademyDateTime::today()]);
        $this->executar('professor', ['from' => $this->vigenciaTeste(), 'professor_id' => 2]);
        $this->assertEquals(1, DB::table('turmas')->where('id', 1)->value('professor_id'));
        $novo = DB::table('turmas_fixas')->where('id', 1)->value('substituida_por_id');
        $this->assertEquals(2, DB::table('turmas_fixas')->where('id', $novo)->value('professor_id'));
        $this->assertSame([1, 3, 5], array_map('intval', array_column(app(AcessoHibridoService::class)->grade(3, 1, $this->vigenciaTeste()), 'dia_semana')));
        $this->assertSame([1], array_map('intval', array_column(app(AcessoHibridoService::class)->grade(3, 2, $this->vigenciaTeste()), 'dia_semana')));
    }

    public function test_pausa_retomada_e_fim_automatico_preservam_desativacao_manual_e_matricula(): void
    {
        $admin = app(AdminTurmaFixaService::class);
        $admin->gerarAulas(3, 1);
        $manual = DB::table('turmas')->where('turma_fixa_id', 1)->where('id', '<>', 1)->orderBy('id')->first();
        DB::table('turmas')->where('id', $manual->id)->update(['ativo' => 0]);
        $matricula = DB::table('matriculas')->where('id', 1)->first();
        $from = $this->vigenciaTeste();
        $to = (new \DateTimeImmutable($from))->modify('+15 days')->format('Y-m-d');
        $this->executar('pausar', ['from' => $from, 'to' => $to, 'motivo' => 'Reforma']);
        $this->assertGreaterThan(0, DB::table('turmas')->where('suspensao_turma_id', 1)->count());
        $this->assertEquals($matricula, DB::table('matriculas')->where('id', 1)->first());
        $this->executar('retomar');
        $this->assertSame(0, DB::table('turmas')->where('suspensao_turma_id', 1)->count());
        $this->assertEquals(0, DB::table('turmas')->where('id', $manual->id)->value('ativo'));
        $this->assertNotNull(DB::table('turma_fixa_pausas')->value('cancelada_em'));
        $this->executar('pausar', ['from' => $from, 'to' => $to]);
        DB::table('turma_fixa_pausas')->whereNull('cancelada_em')->update(['fim' => AcademyDateTime::now()->modify('-1 day')->format('Y-m-d')]);
        $admin->gerarAulas(3, 1);
        $this->assertSame(0, DB::table('turmas')->where('suspensao_turma_id', 1)->count());
    }

    public function test_arquivar_exige_confirmacao_de_retirada_e_reativar_nao_restabelece_grade(): void
    {
        $from = $this->vigenciaTeste();
        try {
            $this->executar('arquivar', ['from' => $from, 'remapeamentos' => []]);
            $this->fail('Deveria exigir conferência dos vínculos.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('remapeamentos', $e->errors());
        }
        $this->executar('arquivar', ['from' => $from, 'remapeamentos' => [['matricula_id' => 1, 'grade' => [], 'retirar' => true]]]);
        $this->assertSame([], app(AcessoHibridoService::class)->grade(3, 1, $from));
        $this->assertEquals(1, DB::table('matriculas')->where('id', 1)->value('status_id'));
        $this->assertTrue((bool) DB::table('matriculas')->where('id', 1)->value('grade_revisao'));
        DB::table('turmas_fixas')->where('id', 1)->update(['vigencia_fim' => AcademyDateTime::now()->modify('-1 day')->format('Y-m-d')]);
        $this->executar('reativar');
        $this->assertNull(DB::table('turmas_fixas')->value('vigencia_fim'));
        $this->assertSame([], app(AcessoHibridoService::class)->grade(3, 1, $from));
    }

    public function test_reserva_pontual_impede_cancelamento_e_lote_e_atomico(): void
    {
        app(AdminTurmaFixaService::class)->gerarAulas(3, 1);
        $aula = DB::table('turmas as t')->join('dias as d', 'd.id', '=', 't.dia_id')->where('d.data', '>=', $this->vigenciaTeste())->first(['t.id', 'd.data']);
        DB::table('aula_reposicoes')->insert(['tenant_id' => 3, 'turma_id' => $aula->id, 'aluno_id' => 11, 'matricula_id' => 11, 'criado_por' => 90]);
        try {
            $this->executar('pausar', ['from' => $this->vigenciaTeste(), 'to' => '2099-01-01']);
            $this->fail('Não deveria apagar uma reposição.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('from', $e->errors());
        }
        $this->assertSame(0, DB::table('turma_fixa_pausas')->count());
        try {
            $this->executar('capacidade', ['capacidade' => 20], [1, 999]);
            $this->fail('Turma inexistente deve impedir lote inteiro.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        $this->assertEquals(12, DB::table('turmas_fixas')->where('id', 1)->value('capacidade'));
        $this->assertSame(0, DB::table('turma_fixa_eventos')->count());
    }

    public function test_duplicar_nao_copia_alunos_excluir_preserva_ocorrencias_e_bloqueia_turma_com_vinculo(): void
    {
        $r = $this->executar('duplicar', ['nome' => 'Cópia', 'dias_semana' => [2], 'horario_inicio' => '18:00', 'horario_fim' => '19:00', 'professor_id' => 2]);
        $id = $r['resultados'][0]['id'];
        $this->assertSame(0, DB::table('matricula_grade')->where('turma_fixa_id', $id)->count());
        $ocorrencias = DB::table('turmas')->where('turma_fixa_id', $id)->count();
        $this->assertGreaterThan(0, $ocorrencias);
        $this->executar('excluir', [], [$id]);
        $this->assertNotNull(DB::table('turmas_fixas')->where('id', $id)->value('deleted_at'));
        $this->assertSame($ocorrencias, DB::table('turmas')->where('turma_fixa_id', $id)->count());
        $this->assertSame(0, DB::table('turmas')->where('turma_fixa_id', $id)->where('ativo', 1)->count());
        $this->expectException(ValidationException::class);
        $this->executar('excluir');
    }

    public function test_detalhe_tenant_e_auditoria(): void
    {
        $r = app(AdminTurmaFixaGestaoService::class)->detalhe(3, 1);
        $this->assertCount(1, $r['alunos']);
        $this->assertSame(3, count($r['alunos'][0]['dias_semana']));
        $this->assertArrayHasKey('vagas', $r['aulas'][0]);
        $this->executar('capacidade', ['capacidade' => 15]);
        $evento = DB::table('turma_fixa_eventos')->first();
        $this->assertEquals(90, $evento->usuario_id);
        $this->assertSame('capacidade', $evento->acao);
        $this->expectException(HttpException::class);
        app(AdminTurmaFixaGestaoService::class)->detalhe(4, 1);
    }

    public function test_listagem_calcula_ocupacao_semanal_por_dia_vinculado(): void
    {
        $turma = app(AdminTurmaFixaService::class)->listar(3)['turmas'][0];

        $this->assertSame(1, $turma->alunos_vinculados);
        $this->assertSame(3, $turma->vagas_ocupadas);
    }

    public function test_professor_preserva_excesso_legado_e_sinalizacao_de_revisao(): void
    {
        DB::table('planos')->where('id', 1)->update(['checkins_semanais' => 1]);
        $this->executar('professor', ['from' => $this->vigenciaTeste(), 'professor_id' => 2]);
        $this->assertCount(3, app(AcessoHibridoService::class)->grade(3, 1, $this->vigenciaTeste()));
        $this->assertTrue((bool) DB::table('matriculas')->where('id', 1)->value('grade_revisao'));
    }

    public function test_falha_de_notificacao_desfaz_operacao_e_auditoria(): void
    {
        $comunicacao = \Mockery::mock(ComunicacaoService::class);
        $comunicacao->shouldReceive('criarComunicacao')->once()->andReturnUsing(function ($tenant, $data, $user) {
            $this->assertSame(3, $tenant);
            $this->assertSame(90, $user);
            $this->assertSame([1], $data['publico']['alunos']);
            $this->assertTrue($data['publicar']);

            return ['status' => 422, 'body' => ['error' => 'Falha de publicação']];
        });
        $this->app->instance(ComunicacaoService::class, $comunicacao);
        try {
            $this->executar('pausar', ['from' => $this->vigenciaTeste(), 'to' => $this->vigenciaTeste(), 'avisar' => true]);
            $this->fail('Publicação deve desfazer pausa.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('avisar', $e->errors());
        }
        $this->assertSame(0, DB::table('turma_fixa_pausas')->count());
        $this->assertSame(0, DB::table('turma_fixa_eventos')->count());
    }

    public function test_cancelamento_manual_durante_pausa_nao_e_restaurado_e_checkin_recusa_suspensao(): void
    {
        app(AdminTurmaFixaService::class)->gerarAulas(3, 1);
        $this->executar('pausar', ['from' => AcademyDateTime::today(), 'to' => '2099-01-01']);
        $aula = HybridFixture::aula();
        $aula['ativo'] = 1;
        $this->assertNotNull(app(AcessoHibridoService::class)->erroCheckin($aula, 3, (array) DB::table('matriculas')->where('id', 1)->first()));
        app(TurmaRepository::class)->desativar(1);
        $this->executar('retomar');
        $this->assertEquals(0, DB::table('turmas')->where('id', 1)->value('ativo'));
        $this->assertNull(DB::table('turmas')->where('id', 1)->value('suspensao_turma_id'));
    }

    public function test_transferencia_sem_vaga_desfaz_arquivamento_e_grade(): void
    {
        $admin = app(AdminTurmaFixaService::class);
        $destino = $admin->criar(3, ['nome' => 'Destino cheio', 'professor_id' => 2, 'modalidade_id' => 1,
            'dias_semana' => [2], 'horario_inicio' => '18:00', 'horario_fim' => '19:00', 'capacidade' => 1]);
        DB::table('matricula_grade')->insert(['tenant_id' => 3, 'matricula_id' => 2, 'turma_fixa_id' => $destino['id'],
            'dia_semana' => 2, 'vigencia_inicio' => AcademyDateTime::today()]);
        try {
            $this->executar('arquivar', ['from' => $this->vigenciaTeste(), 'remapeamentos' => [['matricula_id' => 1,
                'grade' => [['turma_fixa_id' => $destino['id'], 'dia_semana' => 2]]]]]);
            $this->fail('Destino cheio não pode receber transferência.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('remapeamentos', $e->errors());
        }
        $this->assertNull(DB::table('turmas_fixas')->where('id', 1)->value('vigencia_fim'));
        $this->assertCount(3, app(AcessoHibridoService::class)->grade(3, 1, $this->vigenciaTeste()));
        $this->assertSame(0, DB::table('turma_fixa_eventos')->count());
    }

    public function test_arquivamento_em_lote_contem_remapeamento_individual_de_cada_turma(): void
    {
        DB::table('matricula_grade')->where('dia_semana', '<>', 1)->delete();
        $destino = $this->executar('duplicar', ['nome' => 'Outra turma', 'dias_semana' => [2],
            'horario_inicio' => '18:00', 'horario_fim' => '19:00', 'professor_id' => 2])['resultados'][0]['id'];
        DB::table('matricula_grade')->insert(['tenant_id' => 3, 'matricula_id' => 2, 'turma_fixa_id' => $destino,
            'dia_semana' => 2, 'vigencia_inicio' => AcademyDateTime::today()]);
        $this->executar('arquivar', ['por_turma' => [
            ['id' => 1, 'dados' => ['from' => $this->vigenciaTeste(), 'remapeamentos' => [['matricula_id' => 1, 'grade' => [], 'retirar' => true]]]],
            ['id' => $destino, 'dados' => ['from' => $this->vigenciaTeste(), 'remapeamentos' => [['matricula_id' => 2, 'grade' => [], 'retirar' => true]]]],
        ]], [1, $destino]);
        $this->assertSame([], app(AcessoHibridoService::class)->grade(3, 1, $this->vigenciaTeste()));
        $this->assertSame([], app(AcessoHibridoService::class)->grade(3, 2, $this->vigenciaTeste()));
        $this->assertSame(3, DB::table('turma_fixa_eventos')->count());
    }

    public function test_frequencia_tambem_e_validada_nos_periodos_futuros_de_outras_turmas(): void
    {
        DB::table('matricula_grade')->where('dia_semana', '<>', 1)->delete();
        DB::table('planos')->where('id', 1)->update(['checkins_semanais' => 1]);
        $outra = $this->executar('duplicar', ['nome' => 'Outro período', 'dias_semana' => [4],
            'horario_inicio' => '18:00', 'horario_fim' => '19:00', 'professor_id' => 2])['resultados'][0]['id'];
        DB::table('matricula_grade')->insert(['tenant_id' => 3, 'matricula_id' => 1, 'turma_fixa_id' => $outra,
            'dia_semana' => 4, 'vigencia_inicio' => AcademyDateTime::now()->modify('+30 days')->format('Y-m-d')]);
        try {
            $this->executar('grade', ['from' => $this->vigenciaTeste(), 'dias_semana' => [2], 'horario_inicio' => '18:00',
                'horario_fim' => '19:00', 'professor_id' => 2, 'remapeamentos' => [['matricula_id' => 1, 'dias_semana' => [2]]]]);
            $this->fail('Excesso de frequência futuro deve bloquear operação.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('grade', $e->errors());
        }
        $this->assertNull(DB::table('turmas_fixas')->where('id', 1)->value('substituida_por_id'));
    }

    public function test_aviso_real_publicado_so_para_aluno_afetado(): void
    {
        Schema::create('usuarios', function (Blueprint $t) {
            $t->integer('id')->primary();
            $t->string('nome');
            $t->boolean('ativo')->default(true);
        });
        Schema::create('tenant_usuario_papel', function (Blueprint $t) {
            $t->integer('tenant_id');
            $t->integer('usuario_id');
            $t->integer('papel_id');
            $t->boolean('ativo')->default(true);
        });
        DB::table('usuarios')->insert([['id' => 1, 'nome' => 'Aluno fictício'], ['id' => 90, 'nome' => 'Operador fictício']]);
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => 3, 'usuario_id' => 1, 'papel_id' => 1]);
        (require database_path('migrations/2026_10_27_120000_central_comunicacao.php'))->up();
        $this->executar('pausar', ['from' => $this->vigenciaTeste(), 'to' => $this->vigenciaTeste(), 'avisar' => true]);
        $this->assertSame('publicada', DB::table('comunicacoes')->value('status'));
        $this->assertSame('notificacao', DB::table('comunicacoes')->value('tipo'));
        $this->assertEquals(1, DB::table('comunicacao_destinatarios')->value('aluno_id'));
        $this->assertSame(1, DB::table('comunicacao_destinatarios')->count());
    }

    public function test_rotas_novas_exigem_login_permissao_e_tenant_e_releem_operacao(): void
    {
        $this->getJson('/v2/admin/turmas-fixas/1')->assertUnauthorized();
        $this->postJson('/v2/admin/turmas-fixas/acoes', [])->assertUnauthorized();
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);
        $usuarios = \Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')->with(5)->andReturn([
            'id' => 5, 'nome' => 'Admin fictício', 'email' => 'admin@example.invalid', 'tenant_id' => 3, 'papel_id' => 3,
        ]);
        $this->app->instance(UsuarioRepository::class, $usuarios);
        $headers = ['Authorization' => 'Bearer '.app(JwtService::class)->encode(['user_id' => 5, 'tenant_id' => 3])];
        $this->postJson('/v2/admin/turmas-fixas/acoes', ['acao' => 'capacidade', 'ids' => [1], 'dados' => ['capacidade' => 15]], $headers)->assertOk();
        $this->getJson('/v2/admin/turmas-fixas/1', $headers)->assertOk()->assertJsonPath('turma.capacidade', 15)->assertJsonPath('historico.0.usuario_id', 5);
        $this->getJson('/v2/admin/turmas-fixas/999', $headers)->assertNotFound();
        $this->getJson('/v2/admin/turmas-fixas/1?desde[]=invalid', $headers)->assertUnprocessable();
        $this->assertSame(Permissao::AULAS_GERENCIAR, PermissaoRotas::exigida('POST', '/v2/admin/turmas-fixas/acoes'));
    }

    public function test_grade_futura_alem_do_horizonte_recusa_sobreposicao_de_professor(): void
    {
        DB::table('matricula_grade')->delete();
        $from = AcademyDateTime::now()->modify('+120 days')->format('Y-m-d');
        $this->executar('professor', ['from' => $from, 'professor_id' => 2]);
        $outra = app(AdminTurmaFixaService::class)->criar(3, ['nome' => 'Outra turma', 'professor_id' => 1, 'modalidade_id' => 1,
            'dias_semana' => [2], 'horario_inicio' => '18:00', 'horario_fim' => '19:00', 'capacidade' => 12]);
        try {
            $this->executar('grade', ['from' => $from, 'professor_id' => 2, 'dias_semana' => [1, 3, 5],
                'horario_inicio' => '13:00', 'horario_fim' => '14:00', 'remapeamentos' => []], [$outra['id']]);
            $this->fail('Segundo vínculo no mesmo professor/dia/horário deve falhar mesmo sem aulas geradas.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('professor_id', $e->errors());
        }
        $this->assertSame(3, DB::table('turmas_fixas')->count());
    }

    public function test_nova_versao_preserva_cancelamento_manual_e_regras_da_ocorrencia_inalterada(): void
    {
        app(AdminTurmaFixaService::class)->gerarAulas(3, 1);
        $aula = DB::table('turmas as t')->join('dias as d', 'd.id', '=', 't.dia_id')->where('t.turma_fixa_id', 1)
            ->where('d.data', '>=', $this->vigenciaTeste())->orderBy('d.data')->first(['t.*']);
        DB::table('turmas')->where('id', $aula->id)->update(['ativo' => 0, 'checkin_livre' => false, 'limite_alunos' => 9,
            'tolerancia_antes_checkin_minutos' => 30]);
        $this->executar('professor', ['from' => $this->vigenciaTeste(), 'professor_id' => 2]);
        $novoId = DB::table('turmas_fixas')->where('id', 1)->value('substituida_por_id');
        $nova = DB::table('turmas')->where('turma_fixa_id', $novoId)->where('dia_id', $aula->dia_id)->first();
        $this->assertEquals(0, $nova->ativo);
        $this->assertEquals(0, $nova->checkin_livre);
        $this->assertEquals(9, $nova->limite_alunos);
        $this->assertEquals(30, $nova->tolerancia_antes_checkin_minutos);
        $this->assertNull($nova->suspensao_turma_id);
        DB::table('turmas')->where('id', $nova->id)->update(['limite_alunos' => 10]);
        app(AdminTurmaFixaService::class)->gerarAulas(3, (int) $novoId);
        $this->assertEquals(10, DB::table('turmas')->where('id', $nova->id)->value('limite_alunos'));
    }
}
