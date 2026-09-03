<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2SuperAdminContratosRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_contratos_requires_jwt(): void
    {
        $this->getJson('/v2/superadmin/contratos')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_index_lista_contratos(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminContratoService::class);
        $service->shouldReceive('index')
            ->once()
            ->andReturn([
                'status' => 200,
                'body' => ['contratos' => [['id' => 1]], 'total' => 1],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminContratoService::class, $service);

        $this->getJson('/v2/superadmin/contratos', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_proximos_vencimento(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminContratoService::class);
        $service->shouldReceive('proximosVencimento')
            ->once()
            ->with(['dias' => '7'])
            ->andReturn([
                'status' => 200,
                'body' => ['contratos' => [], 'total' => 0, 'dias' => 7],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminContratoService::class, $service);

        $this->getJson('/v2/superadmin/contratos/proximos-vencimento?dias=7', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('dias', 7);
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
