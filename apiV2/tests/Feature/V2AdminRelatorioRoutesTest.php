<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2AdminRelatorioRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_planos_ciclos_requires_jwt(): void
    {
        $this->getJson('/v2/admin/relatorios/planos-ciclos')
            ->assertUnauthorized();
    }

    public function test_planos_ciclos_retorna_relatorio(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminRelatorioService::class);
        $service->shouldReceive('planosCiclos')
            ->once()
            ->with(3, Mockery::type('array'))
            ->andReturn([
                'status' => 200,
                'body' => ['success' => true, 'planos' => [], 'resumo' => ['total_planos' => 0]],
            ]);
        $this->app->instance(\App\Services\Admin\AdminRelatorioService::class, $service);

        $this->getJson('/v2/admin/relatorios/planos-ciclos', [
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
