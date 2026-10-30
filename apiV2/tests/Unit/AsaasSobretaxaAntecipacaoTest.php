<?php

namespace Tests\Unit;

use App\Support\AsaasSobretaxaAntecipacao;
use App\Support\AsaasTarifaCartao;
use Tests\TestCase;

class AsaasSobretaxaAntecipacaoTest extends TestCase
{
    /** @return array<string, mixed> */
    private function repasse70(): array
    {
        return [
            'valor_plano' => 70.0,
            'valor_cobrar' => 72.66,
            'tarifa' => 2.66,
            'fee_percentage' => 2.49,
            'operation_fee' => 0.49,
            'net_value_simulado' => 70.0,
        ];
    }

    public function test_desligada_nao_altera_cobranca_do_asaas(): void
    {
        $r = AsaasSobretaxaAntecipacao::aplicar($this->repasse70(), ['habilitada' => false], 1);

        $this->assertSame(72.66, $r['valor_cobrar']);
        $this->assertSame(2.66, $r['tarifa']);
        $this->assertSame(0.0, $r['sobretaxa']);
        $this->assertFalse($r['antecipacao_habilitada']);
    }

    public function test_avista_1_15_cobre_liquido_do_plano(): void
    {
        $r = AsaasSobretaxaAntecipacao::aplicar($this->repasse70(), [
            'habilitada' => true,
            'avista' => 1.15,
            'parcelada' => 1.60,
        ], 1);

        $this->assertSame(2.66, $r['tarifa']);
        $this->assertGreaterThan(0, $r['sobretaxa']);
        $this->assertSame(1.15, $r['sobretaxa_percentual']);
        $this->assertEqualsWithDelta(72.66 + $r['sobretaxa'], $r['valor_cobrar'], 0.001);
        $this->assertGreaterThanOrEqual(70.0, $r['liquido_estimado']);
    }

    public function test_exemplo_real_nao_duplica_taxa_asaas(): void
    {
        $r = AsaasSobretaxaAntecipacao::aplicar($this->repasse70(), [
            'habilitada' => true,
            'avista' => 1.15,
        ], 1);

        $this->assertEqualsWithDelta(2.66, $r['tarifa'], 0.001);
        $this->assertGreaterThan(72.66, $r['valor_cobrar']);
        $this->assertEqualsWithDelta($r['valor_cobrar'] - 72.66, $r['sobretaxa'], 0.001);
    }

    public function test_furo_igual_a_tarifa_nao_duplica_percentual(): void
    {
        $base = $this->repasse70();
        $base['net_value_simulado'] = 67.34;
        $r = AsaasSobretaxaAntecipacao::aplicar($base, [
            'habilitada' => true,
            'avista' => 1.15,
        ], 1, []);

        $this->assertEqualsWithDelta(2.66, $r['tarifa'], 0.001);
        $this->assertEqualsWithDelta(1.15, $r['sobretaxa_percentual'], 0.001);
        $this->assertLessThan($r['tarifa'], $r['sobretaxa']);
    }

    public function test_prioriza_taxa_oficial_da_conta(): void
    {
        $this->assertSame(1.15, AsaasSobretaxaAntecipacao::taxaOficialConta(1, [
            'anticipation' => ['creditCard' => ['detachedMonthlyFeeValue' => 1.15]],
        ]));
        $this->assertSame(1.6, AsaasSobretaxaAntecipacao::taxaOficialConta(3, [
            'anticipation' => ['creditCard' => ['installmentMonthlyFeeValue' => 1.6]],
        ]));
        $this->assertSame(9.0, AsaasSobretaxaAntecipacao::taxaMensal(1, [
            'avista' => 9.0,
            'parcelada' => 9.0,
        ], [
            'anticipation' => ['creditCard' => ['detachedMonthlyFeeValue' => 1.15]],
        ]));
        $this->assertSame(1.15, AsaasSobretaxaAntecipacao::taxaMensal(1, [], [
            'anticipation' => ['creditCard' => ['detachedMonthlyFeeValue' => 1.15]],
        ]));
    }

    public function test_parcelado_usa_1_6(): void
    {
        $this->assertSame(1.6, AsaasSobretaxaAntecipacao::taxaMensal(3, [
            'avista' => 1.15,
            'parcelada' => 1.60,
        ]));
        $r = AsaasSobretaxaAntecipacao::aplicar($this->repasse70(), [
            'habilitada' => true,
            'avista' => 1.15,
            'parcelada' => 1.60,
        ], 3);
        $this->assertSame(1.6, $r['sobretaxa_percentual']);
        $avista = AsaasSobretaxaAntecipacao::aplicar($this->repasse70(), [
            'habilitada' => true,
            'avista' => 1.15,
            'parcelada' => 1.60,
        ], 1);
        $this->assertGreaterThan($avista['antecipacao_estimada'], $r['antecipacao_estimada']);
    }

    public function test_arredonda_para_cima(): void
    {
        $this->assertSame(0.84, AsaasTarifaCartao::paraCima(72.66 * 1.15 / 100));
        $this->assertSame(0.01, AsaasTarifaCartao::paraCima(0.001));
        $this->assertSame(1.0, AsaasTarifaCartao::paraCima(1.0));
        $this->assertSame(0.50, AsaasTarifaCartao::paraCima(33.33 * 1.5 / 100));
    }

