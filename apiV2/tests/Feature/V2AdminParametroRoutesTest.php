<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2AdminParametroRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_parametros_requires_jwt(): void
    {
        $this->getJson('/v2/admin/parametros')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_parametros_rejects_non_admin(): void
    {
        $token = $this->tokenParaPapel(1);

        $this->getJson('/v2/admin/parametros', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden();
    }

    public function test_index_retorna_categorias(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminParametroService::class);
        $service->shouldReceive('index')
            ->once()
            ->with(3)
            ->andReturn([
                'status' => 200,
                'body' => [
                    'success' => true,
                    'data' => [['categoria' => ['codigo' => 'pagamentos'], 'parametros' => []]],
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminParametroService::class, $service);

        $this->getJson('/v2/admin/parametros', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_patch_parametro(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminParametroService::class);
        $service->shouldReceive('patch')
            ->once()
            ->with(3, 'habilitar_pix', true, 5)
            ->andReturn([
                'status' => 200,
                'body' => ['success' => true, 'message' => 'Parâmetro atualizado com sucesso'],
            ]);
        $this->app->instance(\App\Services\Admin\AdminParametroService::class, $service);

        $this->patchJson('/v2/admin/parametros/habilitar_pix', ['valor' => true], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
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
