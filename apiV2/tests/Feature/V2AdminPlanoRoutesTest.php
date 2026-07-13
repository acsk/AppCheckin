<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2AdminPlanoRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_planos_list_requires_jwt(): void
    {
        $this->getJson('/v2/planos')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_planos_list_ok_with_jwt(): void
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        $usuarios = Mockery::mock(\App\Repositories\UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')->once()->with(5)->andReturn([
            'id' => 5,
            'nome' => 'Admin',
            'email' => 'admin@example.com',
            'tenant_id' => 3,
            'papel_id' => 3,
        ]);
        $this->app->instance(\App\Repositories\UsuarioRepository::class, $usuarios);

        $service = Mockery::mock(\App\Services\Admin\AdminPlanoService::class);
        $service->shouldReceive('index')->once()->with(3, null)->andReturn([
            'status' => 200,
            'body' => ['planos' => [['id' => 1, 'nome' => '2x']], 'total' => 1],
        ]);
        $this->app->instance(\App\Services\Admin\AdminPlanoService::class, $service);

        $token = app(\App\Services\JwtService::class)->encode([
            'user_id' => 5,
            'email' => 'admin@example.com',
            'tenant_id' => 3,
        ]);

        $this->getJson('/v2/planos', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('planos.0.nome', '2x');
    }

    public function test_admin_create_plano_validation(): void
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        $usuarios = Mockery::mock(\App\Repositories\UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')->once()->with(5)->andReturn([
            'id' => 5,
            'nome' => 'Admin',
            'email' => 'admin@example.com',
            'tenant_id' => 3,
            'papel_id' => 3,
        ]);
        $this->app->instance(\App\Repositories\UsuarioRepository::class, $usuarios);

        $token = app(\App\Services\JwtService::class)->encode([
            'user_id' => 5,
            'email' => 'admin@example.com',
            'tenant_id' => 3,
        ]);

        $this->postJson('/v2/admin/planos', [], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_admin_frequencias_ok(): void
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        $usuarios = Mockery::mock(\App\Repositories\UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')->once()->with(5)->andReturn([
            'id' => 5,
            'nome' => 'Admin',
            'email' => 'admin@example.com',
            'tenant_id' => 3,
            'papel_id' => 3,
        ]);
        $this->app->instance(\App\Repositories\UsuarioRepository::class, $usuarios);

        $service = Mockery::mock(\App\Services\Admin\AdminPlanoCicloService::class);
        $service->shouldReceive('listarFrequencias')->once()->andReturn([
            'status' => 200,
            'body' => ['success' => true, 'data' => [['id' => 1, 'nome' => 'Mensal']]],
        ]);
        $this->app->instance(\App\Services\Admin\AdminPlanoCicloService::class, $service);

        $token = app(\App\Services\JwtService::class)->encode([
            'user_id' => 5,
            'email' => 'admin@example.com',
            'tenant_id' => 3,
        ]);

        $this->getJson('/v2/admin/assinatura-frequencias', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('data.0.nome', 'Mensal');
    }
}
