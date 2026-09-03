<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2SuperAdminPlanosRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_planos_alunos_requires_jwt(): void
    {
        $this->getJson('/v2/superadmin/planos')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_planos_alunos_sem_tenant_id(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminPlanoAlunoService::class);
        $service->shouldReceive('listarPlanosAlunos')
            ->once()
            ->with([])
            ->andReturn([
                'status' => 200,
                'body' => [
                    'planos' => [],
                    'total' => 0,
                    'tenants' => [['id' => 2, 'nome' => 'Academia X']],
                    'message' => 'Selecione uma academia para ver os planos',
                ],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminPlanoAlunoService::class, $service);

        $this->getJson('/v2/superadmin/planos', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Selecione uma academia para ver os planos');
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
