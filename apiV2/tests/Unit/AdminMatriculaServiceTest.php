<?php

namespace Tests\Unit;

use App\Repositories\AdminMatriculaRepository;
use App\Repositories\MatriculaRepository;
use App\Services\Admin\AdminMatriculaService;
use App\Services\PagamentoPlanoService;
use Mockery;
use Tests\TestCase;

class AdminMatriculaServiceTest extends TestCase
{
    private function makeService(
        ?AdminMatriculaRepository $repo = null,
        ?PagamentoPlanoService $pagamentosPlano = null,
        ?MatriculaRepository $matriculaRepo = null,
    ): AdminMatriculaService {
        return new AdminMatriculaService(
            $repo ?? Mockery::mock(AdminMatriculaRepository::class),
            $pagamentosPlano ?? Mockery::mock(PagamentoPlanoService::class),
            $matriculaRepo ?? Mockery::mock(MatriculaRepository::class),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_bloquear_not_found(): void
    {
        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findBasicoComStatus')->once()->with(99, 3)->andReturn(null);

        $service = $this->makeService($repo);
        $result = $service->bloquear(99, 3, 5, []);

        $this->assertSame(404, $result['status']);
        $this->assertSame('Matrícula não encontrada', $result['body']['error']);
    }

    public function test_cancelar_not_found(): void
    {
        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findBasicoComStatus')->once()->with(99, 3)->andReturn(null);

        $service = $this->makeService($repo);
        $result = $service->cancelar(99, 3, 5, []);

        $this->assertSame(404, $result['status']);
        $this->assertSame('Matrícula não encontrada', $result['body']['error']);
    }

    public function test_cancelar_ja_cancelada(): void
    {
        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findBasicoComStatus')->once()->with(10, 3)->andReturn([
            'id' => 10,
            'status_codigo' => 'cancelada',
            'aluno_id' => 1,
        ]);

        $service = $this->makeService($repo);
        $result = $service->cancelar(10, 3, 5, []);

        $this->assertSame(400, $result['status']);
        $this->assertSame('Matrícula já está cancelada', $result['body']['error']);
    }

    public function test_atualizar_proxima_data_formato_invalido(): void
    {
        $service = $this->makeService();

        $result = $service->atualizarProximaDataVencimento(1, 3, [
            'proxima_data_vencimento' => '13/07/2026',
        ]);

        $this->assertSame(422, $result['status']);
        $this->assertSame('Formato de data inválido. Use YYYY-MM-DD', $result['body']['error']);
    }

    public function test_atualizar_proxima_data_obrigatoria(): void
    {
        $service = $this->makeService();

        $result = $service->atualizarProximaDataVencimento(1, 3, []);

        $this->assertSame(422, $result['status']);
        $this->assertSame('Data de vencimento é obrigatória', $result['body']['error']);
    }

    public function test_criar_validation_missing_aluno_e_plano(): void
    {
        $service = $this->makeService();

        $result = $service->criar(3, 5, []);

        $this->assertSame(422, $result['status']);
        $this->assertContains('Aluno é obrigatório (envie aluno_id ou usuario_id)', $result['body']['errors']);
        $this->assertContains('Plano ou Pacote é obrigatório (envie plano_id ou pacote_id)', $result['body']['errors']);
        $this->assertNotContains('Dia de vencimento é obrigatório', $result['body']['errors']);
    }

