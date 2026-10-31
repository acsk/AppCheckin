<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Parcela de assinatura (fidelização) ainda em aberto: o provedor cobra sozinho e a baixa chega pelo webhook.
 * Baixa ou edição manual aqui faria o Check-in divergir do Asaas (e o cartão ainda seria cobrado).
 */
final class CobrancaAssinatura
{
    private const ABERTAS = ['pendente', 'vencida'];

    /**
     * @param  list<int>  $pagamentoIds
     * @return array<int, true> ids de pagamentos_plano com cobrança automática em aberto
     */
    public static function emAberto(array $pagamentoIds): array
    {
        $pagamentoIds = array_values(array_filter(array_map('intval', $pagamentoIds)));
        if ($pagamentoIds === []) {
            return [];
        }
        try {
            $ids = DB::table('recorrencia_cobrancas')
                ->whereIn('pagamento_plano_id', $pagamentoIds)
                ->whereIn('status', self::ABERTAS)
                ->whereNotNull('gateway_payment_id')
                ->pluck('pagamento_plano_id');
        } catch (\Throwable $e) {
            Log::warning('CobrancaAssinatura: falha ao consultar cobranças da assinatura', ['message' => $e->getMessage()]);

            return [];
        }

        return array_fill_keys(array_map('intval', $ids->all()), true);
    }

    /**
     * @return array{status: int, body: array<string, mixed>}|null
     */
    public static function bloqueio(int $pagamentoId): ?array
    {
        if (! isset(self::emAberto([$pagamentoId])[$pagamentoId])) {
            return null;
        }

        return ['status' => 422, 'body' => [
            'type' => 'error',
            'code' => 'COBRANCA_AUTOMATICA',
            'error' => 'Esta mensalidade é cobrada automaticamente pela assinatura e recebe baixa sozinha quando o provedor confirma o pagamento.',
            'message' => 'Esta mensalidade é cobrada automaticamente pela assinatura e recebe baixa sozinha quando o provedor confirma o pagamento.',
        ]];
    }
}
