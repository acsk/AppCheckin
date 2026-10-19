<?php

namespace Tests\Unit;

use App\Repositories\TenantPaymentCredentialsRepository;
use App\Services\Admin\AdminPaymentCredentialsService;
use App\Services\Asaas\AsaasService;
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
        $repo->shouldReceive('listarPorTenant')->andReturn([]);
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

    public function test_asaas_salva_sobretaxa_de_antecipacao(): void
    {
        $repo = $this->repoNovo(fn ($dados) => $dados['enable_antecipacao_automatica'] === 1
            && $dados['enable_sobretaxa_antecipacao'] === 1
            && (float) $dados['sobretaxa_antecipacao_avista_percentual'] === 1.15
            && (float) $dados['sobretaxa_antecipacao_parcelada_percentual'] === 1.6);
        $asaas = Mockery::mock(AsaasService::class);
        $asaas->shouldReceive('configurarAntecipacaoAutomatica')->once()->with(true)->andReturn(true);

        $result = $this->service($repo, $asaas)->salvar(1, $this->bodyAsaas([
            'enable_antecipacao_automatica' => true,
            'enable_sobretaxa_antecipacao' => true,
            'sobretaxa_antecipacao_avista_percentual' => 1.15,
            'sobretaxa_antecipacao_parcelada_percentual' => 1.6,
        ]));

        $this->assertSame(200, $result['status']);
        $this->assertArrayNotHasKey('aviso', $result['body']);
    }

    public function test_sobretaxa_exige_antecipacao_automatica(): void
    {
        $repo = $this->repoNovo(fn ($dados) => $dados['enable_antecipacao_automatica'] === 0
            && $dados['enable_sobretaxa_antecipacao'] === 0);
        $asaas = Mockery::mock(AsaasService::class);
        $asaas->shouldNotReceive('configurarAntecipacaoAutomatica');

        $result = $this->service($repo, $asaas)->salvar(1, $this->bodyAsaas(['enable_sobretaxa_antecipacao' => true]));

        $this->assertSame(200, $result['status']);
    }

    public function test_asaas_recusa_antecipacao_volta_a_flag_e_avisa(): void
    {
        $repo = $this->repoNovo(fn ($dados) => $dados['enable_antecipacao_automatica'] === 1);
        $repo->shouldReceive('definirAntecipacaoAutomatica')->once()->with(1, 'asaas', false);
        $asaas = Mockery::mock(AsaasService::class);
        $asaas->shouldReceive('configurarAntecipacaoAutomatica')->once()->with(true)
            ->andThrow(new \RuntimeException('Asaas: Sua conta ainda não está aprovada'));

        $result = $this->service($repo, $asaas)->salvar(1, $this->bodyAsaas(['enable_antecipacao_automatica' => true]));

        $this->assertSame(200, $result['status']);
        $this->assertStringContainsString('não foi ligada no Asaas: Sua conta ainda não está aprovada', $result['body']['aviso']);
    }

    public function test_desliga_antecipacao_no_asaas(): void
    {
        $repo = Mockery::mock(TenantPaymentCredentialsRepository::class);
        $repo->shouldReceive('obterPorTenantProvider')->once()->with(1, 'asaas')->andReturn([
            'provider' => 'asaas', 'environment' => 'sandbox', 'is_active' => 1, 'has_token_test' => true,
            'enable_antecipacao_automatica' => 1, 'enable_sobretaxa_antecipacao' => 1,
        ]);
        $repo->shouldReceive('temTokenAmbiente')->andReturn(true);
        $repo->shouldReceive('existePorTenantProvider')->once()->andReturn(true);
        $repo->shouldReceive('listarPorTenant')->andReturn([]);
        $repo->shouldReceive('atualizar')->once()->with(1, 'asaas', Mockery::on(fn ($d) => $d['enable_antecipacao_automatica'] === 0
            && $d['enable_sobretaxa_antecipacao'] === 0));
        $asaas = Mockery::mock(AsaasService::class);
        $asaas->shouldReceive('garantirWebhook');
        $asaas->shouldReceive('configurarAntecipacaoAutomatica')->once()->with(false)->andReturn(false);

        $result = $this->service($repo, $asaas)->salvar(1, [
            'provider' => 'asaas', 'environment' => 'sandbox', 'is_active' => true, 'enable_antecipacao_automatica' => false,
        ]);

        $this->assertSame(200, $result['status']);
    }

    public function test_obter_usa_o_estado_da_antecipacao_na_conta_asaas(): void
    {
        $repo = Mockery::mock(TenantPaymentCredentialsRepository::class);
        $repo->shouldReceive('listarPorTenant')->once()->with(1)->andReturn([[
            'provider' => 'asaas', 'environment' => 'sandbox', 'is_active' => 1, 'has_token_test' => 1,
            'enable_antecipacao_automatica' => 1, 'enable_sobretaxa_antecipacao' => 1,
        ]]);
        $repo->shouldReceive('definirAntecipacaoAutomatica')->once()->with(1, 'asaas', false);
        $asaas = Mockery::mock(AsaasService::class);
        $asaas->shouldReceive('antecipacaoAutomaticaNaConta')->once()->andReturn(false);

        $result = $this->service($repo, $asaas)->obter(1);

        $asaasRow = collect($result['body']['data']['providers'])->firstWhere('provider', 'asaas');
        $this->assertFalse($asaasRow['enable_antecipacao_automatica']);
    }

    private function repoNovo(\Closure $confere): Mockery\MockInterface
    {
        $repo = Mockery::mock(TenantPaymentCredentialsRepository::class);
        $repo->shouldReceive('obterPorTenantProvider')->once()->with(1, 'asaas')->andReturn(null);
        $repo->shouldReceive('existePorTenantProvider')->once()->with(1, 'asaas')->andReturn(false);
        $repo->shouldReceive('listarPorTenant')->andReturn([]);
        $repo->shouldReceive('inserir')->once()->with(1, Mockery::on($confere));

        return $repo;
    }

    private function service(Mockery\MockInterface $repo, Mockery\MockInterface $asaas): AdminPaymentCredentialsService
    {
        $encryption = Mockery::mock(EncryptionService::class);
        $encryption->shouldReceive('encrypt')->andReturn('cipher');
        $asaas->shouldReceive('garantirWebhook')->byDefault();

        return new AdminPaymentCredentialsService($repo, $encryption, fn () => $asaas);
    }

    /** @return array<string, mixed> */
    private function bodyAsaas(array $extra): array
    {
        return array_merge([
            'provider' => 'asaas',
            'environment' => 'sandbox',
            'access_token_test' => '$aact_hmlg_xxxxxxxx',
            'is_active' => true,
        ], $extra);
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
        $repo->shouldReceive('temTokenAmbiente')->once()->with(1, 'mercadopago', 'production')->andReturn(true);
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
        $repo->shouldReceive('temTokenAmbiente')->once()->with(1, 'mercadopago', 'production')->andReturn(false);
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
