<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2AdminWodRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_wods_requires_jwt(): void
    {
        $this->getJson('/v2/admin/wods')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_wods_rejects_non_admin(): void
    {
        $token = $this->tokenParaPapel(1);

        $this->getJson('/v2/admin/wods', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden();
    }

    public function test_index_retorna_lista(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminWodService::class);
        $service->shouldReceive('index')
            ->once()
            ->with(3, Mockery::type('array'))
            ->andReturn([
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'WODs listados com sucesso',
                    'data' => [['id' => 1, 'titulo' => 'Fran']],
                    'total' => 1,
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminWodService::class, $service);

        $this->getJson('/v2/admin/wods', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('type', 'success')
            ->assertJsonPath('data.0.titulo', 'Fran');
    }

    public function test_listar_modalidades_happy_path(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminWodService::class);
        $service->shouldReceive('listarModalidades')
            ->once()
            ->with(3)
            ->andReturn([
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Modalidades listadas com sucesso',
                    'data' => [['id' => 2, 'nome' => 'CrossFit']],
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminWodService::class, $service);

        $this->getJson('/v2/admin/wods/modalidades', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.0.nome', 'CrossFit');
    }

    private function tokenParaPapel(int $papelId): string
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        $usuarios = Mockery::mock(\App\Repositories\UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')
            ->with(5)
            ->andReturn([
                'id' => 5,
                'nome' => 'Admin',
                'email' => 'admin@example.com',
                'tenant_id' => 3,
                'papel_id' => $papelId,
            ]);
        $this->app->instance(\App\Repositories\UsuarioRepository::class, $usuarios);

        return app(\App\Services\JwtService::class)->encode([
            'user_id' => 5,
            'email' => 'admin@example.com',
            'tenant_id' => 3,
        ]);
    }
}
