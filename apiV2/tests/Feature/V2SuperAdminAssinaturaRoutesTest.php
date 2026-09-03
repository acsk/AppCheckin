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

    public function test_superadmin_assinaturas_requires_jwt(): void
    {
        $this->getJson('/v2/superadmin/assinaturas')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_superadmin_assinaturas_rejects_admin(): void
    {
        $token = $this->tokenParaPapel(3);

        $this->getJson('/v2/superadmin/assinaturas', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden();
    }

    public function test_superadmin_assinaturas_ok(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\Admin\AdminAssinaturaService::class);
        $service->shouldReceive('listarSuperAdmin')
            ->once()
            ->with(Mockery::subset(['tenant_id' => '2']))
            ->andReturn([
                'status' => 200,
                'body' => [
                    'success' => true,
                    'total' => 1,
                    'assinaturas' => [['id' => 9]],
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminAssinaturaService::class, $service);

        $this->getJson('/v2/superadmin/assinaturas?tenant_id=2', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assinaturas.0.id', 9);
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
                'tenant_id' => 3,
                'papel_id' => $papelId,
            ]);
        $this->app->instance(\App\Repositories\UsuarioRepository::class, $usuarios);

        return app(\App\Services\JwtService::class)->encode([
            'user_id' => 5,
            'email' => 'super@example.com',
            'tenant_id' => 3,
        ]);
    }
}
