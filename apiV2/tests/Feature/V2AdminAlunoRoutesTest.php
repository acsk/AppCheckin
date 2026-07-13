<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2AdminAlunoRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_alunos_requires_jwt(): void
    {
        $this->getJson('/v2/admin/alunos')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_alunos_basico_ok_for_admin(): void
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

        $service = Mockery::mock(\App\Services\Admin\AdminAlunoService::class);
        $service->shouldReceive('listarBasico')
            ->once()
            ->with(3)
            ->andReturn([
                'status' => 200,
                'body' => ['alunos' => [['id' => 1, 'nome' => 'JOAO']], 'total' => 1],
            ]);
        $this->app->instance(\App\Services\Admin\AdminAlunoService::class, $service);

        $token = app(\App\Services\JwtService::class)->encode([
            'user_id' => 5,
            'email' => 'admin@example.com',
            'tenant_id' => 3,
        ]);

        $this->getJson('/v2/admin/alunos/basico', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('alunos.0.nome', 'JOAO')
            ->assertJsonPath('total', 1);
    }

    public function test_alunos_create_validation_for_admin(): void
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

        // Usa service real (só validação, sem DB)
        $token = app(\App\Services\JwtService::class)->encode([
            'user_id' => 5,
            'email' => 'admin@example.com',
            'tenant_id' => 3,
        ]);

        $this->postJson('/v2/admin/alunos', [], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertStatus(422)
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('message', 'Erro de validação');
    }
}
