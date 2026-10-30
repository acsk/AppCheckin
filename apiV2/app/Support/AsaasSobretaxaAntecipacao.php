<?php

namespace App\Support;

/**
 * Acréscimo complementar da antecipação automática Asaas, pago pelo aluno junto com a tarifa do cartão.
 * Não recalcula a tarifa de cartão — usa a que o POST /payments/simulate já devolveu.
 *
 * O Asaas antecipa cada parcela pelo prazo até ela cair na conta (~32 dias por parcela) e cobra a taxa
 * mensal sobre o líquido dela; em 12x a última paga ~12,8 meses. Por isso o custo cresce com as parcelas:
 * prazo médio = (N + 1) / 2 parcelas.
 *
 * Taxas comerciais padrão (editáveis na credencial):
 * 1,15% ao mês à vista · 1,6% ao mês no parcelado.
 */
final class AsaasSobretaxaAntecipacao
{
    public const AVISTA_PADRAO = 1.15;

    public const PARCELADA_PADRAO = 1.60;

    /** Dias entre a confirmação e o crédito de cada parcela no Asaas (parcela k cai em ~k × 32 dias). */
    public const DIAS_POR_PARCELA = 32;

    /** Folga sobre o valor do plano: a taxa varia com o dia do mês e a escola não pode receber menos. */
    public const FOLGA_PERCENTUAL = 1.0;

    /**
     * @param  array<string, mixed>  $repasse  saída de AsaasTarifaCartao (valor_plano, valor_cobrar, tarifa, …)
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $creditCard  bloco creditCard da simulação (não usado; mantido pela assinatura)
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
        $operationFee = (float) ($repasse['operation_fee'] ?? 0);
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
        $fator = self::fatorAntecipacao($taxaMes, $parcelas);
        $repasse['antecipacao_habilitada'] = true;
        $repasse['sobretaxa_percentual'] = $taxaMes;

        if ($fator <= 0 || $fator >= 0.9) {
            return $repasse;
        }

        // Líquido do cartão que, depois da antecipação, ainda deixa o plano + folga para a escola
        $alvo = round($valorPlano * (1 + self::FOLGA_PERCENTUAL / 100), 2);
        $liquidoCartao = $alvo / (1 - $fator);
        $valorCobrar = max(
            $valorCobrarAsaas,
            AsaasTarifaCartao::paraCima(AsaasTarifaCartao::valorParaLiquido($liquidoCartao, $feePct, $operationFee)),
        );

        // Arredondamentos podem deixar o líquido centavos abaixo do alvo
        for ($i = 0; $i < 10; $i++) {
            $calculado = self::comValorCobrar($repasse, $valorCobrar, $valorCobrarAsaas, $feePct, $operationFee, $fator);
            if ($calculado['liquido_estimado'] + 0.001 >= $alvo) {
                break;
            }
            $valorCobrar = round($valorCobrar + 0.01, 2);
        }

        return $calculado;
    }

    /**
     * Fração do líquido do cartão que fica com a antecipação: taxa mensal × prazo médio das parcelas.
     */
    public static function fatorAntecipacao(float $taxaMes, int $parcelas): float
    {
        $parcelas = max(1, $parcelas);
        $mesesMedios = (self::DIAS_POR_PARCELA / 30) * ($parcelas + 1) / 2;

        return max(0.0, $taxaMes / 100 * $mesesMedios);
    }

    /**
     * Recalcula sobretaxa, antecipação e líquido para um valor cobrado (ex.: depois de arredondar a parcela).
     *
     * @param  array<string, mixed>  $repasse
     * @return array<string, mixed>
     */
    public static function comValorCobrar(
        array $repasse,
        float $valorCobrar,
        float $valorCobrarAsaas,
        float $feePct,
        float $operationFee,
        float $fator,
    ): array {
        $rate = $feePct / 100.0;
        $aposCartao = $rate > 0 && $rate < 0.99
            ? round($valorCobrar * (1 - $rate) - $operationFee, 2)
            : round($valorCobrar - $operationFee, 2);
        $antecipacao = AsaasTarifaCartao::paraCima($aposCartao * $fator);

        $repasse['valor_cobrar'] = round($valorCobrar, 2);
        $repasse['sobretaxa'] = round(max(0, $valorCobrar - $valorCobrarAsaas), 2);
        $repasse['antecipacao_estimada'] = $antecipacao;
        $repasse['liquido_estimado'] = round($aposCartao - $antecipacao, 2);
        $repasse['fator_antecipacao'] = $fator;

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
