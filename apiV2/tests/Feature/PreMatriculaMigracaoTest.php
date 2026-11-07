<?php

namespace Tests\Feature;

use App\Services\Admin\AdminMatriculaService;
use App\Services\PreMatriculaService;
use App\Support\AcademyDateTime;
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
            $table->unsignedInteger('responsavel_usuario_id')->nullable();
        });
        Schema::create('tenant_usuario_papel', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('usuario_id');
            $table->unsignedInteger('papel_id');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
        Schema::create('alunos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('usuario_id');
            $table->string('nome')->nullable();
            $table->boolean('ativo')->default(true);
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
            $table->unsignedInteger('plano_id')->nullable();
            $table->date('data_vencimento')->nullable();
            $table->date('proxima_data_vencimento')->nullable();
        });
        Schema::create('modalidades', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nome');
        });
        Schema::create('planos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('modalidade_id');
            $table->string('nome')->nullable();
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
        (require database_path('migrations/2026_11_06_230000_pre_matricula_planos_e_exclusao.php'))->up();
        (require database_path('migrations/2026_11_07_000000_pre_matriculas_enviada_por.php'))->up();
        (require database_path('migrations/2026_11_07_010000_pre_matricula_contagem_gratuito.php'))->up();

        DB::table('tenants')->insert(['id' => 1, 'nome' => 'Academia']);
        DB::table('usuarios')->insert(['id' => 1, 'nome' => 'Maria Silva', 'email' => 'maria@example.com', 'cpf' => '123']);
        DB::table('tenant_usuario_papel')->insert([
            'tenant_id' => 1, 'usuario_id' => 1, 'papel_id' => Papel::ALUNO, 'ativo' => true,
        ]);
        DB::table('alunos')->insert(['id' => 1, 'usuario_id' => 1]);
        DB::table('status_matricula')->insert([['id' => 1, 'codigo' => 'ativa'], ['id' => 2, 'codigo' => 'cancelada']]);
        DB::table('modalidades')->insert(['id' => 1, 'nome' => 'Yoga']);
        DB::table('planos')->insert([
            'id' => 1, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => 'Yoga 2x FREE', 'descricao' => 'FREE - Yoga',
            'valor' => 0, 'checkins_semanais' => 2, 'ativo' => true,
        ]);
        DB::table('planos')->insert([
            'id' => 10, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => 'Yoga 2x semana',
            'valor' => 150, 'checkins_semanais' => 2, 'ativo' => true,
        ]);
        DB::table('turmas_fixas')->insert([
            ['id' => 1, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => 'Yoga manhã', 'dias_semana' => '[1,3]', 'horario_inicio' => '08:00', 'horario_fim' => '09:00', 'ativo' => true, 'vigencia_inicio' => AcademyDateTime::today()],
            ['id' => 2, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => 'Yoga noite', 'dias_semana' => '[3,5]', 'horario_inicio' => '18:00', 'horario_fim' => '19:00', 'ativo' => true, 'vigencia_inicio' => AcademyDateTime::today()],
        ]);
        $this->service->salvarConfiguracao([
            'tenant_id' => 1, 'ativa' => true, 'obrigatoria' => false, 'publico' => 'sem_matricula',
            'pares' => [['plano_free_id' => 1, 'plano_pago_id' => 10]],
        ], 99);
    }

    /** @return array{id: int} */
    private function enviarPadrao(int $usuarioId = 1): array
    {
        return $this->service->enviar(1, $usuarioId, [
            'plano_id' => 1,
            'dia_vencimento' => 10,
            'grade' => [
                ['turma_fixa_id' => 1, 'dia_semana' => 1],
                ['turma_fixa_id' => 2, 'dia_semana' => 5],
            ],
        ]);
    }

    /** Simula a criação da matrícula pelo serviço administrativo, devolvendo a data de vencimento informada. */
    private function simularMatricula(string $vencimento, ?int &$matriculaId = null): void
    {
        $mock = \Mockery::mock(AdminMatriculaService::class);
        $mock->shouldReceive('criar')->andReturnUsing(function () use ($vencimento, &$matriculaId): array {
            $matriculaId = (int) DB::table('matriculas')->insertGetId([
                'tenant_id' => 1, 'aluno_id' => 1, 'status_id' => 1, 'plano_id' => 1,
                'data_vencimento' => $vencimento, 'proxima_data_vencimento' => $vencimento,
            ]);

            return ['status' => 201, 'body' => ['matricula' => ['id' => $matriculaId]]];
        });
        $this->app->instance(AdminMatriculaService::class, $mock);
    }

    public function test_aluno_ve_apenas_planos_gratuitos_sem_preco_ou_token_free(): void
    {
        $resultado = $this->service->paraAluno(1, 1);

        $this->assertCount(1, $resultado['planos']);
        $this->assertSame('Yoga', $resultado['planos'][0]['descricao']);
        $this->assertArrayNotHasKey('valor', $resultado['planos'][0]);
        $this->assertArrayNotHasKey('plano_pago_id', $resultado['planos'][0]);
        $this->assertCount(2, $resultado['turmas']);

        DB::table('planos')->insert([
            'id' => 2, 'tenant_id' => 1, 'modalidade_id' => 1, 'descricao' => 'Pago',
            'valor' => 50, 'checkins_semanais' => 2, 'ativo' => true,
        ]);
        $this->assertCount(1, $this->service->paraAluno(1, 1)['planos']);
    }

    public function test_exibe_todos_os_planos_vinculados_mesmo_quando_nao_ha_dias_para_a_frequencia(): void
    {
        DB::table('turmas_fixas')->where('id', 2)->update(['dias_semana' => '[1]']);
        DB::table('planos')->insert([
            ['id' => 3, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => null, 'descricao' => '1x por Semana FREE',
                'valor' => 0, 'checkins_semanais' => 1, 'ativo' => true],
            ['id' => 4, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => null, 'descricao' => '3x por Semana FREE',
                'valor' => 0, 'checkins_semanais' => 3, 'ativo' => true],
            ['id' => 11, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => 'Yoga 1x', 'descricao' => null, 'valor' => 90, 'checkins_semanais' => 1, 'ativo' => true],
            ['id' => 12, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => 'Yoga 3x', 'descricao' => null, 'valor' => 200, 'checkins_semanais' => 3, 'ativo' => true],
        ]);
        $this->service->salvarConfiguracao([
            'tenant_id' => 1, 'ativa' => true, 'obrigatoria' => false, 'publico' => 'sem_matricula',
            'pares' => [
                ['plano_free_id' => 1, 'plano_pago_id' => 10],
                ['plano_free_id' => 3, 'plano_pago_id' => 11],
                ['plano_free_id' => 4, 'plano_pago_id' => 12],
            ],
        ], 99);

        $planos = $this->service->paraAluno(1, 1)['planos'];

        $this->assertCount(3, $planos);
        $this->assertSame([1, 2, 3], array_column($planos, 'checkins_semanais'));
        $this->assertSame(2, $planos[2]['dias_disponiveis']);
    }

    public function test_plano_gratuito_sem_vinculo_nao_e_oferecido(): void
    {
        DB::table('planos')->insert([
            'id' => 3, 'tenant_id' => 1, 'modalidade_id' => 1, 'descricao' => 'Sem par FREE',
            'valor' => 0, 'checkins_semanais' => 1, 'ativo' => true,
        ]);

        $this->assertSame([1], array_column($this->service->paraAluno(1, 1)['planos'], 'id'));
        try {
            $this->service->enviar(1, 1, ['plano_id' => 3, 'dia_vencimento' => 10, 'grade' => [['turma_fixa_id' => 1, 'dia_semana' => 1]]]);
            $this->fail('Plano sem vínculo não pode receber pedidos.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('plano_id', $exception->errors());
        }
    }

    public function test_pares_exigem_mesma_academia_modalidade_frequencia_e_planos_validos(): void
    {
        DB::table('modalidades')->insert(['id' => 2, 'nome' => 'Pilates']);
        DB::table('tenants')->insert(['id' => 2, 'nome' => 'Outra']);
        DB::table('planos')->insert([
            ['id' => 20, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => 'Yoga 1x', 'valor' => 90, 'checkins_semanais' => 1, 'ativo' => true],
            ['id' => 21, 'tenant_id' => 1, 'modalidade_id' => 2, 'nome' => 'Pilates 2x', 'valor' => 90, 'checkins_semanais' => 2, 'ativo' => true],
            ['id' => 22, 'tenant_id' => 2, 'modalidade_id' => 1, 'nome' => 'Outra academia', 'valor' => 90, 'checkins_semanais' => 2, 'ativo' => true],
            ['id' => 23, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => 'Pago inativo', 'valor' => 90, 'checkins_semanais' => 2, 'ativo' => false],
        ]);
        $base = ['tenant_id' => 1, 'ativa' => true, 'obrigatoria' => false, 'publico' => 'sem_matricula'];

        foreach ([[20], [21], [22], [23], [1]] as [$pagoId]) {
            try {
                $this->service->salvarConfiguracao($base + ['pares' => [['plano_free_id' => 1, 'plano_pago_id' => $pagoId]]], 99);
                $this->fail("O plano pago {$pagoId} não deveria ser aceito.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('pares', $exception->errors());
            }
        }
        $this->assertSame(10, $this->service->painel(1)['configuracao']['pares'][0]['plano_pago_id']);
    }

    public function test_ativacao_exige_vinculo_valido_e_grade_suficiente(): void
    {
        $base = ['tenant_id' => 1, 'ativa' => true, 'obrigatoria' => false, 'publico' => 'sem_matricula'];
        try {
            $this->service->salvarConfiguracao($base + ['pares' => []], 99);
            $this->fail('Sem vínculo a pré-matrícula não pode ser ativada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ativa', $exception->errors());
        }

        DB::table('planos')->insert([
            ['id' => 3, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => 'Yoga 4x FREE', 'valor' => 0, 'checkins_semanais' => 4, 'ativo' => true],
            ['id' => 13, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => 'Yoga 4x', 'valor' => 250, 'checkins_semanais' => 4, 'ativo' => true],
        ]);
        try {
            $this->service->salvarConfiguracao($base + ['pares' => [['plano_free_id' => 3, 'plano_pago_id' => 13]]], 99);
            $this->fail('Grade insuficiente (3 dias para 4x) não pode ativar.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ativa', $exception->errors());
        }
        $this->assertCount(1, $this->service->painel(1)['configuracao']['pares']);
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
            ['id' => 3, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => null, 'descricao' => 'Plano revisado FREE',
                'valor' => 0, 'checkins_semanais' => 1, 'ativo' => true],
            ['id' => 11, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => 'Yoga 1x', 'descricao' => null, 'valor' => 90, 'checkins_semanais' => 1, 'ativo' => true],
        ]);
        $this->service->salvarConfiguracao([
            'tenant_id' => 1, 'ativa' => true, 'obrigatoria' => false, 'publico' => 'sem_matricula',
            'pares' => [['plano_free_id' => 1, 'plano_pago_id' => 10], ['plano_free_id' => 3, 'plano_pago_id' => 11]],
        ], 99);
        $envio = $this->service->enviar(1, 1, [
            'plano_id' => 1,
            'dia_vencimento' => 10,
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
        $this->assertSame(11, $solicitacao['plano_destino_id']);
        $this->assertSame('pendente', $solicitacao['status']);
        $this->assertSame(0, DB::table('matriculas')->count());
    }

    public function test_envio_exige_dias_distintos_e_aprovacao_cria_matricula_com_os_dados_revisados(): void
    {
        try {
            $this->service->enviar(1, 1, [
                'plano_id' => 1,
                'dia_vencimento' => 10,
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
            'dia_vencimento' => 10,
            'grade' => [
                ['turma_fixa_id' => 1, 'dia_semana' => 1],
                ['turma_fixa_id' => 2, 'dia_semana' => 5],
            ],
        ]);
        $this->assertSame(0, DB::table('matriculas')->count());
        $this->assertSame(10, (int) DB::table('pre_matriculas')->where('id', $envio['id'])->value('dia_vencimento'));

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
                $this->assertSame(10, $dados['dia_vencimento']);
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
        $this->assertSame(10, DB::table('matriculas')->where('id', 1)->value('plano_destino_id'));
        $this->assertSame(10, DB::table('pre_matriculas')->where('id', $envio['id'])->value('plano_destino_id'));
        $this->assertSame('aprovada', $this->service->paraAluno(1, 1)['status']);
        $this->assertSame(1, $this->service->paraAluno(1, 1)['matricula_id']);
    }

    public function test_falha_ao_criar_matricula_mantem_solicitacao_pendente(): void
    {
        $envio = $this->service->enviar(1, 1, [
            'plano_id' => 1,
            'dia_vencimento' => 10,
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
            'dia_vencimento' => 10,
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

    public function test_esquema_incompleto_deixa_o_recurso_indisponivel_sem_erro_interno(): void
    {
        $this->enviarPadrao();
        Schema::table('pre_matriculas', function (Blueprint $table) {
            $table->dropIndex('pre_matriculas_matricula_id_index');
            $table->dropColumn('matricula_id');
        });

        $this->assertNull($this->service->paraAluno(1, 1));
        try {
            $this->service->painelAcademia(1);
            $this->fail('Com o esquema incompleto o painel deve responder 503.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }
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

    public function test_aprovar_duas_vezes_nao_cria_matricula_duplicada(): void
    {
        $envio = $this->enviarPadrao();
        $this->simularMatricula('2099-01-01');
        $this->service->avaliar(1, $envio['id'], 99, 'aprovada');

        try {
            $this->service->avaliar(1, $envio['id'], 99, 'aprovada');
            $this->fail('A segunda aprovação deve ser recusada.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame(1, DB::table('matriculas')->count());
    }

    public function test_aprovacao_exige_vinculo_ainda_valido(): void
    {
        $envio = $this->enviarPadrao();
        DB::table('planos')->where('id', 10)->update(['ativo' => false]);
        $matriculaService = \Mockery::mock(AdminMatriculaService::class);
        $matriculaService->shouldNotReceive('criar');
        $this->app->instance(AdminMatriculaService::class, $matriculaService);

        try {
            $this->service->avaliar(1, $envio['id'], 99, 'aprovada');
            $this->fail('Sem plano pago válido a aprovação deve falhar.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('plano_id', $exception->errors());
        }
        $this->assertSame('pendente', DB::table('pre_matriculas')->where('id', $envio['id'])->value('status'));
    }

    public function test_fim_do_periodo_gratuito_oferece_o_plano_pago_vinculado(): void
    {
        $envio = $this->enviarPadrao();
        $this->simularMatricula('2020-01-01');
        $this->service->avaliar(1, $envio['id'], 99, 'aprovada');
        // A aprovação recalcula o período; aqui o período gratuito já terminou
        DB::table('matriculas')->update(['proxima_data_vencimento' => '2020-01-01', 'data_vencimento' => '2020-01-01']);

        $periodo = $this->service->paraAluno(1, 1)['periodo_gratuito'];
        $this->assertTrue($periodo['encerrado']);
        $this->assertSame(['id' => 10, 'descricao' => 'Yoga 2x semana'], $periodo['plano_destino']);

        DB::table('matriculas')->update(['proxima_data_vencimento' => '2099-01-01', 'data_vencimento' => '2099-01-01']);
        $this->assertFalse($this->service->paraAluno(1, 1)['periodo_gratuito']['encerrado']);

        // Já contratou o plano pago: o aviso de fim do período gratuito deixa de aparecer
        DB::table('matriculas')->update(['plano_id' => 10, 'proxima_data_vencimento' => '2020-01-01']);
        $this->assertFalse($this->service->paraAluno(1, 1)['periodo_gratuito']['encerrado']);
    }

    public function test_destino_da_matricula_nao_muda_quando_a_configuracao_muda(): void
    {
        $envio = $this->enviarPadrao();
        $this->simularMatricula('2020-01-01');
        $this->service->avaliar(1, $envio['id'], 99, 'aprovada');
        DB::table('planos')->insert(['id' => 14, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => 'Novo', 'valor' => 99, 'checkins_semanais' => 2, 'ativo' => true]);
        $this->service->salvarConfiguracao([
            'tenant_id' => 1, 'ativa' => true, 'obrigatoria' => false, 'publico' => 'sem_matricula',
            'pares' => [['plano_free_id' => 1, 'plano_pago_id' => 14]],
        ], 99);

        $this->assertSame(10, DB::table('matriculas')->value('plano_destino_id'));
        $this->assertSame(10, $this->service->paraAluno(1, 1)['periodo_gratuito']['plano_destino']['id']);
    }

    public function test_matricula_cancelada_libera_novo_envio(): void
    {
        $envio = $this->enviarPadrao();
        $this->simularMatricula('2099-01-01');
        $this->service->avaliar(1, $envio['id'], 99, 'aprovada');
        try {
            $this->enviarPadrao();
            $this->fail('Com matrícula vigente não pode haver novo envio.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pre_matricula', $exception->errors());
        }

        DB::table('matriculas')->update(['status_id' => 2]);

        $this->assertNull($this->service->paraAluno(1, 1)['status']);
        $this->assertGreaterThan($envio['id'], $this->enviarPadrao()['id']);
    }

    public function test_edita_pedidos_aprovados_e_reprovados_sem_alterar_a_matricula(): void
    {
        DB::table('planos')->insert([
            ['id' => 3, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => 'Yoga 1x FREE', 'valor' => 0, 'checkins_semanais' => 1, 'ativo' => true],
            ['id' => 11, 'tenant_id' => 1, 'modalidade_id' => 1, 'nome' => 'Yoga 1x', 'valor' => 90, 'checkins_semanais' => 1, 'ativo' => true],
        ]);
        $this->service->salvarConfiguracao([
            'tenant_id' => 1, 'ativa' => true, 'obrigatoria' => false, 'publico' => 'sem_matricula',
            'pares' => [['plano_free_id' => 1, 'plano_pago_id' => 10], ['plano_free_id' => 3, 'plano_pago_id' => 11]],
        ], 99);
        $dados = ['plano_id' => 3, 'dia_vencimento' => 10, 'grade' => [['turma_fixa_id' => 1, 'dia_semana' => 3]], 'dia_vencimento' => 20];

        $reprovada = $this->enviarPadrao();
        $this->service->avaliar(1, $reprovada['id'], 99, 'reprovada', 'Dados incompletos');
        $this->service->atualizarSolicitacao(1, $reprovada['id'], $dados);
        $linha = DB::table('pre_matriculas')->where('id', $reprovada['id'])->first();
        $this->assertSame('reprovada', $linha->status);
        $this->assertSame(3, (int) $linha->plano_id);
        $this->assertSame(11, (int) $linha->plano_destino_id);

        $aprovada = $this->enviarPadrao();
        $this->simularMatricula('2099-01-01');
        $this->service->avaliar(1, $aprovada['id'], 99, 'aprovada');
        $resultado = $this->service->atualizarSolicitacao(1, $aprovada['id'], $dados);
        $this->assertStringContainsString('A matrícula já criada não foi alterada', $resultado['message']);
        $linha = DB::table('pre_matriculas')->where('id', $aprovada['id'])->first();
        $this->assertSame('aprovada', $linha->status);
        $this->assertSame(3, (int) $linha->plano_id);
        $this->assertSame(10, (int) $linha->plano_destino_id);
        $matricula = DB::table('matriculas')->first();
        $this->assertSame(1, (int) $matricula->plano_id);
        $this->assertSame(10, (int) $matricula->plano_destino_id);
    }

    public function test_exclusao_logica_preserva_matricula_e_registra_autor(): void
    {
        $pendente = $this->enviarPadrao();
        try {
            $this->service->excluirSolicitacao(1, $pendente['id'], 99);
            $this->fail('Pedido pendente não pode ser excluído.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->simularMatricula('2099-01-01');
        $this->service->avaliar(1, $pendente['id'], 99, 'aprovada');
        $this->service->excluirSolicitacao(1, $pendente['id'], 77);

        $linha = DB::table('pre_matriculas')->where('id', $pendente['id'])->first();
        $this->assertNotNull($linha->excluida_em);
        $this->assertSame(77, (int) $linha->excluida_por);
        $this->assertSame([], $this->service->painelAcademia(1)['solicitacoes']);
        $this->assertNull($this->service->paraAluno(1, 1)['status'] ?? null);
        $this->assertSame(1, DB::table('matriculas')->count());
        $this->assertSame(10, (int) DB::table('matriculas')->value('plano_destino_id'));
        try {
            $this->service->atualizarSolicitacao(1, $pendente['id'], ['plano_id' => 1, 'dia_vencimento' => 10, 'grade' => [['turma_fixa_id' => 1, 'dia_semana' => 1], ['turma_fixa_id' => 2, 'dia_semana' => 5]]]);
            $this->fail('Pedido excluído não pode ser editado.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_pedido_excluido_nao_conta_na_duplicidade_e_reprovado_pode_ser_reenviado(): void
    {
        $primeiro = $this->enviarPadrao();
        $this->service->avaliar(1, $primeiro['id'], 99, 'reprovada');
        $this->service->excluirSolicitacao(1, $primeiro['id'], 99);

        $this->assertGreaterThan($primeiro['id'], $this->enviarPadrao()['id']);
    }

    public function test_operacoes_de_uma_academia_nao_alcancam_pedidos_de_outra(): void
    {
        DB::table('tenants')->insert(['id' => 2, 'nome' => 'Outra']);
        $envio = $this->enviarPadrao();
        $this->service->avaliar(1, $envio['id'], 99, 'reprovada');

        foreach ([
            fn () => $this->service->avaliar(2, $envio['id'], 99, 'aprovada'),
            fn () => $this->service->atualizarSolicitacao(2, $envio['id'], ['plano_id' => 1, 'dia_vencimento' => 10, 'grade' => [['turma_fixa_id' => 1, 'dia_semana' => 1]]]),
            fn () => $this->service->excluirSolicitacao(2, $envio['id'], 99),
        ] as $operacao) {
            try {
                $operacao();
                $this->fail('Pedido de outra academia não pode ser alcançado.');
            } catch (HttpException $exception) {
                $this->assertSame(404, $exception->getStatusCode());
            }
        }
        $this->assertSame([], $this->service->painelAcademia(2)['solicitacoes']);
        $this->assertCount(1, $this->service->painelAcademia(1)['solicitacoes']);
    }

    private function criarDependente(bool $vinculado = false): int
    {
        DB::table('usuarios')->insert(['id' => 2, 'nome' => 'Pedro Silva', 'email' => 'pedro@example.com', 'responsavel_usuario_id' => 1]);
        DB::table('usuarios')->insert(['id' => 3, 'nome' => 'Estranho', 'email' => 'x@example.com']);
        if ($vinculado) {
            DB::table('tenant_usuario_papel')->insert(['tenant_id' => 1, 'usuario_id' => 2, 'papel_id' => Papel::ALUNO, 'ativo' => true]);
            DB::table('alunos')->insert(['id' => 2, 'usuario_id' => 2]);
        }

        return 2;
    }

    public function test_beneficiario_so_e_o_proprio_perfil_ou_dependente_do_responsavel(): void
    {
        $this->criarDependente();

        $this->assertSame([1, null], $this->service->resolverBeneficiario(1, null, false));
        $this->assertSame([1, null], $this->service->resolverBeneficiario(1, 1, false));
        $this->assertSame([2, 1], $this->service->resolverBeneficiario(1, 2, false));
        foreach ([[1, 3, false], [2, 1, false], [2, 3, true], [1, 2, true]] as [$ator, $alvo, $perfilDependente]) {
            try {
                $this->service->resolverBeneficiario($ator, $alvo, $perfilDependente);
                $this->fail("Ator {$ator} não pode enviar para {$alvo}.");
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        // Perfil de dependente enviando o próprio pedido é permitido
        $this->assertSame([2, null], $this->service->resolverBeneficiario(2, 2, true));
    }

    public function test_responsavel_envia_pelo_dependente_e_o_pedido_fica_no_nome_dele(): void
    {
        $this->criarDependente();
        $this->assertNull(DB::table('tenant_usuario_papel')->where('usuario_id', 2)->first());

        $envio = $this->service->enviar(1, 2, [
            'plano_id' => 1,
            'dia_vencimento' => 10,
            'grade' => [['turma_fixa_id' => 1, 'dia_semana' => 1], ['turma_fixa_id' => 2, 'dia_semana' => 5]],
        ], 1);

        $linha = DB::table('pre_matriculas')->where('id', $envio['id'])->first();
        $this->assertSame(2, (int) $linha->usuario_id);
        $this->assertSame(1, (int) $linha->enviada_por_id);
        $this->assertNotNull(DB::table('tenant_usuario_papel')->where('usuario_id', 2)->where('tenant_id', 1)->first());
        $solicitacao = $this->service->painelAcademia(1)['solicitacoes'][0];
        $this->assertTrue($solicitacao['dependente']);
        $this->assertSame('Pedro Silva', $solicitacao['nome']);
        $this->assertSame('Maria Silva', $solicitacao['responsavel_nome']);
        $this->assertSame('Maria Silva', $solicitacao['enviada_por_nome']);
        // Cada pessoa tem o próprio pedido: o do responsável continua livre
        $this->assertNull($this->service->paraAluno(1, 1)['status']);
        $this->assertSame('pendente', $this->service->paraAluno(1, 2, 1)['status']);
    }

    public function test_nao_envia_por_quem_nao_e_dependente(): void
    {
        $this->criarDependente();

        try {
            $this->service->enviar(1, 3, [
                'plano_id' => 1,
                'dia_vencimento' => 10,
                'grade' => [['turma_fixa_id' => 1, 'dia_semana' => 1], ['turma_fixa_id' => 2, 'dia_semana' => 5]],
            ], 1);
            $this->fail('Usuário sem vínculo de dependência não pode receber pedido.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('usuario_id', $exception->errors());
        }
        $this->assertSame(0, DB::table('pre_matriculas')->count());
    }

    public function test_lista_a_situacao_de_cada_dependente_para_o_responsavel(): void
    {
        $this->criarDependente(true);

        $dependentes = $this->service->dependentes(1, 1);

        $this->assertCount(1, $dependentes);
        $this->assertSame(2, $dependentes[0]['usuario_id']);
        $this->assertTrue($dependentes[0]['disponivel']);
        $this->assertNull($dependentes[0]['status']);
        $this->assertTrue($dependentes[0]['tem_planos']);

        $this->service->enviar(1, 2, [
            'plano_id' => 1,
            'dia_vencimento' => 10,
            'grade' => [['turma_fixa_id' => 1, 'dia_semana' => 1], ['turma_fixa_id' => 2, 'dia_semana' => 5]],
        ], 1);
        $this->assertSame('pendente', $this->service->dependentes(1, 1)[0]['status']);
        $this->assertSame([], $this->service->dependentes(1, 3));
    }

    public function test_aprovacao_cria_a_matricula_do_dependente(): void
    {
        $this->criarDependente(true);
        $envio = $this->service->enviar(1, 2, [
            'plano_id' => 1,
            'dia_vencimento' => 10,
            'grade' => [['turma_fixa_id' => 1, 'dia_semana' => 1], ['turma_fixa_id' => 2, 'dia_semana' => 5]],
        ], 1);
        $mock = \Mockery::mock(AdminMatriculaService::class);
        $mock->shouldReceive('criar')->once()->with(1, 99, \Mockery::on(fn (array $dados) => $dados['usuario_id'] === 2))
            ->andReturnUsing(function (): array {
                $id = (int) DB::table('matriculas')->insertGetId(['tenant_id' => 1, 'aluno_id' => 2, 'status_id' => 1]);

                return ['status' => 201, 'body' => ['matricula' => ['id' => $id]]];
            });
        $this->app->instance(AdminMatriculaService::class, $mock);

        $this->service->avaliar(1, $envio['id'], 99, 'aprovada');

        $this->assertSame('aprovada', $this->service->paraAluno(1, 2, 1)['status']);
    }

    public function test_datas_da_fila_saem_em_utc_iso_para_o_painel_converter_ao_fuso(): void
    {
        $envio = $this->enviarPadrao();
        DB::table('pre_matriculas')->where('id', $envio['id'])->update(['created_at' => '2026-11-07 01:43:14']);

        $this->assertSame('2026-11-07T01:43:14Z', $this->service->painelAcademia(1)['solicitacoes'][0]['created_at']);
    }

    public function test_dia_de_vencimento_e_obrigatorio_no_envio_e_na_edicao(): void
    {
        $grade = [['turma_fixa_id' => 1, 'dia_semana' => 1], ['turma_fixa_id' => 2, 'dia_semana' => 5]];
        foreach ([[], ['dia_vencimento' => null], ['dia_vencimento' => 0], ['dia_vencimento' => 32]] as $extra) {
            try {
                $this->service->enviar(1, 1, ['plano_id' => 1, 'grade' => $grade] + $extra);
                $this->fail('Vencimento ausente ou inválido não pode ser aceito.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('dia_vencimento', $exception->errors());
            }
        }
        $envio = $this->enviarPadrao();
        try {
            $this->service->atualizarSolicitacao(1, $envio['id'], ['plano_id' => 1, 'grade' => $grade]);
            $this->fail('A edição também exige o vencimento.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('dia_vencimento', $exception->errors());
        }
    }

    public function test_pedido_antigo_sem_vencimento_nao_e_aprovado_ate_informar_o_dia(): void
    {
        $envio = $this->enviarPadrao();
        DB::table('pre_matriculas')->where('id', $envio['id'])->update(['dia_vencimento' => null]);
        $mock = \Mockery::mock(AdminMatriculaService::class);
        $mock->shouldNotReceive('criar');
        $this->app->instance(AdminMatriculaService::class, $mock);

        try {
            $this->service->avaliar(1, $envio['id'], 99, 'aprovada');
            $this->fail('Sem vencimento a aprovação deve falhar.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('dia_vencimento', $exception->errors());
        }
        $this->assertSame('pendente', DB::table('pre_matriculas')->where('id', $envio['id'])->value('status'));
    }

    public function test_periodo_gratuito_conta_a_partir_do_proximo_dia_de_vencimento(): void
    {
        // Aprovado dia 06 com vencimento dia 10: ciclo 10/11 a 10/12
        $this->assertSame(['inicio_ciclo' => '2026-11-10', 'fim' => '2026-12-10'], PreMatriculaService::calcularPeriodoGratuito('2026-11-06', 10, 30));
        // No próprio dia, começa hoje; depois do dia, vai para o mês seguinte
        $this->assertSame(['inicio_ciclo' => '2026-11-10', 'fim' => '2026-12-10'], PreMatriculaService::calcularPeriodoGratuito('2026-11-10', 10, 30));
        $this->assertSame(['inicio_ciclo' => '2026-12-05', 'fim' => '2027-01-04'], PreMatriculaService::calcularPeriodoGratuito('2026-11-20', 5, 30));
        // Dia 31 em mês curto vira o último dia; virada de ano
        $this->assertSame('2026-11-30', PreMatriculaService::calcularPeriodoGratuito('2026-11-06', 31, 30)['inicio_ciclo']);
        $this->assertSame('2027-01-20', PreMatriculaService::calcularPeriodoGratuito('2026-12-21', 20, 30)['inicio_ciclo']);
    }

    public function test_aprovacao_aplica_a_contagem_configurada_na_matricula(): void
    {
        $envio = $this->enviarPadrao();
        $this->simularMatricula('2026-12-06');
        $hoje = AcademyDateTime::today();
        $esperado = PreMatriculaService::calcularPeriodoGratuito($hoje, 10, 30)['fim'];

        $resultado = $this->service->avaliar(1, $envio['id'], 99, 'aprovada');

        $this->assertSame($esperado, $resultado['acesso_gratuito_ate']);
        $this->assertSame($esperado, DB::table('matriculas')->value('proxima_data_vencimento'));
        $this->assertSame($esperado, DB::table('matriculas')->value('data_vencimento'));
    }

    public function test_contagem_pela_aprovacao_mantem_as_datas_da_matricula(): void
    {
        $this->service->salvarConfiguracao([
            'tenant_id' => 1, 'ativa' => true, 'obrigatoria' => false, 'publico' => 'sem_matricula',
            'contagem_gratuito' => 'aprovacao',
        ], 99);
        $this->assertSame('aprovacao', $this->service->painel(1)['configuracao']['contagem_gratuito']);
        $envio = $this->enviarPadrao();
        $this->simularMatricula('2026-12-06');

        $resultado = $this->service->avaliar(1, $envio['id'], 99, 'aprovada');

        $this->assertSame('2026-12-06', $resultado['acesso_gratuito_ate']);
    }

    public function test_contagem_invalida_e_recusada_e_o_padrao_e_dia_de_vencimento(): void
    {
        $this->assertSame('dia_vencimento', $this->service->painel(1)['configuracao']['contagem_gratuito']);
        try {
            $this->service->salvarConfiguracao([
                'tenant_id' => 1, 'ativa' => true, 'obrigatoria' => false, 'publico' => 'sem_matricula',
                'contagem_gratuito' => 'qualquer',
            ], 99);
            $this->fail('Contagem inválida não pode ser aceita.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('contagem_gratuito', $exception->errors());
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
            'dia_vencimento' => 10,
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
