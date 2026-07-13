<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2AdminModalidadeRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_modalidades_requires_jwt(): void
    {
        $this->getJson('/v2/admin/modalidades')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_modalidades_rejects_non_admin(): void
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        $usuarios = Mockery::mock(\App\Repositories\UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')
            ->once()
            ->with(10)
            ->andReturn([
                'id' => 10,
                'nome' => 'Aluno',
                'email' => 'aluno@example.com',
                'tenant_id' => 3,
                'papel_id' => 1,
            ]);
        $this->app->instance(\App\Repositories\UsuarioRepository::class, $usuarios);

        $token = app(\App\Services\JwtService::class)->encode([
            'user_id' => 10,
            'email' => 'aluno@example.com',
            'tenant_id' => 3,
        ]);

        $this->getJson('/v2/admin/modalidades', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden()
            ->assertJsonPath('erro', 'Acesso negado. Apenas administradores podem acessar este recurso.');
    }

    public function test_modalidades_index_ok_for_admin(): void
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        $usuarios = Mockery::mock(\App\Repositories\UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')
            ->once()
            ->with(5)
            ->andReturn([
                'id' => 5,
                'nome' => 'Admin',
                'email' => 'admin@example.com',
                'tenant_id' => 3,
                'papel_id' => 3,
            ]);
        $this->app->instance(\App\Repositories\UsuarioRepository::class, $usuarios);

        $service = Mockery::mock(\App\Services\Admin\AdminModalidadeService::class);
        $service->shouldReceive('index')
            ->once()
            ->with(3, false)
            ->andReturn([
                'status' => 200,
                'body' => ['modalidades' => [['id' => 1, 'nome' => 'Natação']]],
            ]);
        $this->app->instance(\App\Services\Admin\AdminModalidadeService::class, $service);

        $token = app(\App\Services\JwtService::class)->encode([
            'user_id' => 5,
            'email' => 'admin@example.com',
            'tenant_id' => 3,
        ]);

        $this->getJson('/v2/admin/modalidades', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('modalidades.0.nome', 'Natação');
    }
}
