<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2SuperAdminAssinaturaRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_assinaturas_requires_jwt(): void
    {
        $this->getJson('/v2/superadmin/assinaturas?tenant_id=1')
            ->assertUnauthorized();
    }

    public function test_assinaturas_rejects_non_superadmin(): void
    {
        $token = $this->tokenParaPapel(3);

        $this->getJson('/v2/superadmin/assinaturas?tenant_id=1', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden();
    }

    public function test_assinaturas_lista_por_tenant(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\Admin\AdminAssinaturaService::class);
        $service->shouldReceive('listarTodas')
            ->once()
            ->with(Mockery::subset(['tenant_id' => '1']))
            ->andReturn([
                'status' => 200,
                'body' => [
                    'success' => true,
                    'total' => 1,
                    'assinaturas' => [['id' => 99]],
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminAssinaturaService::class, $service);

        $this->getJson('/v2/superadmin/assinaturas?tenant_id=1', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('assinaturas.0.id', 99);
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
