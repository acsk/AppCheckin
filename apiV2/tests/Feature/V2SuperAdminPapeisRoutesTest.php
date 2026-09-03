<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2SuperAdminPapeisRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_papeis_requires_jwt(): void
    {
        $this->getJson('/v2/superadmin/papeis')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_papeis_superadmin_path(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminPapelService::class);
        $service->shouldReceive('listarPapeis')
            ->once()
            ->andReturn([
                'status' => 200,
                'body' => [
                    'papeis' => [
                        ['id' => 1, 'nome' => 'Aluno', 'descricao' => 'Pode acessar o app mobile e fazer check-in'],
                    ],
                ],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminPapelService::class, $service);

        $this->getJson('/v2/superadmin/papeis', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('papeis.0.nome', 'Aluno');
    }

    public function test_papeis_alias_root_path(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminPapelService::class);
        $service->shouldReceive('listarPapeis')
            ->once()
            ->andReturn([
                'status' => 200,
                'body' => ['papeis' => [['id' => 3, 'nome' => 'Admin']]],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminPapelService::class, $service);

        $this->getJson('/v2/papeis', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('papeis.0.nome', 'Admin');
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
