<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2SuperAdminUsuariosRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_usuarios_requires_jwt(): void
    {
        $this->getJson('/v2/superadmin/usuarios')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_usuarios_rejects_aluno(): void
    {
        $token = $this->tokenParaPapel(1);

        $this->getJson('/v2/superadmin/usuarios', [
            'Authorization' => 'Bearer '.$token,
        ])->assertForbidden();
    }

    public function test_index_lista_usuarios(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminUsuarioService::class);
        $service->shouldReceive('index')
            ->once()
            ->with([])
            ->andReturn([
                'status' => 200,
                'body' => ['total' => 1, 'usuarios' => [['id' => 10, 'nome' => 'João']]],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminUsuarioService::class, $service);

        $this->getJson('/v2/superadmin/usuarios', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('usuarios.0.nome', 'João');
    }

    public function test_show_retorna_usuario(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminUsuarioService::class);
        $service->shouldReceive('show')
            ->once()
            ->with(10)
            ->andReturn([
                'status' => 200,
                'body' => ['id' => 10, 'nome' => 'João', 'email' => 'joao@test.com'],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminUsuarioService::class, $service);

        $this->getJson('/v2/superadmin/usuarios/10', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('nome', 'João');
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
