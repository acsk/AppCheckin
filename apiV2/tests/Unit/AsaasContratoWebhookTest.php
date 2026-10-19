<?php

namespace Tests\Unit;

use App\Repositories\PagamentoContratoRepository;
use App\Services\Asaas\AsaasContratoWebhook;
use App\Services\SuperAdmin\CobrancaContratoService;
use App\Services\SuperAdmin\SuperAdminPagamentoContratoService;
use App\Support\Documento;
use App\Support\ReferenciaExterna;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AsaasContratoWebhookTest extends TestCase
{
    private MockInterface $pagamentos;

    private MockInterface $confirmacao;

    private MockInterface $cobrancas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pagamentos = Mockery::mock(PagamentoContratoRepository::class);
        $this->confirmacao = Mockery::mock(SuperAdminPagamentoContratoService::class);
        $this->cobrancas = Mockery::mock(CobrancaContratoService::class);
    }

    private function webhook(): AsaasContratoWebhook
    {
        return new AsaasContratoWebhook($this->pagamentos, $this->confirmacao, $this->cobrancas);
    }

    public function test_referencia_de_contrato(): void
    {
        $ref = ReferenciaExterna::contrato(77);

        $this->assertMatchesRegularExpression('/^CTR-77-\d+[0-9a-f]{8}$/', $ref);
        $this->assertSame(77, ReferenciaExterna::pagamentoContrato($ref));
        $this->assertNull(ReferenciaExterna::pagamentoContrato(ReferenciaExterna::matricula(77)));
    }

    public function test_documentos(): void
    {
        $this->assertTrue(Documento::cnpjValido('11.444.777/0001-61'));
        $this->assertFalse(Documento::cnpjValido('11.444.777/0001-62'));
        $this->assertTrue(Documento::cpfValido('529.982.247-25'));
        $this->assertFalse(Documento::cpfValido('111.111.111-11'));
    }

    public function test_pagamento_confirmado_ativa_e_cobra_o_proximo_ciclo(): void
    {
        $this->pagamentos->shouldReceive('buscarPorId')->with(77)->andReturn(['id' => 77, 'tenant_plano_id' => 30, 'status_pagamento_id' => 1, 'gateway_billing_type' => 'PIX']);
        $this->confirmacao->shouldReceive('confirmar')->once()
            ->with(77, Mockery::on(fn (array $d) => $d['data_pagamento'] === '2026-10-19'))
            ->andReturn(['status' => 200, 'body' => []]);
        $this->cobrancas->shouldReceive('gerarProxima')->once()->with(30, 'PIX')->andReturn(['id' => 'pay_2']);

        $res = $this->webhook()->processar('PAYMENT_RECEIVED', ['id' => 'pay_1', 'billingType' => 'PIX', 'paymentDate' => '2026-10-19'], 77, true);

        $this->assertSame(200, $res['status']);
        $this->assertSame('pay_2', $res['body']['proxima_cobranca']);
    }

    public function test_pagamento_ja_confirmado_e_ignorado(): void
    {
        $this->pagamentos->shouldReceive('buscarPorId')->andReturn(['id' => 77, 'tenant_plano_id' => 30, 'status_pagamento_id' => 2]);
        $this->confirmacao->shouldNotReceive('confirmar');

        $res = $this->webhook()->processar('PAYMENT_CONFIRMED', ['id' => 'pay_1'], 77, true);

        $this->assertSame('ja_confirmado', $res['body']['ignored']);
    }

    public function test_evento_sem_pagamento_nao_confirma(): void
    {
        $this->pagamentos->shouldReceive('buscarPorGatewayId')->with('pay_1')->andReturn(['id' => 77, 'tenant_plano_id' => 30, 'status_pagamento_id' => 1]);
        $this->confirmacao->shouldNotReceive('confirmar');

        $res = $this->webhook()->processar('PAYMENT_CREATED', ['id' => 'pay_1'], null, false);

        $this->assertSame('PAYMENT_CREATED', $res['body']['ignored']);
    }

    public function test_falha_na_proxima_cobranca_nao_desfaz_a_confirmacao(): void
    {
        $this->pagamentos->shouldReceive('buscarPorId')->andReturn(['id' => 77, 'tenant_plano_id' => 30, 'status_pagamento_id' => 1]);
        $this->confirmacao->shouldReceive('confirmar')->andReturn(['status' => 200, 'body' => []]);
        $this->cobrancas->shouldReceive('gerarProxima')->andThrow(new \RuntimeException('Asaas fora'));

        $res = $this->webhook()->processar('PAYMENT_RECEIVED', ['id' => 'pay_1', 'billingType' => 'BOLETO'], 77, true);

        $this->assertSame(200, $res['status']);
        $this->assertNull($res['body']['proxima_cobranca']);
    }
}
