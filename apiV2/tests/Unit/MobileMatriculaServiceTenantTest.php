<?php

namespace Tests\Unit;

use App\Repositories\MatriculaRepository;
use App\Services\Mobile\MobileAssinaturaService;
use App\Services\Mobile\MobileMatriculaService;
use Tests\TestCase;

class MobileMatriculaServiceTenantTest extends TestCase
{
    public function test_detalhe_rejects_null_tenant(): void
    {
        $service = new MobileMatriculaService(new MatriculaRepository);

        $result = $service->detalhe(1, null, 10);

        $this->assertSame(400, $result['status']);
        $this->assertSame('TENANT_NAO_SELECIONADO', $result['body']['code']);
    }

    public function test_verificar_pagamento_rejects_null_tenant(): void
    {
        $service = new MobileMatriculaService(new MatriculaRepository);

        $result = $service->verificarPagamento(1, null, ['matricula_id' => 10]);

        $this->assertSame(400, $result['status']);
        $this->assertSame('TENANT_NAO_SELECIONADO', $result['body']['code']);
    }

    public function test_minhas_assinaturas_rejects_null_tenant(): void
    {
        $service = new MobileAssinaturaService;

        $result = $service->minhasAssinaturas(1, null, 5);

        $this->assertSame(400, $result['status']);
        $this->assertSame('TENANT_NAO_SELECIONADO', $result['body']['code']);
    }
}
