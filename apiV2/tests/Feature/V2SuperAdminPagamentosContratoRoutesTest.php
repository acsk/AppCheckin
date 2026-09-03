<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2SuperAdminPagamentosContratoRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_pagamentos_requires_jwt(): void
    {
        $this->getJson('/v2/superadmin/pagamentos-contrato')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_index_lista_pagamentos(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminPagamentoContratoService::class);
        $service->shouldReceive('index')
            ->once()
            ->with([])
            ->andReturn([
                'status' => 200,
                'body' => ['pagamentos' => [['id' => 1, 'valor' => 99.9]]],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminPagamentoContratoService::class, $service);

        $this->getJson('/v2/superadmin/pagamentos-contrato', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('pagamentos.0.valor', 99.9);
    }

    public function test_resumo(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminPagamentoContratoService::class);
        $service->shouldReceive('resumo')
            ->once()
            ->with([])
            ->andReturn([
                'status' => 200,
                'body' => ['resumo' => [['status' => 'Pago', 'quantidade' => 2]]],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminPagamentoContratoService::class, $service);

        $this->getJson('/v2/superadmin/pagamentos-contrato/resumo', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('resumo.0.status', 'Pago');
    }

    public function test_confirmar_pagamento(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminPagamentoContratoService::class);
        $service->shouldReceive('confirmar')
            ->once()
            ->with(5, ['data_pagamento' => '2026-09-03'])
            ->andReturn([
                'status' => 200,
                'body' => ['type' => 'success', 'message' => 'Pagamento confirmado com sucesso'],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminPagamentoContratoService::class, $service);

        $this->postJson('/v2/superadmin/pagamentos-contrato/5/confirmar', [
            'data_pagamento' => '2026-09-03',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Pagamento confirmado com sucesso');
    }

    private function tokenParaPapel(int $papelId): string
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        $usuarios = Mockery::mock(\App\Repositories\UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')
            ->with(5)
            ->andReturn([
                'id' => 5,
                'nome' => 'Super',
                'email' => 'super@example.com',
                'tenant_id' => 1,
                'papel_id' => $papelId,
            ]);
        $this->app->instance(\App\Repositories\UsuarioRepository::class, $usuarios);

        return app(\App\Services\JwtService::class)->encode([
            'user_id' => 5,
            'email' => 'super@example.com',
            'tenant_id' => 1,
            'is_super_admin' => $papelId === 4,
        ]);
    }
}
