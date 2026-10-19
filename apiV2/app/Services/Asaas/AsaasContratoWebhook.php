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
    private const PAGO = 2;

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
            return ['status' => 200, 'body' => ['success' => true, 'received' => true, 'ignored' => $event]];
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
}
