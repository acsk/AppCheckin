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

    public function test_usa_furo_da_simulacao_sem_somar_percentual_de_novo(): void
    {
        $base = $this->repasse70();
        $base['net_value_simulado'] = 69.07;
        $r = AsaasSobretaxaAntecipacao::aplicar($base, [
            'habilitada' => true,
            'avista' => 1.15,
        ], 1, []);

        $this->assertEqualsWithDelta(0.93, $r['antecipacao_estimada'], 0.001);
        $this->assertGreaterThanOrEqual(70.0, $r['liquido_estimado']);
    }

    public function test_prioriza_antecipacao_oficial_da_simulacao(): void
    {
        $r = AsaasSobretaxaAntecipacao::aplicar($this->repasse70(), [
            'habilitada' => true,
            'avista' => 9.99,
        ], 1, ['anticipationFee' => 0.93]);

        $this->assertEqualsWithDelta(0.93, $r['antecipacao_estimada'], 0.001);
        $this->assertGreaterThan(
            0.05,
            abs($r['antecipacao_estimada'] - AsaasTarifaCartao::paraCima(72.66 * 9.99 / 100))
        );
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
}
