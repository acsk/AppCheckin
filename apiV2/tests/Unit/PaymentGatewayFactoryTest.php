<?php

namespace Tests\Unit;

use App\Services\Asaas\AsaasService;
use App\Services\MercadoPagoService;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class PaymentGatewayFactoryTest extends TestCase
{
    use RequiresSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();
        DB::statement('CREATE TABLE tenant_payment_credentials (
            id INTEGER PRIMARY KEY,
            tenant_id INTEGER,
            provider TEXT,
            environment TEXT,
            access_token_test TEXT,
            access_token_prod TEXT,
            is_active INTEGER
        )');
    }

    public function test_defaults_to_mercadopago(): void
    {
        $factory = new PaymentGatewayFactory();
        $this->assertSame('mercadopago', $factory->providerForTenant(9));
        $this->assertInstanceOf(MercadoPagoService::class, $factory->pixGateway(9));
    }

    public function test_returns_asaas_when_configured(): void
    {
        DB::table('tenant_payment_credentials')->insert([
            'tenant_id' => 2,
            'provider' => 'asaas',
            'environment' => 'sandbox',
            'access_token_test' => 'encrypted',
            'is_active' => 1,
        ]);

        $factory = new PaymentGatewayFactory();
        $this->assertSame('asaas', $factory->providerForTenant(2));
        $this->assertInstanceOf(MercadoPagoService::class, $factory->pixGateway(2));
    }

    public function test_sandbox_hidden_from_non_admin(): void
    {
        DB::table('tenant_payment_credentials')->insert([
            [
                'tenant_id' => 4,
                'provider' => 'mercadopago',
                'environment' => 'production',
                'access_token_test' => '',
                'access_token_prod' => 'mp',
                'is_active' => 1,
            ],
            [
                'tenant_id' => 4,
                'provider' => 'asaas',
                'environment' => 'sandbox',
                'access_token_test' => '$aact_hmlg_xxxxxxxx',
                'is_active' => 1,
            ],
        ]);

        $factory = new PaymentGatewayFactory();
        $aluno = $factory->listarDisponiveis(4, false);
        $admin = $factory->listarDisponiveis(4, true);

        $this->assertSame(['mercadopago'], array_column($aluno, 'id'));
        $this->assertContains('mercadopago', array_column($admin, 'id'));
        $this->assertContains('asaas', array_column($admin, 'id'));

        $escolha = $factory->resolverPix(4, true, null);
        $this->assertFalse($escolha['ok']);
        $this->assertSame('ESCOLHA_GATEWAY', $escolha['code']);
        $this->assertSame(409, $escolha['status']);
    }

    public function test_inactive_asaas_is_ignored_even_with_keys(): void
    {
        DB::table('tenant_payment_credentials')->insert([
            [
                'tenant_id' => 5,
                'provider' => 'mercadopago',
                'environment' => 'sandbox',
                'access_token_test' => 'mp-token',
                'is_active' => 1,
            ],
            [
                'tenant_id' => 5,
                'provider' => 'asaas',
                'environment' => 'sandbox',
                'access_token_test' => '$aact_hmlg_xxxxxxxx',
                'is_active' => 0,
            ],
        ]);

        $factory = new PaymentGatewayFactory();
        $admin = $factory->listarDisponiveis(5, true);

        $this->assertSame(['mercadopago'], array_column($admin, 'id'));
        $this->assertInstanceOf(MercadoPagoService::class, $factory->pixGateway(5));
    }
}
