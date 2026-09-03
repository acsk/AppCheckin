<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2AdminDashboardRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_dashboard_requires_jwt(): void
    {
        $this->getJson('/v2/admin/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_dashboard_rejects_non_admin(): void
    {
        $token = $this->tokenParaPapel(1);

        $this->getJson('/v2/admin/dashboard', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden();
    }

    public function test_index_retorna_stats(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminDashboardService::class);
        $service->shouldReceive('index')
            ->once()
            ->with(3)
            ->andReturn([
                'status' => 200,
                'body' => [
                    'total_alunos' => 10,
                    'contas_pendentes_valor' => 150.0,
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminDashboardService::class, $service);

        $this->getJson('/v2/admin/dashboard', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('total_alunos', 10)
            ->assertJsonPath('contas_pendentes_valor', 150);
    }

    public function test_cards_retorna_dados(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminDashboardService::class);
        $service->shouldReceive('cards')
            ->once()
            ->with(3)
            ->andReturn([
                'status' => 200,
                'body' => [
                    'success' => true,
                    'data' => [
                        'total_alunos' => ['total' => 10, 'ativos' => 8, 'inativos' => 2],
                        'receita_mensal' => ['valor' => 500, 'valor_formatado' => 'R$ 500,00', 'contas_pendentes' => 3],
                        'checkins_hoje' => ['hoje' => 5, 'no_mes' => 120],
                        'planos_vencendo' => ['vencendo' => 2, 'novos_este_mes' => 4],
                    ],
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminDashboardService::class, $service);

        $this->getJson('/v2/admin/dashboard/cards', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_alunos.total', 10);
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
