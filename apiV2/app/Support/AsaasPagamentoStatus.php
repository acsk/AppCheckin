<?php

namespace App\Support;

class AsaasPagamentoStatus
{
    /**
     * @param  array<string, mixed>  $payment
     * @return array{
     *     provedor: string,
     *     status: string,
     *     status_label: string,
     *     billing_type: ?string,
     *     data: ?string,
     *     data_formatada: ?string
     * }
     */
    public static function snapshot(array $payment): array
    {
        $status = strtoupper(trim((string) ($payment['status'] ?? '')));
        $billing = strtoupper(trim((string) ($payment['billingType'] ?? '')));
        $data = self::dataPagamento($payment);

        return [
            'provedor' => 'asaas',
            'status' => $status,
            'status_label' => self::rotulo($status),
            'billing_type' => $billing !== '' ? $billing : null,
            'data' => $data,
            'data_formatada' => self::formatarData($data),
        ];
    }

    public static function pago(string $status): bool
    {
        return in_array(strtoupper(trim($status)), [
            'RECEIVED',
            'CONFIRMED',
            'RECEIVED_IN_CASH',
            'APPROVED',
        ], true);
    }

    public static function rotulo(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'CONFIRMED' => 'Confirmada',
            'RECEIVED', 'RECEIVED_IN_CASH' => 'Recebido',
            'OVERDUE' => 'Vencida',
            'PENDING' => 'Aguardando',
            default => $status !== '' ? $status : 'Aguardando',
        };
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    public static function dataPagamento(array $payment): ?string
    {
        foreach (['confirmedDate', 'paymentDate', 'clientPaymentDate', 'creditDate'] as $campo) {
            $valor = substr((string) ($payment[$campo] ?? ''), 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1) {
                return $valor;
            }
        }

        return null;
    }

    public static function formatarData(?string $iso): ?string
    {
        if ($iso === null || preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m) !== 1) {
            return null;
        }

        return $m[3].'/'.$m[2].'/'.$m[1];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function mensagem(array $snapshot, bool $matriculaAtiva): string
    {
        $label = (string) ($snapshot['status_label'] ?? self::rotulo((string) ($snapshot['status'] ?? '')));
        $data = $snapshot['data_formatada'] ?? null;
        $comData = is_string($data) && $data !== '' ? $label.' em '.$data : $label;

        if ($matriculaAtiva) {
            return 'Pagamento '.$comData.' no Asaas.';
        }

        return 'Pagamento '.$comData.' no Asaas. Aguarde a ativação da matrícula.';
    }

    public static function eventoWebhook(string $status): string
    {
        return strtoupper(trim($status)) === 'CONFIRMED'
            ? 'PAYMENT_CONFIRMED'
            : 'PAYMENT_RECEIVED';
    }

    public static function formaPagamentoBusca(string $billingType): array
    {
        return match (strtoupper(trim($billingType))) {
            'RECEIVED_IN_CASH' => ['dinheiro'],
            'CREDIT_CARD', 'DEBIT_CARD' => [
                'crédito à vista',
                'credito a vista',
                'crédito',
                'credito',
                'cartão',
                'cartao',
            ],
            'BOLETO' => ['boleto'],
            default => ['pix'],
        };
    }
}
