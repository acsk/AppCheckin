<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;

class V2AdminPaymentCredentialsRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_payment_credentials_requires_jwt(): void
    {
        $this->getJson('/v2/admin/payment-credentials')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MISSING_TOKEN');
    }

    public function test_payment_credentials_rejects_non_admin(): void
    {
        $token = $this->tokenParaPapel(1);

        $this->getJson('/v2/admin/payment-credentials', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden()
            ->assertJsonPath('erro', 'Acesso negado. Apenas administradores podem acessar este recurso.');
    }

    public function test_obter_retorna_vazio_quando_sem_credenciais(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminPaymentCredentialsService::class);
        $service->shouldReceive('obter')
            ->once()
            ->with(3)
            ->andReturn([
                'status' => 200,
                'body' => [
                    'success' => true,
                    'data' => null,
                    'message' => 'Nenhuma credencial configurada',
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminPaymentCredentialsService::class, $service);

        $this->getJson('/v2/admin/payment-credentials', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    }

    public function test_salvar_retorna_sucesso(): void
    {
        $token = $this->tokenParaPapel(3);

        $payload = [
            'provider' => 'mercadopago',
            'environment' => 'sandbox',
            'public_key_test' => 'TEST-key',
            'is_active' => true,
        ];

        $service = Mockery::mock(\App\Services\Admin\AdminPaymentCredentialsService::class);
        $service->shouldReceive('salvar')
            ->once()
            ->with(3, Mockery::subset($payload))
            ->andReturn([
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Credenciais cadastradas com sucesso',
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminPaymentCredentialsService::class, $service);

        $this->postJson('/v2/admin/payment-credentials', $payload, [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Credenciais cadastradas com sucesso');
    }

    public function test_testar_retorna_erro_sem_credenciais(): void
    {
        $token = $this->tokenParaPapel(3);

        $service = Mockery::mock(\App\Services\Admin\AdminPaymentCredentialsService::class);
        $service->shouldReceive('testar')
            ->once()
            ->with(3)
            ->andReturn([
                'status' => 400,
                'body' => [
                    'success' => false,
                    'message' => 'Credenciais não configuradas ou inválidas',
                ],
            ]);
        $this->app->instance(\App\Services\Admin\AdminPaymentCredentialsService::class, $service);

        $this->postJson('/v2/admin/payment-credentials/test', [], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Credenciais não configuradas ou inválidas');
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
