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

    /**
     * Tarifa Asaas: value cobrado do aluno, netValue líquido na conta.
     *
     * @param  array<string, mixed>  $payment
     * @return array{valor: float, valor_liquido: ?float, desconto_provedor: ?float}
     */
    public static function tarifaProvedor(array $payment): array
    {
        $valor = round((float) ($payment['value'] ?? 0), 2);
        if (! array_key_exists('netValue', $payment) || $payment['netValue'] === null || $payment['netValue'] === '') {
            return [
                'valor' => $valor,
                'valor_liquido' => null,
                'desconto_provedor' => null,
            ];
        }

        $liquido = round((float) $payment['netValue'], 2);
        $tarifa = round($valor - $liquido, 2);
        if ($tarifa < 0) {
            $tarifa = 0.0;
        }

        return [
            'valor' => $valor,
            'valor_liquido' => $liquido,
            'desconto_provedor' => $tarifa,
        ];
    }

    /**
     * Cartão parcelado: no Check-in a parcela é o valor cheio, então valor e líquido somam todas as
     * cobranças do parcelamento. Líquido fica de fora se alguma cobrança ainda não tiver netValue.
     *
     * @param  array<string, mixed>  $payment
     * @param  list<array<string, mixed>>  $parcelas
     * @return array<string, mixed>
     */
    public static function somarParcelamento(array $payment, array $parcelas): array
    {
        if (count($parcelas) < 2) {
            return $payment;
        }

        $valor = 0.0;
        $liquido = 0.0;
        $semLiquido = false;
        foreach ($parcelas as $p) {
            $valor += (float) ($p['value'] ?? 0);
            if (! array_key_exists('netValue', $p) || $p['netValue'] === null || $p['netValue'] === '') {
                $semLiquido = true;
            } else {
                $liquido += (float) $p['netValue'];
            }
        }

        $payment['value'] = round($valor, 2);
        if ($semLiquido) {
            unset($payment['netValue']);
        } else {
            $payment['netValue'] = round($liquido, 2);
        }

        return $payment;
    }

    /**
     * Antecipação em análise/creditada: o líquido a receber (já com taxa de antecipação)
     * substitui o netValue da cobrança (que só desconta a tarifa do cartão).
     *
     * @param  array<string, mixed>  $payment
     * @param  array<string, mixed>|null  $antecipacao
     * @return array<string, mixed>
     */
    public static function aplicarAntecipacao(array $payment, ?array $antecipacao): array
    {
        if ($antecipacao === null || $antecipacao === []) {
            return $payment;
        }

        $status = strtoupper((string) ($antecipacao['status'] ?? ''));
        if (in_array($status, ['CANCELLED', 'DENIED', 'DEBITED'], true)) {
            return $payment;
        }

        if (! array_key_exists('netValue', $antecipacao) || $antecipacao['netValue'] === null || $antecipacao['netValue'] === '') {
            return $payment;
        }

        $payment['netValue'] = round((float) $antecipacao['netValue'], 2);
        $payment['anticipation'] = $antecipacao;

        return $payment;
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
