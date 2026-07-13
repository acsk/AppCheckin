<?php

namespace Tests\Unit;

use App\Repositories\AdminMatriculaRepository;
use App\Services\Admin\AdminMatriculaService;
use App\Services\PagamentoPlanoService;
use Mockery;
use Tests\TestCase;

class AdminMatriculaServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_bloquear_not_found(): void
    {
        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findBasicoComStatus')->once()->with(99, 3)->andReturn(null);

        $service = new AdminMatriculaService($repo, Mockery::mock(PagamentoPlanoService::class));
        $result = $service->bloquear(99, 3, 5, []);

        $this->assertSame(404, $result['status']);
        $this->assertSame('Matrícula não encontrada', $result['body']['error']);
    }

    public function test_cancelar_not_found(): void
    {
        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findBasicoComStatus')->once()->with(99, 3)->andReturn(null);

        $service = new AdminMatriculaService($repo, Mockery::mock(PagamentoPlanoService::class));
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

        $service = new AdminMatriculaService($repo, Mockery::mock(PagamentoPlanoService::class));
        $result = $service->cancelar(10, 3, 5, []);

        $this->assertSame(400, $result['status']);
        $this->assertSame('Matrícula já está cancelada', $result['body']['error']);
    }

    public function test_atualizar_proxima_data_formato_invalido(): void
    {
        $service = new AdminMatriculaService(
            Mockery::mock(AdminMatriculaRepository::class),
            Mockery::mock(PagamentoPlanoService::class),
        );

        $result = $service->atualizarProximaDataVencimento(1, 3, [
            'proxima_data_vencimento' => '13/07/2026',
        ]);

        $this->assertSame(422, $result['status']);
        $this->assertSame('Formato de data inválido. Use YYYY-MM-DD', $result['body']['error']);
    }

    public function test_atualizar_proxima_data_obrigatoria(): void
    {
        $service = new AdminMatriculaService(
            Mockery::mock(AdminMatriculaRepository::class),
            Mockery::mock(PagamentoPlanoService::class),
        );

        $result = $service->atualizarProximaDataVencimento(1, 3, []);

        $this->assertSame(422, $result['status']);
        $this->assertSame('Data de vencimento é obrigatória', $result['body']['error']);
    }

    public function test_criar_validation_missing_aluno_e_plano(): void
    {
        $service = new AdminMatriculaService(
            Mockery::mock(AdminMatriculaRepository::class),
            Mockery::mock(PagamentoPlanoService::class),
        );

        $result = $service->criar(3, 5, []);

        $this->assertSame(422, $result['status']);
        $this->assertContains('Aluno é obrigatório (envie aluno_id ou usuario_id)', $result['body']['errors']);
        $this->assertContains('Plano ou Pacote é obrigatório (envie plano_id ou pacote_id)', $result['body']['errors']);
        $this->assertContains('Dia de vencimento é obrigatório', $result['body']['errors']);
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

        $service = new AdminMatriculaService($repo, Mockery::mock(PagamentoPlanoService::class));
        $result = $service->darBaixaConta(10, 3, 5, []);

        $this->assertSame(400, $result['status']);
        $this->assertSame('data_vencimento é obrigatória para baixa manual', $result['body']['error']);
    }

    public function test_alterar_plano_missing_plano_id(): void
    {
        $service = new AdminMatriculaService(
            Mockery::mock(AdminMatriculaRepository::class),
            Mockery::mock(PagamentoPlanoService::class),
        );

        $result = $service->alterarPlano(1, 3, 5, []);

        $this->assertSame(422, $result['status']);
        $this->assertSame('plano_id é obrigatório', $result['body']['error']);
    }

    public function test_alterar_plano_not_found(): void
    {
        $repo = Mockery::mock(AdminMatriculaRepository::class);
        $repo->shouldReceive('findParaAlterarPlano')->once()->with(99, 3)->andReturn(null);

        $service = new AdminMatriculaService($repo, Mockery::mock(PagamentoPlanoService::class));
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

        $service = new AdminMatriculaService($repo, Mockery::mock(PagamentoPlanoService::class));
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

        $service = new AdminMatriculaService($repo, Mockery::mock(PagamentoPlanoService::class));
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

        $service = new AdminMatriculaService($repo, Mockery::mock(PagamentoPlanoService::class));
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

        $service = new AdminMatriculaService($repo, Mockery::mock(PagamentoPlanoService::class));
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

        $service = new AdminMatriculaService($repo, Mockery::mock(PagamentoPlanoService::class));
        $result = $service->destroy(10, 3);

        $this->assertSame(422, $result['status']);
        $this->assertFalse($result['body']['success']);
        $this->assertSame(55, $result['body']['pacote_contrato_id']);
        $this->assertStringContainsString('pacote', $result['body']['error']);
    }
}