    public function test_dar_baixa_requer_data_vencimento(): void
    {
        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findPagamentoParaBaixa')->once()->with(10, 3)->andReturn([
            'id' => 10,
            'status_pagamento_id' => 1,
            'matricula_id' => 1,
            'tenant_id' => 3,
            'aluno_id' => 2,
            'plano_id' => 4,
            'valor' => 100,
            'data_vencimento' => '2026-07-01',
            'duracao_dias' => 30,
        ]);

        $service = $this->makeService($repo);
        $result = $service->darBaixaConta(10, 3, 5, []);

        $this->assertSame(400, $result['status']);
        $this->assertSame('data_vencimento é obrigatória para baixa manual', $result['body']['error']);
    }

    public function test_dar_baixa_bloqueia_parcela_de_pacote(): void
    {
        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findPagamentoParaBaixa')->once()->with(10, 3)->andReturn([
            'id' => 10,
            'status_pagamento_id' => 1,
            'matricula_id' => 1,
            'tenant_id' => 3,
            'aluno_id' => 2,
            'plano_id' => 4,
            'valor' => 180,
            'data_vencimento' => '2026-10-13',
            'duracao_dias' => 30,
            'pacote_contrato_id' => 7,
        ]);

        $service = $this->makeService($repo);
        $result = $service->darBaixaConta(10, 3, 5, [
            'data_vencimento' => '2026-10-13',
        ]);

        $this->assertSame(400, $result['status']);
        $this->assertSame('PACOTE_BAIXA_INDIVIDUAL_BLOQUEADA', $result['body']['code']);
        $this->assertSame(7, $result['body']['pacote_contrato_id']);
    }

    public function test_dar_baixa_permite_proxima_parcela_sem_vinculo_de_pagamento(): void
    {
        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findPagamentoParaBaixa')->once()->with(11, 3)->andReturn([
            'id' => 11,
            'status_pagamento_id' => 1,
            'matricula_id' => 1,
            'tenant_id' => 3,
            'aluno_id' => 2,
            'plano_id' => 4,
            'valor' => 200,
            'data_vencimento' => '2026-12-13',
            'duracao_dias' => 30,
            'pacote_contrato_id' => null,
            'matricula_pacote_contrato_id' => 7,
        ]);

        $service = $this->makeService($repo);
        $result = $service->darBaixaConta(11, 3, 5, []);

        $this->assertSame(400, $result['status']);
        $this->assertSame('data_vencimento é obrigatória para baixa manual', $result['body']['error']);
    }

    public function test_alterar_plano_missing_plano_id(): void
    {
        $service = $this->makeService();

        $result = $service->alterarPlano(1, 3, 5, []);

        $this->assertSame(422, $result['status']);
        $this->assertSame('plano_id é obrigatório', $result['body']['error']);
    }

    public function test_alterar_plano_not_found(): void
    {
        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findParaAlterarPlano')->once()->with(99, 3)->andReturn(null);

        $service = $this->makeService($repo);
        $result = $service->alterarPlano(99, 3, 5, ['plano_id' => 10]);

        $this->assertSame(404, $result['status']);
        $this->assertSame('Matrícula não encontrada', $result['body']['error']);
    }

    public function test_alterar_plano_data_inicio_invalida(): void
    {
        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findParaAlterarPlano')->once()->with(10, 3)->andReturn([
            'id' => 10,
            'plano_id' => 1,
            'plano_ciclo_id' => null,
            'status_codigo' => 'ativa',
            'valor' => 100,
            'dia_vencimento' => 10,
            'aluno_id' => 2,
            'plano_nome' => 'Mensal',
            'data_inicio' => '2026-01-01',
            'data_vencimento' => '2026-02-01',
        ]);
        $repo->shouldReceive('findPlano')->once()->with(20, 3)->andReturn([
            'id' => 20,
            'nome' => 'Trimestral',
            'valor' => 250,
            'duracao_dias' => 90,
        ]);

        $service = $this->makeService($repo);
        $result = $service->alterarPlano(10, 3, 5, [
            'plano_id' => 20,
            'data_inicio' => '13/07/2026',
        ]);

        $this->assertSame(422, $result['status']);
        $this->assertSame('Formato de data inválido. Use YYYY-MM-DD', $result['body']['error']);
    }

    public function test_alterar_plano_aluno_sem_usuario(): void
    {
        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findParaAlterarPlano')->once()->with(10, 3)->andReturn([
            'id' => 10,
            'plano_id' => 1,
            'plano_ciclo_id' => null,
            'status_codigo' => 'ativa',
            'valor' => 100,
            'dia_vencimento' => 10,
            'aluno_id' => 2,
            'plano_nome' => 'Mensal',
            'data_inicio' => '2026-01-01',
            'data_vencimento' => '2026-02-01',
        ]);
        $repo->shouldReceive('findPlano')->once()->with(20, 3)->andReturn([
            'id' => 20,
            'nome' => 'Trimestral',
            'valor' => 250,
            'duracao_dias' => 90,
        ]);
        $repo->shouldReceive('creditosAluno')->once()->with(3, 2)->andReturn([
            'saldo_total' => 0.0,
            'creditos_ativos' => [],
        ]);
        $repo->shouldReceive('statusIdPorCodigo')->once()->with('pendente')->andReturn(2);
        $repo->shouldReceive('motivoIdPorCodigo')->once()->with('upgrade')->andReturn(1);
        $repo->shouldReceive('findUsuarioIdPorAluno')->once()->with(2)->andReturn(null);

        $service = $this->makeService($repo);
        $result = $service->alterarPlano(10, 3, 5, [
            'plano_id' => 20,
            'data_inicio' => '2026-07-13',
        ]);

        $this->assertSame(404, $result['status']);
        $this->assertSame('Aluno não encontrado', $result['body']['error']);
    }

    public function test_atualizar_proxima_data_update_falhou(): void
    {
        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findBasicoComStatus')->once()->with(10, 3)->andReturn([
            'id' => 10,
            'status_codigo' => 'ativa',
            'proxima_data_vencimento' => '2026-07-01',
            'periodo_teste' => 0,
        ]);
        $repo->shouldReceive('atualizarProximaDataVencimento')
            ->once()
            ->andReturn(['ok' => false]);

        $service = $this->makeService($repo);
        $result = $service->atualizarProximaDataVencimento(10, 3, [
            'proxima_data_vencimento' => '2026-08-01',
        ]);

        $this->assertSame(500, $result['status']);
        $this->assertSame('Erro ao atualizar data de vencimento', $result['body']['error']);
    }

    public function test_delete_not_found(): void
    {
        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findParaHardDelete')->once()->with(99, 3)->andReturn(null);

        $service = $this->makeService($repo);
        $result = $service->destroy(99, 3);

        $this->assertSame(404, $result['status']);
        $this->assertSame('Matrícula não encontrada', $result['body']['error']);
    }

    public function test_delete_blocked_by_pacote_contrato_id(): void
    {
        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findParaHardDelete')->once()->with(10, 3)->andReturn([
            'id' => 10,
            'aluno_id' => 1,
            'pacote_contrato_id' => 55,
        ]);

        $service = $this->makeService($repo);
        $result = $service->destroy(10, 3);

        $this->assertSame(422, $result['status']);
        $this->assertFalse($result['body']['success']);
        $this->assertSame(55, $result['body']['pacote_contrato_id']);
        $this->assertStringContainsString('pacote', $result['body']['error']);
    }

    public function test_show_motivo_status_limite_checkins_tem_prioridade_sobre_pagamento(): void
    {
        $adminRepo = Mockery::mock(AdminMatriculaRepository::class);
        $pagamentosPlano = Mockery::mock(PagamentoPlanoService::class);
        $matriculaRepo = Mockery::mock(MatriculaRepository::class);

        $pagamentosPlano->shouldReceive('marcarAtrasados')->once()->with(3);

        $adminRepo->shouldReceive('findDetalhe')->once()->with(412, 3)->andReturn([
            'id' => 412,
            'aluno_id' => 1,
            'status_codigo' => 'pendente',
            'proxima_data_vencimento' => '2099-12-01',
            'data_vencimento' => '2099-11-01',
            'plano_ciclo_id' => null,
            'pacote_contrato_id' => null,
        ]);
        $adminRepo->shouldReceive('listarPagamentosResumo')->once()->with(412)->andReturn([
            ['id' => 1, 'valor' => 100, 'status_pagamento_id' => 2, 'data_pagamento' => '2026-01-01'],
        ]);
        $adminRepo->shouldReceive('mpPaymentIds')->once()->with(412, 3)->andReturn([]);
        $adminRepo->shouldReceive('creditosAluno')->once()->with(3, 1)->andReturn([
            'saldo_total' => 0.0,
            'creditos_ativos' => [],
        ]);
        $adminRepo->shouldReceive('listarOutrasMatriculasDoAluno')->once()->andReturn([]);

        $limiteDetalhe = [
            'plano' => 'Mensal',
            'limite_mensal' => 17,
            'checkins_mes' => 17,
            'mensagem' => 'O aluno atingiu o limite de check-ins do ciclo do plano.',
        ];
        $matriculaRepo->shouldReceive('avaliarLimiteMensalPorMatricula')
            ->once()
            ->with(412, false)
            ->andReturn($limiteDetalhe);

        $result = $this->makeService($adminRepo, $pagamentosPlano, $matriculaRepo)->show(412, 3);

        $this->assertSame(200, $result['status']);
        $this->assertSame('limite_checkins', $result['body']['matricula']['motivo_status']);
        $this->assertSame($limiteDetalhe, $result['body']['matricula']['limite_ciclo']);
    }

    public function test_show_motivo_status_aguardando_renovacao_sem_limite_checkins(): void
    {
        $adminRepo = Mockery::mock(AdminMatriculaRepository::class);
        $pagamentosPlano = Mockery::mock(PagamentoPlanoService::class);
        $matriculaRepo = Mockery::mock(MatriculaRepository::class);

        $pagamentosPlano->shouldReceive('marcarAtrasados')->once()->with(3);

        $adminRepo->shouldReceive('findDetalhe')->once()->with(10, 3)->andReturn([
            'id' => 10,
            'aluno_id' => 2,
            'status_codigo' => 'pendente',
            'proxima_data_vencimento' => '2099-12-01',
            'data_vencimento' => '2099-11-01',
            'plano_ciclo_id' => null,
            'pacote_contrato_id' => null,
        ]);
        $adminRepo->shouldReceive('listarPagamentosResumo')->once()->with(10)->andReturn([
            ['id' => 1, 'valor' => 100, 'status_pagamento_id' => 2, 'data_pagamento' => '2026-01-01'],
        ]);
        $adminRepo->shouldReceive('mpPaymentIds')->once()->with(10, 3)->andReturn([]);
        $adminRepo->shouldReceive('creditosAluno')->once()->with(3, 2)->andReturn([
            'saldo_total' => 0.0,
            'creditos_ativos' => [],
        ]);
        $adminRepo->shouldReceive('listarOutrasMatriculasDoAluno')->once()->andReturn([]);

        $matriculaRepo->shouldReceive('avaliarLimiteMensalPorMatricula')
            ->once()
            ->with(10, false)
            ->andReturn(null);

        $result = $this->makeService($adminRepo, $pagamentosPlano, $matriculaRepo)->show(10, 3);

        $this->assertSame(200, $result['status']);
        $this->assertNull($result['body']['matricula']['limite_ciclo']);
        $this->assertSame('aguardando_renovacao', $result['body']['matricula']['motivo_status']);
    }
}
