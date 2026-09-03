<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2SuperAdminAcademiasRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_academias_requires_jwt(): void
    {
        $this->getJson('/v2/superadmin/academias')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_academias_rejects_non_superadmin(): void
    {
        $token = $this->tokenParaPapel(3);

        $this->getJson('/v2/superadmin/academias', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden()
            ->assertJsonPath('erro', 'Acesso negado. Apenas Super Admin pode acessar este recurso.');
    }

    public function test_index_retorna_academias(): void
    {
        $token = $this->tokenParaPapel(4);

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminAcademiaService::class);
        $service->shouldReceive('listarAcademias')
            ->once()
            ->with([])
            ->andReturn([
                'status' => 200,
                'body' => ['academias' => [['id' => 2, 'nome' => 'CrossFit ABC']]],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminAcademiaService::class, $service);

        $this->getJson('/v2/superadmin/academias', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('academias.0.nome', 'CrossFit ABC');
    }

    public function test_store_cria_academia(): void
    {
        $token = $this->tokenParaPapel(4);
        $payload = ['nome' => 'Nova Academia', 'email' => 'a@test.com', 'senha_admin' => '123456'];

        $service = Mockery::mock(\App\Services\SuperAdmin\SuperAdminAcademiaService::class);
        $service->shouldReceive('criarAcademia')
            ->once()
            ->with(Mockery::subset($payload))
            ->andReturn([
                'status' => 201,
                'body' => ['message' => 'Academia e administrador criados com sucesso'],
            ]);
        $this->app->instance(\App\Services\SuperAdmin\SuperAdminAcademiaService::class, $service);

        $this->postJson('/v2/superadmin/academias', $payload, [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Academia e administrador criados com sucesso');
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
