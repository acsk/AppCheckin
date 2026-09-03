<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2SuperAdminPlanosSistemaRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_planos_sistema_requires_jwt(): void
    {
        $this->getJson('/v2/superadmin/planos-sistema')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_index_lista_planos(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminPlanoSistemaService::class);
        $service->shouldReceive('index')
            ->once()
            ->with([])
            ->andReturn([
                'status' => 200,
                'body' => ['planos' => [['id' => 1, 'nome' => 'Starter']], 'total' => 1],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminPlanoSistemaService::class, $service);

        $this->getJson('/v2/superadmin/planos-sistema', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('planos.0.nome', 'Starter');
    }

    public function test_store_cria_plano(): void
    {
        $token = $this->tokenParaPapel(4);
        $payload = ['nome' => 'Pro', 'valor' => 199.9];

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminPlanoSistemaService::class);
        $service->shouldReceive('create')
            ->once()
            ->with(Mockery::subset($payload))
            ->andReturn([
                'status' => 201,
                'body' => ['message' => 'Plano criado com sucesso', 'plano' => ['id' => 5]],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminPlanoSistemaService::class, $service);

        $this->postJson('/v2/superadmin/planos-sistema', $payload, [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Plano criado com sucesso');
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
