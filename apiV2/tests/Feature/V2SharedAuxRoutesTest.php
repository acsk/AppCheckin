<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2SharedAuxRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cep_invalido_retorna_400(): void
    {
        $service = Mockery::mock(\App\Services\CepService::class);
        $service->shouldReceive('buscar')
            ->once()
            ->with('123')
            ->andReturn([
                'status' => 400,
                'body' => ['type' => 'error', 'message' => 'CEP inválido. Deve conter 8 dígitos.'],
            ]);
        $this->app->instance(\App\Services\CepService::class, $service);

        $this->getJson('/v2/cep/123')
            ->assertStatus(400)
            ->assertJsonPath('type', 'error');
    }

    public function test_status_tipo_invalido(): void
    {
        $service = Mockery::mock(\App\Services\StatusService::class);
        $service->shouldReceive('listar')
            ->once()
            ->with('invalido')
            ->andReturn([
                'status' => 400,
                'body' => ['error' => 'Tipo de status inválido'],
            ]);
        $this->app->instance(\App\Services\StatusService::class, $service);

        $this->getJson('/v2/status/invalido')
            ->assertStatus(400);
    }

    public function test_formas_pagamento_requer_jwt(): void
    {
        $this->getJson('/v2/formas-pagamento')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_config_formas_pagamento_ativas(): void
    {
        $token = $this->tokenComTenant(3);

        $config = Mockery::mock(\App\Services\ConfigTenantService::class);
        $config->shouldReceive('listarFormasPagamentoAtivas')
            ->once()
            ->with(3)
            ->andReturn([
                'status' => 200,
                'body' => ['formas' => [['id' => 1, 'nome' => 'PIX']]],
            ]);
        $this->app->instance(\App\Services\ConfigTenantService::class, $config);

        $this->getJson('/v2/config/formas-pagamento-ativas', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('formas.0.nome', 'PIX');
    }

    private function tokenComTenant(int $tenantId): string
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        $usuarios = Mockery::mock(\App\Repositories\UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')
            ->with(5)
            ->andReturn([
                'id' => 5,
                'nome' => 'Admin',
                'email' => 'admin@example.com',
                'tenant_id' => $tenantId,
                'papel_id' => 3,
            ]);
        $this->app->instance(\App\Repositories\UsuarioRepository::class, $usuarios);

        return app(\App\Services\JwtService::class)->encode([
            'user_id' => 5,
            'email' => 'admin@example.com',
            'tenant_id' => $tenantId,
        ]);
    }
}
