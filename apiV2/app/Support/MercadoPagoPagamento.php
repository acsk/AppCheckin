<?php

namespace App\Support;

final class MercadoPagoPagamento
{
    /**
     * Mapear status do Mercado Pago para status interno
     * 
     * @param string $mpStatus Status do MP
     * @return string Status interno
     */
    public static function mapearStatus(string $mpStatus): string
    {
        $statusMap = [
            'approved' => 'pago',
            'pending' => 'pendente',
            'in_process' => 'processando',
            'rejected' => 'recusado',
            'cancelled' => 'cancelado',
            'refunded' => 'reembolsado',
            'charged_back' => 'estornado'
        ];
        
        return $statusMap[$mpStatus] ?? 'pendente';
    }

    /**
     * formas_pagamento.id de um pagamento do MP: 2=Pix, 3=Débito, 8=Boleto, 9=Cartão.
     * Pix chega como payment_type_id=bank_transfer (o polling antigo gravava como 8 = Boleto).
     */
    public static function formaPagamentoId(array $p): int
    {
        $method = strtolower((string) ($p['payment_method_id'] ?? ''));
        $type = strtolower((string) ($p['payment_type_id'] ?? ''));

        return match (true) {
            $method === 'pix' || $type === 'bank_transfer' || $type === 'account_money' || $method === 'account_money' => 2,
            $type === 'debit_card' || $type === 'prepaid_card' => 3,
            $type === 'ticket' || $type === 'atm' || in_array($method, ['bolbradesco', 'pec'], true) => 8,
            $type === 'credit_card' => 9,
            default => 2,
        };
    }

    /** Líquido de um pagamento aprovado: net_received_amount, ou valor menos fee_details. Null antes da aprovação. */
    public static function liquidoDoPagamento(array $p): ?float
    {
        if (! in_array($p['status'] ?? null, ['approved', 'authorized'], true)) {
            return null;
        }
        $liquido = $p['transaction_details']['net_received_amount'] ?? null;
        if ($liquido !== null && $liquido !== '' && (float) $liquido > 0) {
            return round((float) $liquido, 2);
        }
        $taxa = self::taxaDoPagamento($p);

        return $taxa === null ? null : round((float) ($p['transaction_amount'] ?? 0) - $taxa, 2);
    }

    public static function taxaDoPagamento(array $p): ?float
    {
        if (empty($p['fee_details']) || ! is_array($p['fee_details'])) {
            return null;
        }
        $taxa = 0.0;
        foreach ($p['fee_details'] as $fee) {
            $taxa += (float) ($fee['amount'] ?? 0);
        }

        return round($taxa, 2);
    }
}