    public function test_valores_diferentes(): void
    {
        foreach ([20.0, 50.0, 100.0, 180.0] as $plano) {
            $cobrar = AsaasTarifaCartao::valorParaLiquido($plano, 2.49, 0.49);
            $r = AsaasSobretaxaAntecipacao::aplicar([
                'valor_plano' => $plano,
                'valor_cobrar' => $cobrar,
                'tarifa' => round($cobrar - $plano, 2),
                'fee_percentage' => 2.49,
                'operation_fee' => 0.49,
                'net_value_simulado' => $plano,
            ], ['habilitada' => true, 'avista' => 1.15], 1);
            $this->assertSame(round($cobrar - $plano, 2), $r['tarifa']);
            $this->assertGreaterThanOrEqual($plano, $r['liquido_estimado']);
        }
    }

    /** Caso real (matrícula 470): R$ 400 em 12x, cartão 3,99% + R$ 0,49, antecipação 1,6% ao mês. */
    private function repasse400(): array
    {
        $cobrar = AsaasTarifaCartao::valorParaLiquido(400.0, 3.99, 0.49);

        return [
            'valor_plano' => 400.0,
            'valor_cobrar' => $cobrar,
            'tarifa' => round($cobrar - 400.0, 2),
            'fee_percentage' => 3.99,
            'operation_fee' => 0.49,
            'net_value_simulado' => 400.0,
        ];
    }

    /**
     * Líquido como o Asaas calcula: cartão sobre o total, antecipação de cada parcela pelos dias até o crédito.
     *
     * @param  list<int>  $dias
     */
    private function liquidoAsaas(float $cobrado, int $parcelas, array $dias, float $taxaMes = 1.6): float
    {
        $liquidoCartao = $cobrado * (1 - 0.0399) - 0.49;
        $porParcela = $liquidoCartao / $parcelas;
        $antecipacao = 0.0;
        foreach (array_slice($dias, 0, $parcelas) as $d) {
            $antecipacao += $porParcela * $taxaMes / 100 * $d / 30;
        }

        return round($liquidoCartao - $antecipacao, 2);
    }

    public function test_doze_vezes_escola_recebe_o_plano_com_as_datas_reais_do_asaas(): void
    {
        // Dias até o crédito de cada parcela na matrícula 470 (confirmada em 29/10/2026)
        $dias = [32, 64, 96, 130, 160, 193, 224, 256, 288, 320, 354, 384];
        $hoje = 434.84;
        $this->assertLessThan(380.0, $this->liquidoAsaas($hoje, 12, $dias));

        $r = AsaasSobretaxaAntecipacao::aplicar($this->repasse400(), ['habilitada' => true, 'parcelada' => 1.6], 12);

        $this->assertEqualsWithDelta(473.8, $r['valor_cobrar'], 0.5);
        $this->assertGreaterThanOrEqual(400.0, $this->liquidoAsaas($r['valor_cobrar'], 12, $dias));
        $this->assertGreaterThanOrEqual(404.0, $r['liquido_estimado']);
    }

    public function test_parcelas_diferentes_cobrem_o_plano_com_folga(): void
    {
        $anterior = 0.0;
        foreach ([1, 2, 3, 6, 12] as $n) {
            $r = AsaasSobretaxaAntecipacao::aplicar($this->repasse400(), ['habilitada' => true, 'avista' => 1.15, 'parcelada' => 1.6], $n);
            $this->assertGreaterThanOrEqual(404.0, $r['liquido_estimado'], "{$n}x");
            $this->assertGreaterThan($anterior, $r['valor_cobrar'], "{$n}x mais caro que menos parcelas");
            $anterior = $r['valor_cobrar'];
        }
    }

    public function test_fator_usa_prazo_medio_das_parcelas(): void
    {
        $this->assertEqualsWithDelta(0.016 * 32 / 30, AsaasSobretaxaAntecipacao::fatorAntecipacao(1.6, 1), 0.00001);
        $this->assertEqualsWithDelta(0.016 * 32 / 30 * 1.5, AsaasSobretaxaAntecipacao::fatorAntecipacao(1.6, 2), 0.00001);
        $this->assertEqualsWithDelta(0.016 * 32 / 30 * 6.5, AsaasSobretaxaAntecipacao::fatorAntecipacao(1.6, 12), 0.00001);
    }

    public function test_com_valor_cobrar_recalcula_depois_de_arredondar_a_parcela(): void
    {
        $r = AsaasSobretaxaAntecipacao::aplicar($this->repasse400(), ['habilitada' => true, 'parcelada' => 1.6], 12);
        $parcela = AsaasTarifaCartao::paraCima($r['valor_cobrar'] / 12);
        $cheio = round($parcela * 12, 2);
        $r2 = AsaasSobretaxaAntecipacao::comValorCobrar($r, $cheio, 417.13, 3.99, 0.49, $r['fator_antecipacao']);

        $this->assertSame($cheio, $r2['valor_cobrar']);
        $this->assertEqualsWithDelta($cheio - 417.13, $r2['sobretaxa'], 0.001);
        $this->assertGreaterThanOrEqual($r['liquido_estimado'], $r2['liquido_estimado']);
    }

    public function test_max_parcelas_padrao_e_limites(): void
    {
        $this->assertSame(6, AsaasTarifaCartao::maxParcelas(null));
        $this->assertSame(6, AsaasTarifaCartao::maxParcelas('abc'));
        $this->assertSame(6, AsaasTarifaCartao::maxParcelas(0));
        $this->assertSame(1, AsaasTarifaCartao::maxParcelas('1'));
        $this->assertSame(12, AsaasTarifaCartao::maxParcelas(18));
    }
}
