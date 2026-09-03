<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2AdminMatriculaExtrasRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_simular_cancelamento_requires_jwt(): void
    {
        $this->getJson('/v2/admin/matriculas/1/simular-cancelamento')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_simular_cancelamento_ok_for_admin(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminMatriculaService::class);
        $service->shouldReceive('simularCancelamento')
            ->once()
            ->with(1, 3)
            ->andReturn([
                'status' => 200,
                'body' => ['matricula_id' => 1, 'valor_proporcional_credito' => 50.0],
            ]);
        $this->app->instance(\App\Services\Admin\AdminMatriculaService::class, $service);

        $this->getJson('/v2/admin/matriculas/1/simular-cancelamento', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('matricula_id', 1);
    }

    public function test_cancelar_com_credito_requires_jwt(): void
    {
        $this->postJson('/v2/admin/matriculas/1/cancelar-com-credito', ['gerar_credito' => true])
            ->assertUnauthorized();
    }

    public function test_confirmar_pagamento_matricula_requires_jwt(): void
    {
        $this->postJson('/v2/admin/matriculas/1/pagamentos/2/confirmar', [])
            ->assertUnauthorized();
    }

    public function test_confirmar_pagamento_matricula_delega_service(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminMatriculaService::class);
        $service->shouldReceive('confirmarPagamentoMatricula')
            ->once()
            ->with(1, 2, 3, 5, Mockery::type('array'))
            ->andReturn([
                'status' => 200,
                'body' => ['message' => 'Pagamento confirmado'],
            ]);
        $this->app->instance(\App\Services\Admin\AdminMatriculaService::class, $service);

        $this->postJson('/v2/admin/matriculas/1/pagamentos/2/confirmar', [
            'forma_pagamento_id' => 1,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Pagamento confirmado');
    }

    public function test_dar_baixa_pacote_requires_jwt(): void
    {
        $this->postJson('/v2/admin/matriculas/pacote-contrato/10/baixa', [])
            ->assertUnauthorized();
    }

    public function test_criar_assinatura_matricula_requires_jwt(): void
    {
        $this->postJson('/v2/admin/matriculas/1/assinatura', [])
            ->assertUnauthorized();
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
