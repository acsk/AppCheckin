<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2AdminAssinaturaRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_assinaturas_requires_jwt(): void
    {
        $this->getJson('/v2/admin/assinaturas')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_assinaturas_rejects_non_admin(): void
    {
        $token = $this->tokenParaPapel(1);

        $this->getJson('/v2/admin/assinaturas', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden()
            ->assertJsonPath('erro', 'Acesso negado. Apenas administradores podem acessar este recurso.');
    }

    public function test_index_retorna_lista_paginada(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminAssinaturaService::class);
        $service->shouldReceive('listar')
            ->once()
            ->with(3, Mockery::subset(['page' => '1', 'per_page' => '20']))
            ->andReturn([
                'status' => 200,
                'body' => [
                    'success' => true,
                    'total' => 1,
                    'page' => 1,
                    'per_page' => 20,
                    'total_pages' => 1,
                    'assinaturas' => [
                        [
                            'id' => 10,
                            'aluno_nome' => 'João Silva',
                            'status' => ['codigo' => 'ativa', 'nome' => 'Ativa', 'cor' => '#10b981'],
                        ],
                    ],
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminAssinaturaService::class, $service);

        $this->getJson('/v2/admin/assinaturas?page=1&per_page=20', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assinaturas.0.aluno_nome', 'João Silva');
    }

    public function test_index_repassa_filtros(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminAssinaturaService::class);
        $service->shouldReceive('listar')
            ->once()
            ->with(3, Mockery::subset([
                'status' => 'ativa',
                'tipo_cobranca' => 'recorrente',
                'busca' => 'joao',
            ]))
            ->andReturn([
                'status' => 200,
                'body' => [
                    'success' => true,
                    'total' => 0,
                    'page' => 1,
                    'per_page' => 20,
                    'total_pages' => 0,
                    'assinaturas' => [],
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminAssinaturaService::class, $service);

        $this->getJson('/v2/admin/assinaturas?status=ativa&tipo_cobranca=recorrente&busca=joao', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_historico_aluno_requires_jwt(): void
    {
        $this->getJson('/v2/admin/alunos/1/assinaturas')
            ->assertUnauthorized();
    }

    public function test_historico_aluno_ok(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminAssinaturaService::class);
        $service->shouldReceive('listarPorAluno')
            ->once()
            ->with(3, 1)
            ->andReturn([
                'status' => 200,
                'body' => [
                    'success' => true,
                    'total' => 1,
                    'assinaturas' => [['id' => 10]],
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminAssinaturaService::class, $service);

        $this->getJson('/v2/admin/alunos/1/assinaturas', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_sincronizar_matricula_requires_jwt(): void
    {
        $this->postJson('/v2/admin/assinaturas/1/sincronizar-matricula')
            ->assertUnauthorized();
    }

    public function test_show_assinatura_requires_jwt(): void
    {
        $this->getJson('/v2/admin/assinaturas/1')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_show_delegates_to_service(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminAssinaturaService::class);
        $service->shouldReceive('buscar')
            ->once()
            ->with(7, 3)
            ->andReturn([
                'status' => 200,
                'body' => ['success' => true, 'assinatura' => ['id' => 7]],
            ]);
        $this->app->instance(\App\Services\Admin\AdminAssinaturaService::class, $service);

        $this->getJson('/v2/admin/assinaturas/7', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('assinatura.id', 7);
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
