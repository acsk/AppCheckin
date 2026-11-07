<?php

namespace Tests\Unit;

use App\Repositories\AdminMatriculaRepository;
use App\Services\Admin\AdminMatriculaService;
use App\Services\PagamentoPlanoService;
use App\Support\AcademyDateTime;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/** Uma matrícula por aluno e modalidade: o painel reaproveita a matrícula existente, mesmo paga. */
class AdminMatriculaUnicaTest extends TestCase
{
    private const T = 3;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('pagamentos_plano');
        Schema::dropIfExists('status_pagamento');
        Schema::create('status_pagamento', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
        });
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('matricula_id');
            $t->decimal('valor', 10, 2);
            $t->date('data_vencimento');
            $t->unsignedInteger('status_pagamento_id');
        });
        DB::table('status_pagamento')->insert([
            ['id' => 1, 'nome' => 'Aguardando'],
            ['id' => 2, 'nome' => 'Pago'],
            ['id' => 4, 'nome' => 'Cancelado'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('pagamentos_plano');
        Schema::dropIfExists('status_pagamento');
        Mockery::close();
        parent::tearDown();
    }

    /** @param array<string, mixed>|null $ultima  ultimaMatriculaNaModalidade */
    private function repo(?array $ultima, array $ativas = []): Mockery\MockInterface
    {
        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findUsuarioIdPorAluno')->with(158)->andReturn(171);
        $repo->shouldReceive('findUsuarioAluno')->andReturn(['id' => 171]);
        $repo->shouldReceive('ensureVinculoAlunoTenant');
        $repo->shouldReceive('findUsuarioAlunoNoTenant')->andReturn(['id' => 171]);
        $repo->shouldReceive('findPlano')->andReturnUsing(fn (int $id) => ['id' => $id, 'valor' => $id === 8 ? 120 : 200, 'duracao_dias' => 30, 'modalidade_id' => 3]);
        $repo->shouldReceive('buscarMatriculaDuplicadaMesmoPlanoCiclo')->andReturn(null);
        $repo->shouldReceive('matriculasAtivasValidas')->andReturn($ativas);
        $repo->shouldReceive('ultimaMatriculaNaModalidade')->andReturn($ultima);
        $repo->shouldReceive('statusIdPorCodigo')->andReturnUsing(fn (string $c) => ['ativa' => 1, 'vencida' => 2, 'cancelada' => 3, 'pendente' => 5][$c] ?? null);
        $repo->shouldReceive('marcarStatusMatricula');
        $repo->shouldReceive('motivoIdPorCodigo')->andReturnUsing(fn (string $c) => ['nova' => 1, 'renovacao' => 2, 'upgrade' => 3, 'downgrade' => 4][$c] ?? 1);
        $repo->shouldReceive('countPagamentoAtivoContasReceber')->andReturn(0);
        $repo->shouldReceive('countParcelasEmAtraso')->andReturn(0);
        $repo->shouldReceive('desativarDescontosSeTabelaExiste');
        $repo->shouldReceive('inserirHistoricoPlanos');
        $repo->shouldReceive('findMatriculaCriada')->andReturn([]);
        $repo->shouldReceive('listarPagamentosCriar')->andReturn([]);
        $repo->shouldNotReceive('inserirMatricula');
        $repo->shouldNotReceive('finalizarMatricula');

        return $repo;
    }

    private function servico(Mockery\MockInterface $repo, ?PagamentoPlanoService $pagamentos = null): AdminMatriculaService
    {
        return new AdminMatriculaService(
            $repo,
            $pagamentos ?? Mockery::mock(PagamentoPlanoService::class),
            Mockery::mock(\App\Repositories\MatriculaRepository::class),
            Mockery::mock(\App\Services\Admin\AdminPacoteService::class),
            Mockery::mock(\App\Services\Admin\AdminPagamentoPlanoService::class),
            Mockery::mock(\App\Repositories\AdminAssinaturaRepository::class),
            Mockery::mock(\App\Services\MatriculaMigracaoAptidaoService::class),
        );
    }

    // Caso do Daniel: #229 cancelada e paga; antes nascia a #450
    public function test_renovacao_de_matricula_paga_e_cancelada_usa_a_mesma(): void
    {
        $repo = $this->repo(['id' => 229, 'plano_id' => 8, 'valor' => '120.00', 'status_codigo' => 'cancelada', 'data_vencimento' => '2026-10-04', 'proxima_data_vencimento' => '2026-10-04']);
        $repo->shouldReceive('atualizarMatriculaReuse')->once()->with(229, self::T, Mockery::on(fn (array $p) => $p['plano_id'] === 8 && $p['motivo_id'] === 2 && $p['status_id'] === 5))->andReturnNull();
        $repo->shouldReceive('inserirPagamentoPlano')->once()->with(Mockery::on(fn (array $p) => $p['matricula_id'] === 229))->andReturn(1);

        $r = $this->servico($repo)->criar(self::T, 5, ['aluno_id' => 158, 'plano_id' => 8, 'data_inicio' => '2026-10-22']);

        $this->assertSame(201, $r['status']);
    }

    public function test_troca_de_plano_depois_do_vencimento_usa_a_mesma(): void
    {
        $repo = $this->repo(['id' => 113, 'plano_id' => 8, 'valor' => '120.00', 'status_codigo' => 'vencida', 'data_vencimento' => '2026-06-16', 'proxima_data_vencimento' => '2026-06-16']);
        $repo->shouldReceive('atualizarMatriculaReuse')->once()->with(113, self::T, Mockery::on(fn (array $p) => $p['plano_id'] === 9 && $p['plano_anterior_id'] === 8 && $p['motivo_id'] === 3))->andReturnNull();
        $repo->shouldReceive('inserirPagamentoPlano')->once()->andReturn(1);

        $this->assertSame(201, $this->servico($repo)->criar(self::T, 5, ['aluno_id' => 158, 'plano_id' => 9])['status']);
    }

    public function test_ativa_que_vence_hoje_renova_na_mesma_sem_finalizar(): void
    {
        $hoje = AcademyDateTime::today();
        $ativa = ['id' => 450, 'plano_id' => 8, 'valor' => '120.00', 'status_codigo' => 'ativa', 'modalidade_id' => 3, 'data_vencimento' => $hoje, 'proxima_data_vencimento' => $hoje];
        $repo = $this->repo($ativa, [$ativa]);
        $repo->shouldReceive('atualizarMatriculaReuse')->once()->with(450, self::T, Mockery::on(fn (array $p) => $p['motivo_id'] === 2))->andReturnNull();
        $repo->shouldReceive('inserirPagamentoPlano')->once()->andReturn(1);

        $this->assertSame(201, $this->servico($repo)->criar(self::T, 5, ['aluno_id' => 158, 'plano_id' => 8])['status']);
    }

    public function test_compra_pendente_nao_paga_tem_a_cobranca_cancelada_ao_refazer(): void
    {
        $repo = $this->repo(['id' => 460, 'plano_id' => 9, 'valor' => '200.00', 'status_codigo' => 'pendente', 'data_vencimento' => '2099-01-01', 'proxima_data_vencimento' => '2099-01-01']);
        $repo->shouldReceive('atualizarMatriculaReuse')->once()->with(460, self::T, Mockery::any())->andReturnNull();
        $repo->shouldReceive('inserirPagamentoPlano')->once()->andReturn(1);
        $pagamentos = Mockery::mock(PagamentoPlanoService::class);
        $pagamentos->shouldReceive('cancelarParcelasAbertas')->once()->with(self::T, 460, Mockery::type('string'))->andReturn(1);

        $this->assertSame(201, $this->servico($repo, $pagamentos)->criar(self::T, 5, ['aluno_id' => 158, 'plano_id' => 8])['status']);
    }

    // Caso da matrícula 298: parcela do dia já paga e o painel confirmou "Cobrar mesmo assim"
    public function test_periodo_ja_pago_nao_gera_nova_cobranca_nem_com_confirmacao(): void
    {
        $hoje = AcademyDateTime::today();
        $ativa = ['id' => 298, 'plano_id' => 8, 'valor' => '120.00', 'status_codigo' => 'ativa', 'modalidade_id' => 3, 'data_vencimento' => $hoje, 'proxima_data_vencimento' => $hoje];
        DB::table('pagamentos_plano')->insert(['id' => 1026, 'tenant_id' => self::T, 'matricula_id' => 298, 'valor' => 70, 'data_vencimento' => $hoje, 'status_pagamento_id' => 2]);
        $repo = $this->repo($ativa, [$ativa]);
        $repo->shouldNotReceive('atualizarMatriculaReuse');
        $repo->shouldNotReceive('inserirPagamentoPlano');

        $r = $this->servico($repo)->criar(self::T, 5, ['aluno_id' => 158, 'plano_id' => 9, 'permitir_cobranca_no_periodo' => true]);

        $this->assertSame(409, $r['status']);
        $this->assertSame('PERIODO_JA_PAGO', $r['body']['code']);
    }

    public function test_parcela_em_aberto_no_periodo_pede_confirmacao(): void
    {
        $hoje = AcademyDateTime::today();
        $ativa = ['id' => 298, 'plano_id' => 8, 'valor' => '120.00', 'status_codigo' => 'ativa', 'modalidade_id' => 3, 'data_vencimento' => $hoje, 'proxima_data_vencimento' => $hoje];
        DB::table('pagamentos_plano')->insert(['tenant_id' => self::T, 'matricula_id' => 298, 'valor' => 120, 'data_vencimento' => $hoje, 'status_pagamento_id' => 1]);
        $repo = $this->repo($ativa, [$ativa]);
        $repo->shouldNotReceive('inserirPagamentoPlano');

        $r = $this->servico($repo)->criar(self::T, 5, ['aluno_id' => 158, 'plano_id' => 8]);

        $this->assertSame(409, $r['status']);
        $this->assertSame('COBRANCA_EXISTENTE_NO_PERIODO', $r['body']['code']);
    }
}
