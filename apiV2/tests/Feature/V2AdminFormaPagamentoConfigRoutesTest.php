<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2AdminFormaPagamentoConfigRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_formas_pagamento_config_requires_jwt(): void
    {
        $this->getJson('/v2/admin/formas-pagamento-config')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_formas_pagamento_config_rejects_non_admin(): void
    {
        $token = $this->tokenParaPapel(1);

        $this->getJson('/v2/admin/formas-pagamento-config', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden()
            ->assertJsonPath('erro', 'Acesso negado. Apenas administradores podem acessar este recurso.');
    }

    public function test_index_retorna_lista(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminFormaPagamentoConfigService::class);
        $service->shouldReceive('listar')
            ->once()
            ->with(3, false)
            ->andReturn([
                'status' => 200,
                'body' => [
                    'formas_pagamento' => [
                        ['id' => 1, 'forma_pagamento_nome' => 'PIX', 'ativo' => 1],
                    ],
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminFormaPagamentoConfigService::class, $service);

        $this->getJson('/v2/admin/formas-pagamento-config', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('formas_pagamento.0.forma_pagamento_nome', 'PIX');
    }

    public function test_show_retorna_404_quando_nao_encontrado(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminFormaPagamentoConfigService::class);
        $service->shouldReceive('buscar')
            ->once()
            ->with(99, 3)
            ->andReturn([
                'status' => 404,
                'body' => ['type' => 'error', 'message' => 'Configuração não encontrada'],
            ]);
        $this->app->instance(\App\Services\Admin\AdminFormaPagamentoConfigService::class, $service);

        $this->getJson('/v2/admin/formas-pagamento-config/99', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Configuração não encontrada');
    }

    public function test_update_retorna_sucesso(): void
    {
        $token = $this->tokenParaPapel(3);

        $payload = [
            'ativo' => 1,
            'taxa_percentual' => 2.5,
            'taxa_fixa' => 0,
            'aceita_parcelamento' => 0,
        ];

        $service = Mockery::mock(\App\Services\Admin\AdminFormaPagamentoConfigService::class);
        $service->shouldReceive('atualizar')
            ->once()
            ->with(5, 3, Mockery::subset($payload))
            ->andReturn([
                'status' => 200,
                'body' => ['type' => 'success', 'message' => 'Configuração atualizada com sucesso'],
            ]);
        $this->app->instance(\App\Services\Admin\AdminFormaPagamentoConfigService::class, $service);

        $this->putJson('/v2/admin/formas-pagamento-config/5', $payload, [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Configuração atualizada com sucesso');
    }

    public function test_calcular_taxas_requer_campos(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminFormaPagamentoConfigService::class);
        $service->shouldReceive('calcularTaxas')
            ->once()
            ->with(3, [])
            ->andReturn([
                'status' => 400,
                'body' => ['type' => 'error', 'message' => 'Forma de pagamento e valor são obrigatórios'],
            ]);
        $this->app->instance(\App\Services\Admin\AdminFormaPagamentoConfigService::class, $service);

        $this->postJson('/v2/admin/formas-pagamento-config/calcular-taxas', [], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Forma de pagamento e valor são obrigatórios');
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
