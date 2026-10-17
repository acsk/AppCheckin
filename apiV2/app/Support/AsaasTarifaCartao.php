<?php

namespace App\Support;

/**
 * Repasse da tarifa de cartão Asaas para o aluno:
 * líquido desejado ≈ valor_cobrado × (1 − feePercentage/100) − operationFee.
 */
final class AsaasTarifaCartao
{
    /**
     * Valor a cobrar para que o líquido fique em $liquidoDesejado.
     */
    public static function valorParaLiquido(float $liquidoDesejado, float $feePercentage, float $operationFee): float
    {
        $liquidoDesejado = round(max(0, $liquidoDesejado), 2);
        $operationFee = round(max(0, $operationFee), 2);
        $rate = $feePercentage / 100.0;
        if ($rate >= 0.99) {
            return round($liquidoDesejado + $operationFee, 2);
        }
        if ($rate <= 0 && $operationFee <= 0) {
            return $liquidoDesejado;
        }

        return round(($liquidoDesejado + $operationFee) / (1 - $rate), 2);
    }

    /**
     * @param  array<string, mixed>  $creditCard  bloco creditCard do POST /payments/simulate
     * @return array{valor_plano: float, valor_cobrar: float, tarifa: float, fee_percentage: float, operation_fee: float, net_value_simulado: ?float}
     */
    public static function aPartirDaSimulacao(float $valorPlano, array $creditCard): array
    {
        $valorPlano = round($valorPlano, 2);
        $pct = (float) ($creditCard['feePercentage'] ?? 0);
        $op = (float) ($creditCard['operationFee'] ?? 0);
        $cobrar = self::valorParaLiquido($valorPlano, $pct, $op);
        $net = array_key_exists('netValue', $creditCard) && $creditCard['netValue'] !== null
            ? round((float) $creditCard['netValue'], 2)
            : null;

        return [
            'valor_plano' => $valorPlano,
            'valor_cobrar' => $cobrar,
            'tarifa' => round($cobrar - $valorPlano, 2),
            'fee_percentage' => $pct,
            'operation_fee' => $op,
            'net_value_simulado' => $net,
        ];
    }

    /**
     * Ajuste fino quando a 2ª simulação ainda deixa o líquido abaixo do plano.
     */
    public static function ajustarPeloLiquidoSimulado(
        float $valorCobrar,
        float $liquidoDesejado,
        float $liquidoSimulado,
        float $feePercentage = 0.0,
    ): float {
        $delta = round($liquidoDesejado - $liquidoSimulado, 2);
        if ($delta <= 0) {
            return round($valorCobrar, 2);
        }
        $rate = $feePercentage / 100.0;
        if ($rate > 0 && $rate < 0.99) {
            return round($valorCobrar + ($delta / (1 - $rate)), 2);
        }

        return round($valorCobrar + $delta, 2);
    }

    /** Parcelas simuladas no app (à vista + as mais usadas no cartão). */
    public const OPCOES_PARCELAS = [1, 2, 3, 4, 5, 6, 12];

    /** Asaas costuma recusar parcela menor que R$ 5. */
    public const MIN_VALOR_PARCELA = 5.0;

    public static function valorParcela(float $total, int $parcelas): float
    {
        return round($total / max(1, $parcelas), 2);
    }

    public static function cabeEmParcelas(float $total, int $parcelas): bool
    {
        if ($parcelas <= 1) {
            return true;
        }

        return self::valorParcela($total, $parcelas) + 0.001 >= self::MIN_VALOR_PARCELA;
    }

    public static function rotulo(int $parcelas, float $valorParcela): string
    {
        $fmt = 'R$ '.number_format($valorParcela, 2, ',', '.');
        if ($parcelas <= 1) {
            return 'À vista · '.$fmt;
        }

        return $parcelas.'x de '.$fmt;
    }
}
