<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2AdminPagamentoCreditoRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_pagamentos_plano_requires_jwt(): void
    {
        $this->getJson('/v2/admin/pagamentos-plano')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_pagamentos_plano_rejects_non_admin(): void
    {
        $token = $this->tokenParaPapel(1);

        $this->getJson('/v2/admin/pagamentos-plano', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden()
            ->assertJsonPath('erro', 'Acesso negado. Apenas administradores podem acessar este recurso.');
    }

    public function test_index_pagamentos_plano(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminPagamentoPlanoService::class);
        $service->shouldReceive('index')
            ->once()
            ->with(3, Mockery::type('array'))
            ->andReturn([
                'status' => 200,
                'body' => ['pagamentos' => [['id' => 1, 'valor' => 100]]],
            ]);
        $this->app->instance(\App\Services\Admin\AdminPagamentoPlanoService::class, $service);

        $this->getJson('/v2/admin/pagamentos-plano', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('pagamentos.0.id', 1);
    }

    public function test_credito_saldo(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminCreditoAlunoService::class);
        $service->shouldReceive('saldo')
            ->once()
            ->with(3, 5)
            ->andReturn([
                'status' => 200,
                'body' => ['saldo_total' => 150.0, 'creditos_ativos' => []],
            ]);
        $this->app->instance(\App\Services\Admin\AdminCreditoAlunoService::class, $service);

        $this->getJson('/v2/admin/alunos/5/creditos/saldo', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('saldo_total', 150);
    }

    public function test_matricula_descontos_listar(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminMatriculaDescontoService::class);
        $service->shouldReceive('listar')
            ->once()
            ->with(3, 10)
            ->andReturn([
                'status' => 200,
                'body' => ['descontos' => [['id' => 1, 'motivo' => 'Promo']]],
            ]);
        $this->app->instance(\App\Services\Admin\AdminMatriculaDescontoService::class, $service);

        $this->getJson('/v2/admin/matriculas/10/descontos', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('descontos.0.motivo', 'Promo');
    }

    public function test_contas_receber_index(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminContasReceberService::class);
        $service->shouldReceive('index')
            ->once()
            ->with(3, Mockery::type('array'))
            ->andReturn([
                'status' => 200,
                'body' => ['contas' => [], 'total' => 0],
            ]);
        $this->app->instance(\App\Services\Admin\AdminContasReceberService::class, $service);

        $this->getJson('/v2/admin/contas-receber', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('total', 0);
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
