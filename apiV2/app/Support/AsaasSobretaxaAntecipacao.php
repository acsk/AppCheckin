<?php

namespace App\Support;

/**
 * Acréscimo complementar da antecipação automática Asaas (D+2).
 * Não recalcula a tarifa de cartão — usa a que o POST /payments/simulate já devolveu.
 *
 * Taxas comerciais padrão (editáveis na credencial):
 * 1,15% ao mês à vista · 1,6% ao mês no parcelado.
 */
final class AsaasSobretaxaAntecipacao
{
    public const AVISTA_PADRAO = 1.15;

    public const PARCELADA_PADRAO = 1.60;

    /**
     * @param  array<string, mixed>  $repasse  saída de AsaasTarifaCartao (valor_plano, valor_cobrar, tarifa, …)
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $creditCard  bloco creditCard da simulação (pode trazer taxa oficial)
     * @param  array<string, mixed>  $taxasConta  GET /myAccount/fees quando disponível
     * @return array<string, mixed>
     */
    public static function aplicar(
        array $repasse,
        array $config,
        int $parcelas = 1,
        array $creditCard = [],
        array $taxasConta = [],
    ): array {
        $valorPlano = round((float) ($repasse['valor_plano'] ?? 0), 2);
        $valorCobrarAsaas = round((float) ($repasse['valor_cobrar'] ?? $valorPlano), 2);
        $tarifaAsaas = round((float) ($repasse['tarifa'] ?? max(0, $valorCobrarAsaas - $valorPlano)), 2);
        $feePct = (float) ($repasse['fee_percentage'] ?? 0);
        $parcelas = max(1, $parcelas);

        $repasse['tarifa'] = $tarifaAsaas;
        $repasse['sobretaxa'] = 0.0;
        $repasse['sobretaxa_percentual'] = 0.0;
        $repasse['antecipacao_estimada'] = 0.0;
        $repasse['liquido_estimado'] = $repasse['net_value_simulado'] ?? $valorPlano;
        $repasse['antecipacao_habilitada'] = false;

        if (empty($config['habilitada'])) {
            return $repasse;
        }

        $taxaMes = self::taxaMensal($parcelas, $config, $taxasConta);
        $antecipacaoOficial = self::antecipacaoOficial($creditCard);
        $baseAntecipacao = $valorCobrarAsaas > 0 ? $valorCobrarAsaas : $valorPlano;
        $antecipacaoEstimada = $antecipacaoOficial !== null
            ? $antecipacaoOficial
            : AsaasTarifaCartao::paraCima($baseAntecipacao * $taxaMes / 100.0);

        $netSimulado = isset($repasse['net_value_simulado']) && $repasse['net_value_simulado'] !== null
            ? round((float) $repasse['net_value_simulado'], 2)
            : null;

        // Furo só conta como antecipação quando a simulação foi do valor a cobrar (já com tarifa).
        // Simular o valor do plano faz (plano − líquido) ≈ tarifa do cartão — não somar de novo.
        $buracoSimulado = $netSimulado !== null
            ? AsaasTarifaCartao::paraCima($valorPlano - $netSimulado)
            : 0.0;
        $buracoEhTarifaCartao = $buracoSimulado > 0.001
            && abs($buracoSimulado - $tarifaAsaas) < 0.05;

        if ($antecipacaoOficial !== null) {
            $antecipacao = $antecipacaoOficial;
        } elseif ($buracoSimulado > 0.001 && ! $buracoEhTarifaCartao) {
            $antecipacao = $buracoSimulado;
        } else {
            $antecipacao = $antecipacaoEstimada;
        }

        $repasse['antecipacao_habilitada'] = true;
        $repasse['antecipacao_estimada'] = $antecipacao;
        $repasse['sobretaxa_percentual'] = $taxaMes;

        if ($antecipacao <= 0.001) {
            $repasse['liquido_estimado'] = $netSimulado ?? $valorPlano;

            return $repasse;
        }

        $operationFee = (float) ($repasse['operation_fee'] ?? 0);
        $sobretaxa = self::sobretaxaParaCobrir($antecipacao, $feePct);
        $valorCobrar = round($valorCobrarAsaas + $sobretaxa, 2);
        $liquidoEstimado = self::liquidoEstimado($valorCobrar, $feePct, $operationFee, $antecipacao, $valorCobrarAsaas);

        if ($liquidoEstimado + 0.001 < $valorPlano) {
            $falta = AsaasTarifaCartao::paraCima($valorPlano - $liquidoEstimado);
            $sobretaxa = round($sobretaxa + self::sobretaxaParaCobrir($falta, $feePct), 2);
            $valorCobrar = round($valorCobrarAsaas + $sobretaxa, 2);
            $liquidoEstimado = self::liquidoEstimado($valorCobrar, $feePct, $operationFee, $antecipacao, $valorCobrarAsaas);
        }

        $repasse['sobretaxa'] = $sobretaxa;
        $repasse['valor_cobrar'] = $valorCobrar;
        $repasse['liquido_estimado'] = $liquidoEstimado;

        return $repasse;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $taxasConta
     */
    public static function taxaMensal(int $parcelas, array $config, array $taxasConta = []): float
    {
        $chave = $parcelas > 1 ? 'parcelada' : 'avista';
        $padrao = $parcelas > 1 ? self::PARCELADA_PADRAO : self::AVISTA_PADRAO;
        if (array_key_exists($chave, $config) && is_numeric($config[$chave])) {
            return self::percentualValido($config[$chave], $padrao);
        }

        $oficial = self::taxaOficialConta($parcelas, $taxasConta);
        if ($oficial !== null) {
            return $oficial;
        }

        return $padrao;
    }

    /**
     * Valor de antecipação devolvido pela simulação, se a API expuser o campo.
     *
     * @param  array<string, mixed>  $creditCard
     */
    public static function antecipacaoOficial(array $creditCard): ?float
    {
        foreach (['anticipationFee', 'anticipationFeeValue', 'feeAnticipation', 'anticipationValue'] as $key) {
            if (isset($creditCard[$key]) && is_numeric($creditCard[$key])) {
                $v = round((float) $creditCard[$key], 2);

                return $v > 0 ? $v : null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $taxasConta
     */
    public static function taxaOficialConta(int $parcelas, array $taxasConta): ?float
    {
        $cc = $taxasConta['anticipation']['creditCard'] ?? null;
        if (! is_array($cc)) {
            return null;
        }
        $campo = $parcelas > 1 ? 'installmentMonthlyFeeValue' : 'detachedMonthlyFeeValue';
        if (! isset($cc[$campo]) || ! is_numeric($cc[$campo])) {
            return null;
        }
        $v = (float) $cc[$campo];
        if ($v <= 0) {
            return null;
        }
        // Algumas contas devolvem 0,0115 em vez de 1,15.
        if ($v > 0 && $v < 0.2) {
            $v *= 100;
        }

        return round($v, 2);
    }

    public static function sobretaxaParaCobrir(float $antecipacao, float $feePercentage): float
    {
        $antecipacao = AsaasTarifaCartao::paraCima($antecipacao);
        $rate = $feePercentage / 100.0;
        if ($rate > 0 && $rate < 0.99) {
            return AsaasTarifaCartao::paraCima($antecipacao / (1 - $rate));
        }

        return $antecipacao;
    }

    private static function liquidoEstimado(
        float $valorCobrar,
        float $feePct,
        float $operationFee,
        float $antecipacaoNaCobrancaAsaas,
        float $valorCobrarAsaas,
    ): float {
        $rate = $feePct / 100.0;
        $antec = $antecipacaoNaCobrancaAsaas;
        if ($valorCobrarAsaas > 0 && $valorCobrar > $valorCobrarAsaas && $antec > 0) {
            $antec = AsaasTarifaCartao::paraCima($antec * ($valorCobrar / $valorCobrarAsaas));
        }
        $aposCartao = $rate > 0 && $rate < 0.99
            ? round($valorCobrar * (1 - $rate) - $operationFee, 2)
            : round($valorCobrar - $operationFee, 2);

        return round($aposCartao - $antec, 2);
    }

    private static function percentualValido(mixed $valor, float $padrao): float
    {
        if (! is_numeric($valor)) {
            return $padrao;
        }
        $v = (float) $valor;
        if ($v < 0) {
            return 0.0;
        }
        if ($v > 30) {
            return $padrao;
        }

        return round($v, 2);
    }
}
