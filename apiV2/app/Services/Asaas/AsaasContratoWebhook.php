<?php

namespace App\Services\Asaas;

use App\Repositories\PagamentoContratoRepository;
use App\Services\SuperAdmin\CobrancaContratoService;
use App\Services\SuperAdmin\SuperAdminPagamentoContratoService;
use Illuminate\Support\Facades\Log;

/**
 * Pagamentos de contrato do SaaS (referência CTR) recebidos na conta Asaas do sistema:
 * confirma o pagamento, ativa o contrato e cobra o próximo ciclo.
 */
class AsaasContratoWebhook
{
    private const AGUARDANDO = 1;

    private const PAGO = 2;

    private const ABERTOS = [1, 3]; // Aguardando, Atrasado

    public function __construct(
        private readonly PagamentoContratoRepository $pagamentos,
        private readonly SuperAdminPagamentoContratoService $confirmacao,
        private readonly CobrancaContratoService $cobrancas,
    ) {}

    /**
     * @param  array<string, mixed>  $payment
     * @return array{status: int, body: array<string, mixed>}
     */
    public function processar(string $event, array $payment, ?int $pagamentoId, bool $pago): array
    {
        $gatewayId = (string) ($payment['id'] ?? '');
        $pagamento = $pagamentoId
            ? $this->pagamentos->buscarPorId($pagamentoId)
            : ($gatewayId !== '' ? $this->pagamentos->buscarPorGatewayId($gatewayId) : null);

        if (! $pagamento) {
            Log::warning('Asaas webhook CTR sem pagamento de contrato', ['payment_id' => $gatewayId, 'ref_id' => $pagamentoId]);

            return ['status' => 200, 'body' => ['success' => true, 'received' => true, 'ignored' => 'pagamento_contrato_nao_encontrado']];
        }

        if (! $pago) {
            return $this->atualizarEmAberto($event, $payment, $pagamento);
        }

        if ((int) $pagamento['status_pagamento_id'] === self::PAGO) {
            return ['status' => 200, 'body' => ['success' => true, 'received' => true, 'ignored' => 'ja_confirmado']];
        }

        $billingType = strtoupper((string) ($payment['billingType'] ?? $pagamento['gateway_billing_type'] ?? ''));
        $dataPagamento = $payment['clientPaymentDate'] ?? $payment['paymentDate'] ?? $payment['confirmedDate'] ?? null;

        $res = $this->confirmacao->confirmar((int) $pagamento['id'], [
            'data_pagamento' => is_string($dataPagamento) ? substr($dataPagamento, 0, 10) : null,
            'observacoes' => 'Pago pelo Asaas ('.($billingType ?: 'cobrança').')',
        ]);
        if ($res['status'] >= 400) {
            return ['status' => 500, 'body' => ['success' => false, 'error' => $res['body']['message'] ?? 'Falha ao confirmar pagamento do contrato']];
        }

        $proxima = null;
        if (in_array($billingType, ['PIX', 'BOLETO', 'CREDIT_CARD'], true)) {
            try {
                $proxima = $this->cobrancas->gerarProxima((int) $pagamento['tenant_plano_id'], $billingType);
            } catch (\Throwable $e) {
                Log::error('Asaas webhook CTR: próxima cobrança não gerada', ['contrato_id' => $pagamento['tenant_plano_id'], 'error' => $e->getMessage()]);
            }
        }

        return ['status' => 200, 'body' => [
            'success' => true,
            'received' => true,
            'pagamento_contrato_id' => (int) $pagamento['id'],
            'proxima_cobranca' => $proxima['id'] ?? null,
        ]];
    }

    /**
     * Eventos que não são pagamento: cobrança excluída, vencida ou restaurada no Asaas.
     *
     * @param  array<string, mixed>  $payment
     * @param  array<string, mixed>  $pagamento
     * @return array{status: int, body: array<string, mixed>}
     */
    private function atualizarEmAberto(string $event, array $payment, array $pagamento): array
    {
        $ok = fn (string $acao) => ['status' => 200, 'body' => ['success' => true, 'received' => true, 'pagamento_contrato_id' => (int) $pagamento['id'], 'acao' => $acao]];
        $ignorado = fn (string $motivo) => ['status' => 200, 'body' => ['success' => true, 'received' => true, 'ignored' => $motivo]];

        $status = (int) $pagamento['status_pagamento_id'];
        if (! in_array($status, self::ABERTOS, true)) {
            return $ignorado('pagamento_nao_esta_em_aberto');
        }

        $gatewayId = (string) ($payment['id'] ?? '');
        $statusAsaas = strtoupper((string) ($payment['status'] ?? ''));

        if ($event === 'PAYMENT_DELETED' || ($payment['deleted'] ?? false) === true) {
            // A dívida continua: só o link morre, e a academia pode gerar outra cobrança
            if ($gatewayId !== '' && $this->pagamentos->limparGateway((int) $pagamento['id'], $gatewayId)) {
                Log::info('Asaas: cobrança de contrato excluída, link removido', ['pagamento_contrato_id' => $pagamento['id'], 'payment_id' => $gatewayId]);

                return $ok('cobranca_removida');
            }

            return $ignorado('cobranca_antiga');
        }

        if ($event === 'PAYMENT_RESTORED' && $gatewayId !== '' && empty($pagamento['gateway_payment_id'])) {
            $this->pagamentos->salvarGateway((int) $pagamento['id'], [
                'gateway' => 'asaas',
                'gateway_payment_id' => $gatewayId,
                'gateway_billing_type' => isset($payment['billingType']) ? strtoupper((string) $payment['billingType']) : null,
                'gateway_url' => $payment['invoiceUrl'] ?? $payment['bankSlipUrl'] ?? null,
            ]);

            return $ok('cobranca_restaurada');
        }

        if (($event === 'PAYMENT_OVERDUE' || $statusAsaas === 'OVERDUE') && $status === self::AGUARDANDO) {
            $this->pagamentos->marcarAtrasado((int) $pagamento['id']);

            return $ok('marcado_atrasado');
        }

        return $ignorado($event);
    }
}
