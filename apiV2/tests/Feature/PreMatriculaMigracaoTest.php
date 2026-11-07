<?php

namespace Tests\Feature;

use App\Services\Admin\AdminMatriculaService;
use App\Services\PreMatriculaService;
use App\Support\Papel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class PreMatriculaMigracaoTest extends TestCase
{
    use RequiresSqlite;

    private PreMatriculaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();
        $this->service = app(PreMatriculaService::class);

        Schema::create('tenants', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nome');
        });
        Schema::create('usuarios', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nome');
            $table->string('email');
            $table->string('cpf')->nullable();
            $table->boolean('ativo')->default(true);
        });
        Schema::create('tenant_usuario_papel', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('usuario_id');
            $table->unsignedInteger('papel_id');
            $table->boolean('ativo')->default(true);
        });
        Schema::create('alunos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('usuario_id');
        });
        Schema::create('status_matricula', function (Blueprint $table) {
            $table->increments('id');
            $table->string('codigo');
        });
        Schema::create('matriculas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('aluno_id');
            $table->unsignedInteger('status_id');
        });
        Schema::create('modalidades', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nome');
        });
        Schema::create('planos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('modalidade_id');
            $table->string('descricao')->nullable();
            $table->decimal('valor', 10, 2);
            $table->unsignedTinyInteger('checkins_semanais');
            $table->boolean('ativo')->default(true);
        });
        Schema::create('turmas_fixas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('modalidade_id');
            $table->string('nome');
            $table->json('dias_semana');
            $table->time('horario_inicio');
            $table->time('horario_fim');
            $table->boolean('ativo')->default(true);
            $table->date('vigencia_inicio');
            $table->date('vigencia_fim')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        (require database_path('migrations/2026_11_06_190000_pre_matriculas_migracao.php'))->up();
        (require database_path('migrations/2026_11_06_213500_add_matricula_id_to_pre_matriculas.php'))->up();

        DB::table('tenants')->insert(['id' => 1, 'nome' => 'Academia']);
        DB::table('usuarios')->insert(['id' => 1, 'nome' => 'Maria Silva', 'email' => 'maria@example.com', 'cpf' => '123']);
        DB::table('tenant_usuario_papel')->insert([
            'tenant_id' => 1, 'usuario_id' => 1, 'papel_id' => Papel::ALUNO, 'ativo' => true,
        ]);
        DB::table('alunos')->insert(['id' => 1, 'usuario_id' => 1]);
        DB::table('status_matricula')->insert(['id' => 1, 'codigo' => 'ativa']);
        DB::table('modalidades')->insert(['id' => 1, 'nome' => 'Yoga']);
        DB::table('planos')->insert([
            'id' => 1, 'tenant_id' => 1, 'modalidade_id' => 1, 'descricao' => 'FREE - Yoga',
            'valor' => 0, 'checkins_semanais' => 2, 'ativo' => true,
        ]);
        DB::table('turmas_fixas')->insert([
            ['id' => 1, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => 'Yoga manhã', 'dias_semana' => '[1,3]', 'horario_inicio' => '08:00', 'horario_fim' => '09:00', 'ativo' => true, 'vigencia_inicio' => now()->toDateString()],
            ['id' => 2, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => 'Yoga noite', 'dias_semana' => '[3,5]', 'horario_inicio' => '18:00', 'horario_fim' => '19:00', 'ativo' => true, 'vigencia_inicio' => now()->toDateString()],
        ]);
        $this->service->salvarConfiguracao([
            'tenant_id' => 1, 'ativa' => true, 'obrigatoria' => false, 'publico' => 'sem_matricula',
        ], 99);
    }

    public function test_aluno_ve_apenas_planos_gratuitos_sem_preco_ou_token_free(): void
    {
        $resultado = $this->service->paraAluno(1, 1);

        $this->assertCount(1, $resultado['planos']);
        $this->assertSame('Yoga', $resultado['planos'][0]['descricao']);
        $this->assertArrayNotHasKey('valor', $resultado['planos'][0]);
        $this->assertCount(2, $resultado['turmas']);

        DB::table('planos')->insert([
            'id' => 2, 'tenant_id' => 1, 'modalidade_id' => 1, 'descricao' => 'Pago',
            'valor' => 50, 'checkins_semanais' => 2, 'ativo' => true,
        ]);
        $this->assertCount(1, $this->service->paraAluno(1, 1)['planos']);
    }

    public function test_exibe_todos_os_planos_gratuitos_mesmo_quando_nao_ha_dias_para_a_frequencia(): void
    {
        DB::table('turmas_fixas')->where('id', 2)->update(['dias_semana' => '[1]']);
        DB::table('planos')->insert([
            ['id' => 3, 'tenant_id' => 1, 'modalidade_id' => 1, 'descricao' => '1x por Semana FREE',
                'valor' => 0, 'checkins_semanais' => 1, 'ativo' => true],
            ['id' => 4, 'tenant_id' => 1, 'modalidade_id' => 1, 'descricao' => '3x por Semana FREE',
            'valor' => 0, 'checkins_semanais' => 3, 'ativo' => true,
            ],
        ]);

        $planos = $this->service->paraAluno(1, 1)['planos'];

        $this->assertCount(3, $planos);
        $this->assertSame([1, 2, 3], array_column($planos, 'checkins_semanais'));
        $this->assertSame(2, $planos[2]['dias_disponiveis']);
    }

    public function test_publico_da_configuracao_e_persistido_e_aplicado_ao_aluno(): void
    {
        $this->service->salvarConfiguracao([
            'tenant_id' => 1, 'ativa' => true, 'obrigatoria' => true, 'publico' => 'ambos',
        ], 99);
        $this->assertSame('ambos', $this->service->painel(1)['configuracao']['publico']);
        $this->assertTrue($this->service->paraAluno(1, 1)['disponivel']);

        $this->service->salvarConfiguracao([
            'tenant_id' => 1, 'ativa' => true, 'obrigatoria' => true, 'publico' => 'com_matricula',
        ], 99);
        $this->assertSame('com_matricula', $this->service->painel(1)['configuracao']['publico']);
        $this->assertFalse($this->service->paraAluno(1, 1)['disponivel']);

        DB::table('matriculas')->insert(['tenant_id' => 1, 'aluno_id' => 1, 'status_id' => 1]);
        $this->assertTrue($this->service->paraAluno(1, 1)['disponivel']);

        $this->service->salvarConfiguracao([
            'tenant_id' => 1, 'ativa' => true, 'obrigatoria' => true, 'publico' => 'sem_matricula',
        ], 99);
        $this->assertSame('sem_matricula', $this->service->painel(1)['configuracao']['publico']);
        $this->assertFalse($this->service->paraAluno(1, 1)['disponivel']);

        DB::table('matriculas')->delete();
        $this->assertTrue($this->service->paraAluno(1, 1)['disponivel']);
    }

    public function test_admin_pode_ajustar_plano_grade_e_vencimento_antes_de_aprovar(): void
    {
        DB::table('planos')->insert([
            'id' => 3, 'tenant_id' => 1, 'modalidade_id' => 1, 'descricao' => 'Plano revisado FREE',
            'valor' => 0, 'checkins_semanais' => 1, 'ativo' => true,
        ]);
        $envio = $this->service->enviar(1, 1, [
            'plano_id' => 1,
            'grade' => [
                ['turma_fixa_id' => 1, 'dia_semana' => 1],
                ['turma_fixa_id' => 2, 'dia_semana' => 5],
            ],
        ]);

        $resultado = $this->service->atualizarSolicitacao(1, $envio['id'], [
            'plano_id' => 3,
            'grade' => [['turma_fixa_id' => 1, 'dia_semana' => 3]],
            'dia_vencimento' => 15,
        ]);

        $solicitacao = $this->service->painelAcademia(1)['solicitacoes'][0];
        $this->assertSame('Pré-matrícula ajustada. O pedido continua aguardando aprovação.', $resultado['message']);
        $this->assertSame(3, $solicitacao['plano_id']);
        $this->assertSame('Plano revisado', $solicitacao['plano_descricao']);
        $this->assertSame(1, $solicitacao['checkins_semanais']);
        $this->assertSame(3, $solicitacao['grade'][0]['dia_semana']);
        $this->assertSame(15, $solicitacao['dia_vencimento']);
        $this->assertSame('pendente', $solicitacao['status']);
        $this->assertSame(0, DB::table('matriculas')->count());
    }

    public function test_envio_exige_dias_distintos_e_aprovacao_cria_matricula_com_os_dados_revisados(): void
    {
        try {
            $this->service->enviar(1, 1, [
                'plano_id' => 1,
                'grade' => [
                    ['turma_fixa_id' => 1, 'dia_semana' => 1],
                    ['turma_fixa_id' => 2, 'dia_semana' => 1],
                ],
            ]);
            $this->fail('A solicitação não deve aceitar dias duplicados.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('grade', $exception->errors());
        }

        $envio = $this->service->enviar(1, 1, [
            'plano_id' => 1,
            'grade' => [
                ['turma_fixa_id' => 1, 'dia_semana' => 1],
                ['turma_fixa_id' => 2, 'dia_semana' => 5],
            ],
        ]);
        $this->assertSame(0, DB::table('matriculas')->count());
        $this->assertNull(DB::table('pre_matriculas')->where('id', $envio['id'])->value('dia_vencimento'));

        DB::table('planos')->where('id', 1)->update(['descricao' => 'Descrição atualizada']);
        DB::table('turmas_fixas')->where('id', 1)->update(['nome' => 'Nome atualizado']);
        $solicitacao = $this->service->painel(1)['solicitacoes'][0];
        $this->assertSame('Yoga', $solicitacao['plano_descricao']);
        $this->assertSame('Yoga manhã', $solicitacao['grade'][0]['turma_nome']);

        $matriculaService = \Mockery::mock(AdminMatriculaService::class);
        $matriculaService->shouldReceive('criar')->once()
            ->with(1, 99, \Mockery::on(function (array $dados): bool {
                $this->assertSame(1, $dados['usuario_id']);
                $this->assertSame(1, $dados['plano_id']);
                $this->assertSame('turma_fixa', $dados['tipo_acesso']);
                $this->assertNull($dados['dia_vencimento']);
                $this->assertSame([
                    ['turma_fixa_id' => 1, 'dia_semana' => 1],
                    ['turma_fixa_id' => 2, 'dia_semana' => 5],
                ], $dados['grade']);

                return true;
            }))
            ->andReturnUsing(function (int $tenantId, ?int $adminId, array $dados): array {
                $this->assertSame(1, $tenantId);
                $this->assertSame(99, $adminId);
                $id = (int) DB::table('matriculas')->insertGetId([
                    'tenant_id' => 1,
                    'aluno_id' => 1,
                    'status_id' => 1,
                ]);

                return ['status' => 201, 'body' => ['matricula' => ['id' => $id]]];
            });
        $this->app->instance(AdminMatriculaService::class, $matriculaService);

        $resultado = $this->service->avaliar(1, $envio['id'], 99, 'aprovada');

        $this->assertSame('Pré-matrícula aprovada e matrícula criada.', $resultado['message']);
        $this->assertSame(1, $resultado['matricula_id']);
        $this->assertSame(1, DB::table('matriculas')->count());
        $this->assertSame(1, DB::table('pre_matriculas')->where('id', $envio['id'])->value('matricula_id'));
        $this->assertSame('aprovada', $this->service->paraAluno(1, 1)['status']);
        $this->assertSame(1, $this->service->paraAluno(1, 1)['matricula_id']);
    }

    public function test_falha_ao_criar_matricula_mantem_solicitacao_pendente(): void
    {
        $envio = $this->service->enviar(1, 1, [
            'plano_id' => 1,
            'grade' => [
                ['turma_fixa_id' => 1, 'dia_semana' => 1],
                ['turma_fixa_id' => 2, 'dia_semana' => 5],
            ],
        ]);
        $matriculaService = \Mockery::mock(AdminMatriculaService::class);
        $matriculaService->shouldReceive('criar')->once()->andReturn([
            'status' => 409,
            'body' => ['message' => 'Não há vaga disponível.'],
        ]);
        $this->app->instance(AdminMatriculaService::class, $matriculaService);

        try {
            $this->service->avaliar(1, $envio['id'], 99, 'aprovada');
            $this->fail('A aprovação deve falhar se a matrícula não puder ser criada.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame('Não há vaga disponível.', $exception->getMessage());
        }

        $this->assertSame('pendente', DB::table('pre_matriculas')->where('id', $envio['id'])->value('status'));
        $this->assertNull(DB::table('pre_matriculas')->where('id', $envio['id'])->value('matricula_id'));
        $this->assertSame(0, DB::table('matriculas')->count());
    }

    public function test_reprovacao_nao_cria_matricula(): void
    {
        $envio = $this->service->enviar(1, 1, [
            'plano_id' => 1,
            'grade' => [
                ['turma_fixa_id' => 1, 'dia_semana' => 1],
                ['turma_fixa_id' => 2, 'dia_semana' => 5],
            ],
        ]);
        $matriculaService = \Mockery::mock(AdminMatriculaService::class);
        $matriculaService->shouldNotReceive('criar');
        $this->app->instance(AdminMatriculaService::class, $matriculaService);

        $resultado = $this->service->avaliar(1, $envio['id'], 99, 'reprovada');

        $this->assertSame('Pré-matrícula reprovada.', $resultado['message']);
        $this->assertNull($resultado['matricula_id']);
        $this->assertSame('reprovada', DB::table('pre_matriculas')->where('id', $envio['id'])->value('status'));
        $this->assertNull(DB::table('pre_matriculas')->where('id', $envio['id'])->value('matricula_id'));
        $this->assertSame(0, DB::table('matriculas')->count());
    }

    public function test_ambiente_sem_a_coluna_matricula_id_continua_funcionando(): void
    {
        $envio = $this->service->enviar(1, 1, [
            'plano_id' => 1,
            'grade' => [
                ['turma_fixa_id' => 1, 'dia_semana' => 1],
                ['turma_fixa_id' => 2, 'dia_semana' => 5],
            ],
        ]);
        Schema::table('pre_matriculas', fn (Blueprint $table) => $table->dropColumn('matricula_id'));

        $this->assertSame('pendente', $this->service->paraAluno(1, 1)['status']);
        $this->assertNull($this->service->painelAcademia(1)['solicitacoes'][0]['matricula_id']);
        $this->service->avaliar(1, $envio['id'], 99, 'reprovada');
        $this->assertSame('reprovada', DB::table('pre_matriculas')->where('id', $envio['id'])->value('status'));
    }

    public function test_sem_tabelas_o_app_nao_recebe_erro_e_o_painel_recebe_503_legivel(): void
    {
        Schema::drop('pre_matriculas');
        Schema::drop('pre_matricula_configuracoes');

        $this->assertNull($this->service->paraAluno(1, 1));
        try {
            $this->service->painelAcademia(1);
            $this->fail('O painel deve informar que a atualização do banco está pendente.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
            $this->assertStringContainsString('atualização do banco de dados está pendente', $exception->getMessage());
        }
    }

    public function test_configuracao_preserva_data_de_criacao(): void
    {
        DB::table('pre_matricula_configuracoes')->where('tenant_id', 1)->update(['created_at' => '2025-01-02 03:04:05']);

        $this->service->salvarConfiguracao([
            'tenant_id' => 1, 'ativa' => false, 'obrigatoria' => true, 'publico' => 'ambos',
        ], 99);

        $this->assertSame('2025-01-02 03:04:05', DB::table('pre_matricula_configuracoes')->value('created_at'));
    }

    public function test_excluir_configuracao_desativa_novos_pedidos_e_preserva_os_existentes(): void
    {
        $envio = $this->service->enviar(1, 1, [
            'plano_id' => 1,
            'grade' => [
                ['turma_fixa_id' => 1, 'dia_semana' => 1],
                ['turma_fixa_id' => 2, 'dia_semana' => 5],
            ],
        ]);

        $configuracao = $this->service->excluirConfiguracao(1);

        $this->assertFalse($configuracao['configurada']);
        $this->assertNull($this->service->paraAluno(1, 1));
        $this->assertSame(1, DB::table('pre_matriculas')->where('id', $envio['id'])->count());
        $this->assertSame(1, count($this->service->painel(1)['solicitacoes']));
    }
}
