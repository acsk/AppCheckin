<?php

namespace Tests\Unit;

use App\Services\Admin\AdminPagamentoPlanoService;
use App\Services\Asaas\AsaasWebhookService;
use App\Services\PagamentoPlanoService;
use App\Support\CobrancaAssinatura;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class PagamentoBaixaAutomaticaTest extends TestCase
{
    use RequiresSqlite;

    public function test_baixa_pelo_asaas_troca_o_texto_do_mercado_pago(): void
    {
        $obs = AsaasWebhookService::observacaoPagoAsaas(
            'Aguardando pagamento via Mercado Pago [Valor cobrado R$ 215,77]',
            'pay_wjqnjllhni8q8uol',
            ['billingType' => 'CREDIT_CARD']
        );

        $this->assertSame('Pago via Asaas (Cartão) [Valor cobrado R$ 215,77] [Asaas pay_wjqnjllhni8q8uol]', $obs);
    }

    public function test_corrige_observacao_ja_gravada_sem_duplicar_o_id(): void
    {
        $obs = AsaasWebhookService::observacaoPagoAsaas(
            'Aguardando pagamento via Mercado Pago [Valor cobrado R$ 215,77] [Asaas pay_x] valor R$ 107,89 líquido R$ 102,01 desconto_provedor R$ 5,88',
            'pay_x',
            ['billingType' => 'CREDIT_CARD']
        );

        $this->assertStringStartsWith('Pago via Asaas (Cartão)', $obs);
        $this->assertSame(1, substr_count($obs, 'pay_x'));
        $this->assertStringContainsString('líquido R$ 102,01', $obs);
    }

    public function test_texto_de_espera_usa_o_provedor_da_compra(): void
    {
        $this->assertSame('Aguardando pagamento via Asaas', PagamentoPlanoService::obsAguardando('asaas'));
        $this->assertSame('Aguardando pagamento via Mercado Pago', PagamentoPlanoService::obsAguardando('mercadopago'));
        $this->assertSame('Aguardando pagamento', PagamentoPlanoService::obsAguardando(null));
    }

    public function test_so_parcela_paga_pela_integracao_fica_bloqueada(): void
    {
        $this->requireSqlite();
        Schema::create('tipos_baixa', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
        });
        DB::table('tipos_baixa')->insert([['id' => 1, 'nome' => 'Manual'], ['id' => 4, 'nome' => 'Integração']]);

        $service = (new \ReflectionClass(AdminPagamentoPlanoService::class))->newInstanceWithoutConstructor();
        $bloqueio = new \ReflectionMethod($service, 'bloqueioBaixaAutomatica');

        $auto = $bloqueio->invoke($service, ['status_pagamento_id' => 2, 'tipo_baixa_id' => 4]);
        $this->assertSame(422, $auto['status']);
        $this->assertSame('BAIXA_AUTOMATICA', $auto['body']['code']);

        $this->assertNull($bloqueio->invoke($service, ['status_pagamento_id' => 2, 'tipo_baixa_id' => 1]), 'baixa manual continua editável');
        $this->assertNull($bloqueio->invoke($service, ['status_pagamento_id' => 1, 'tipo_baixa_id' => null]), 'parcela em aberto continua editável');
    }

    public function test_mensalidade_da_assinatura_em_aberto_nao_aceita_baixa_manual(): void
    {
        $this->requireSqlite();
        Schema::create('recorrencia_cobrancas', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('pagamento_plano_id')->nullable();
            $t->string('gateway_payment_id')->nullable();
            $t->string('status');
        });
        DB::table('recorrencia_cobrancas')->insert([
            ['pagamento_plano_id' => 10, 'gateway_payment_id' => 'pay_a', 'status' => 'pendente'],
            ['pagamento_plano_id' => 11, 'gateway_payment_id' => 'pay_b', 'status' => 'vencida'],
            ['pagamento_plano_id' => 12, 'gateway_payment_id' => 'pay_c', 'status' => 'paga'],
            ['pagamento_plano_id' => 13, 'gateway_payment_id' => 'pay_d', 'status' => 'cancelada'],
        ]);

        $this->assertSame([10 => true, 11 => true], CobrancaAssinatura::emAberto([10, 11, 12, 13, 99]));
        $this->assertSame('COBRANCA_AUTOMATICA', CobrancaAssinatura::bloqueio(10)['body']['code']);
        $this->assertNull(CobrancaAssinatura::bloqueio(13), 'assinatura cancelada: a parcela volta a ser manual');
        $this->assertNull(CobrancaAssinatura::bloqueio(99), 'parcela avulsa continua com baixa manual');
    }
}
