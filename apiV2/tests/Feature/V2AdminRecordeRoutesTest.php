<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2AdminRecordeRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_recordes_requires_jwt(): void
    {
        $this->getJson('/v2/admin/recordes/definicoes')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_recordes_rejects_non_admin(): void
    {
        $token = $this->tokenParaPapel(1);

        $this->getJson('/v2/admin/recordes/definicoes', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden();
    }

    public function test_listar_definicoes_happy_path(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminRecordeService::class);
        $service->shouldReceive('listarDefinicoes')
            ->once()
            ->with(3, Mockery::type('array'))
            ->andReturn([
                'status' => 200,
                'body' => [
                    'definicoes' => [['id' => 1, 'nome' => 'Back Squat']],
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminRecordeService::class, $service);

        $this->getJson('/v2/admin/recordes/definicoes', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('definicoes.0.nome', 'Back Squat');
    }

    public function test_listar_recordes_happy_path(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminRecordeService::class);
        $service->shouldReceive('listarRecordes')
            ->once()
            ->with(3, Mockery::type('array'))
            ->andReturn([
                'status' => 200,
                'body' => [
                    'recordes' => [['id' => 10, 'definicao_nome' => 'Back Squat']],
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminRecordeService::class, $service);

        $this->getJson('/v2/admin/recordes', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('recordes.0.definicao_nome', 'Back Squat');
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
