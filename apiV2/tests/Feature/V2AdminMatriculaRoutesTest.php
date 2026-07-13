<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2AdminMatriculaRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_matriculas_requires_jwt(): void
    {
        $this->getJson('/v2/admin/matriculas')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_matriculas_index_ok_for_admin(): void
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

        $service = Mockery::mock(\App\Services\Admin\AdminMatriculaService::class);
        $service->shouldReceive('index')
            ->once()
            ->with(3, Mockery::type('array'))
            ->andReturn([
                'status' => 200,
                'body' => [
                    'matriculas' => [['id' => 1, 'usuario_nome' => 'ANA']],
                    'total' => 1,
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminMatriculaService::class, $service);

        $token = app(\App\Services\JwtService::class)->encode([
            'user_id' => 5,
            'email' => 'admin@example.com',
            'tenant_id' => 3,
        ]);

        $this->getJson('/v2/admin/matriculas', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('matriculas.0.usuario_nome', 'ANA')
            ->assertJsonPath('total', 1);
    }

    public function test_matriculas_vencimentos_hoje_route_registered(): void
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

        $service = Mockery::mock(\App\Services\Admin\AdminMatriculaService::class);
        $service->shouldReceive('vencimentosHoje')
            ->once()
            ->with(3)
            ->andReturn([
                'status' => 200,
                'body' => ['vencimentos' => [], 'total' => 0, 'data' => '2026-07-13'],
            ]);
        $this->app->instance(\App\Services\Admin\AdminMatriculaService::class, $service);

        $token = app(\App\Services\JwtService::class)->encode([
            'user_id' => 5,
            'email' => 'admin@example.com',
            'tenant_id' => 3,
        ]);

        $this->getJson('/v2/admin/matriculas/vencimentos/hoje', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_post_matriculas_requires_jwt(): void
    {
        $this->postJson('/v2/admin/matriculas', [
            'aluno_id' => 1,
            'plano_id' => 1,
            'dia_vencimento' => 10,
        ])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_post_contas_baixa_requires_jwt(): void
    {
        $this->postJson('/v2/admin/matriculas/contas/1/baixa', [
            'data_vencimento' => '2026-07-13',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_post_alterar_plano_requires_jwt(): void
    {
        $this->postJson('/v2/admin/matriculas/1/alterar-plano', [
            'plano_id' => 2,
        ])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_delete_matriculas_requires_jwt(): void
    {
        $this->deleteJson('/v2/admin/matriculas/1')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_get_delete_preview_requires_jwt(): void
    {
        $this->getJson('/v2/admin/matriculas/1/delete-preview')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }
}
