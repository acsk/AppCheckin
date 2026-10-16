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
        $this->assertInstanceOf(AsaasService::class, $factory->pixGateway(2));
    }
}
