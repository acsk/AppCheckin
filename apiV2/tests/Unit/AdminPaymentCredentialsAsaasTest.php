<?php

namespace Tests\Unit;

use App\Repositories\TenantPaymentCredentialsRepository;
use App\Services\Admin\AdminPaymentCredentialsService;
use App\Services\EncryptionService;
use Mockery;
use Tests\TestCase;

class AdminPaymentCredentialsAsaasTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_asaas_sandbox_requires_api_key(): void
    {
        $repo = Mockery::mock(TenantPaymentCredentialsRepository::class);
        $repo->shouldReceive('obterPorTenantProvider')->once()->with(1, 'asaas')->andReturn(null);
        $encryption = Mockery::mock(EncryptionService::class);

        $service = new AdminPaymentCredentialsService($repo, $encryption);
        $result = $service->salvar(1, [
            'provider' => 'asaas',
            'environment' => 'sandbox',
            'is_active' => true,
        ]);

        $this->assertSame(400, $result['status']);
        $this->assertStringContainsString('API key', $result['body']['message']);
    }

    public function test_asaas_rejects_key_without_official_prefix(): void
    {
        $repo = Mockery::mock(TenantPaymentCredentialsRepository::class);
        $repo->shouldReceive('obterPorTenantProvider')->once()->with(1, 'asaas')->andReturn(null);
        $encryption = Mockery::mock(EncryptionService::class);
        $encryption->shouldNotReceive('encrypt');

        $service = new AdminPaymentCredentialsService($repo, $encryption);
        $result = $service->salvar(1, [
            'provider' => 'asaas',
            'environment' => 'sandbox',
            'access_token_test' => '00000000-0000-0000-0000-000000000000',
            'is_active' => true,
        ]);

        $this->assertSame(400, $result['status']);
        $this->assertStringContainsString('$aact_hmlg_', $result['body']['message']);
    }

    public function test_asaas_encrypts_and_saves_sandbox_key(): void
    {
        $repo = Mockery::mock(TenantPaymentCredentialsRepository::class);
        $repo->shouldReceive('obterPorTenantProvider')->once()->with(1, 'asaas')->andReturn(null);
        $repo->shouldReceive('existePorTenantProvider')->once()->with(1, 'asaas')->andReturn(false);
        $repo->shouldReceive('inserir')->once()->with(1, Mockery::on(function ($dados) {
            return $dados['provider'] === 'asaas'
                && $dados['environment'] === 'sandbox'
                && $dados['access_token_test'] === 'cipher';
        }));

        $encryption = Mockery::mock(EncryptionService::class);
        $encryption->shouldReceive('encrypt')->once()->with('$aact_hmlg_xxxxxxxx')->andReturn('cipher');

        $service = new AdminPaymentCredentialsService($repo, $encryption);
        $result = $service->salvar(1, [
            'provider' => 'asaas',
            'environment' => 'sandbox',
            'access_token_test' => '$aact_hmlg_xxxxxxxx',
            'is_active' => true,
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['success']);
    }

    public function test_alterar_ambiente_keeps_encrypted_keys(): void
    {
        $rowSandbox = [
            'provider' => 'mercadopago',
            'environment' => 'sandbox',
            'is_active' => 1,
            'has_token_test' => true,
            'has_token_prod' => true,
            'public_key_test' => null,
            'public_key_prod' => null,
        ];
        $rowProd = [...$rowSandbox, 'environment' => 'production'];

        $repo = Mockery::mock(TenantPaymentCredentialsRepository::class);
        $repo->shouldReceive('obterPorTenantProvider')
            ->twice()
            ->with(1, 'mercadopago')
            ->andReturn($rowSandbox, $rowProd);
        $repo->shouldReceive('atualizarAmbiente')->once()->with(1, 'mercadopago', 'production');

        $service = new AdminPaymentCredentialsService($repo, Mockery::mock(EncryptionService::class));
        $result = $service->alterarAmbiente(1, [
            'provider' => 'mercadopago',
            'environment' => 'production',
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame('production', $result['body']['data']['environment']);
        $this->assertStringContainsString('criptografadas', $result['body']['message']);
    }

    public function test_alterar_ambiente_requires_saved_production_token(): void
    {
        $repo = Mockery::mock(TenantPaymentCredentialsRepository::class);
        $repo->shouldReceive('obterPorTenantProvider')->once()->with(1, 'mercadopago')->andReturn([
            'provider' => 'mercadopago',
            'environment' => 'sandbox',
            'has_token_test' => true,
            'has_token_prod' => false,
        ]);
        $repo->shouldNotReceive('atualizarAmbiente');

        $service = new AdminPaymentCredentialsService($repo, Mockery::mock(EncryptionService::class));
        $result = $service->alterarAmbiente(1, [
            'provider' => 'mercadopago',
            'environment' => 'production',
        ]);

        $this->assertSame(400, $result['status']);
        $this->assertStringContainsString('produção', $result['body']['message']);
    }
}
