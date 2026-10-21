<?php

namespace Tests\Unit;

use App\Repositories\AdminPacoteRepository;
use App\Services\Admin\AdminPacoteService;
use App\Services\Admin\PacoteDescontoService;
use Mockery;
use Tests\TestCase;

class AdminPacoteServiceBeneficiariosTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function service(array $contrato, $repo = null): array
    {
        $repo ??= Mockery::mock(AdminPacoteRepository::class);
        $repo->shouldReceive('findContratoComLimite')->with(20, 3)->andReturn($contrato);
        $repo->shouldReceive('findAlunoIdDoPaganteNoTenant')->with(3, 107)->andReturn(94);

        return [new AdminPacoteService($repo, Mockery::mock(PacoteDescontoService::class)), $repo];
    }

    public function test_limite_conta_o_pagante(): void
    {
        [$service] = $this->service(['id' => 20, 'status' => 'pendente', 'pacote_id' => 10, 'pagante_usuario_id' => 107, 'qtd_beneficiarios' => 1]);

        $r = $service->definirBeneficiarios(3, 20, [95, 96]);

        $this->assertSame(400, $r['status']);
        $this->assertSame('O pacote tem 2 vagas', $r['body']['message']);
    }

    public function test_contrato_ativo_nao_tira_ninguem(): void
    {
        $repo = Mockery::mock(AdminPacoteRepository::class);
        $repo->shouldReceive('filtrarAlunosValidosNoTenant')->andReturn([94]);
        $repo->shouldReceive('listarBeneficiariosDoContrato')->with(20, 3)->andReturn([['aluno_id' => 94], ['aluno_id' => 96]]);
        $repo->shouldNotReceive('inserirBeneficiario');
        $repo->shouldNotReceive('deletarBeneficiarios');
        [$service] = $this->service(['id' => 20, 'status' => 'ativo', 'pacote_id' => 10, 'pagante_usuario_id' => 107, 'qtd_beneficiarios' => 1], $repo);

        $r = $service->definirBeneficiarios(3, 20, []);

        $this->assertSame(400, $r['status']);
    }

    public function test_contrato_cancelado_nao_muda(): void
    {
        [$service] = $this->service(['id' => 20, 'status' => 'cancelado', 'pacote_id' => 10, 'pagante_usuario_id' => 107, 'qtd_beneficiarios' => 1]);

        $this->assertSame(400, $service->definirBeneficiarios(3, 20, [95])['status']);
    }
}
