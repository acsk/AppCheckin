<?php

namespace App\Support;

/**
 * Aritmética de taxas e parcelamento das formas de pagamento do tenant.
 *
 * Porte fiel de api/app/Models/TenantFormaPagamento.php (Slim): os valores
 * monetários saem como string via number_format(..., 2, '.', '') e o branch
 * "sem configuração" de valorLiquido devolve floats, como na Slim.
 */
final class FormaPagamentoCalculo
{
    /**
     * @param  array<string, mixed>|null  $config  colunas taxa_percentual e taxa_fixa
     * @return array<string, mixed>
     */
    public static function valorLiquido(?array $config, float $valorBruto): array
    {
        if (! $config) {
            return [
                'valor_bruto' => $valorBruto,
                'taxa_percentual' => 0.00,
                'taxa_fixa' => 0.00,
                'valor_taxas' => 0.00,
                'valor_liquido' => $valorBruto,
            ];
        }

        $taxaPercentual = ($valorBruto * $config['taxa_percentual']) / 100;
        $taxaFixa = (float) $config['taxa_fixa'];
        $valorTaxas = $taxaPercentual + $taxaFixa;
        $valorLiquido = $valorBruto - $valorTaxas;

        return [
            'valor_bruto' => number_format($valorBruto, 2, '.', ''),
            'taxa_percentual' => number_format($taxaPercentual, 2, '.', ''),
            'taxa_fixa' => number_format($taxaFixa, 2, '.', ''),
            'valor_taxas' => number_format($valorTaxas, 2, '.', ''),
            'valor_liquido' => number_format($valorLiquido, 2, '.', ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $config  linha completa de tenant_formas_pagamento
     * @return array<string, mixed> chave `erro` quando a regra de negócio rejeita
     */
    public static function parcelas(array $config, float $valorTotal, int $numeroParcelas = 1): array
    {
        if ($valorTotal < $config['valor_minimo']) {
            return [
                'erro' => 'Valor mínimo para esta forma de pagamento é R$ '.number_format($config['valor_minimo'], 2, ',', '.'),
            ];
        }

        if (! $config['aceita_parcelamento'] && $numeroParcelas > 1) {
            return ['erro' => 'Esta forma de pagamento não aceita parcelamento'];
        }

        if ($numeroParcelas < $config['parcelas_minimas'] || $numeroParcelas > $config['parcelas_maximas']) {
            return [
                'erro' => "Número de parcelas deve estar entre {$config['parcelas_minimas']} e {$config['parcelas_maximas']}",
            ];
        }

        $taxaPercentual = ($valorTotal * $config['taxa_percentual']) / 100;
        $taxaFixa = (float) $config['taxa_fixa'];
        $valorComTaxas = $valorTotal + $taxaPercentual + $taxaFixa;

        $aplicarJuros = $numeroParcelas > $config['parcelas_sem_juros'];
        $valorFinal = $valorComTaxas;
        $valorTotalJuros = 0;

        if ($aplicarJuros) {
            // Juros compostos sobre as parcelas que excedem as parcelas sem juros
            $taxaJuros = $config['juros_parcelamento'] / 100;
            $parcelasComJuros = $numeroParcelas - $config['parcelas_sem_juros'];
            $valorFinal = $valorComTaxas * pow(1 + $taxaJuros, $parcelasComJuros);
            $valorTotalJuros = $valorFinal - $valorComTaxas;
        }

        $valorParcela = $valorFinal / $numeroParcelas;

        return [
            'valor_original' => number_format($valorTotal, 2, '.', ''),
            'numero_parcelas' => $numeroParcelas,
            'parcelas_sem_juros' => (int) $config['parcelas_sem_juros'],
            'aplica_juros' => $aplicarJuros,
            'juros_percentual' => (float) $config['juros_parcelamento'],
            'taxa_operadora_percentual' => (float) $config['taxa_percentual'],
            'taxa_operadora_fixa' => number_format($config['taxa_fixa'], 2, '.', ''),
            'valor_total_taxas' => number_format($taxaPercentual + $taxaFixa, 2, '.', ''),
            'valor_total_juros' => number_format($valorTotalJuros, 2, '.', ''),
            'valor_final_total' => number_format($valorFinal, 2, '.', ''),
            'valor_por_parcela' => number_format($valorParcela, 2, '.', ''),
            'descricao_parcelamento' => self::descricaoParcelamento(
                $numeroParcelas,
                $valorParcela,
                (int) $config['parcelas_sem_juros'],
                $aplicarJuros,
            ),
        ];
    }

    public static function descricaoParcelamento(
        int $parcelas,
        float $valorParcela,
        int $parcelasSemJuros,
        bool $aplicaJuros,
    ): string {
        $descricao = "{$parcelas}x de R$ ".number_format($valorParcela, 2, ',', '.');

        if ($aplicaJuros) {
            $descricao .= ' com juros';
        } elseif ($parcelas <= $parcelasSemJuros) {
            $descricao .= ' sem juros';
        }

        return $descricao;
    }
}
