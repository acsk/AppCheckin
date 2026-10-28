<?php

namespace Tests\Unit;

use App\Services\Asaas\AsaasService;
use App\Services\MercadoPago\MercadoPagoService;
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
            is_active INTEGER,
            enable_pix INTEGER DEFAULT 1,
            enable_boleto INTEGER DEFAULT 1,
            enable_credit_card INTEGER DEFAULT 1
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
                'access_token_prod' => '',
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

    public function test_inactive_hidden_from_aluno_and_admin(): void
    {
        DB::table('tenant_payment_credentials')->insert([
            [
                'tenant_id' => 5,
                'provider' => 'mercadopago',
                'environment' => 'production',
                'access_token_test' => '',
                'access_token_prod' => 'mp-token',
                'is_active' => 0,
                'enable_pix' => 1,
            ],
            [
                'tenant_id' => 5,
                'provider' => 'asaas',
                'environment' => 'sandbox',
                'access_token_test' => '$aact_hmlg_xxxxxxxx',
                'access_token_prod' => '',
                'is_active' => 1,
                'enable_pix' => 1,
            ],
        ]);

        $factory = new PaymentGatewayFactory();
        $aluno = $factory->listarDisponiveis(5, false, 'pix');
        $admin = $factory->listarDisponiveis(5, true, 'pix');

        $this->assertSame([], array_column($aluno, 'id'));
        $this->assertSame(['asaas'], array_column($admin, 'id'));

        $escolhaAdmin = $factory->resolverPix(5, true, null);
        $this->assertTrue($escolhaAdmin['ok']);
        $this->assertSame('asaas', $escolhaAdmin['provider']);
    }

    public function test_cartao_desligado_no_mp_nao_lista_mp_para_cartao(): void
    {
        DB::table('tenant_payment_credentials')->insert([
            [
                'tenant_id' => 8,
                'provider' => 'mercadopago',
                'environment' => 'production',
                'access_token_test' => '',
                'access_token_prod' => 'APP_USR-mp',
                'is_active' => 1,
                'enable_pix' => 1,
                'enable_boleto' => 1,
                'enable_credit_card' => 0,
            ],
            [
                'tenant_id' => 8,
                'provider' => 'asaas',
                'environment' => 'production',
                'access_token_test' => '',
                'access_token_prod' => '$aact_prod_xxxxxxxx',
                'is_active' => 1,
                'enable_pix' => 1,
                'enable_boleto' => 1,
                'enable_credit_card' => 1,
            ],
        ]);

        $factory = new PaymentGatewayFactory();
        $cartao = $factory->listarDisponiveis(8, false, 'cartao');
        $pix = $factory->listarDisponiveis(8, false, 'pix');
        $metodos = $factory->metodosHabilitados(8, false);

        $this->assertSame(['asaas'], array_column($cartao, 'id'));
        $this->assertSame(['asaas', 'mercadopago'], array_column($pix, 'id'));
        $this->assertTrue($metodos['cartao']);
        $this->assertTrue($metodos['pix']);

        $escolhaCartao = $factory->resolverMetodo(8, false, 'cartao', null);
        $this->assertTrue($escolhaCartao['ok']);
        $this->assertSame('asaas', $escolhaCartao['provider']);

        $escolhaPix = $factory->resolverPix(8, false, null);
        $this->assertFalse($escolhaPix['ok']);
        $this->assertSame('ESCOLHA_GATEWAY', $escolhaPix['code']);
    }

    public function test_cartao_some_se_nenhum_provedor_visivel_tem_cartao(): void
    {
        DB::table('tenant_payment_credentials')->insert([
            [
                'tenant_id' => 9,
                'provider' => 'mercadopago',
                'environment' => 'production',
                'access_token_test' => '',
                'access_token_prod' => 'APP_USR-mp',
                'is_active' => 1,
                'enable_pix' => 1,
                'enable_boleto' => 0,
                'enable_credit_card' => 0,
            ],
            [
                'tenant_id' => 9,
                'provider' => 'asaas',
                'environment' => 'sandbox',
                'access_token_test' => '$aact_hmlg_xxxxxxxx',
                'access_token_prod' => '',
                'is_active' => 1,
                'enable_pix' => 1,
                'enable_boleto' => 1,
                'enable_credit_card' => 1,
            ],
        ]);

        $factory = new PaymentGatewayFactory();
        $aluno = $factory->metodosHabilitados(9, false);
        $admin = $factory->metodosHabilitados(9, true);

        $this->assertFalse($aluno['cartao']);
        $this->assertTrue($aluno['pix']);
        $this->assertTrue($admin['cartao']);
    }

    public function test_mp_com_pix_nao_entra_na_lista_de_cartao(): void
    {
        DB::table('tenant_payment_credentials')->insert([
            'tenant_id' => 11,
            'provider' => 'mercadopago',
            'environment' => 'production',
            'access_token_test' => '',
            'access_token_prod' => 'APP_USR-mp',
            'is_active' => 1,
            'enable_pix' => 1,
            'enable_boleto' => 0,
            'enable_credit_card' => 0,
        ]);

        $factory = new PaymentGatewayFactory();
        $this->assertSame(['mercadopago'], array_column($factory->listarDisponiveis(11, false, 'pix'), 'id'));
        $this->assertSame([], $factory->listarDisponiveis(11, false, 'cartao'));
        $this->assertFalse($factory->metodosHabilitados(11, false)['cartao']);
        $escolha = $factory->resolverMetodo(11, false, 'cartao', null);
        $this->assertFalse($escolha['ok']);
        $this->assertSame('SEM_GATEWAY', $escolha['code']);
    }
}
