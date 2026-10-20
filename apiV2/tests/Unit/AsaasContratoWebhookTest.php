<?php

namespace Tests\Unit;

use App\Repositories\PagamentoContratoRepository;
use App\Repositories\TenantPlanoRepository;
use App\Services\Asaas\AsaasContratoWebhook;
use App\Services\Asaas\AsaasService;
use App\Services\SuperAdmin\CobrancaContratoService;
use App\Services\SuperAdmin\SuperAdminPagamentoContratoService;
use Mockery;
use Tests\TestCase;

class AsaasContratoWebhookTest extends TestCase
{
    private function pagamento(array $extra = []): array
    {
        return $extra + ['id' => 13, 'tenant_plano_id' => 18, 'status_pagamento_id' => 1, 'gateway_payment_id' => 'pay_atual', 'gateway_billing_type' => 'PIX'];
    }

    private function webhook(PagamentoContratoRepository $pagamentos): AsaasContratoWebhook
    {
        $confirmacao = Mockery::mock(SuperAdminPagamentoContratoService::class);
        $confirmacao->shouldNotReceive('confirmar');

        return new AsaasContratoWebhook($pagamentos, $confirmacao, Mockery::mock(CobrancaContratoService::class));
    }

    public function test_cobranca_excluida_no_asaas_remove_o_link_e_mantem_a_divida(): void
    {
        $pagamentos = Mockery::mock(PagamentoContratoRepository::class);
        $pagamentos->shouldReceive('buscarPorId')->with(13)->andReturn($this->pagamento());
        $pagamentos->shouldReceive('limparGateway')->once()->with(13, 'pay_atual')->andReturn(true);
        $pagamentos->shouldNotReceive('cancelar');

        $res = $this->webhook($pagamentos)->processar('PAYMENT_DELETED', ['id' => 'pay_atual', 'deleted' => true], 13, false);

        $this->assertSame('cobranca_removida', $res['body']['acao']);
    }

    public function test_exclusao_de_cobranca_antiga_nao_apaga_o_link_novo(): void
    {
        $pagamentos = Mockery::mock(PagamentoContratoRepository::class);
        $pagamentos->shouldReceive('buscarPorId')->andReturn($this->pagamento());
        // A troca de forma cancela a cobrança antiga no Asaas; o link salvo já é o da nova
        $pagamentos->shouldReceive('limparGateway')->once()->with(13, 'pay_antiga')->andReturn(false);

        $res = $this->webhook($pagamentos)->processar('PAYMENT_DELETED', ['id' => 'pay_antiga'], 13, false);

        $this->assertSame('cobranca_antiga', $res['body']['ignored']);
    }

    public function test_cobranca_vencida_marca_atrasado(): void
    {
        $pagamentos = Mockery::mock(PagamentoContratoRepository::class);
        $pagamentos->shouldReceive('buscarPorId')->andReturn($this->pagamento());
        $pagamentos->shouldReceive('marcarAtrasado')->once()->with(13)->andReturn(true);

        $res = $this->webhook($pagamentos)->processar('PAYMENT_OVERDUE', ['id' => 'pay_atual', 'status' => 'OVERDUE'], 13, false);

        $this->assertSame('marcado_atrasado', $res['body']['acao']);
    }

    public function test_cobranca_ja_paga_nao_e_alterada_por_exclusao(): void
    {
        $pagamentos = Mockery::mock(PagamentoContratoRepository::class);
        $pagamentos->shouldReceive('buscarPorId')->andReturn($this->pagamento(['status_pagamento_id' => 2]));
        $pagamentos->shouldNotReceive('limparGateway');

        $res = $this->webhook($pagamentos)->processar('PAYMENT_DELETED', ['id' => 'pay_atual'], 13, false);

        $this->assertSame('pagamento_nao_esta_em_aberto', $res['body']['ignored']);
    }

    public function test_sincronizar_aplica_exclusao_vista_no_asaas(): void
    {
        $pagamentos = Mockery::mock(PagamentoContratoRepository::class);
        $pagamentos->shouldReceive('listarAbertosComGateway')->with(2, 20)->andReturn([$this->pagamento()]);
        $pagamentos->shouldReceive('buscarPorId')->with(13)->andReturn($this->pagamento());
        $pagamentos->shouldReceive('limparGateway')->once()->with(13, 'pay_atual')->andReturn(true);
        $this->app->instance(PagamentoContratoRepository::class, $pagamentos);

        $asaas = Mockery::mock(AsaasService::class);
        $asaas->shouldReceive('obterPagamento')->with('pay_atual')->andReturn(['id' => 'pay_atual', 'status' => 'PENDING', 'deleted' => true]);

        $confirmacao = Mockery::mock(SuperAdminPagamentoContratoService::class);
        $confirmacao->shouldNotReceive('confirmar');
        $this->app->instance(SuperAdminPagamentoContratoService::class, $confirmacao);

        $service = new CobrancaContratoService($pagamentos, Mockery::mock(TenantPlanoRepository::class), fn () => $asaas);
        $this->app->instance(CobrancaContratoService::class, $service);

        $this->assertSame(['conferidas' => 1, 'alteradas' => 1, 'erros' => 0], $service->sincronizar(2, 20));
    }
}
