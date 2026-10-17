<?php

namespace Tests\Unit;

use App\Support\AsaasTarifaCartao;
use Tests\TestCase;

class AsaasTarifaCartaoTest extends TestCase
{
    public function test_valor_para_liquido_com_percentual_e_taxa_fixa(): void
    {
        // 100 líquido, 2,49% + R$ 0,49 → cobrar (100,49) / 0,9751 ≈ 103,06
        $cobrar = AsaasTarifaCartao::valorParaLiquido(100.0, 2.49, 0.49);

        $this->assertSame(103.06, $cobrar);
        $this->assertGreaterThanOrEqual(100.0, round($cobrar * (1 - 0.0249) - 0.49, 2));
    }

    public function test_sem_taxa_mantem_valor_do_plano(): void
    {
        $this->assertSame(120.0, AsaasTarifaCartao::valorParaLiquido(120.0, 0.0, 0.0));
    }

    public function test_a_partir_da_simulacao(): void
    {
        $r = AsaasTarifaCartao::aPartirDaSimulacao(120.0, [
            'feePercentage' => 2.99,
            'operationFee' => 0.49,
            'netValue' => 116.0,
        ]);

        $this->assertSame(120.0, $r['valor_plano']);
        $this->assertEqualsWithDelta(124.20, $r['valor_cobrar'], 0.001);
        $this->assertEqualsWithDelta(4.20, $r['tarifa'], 0.001);
        $this->assertSame(2.99, $r['fee_percentage']);
        $this->assertSame(0.49, $r['operation_fee']);
    }

    public function test_ajuste_quando_liquido_simulado_ainda_fica_abaixo(): void
    {
        $this->assertEqualsWithDelta(103.16, AsaasTarifaCartao::ajustarPeloLiquidoSimulado(103.06, 100.0, 99.90, 2.49), 0.01);
        $this->assertSame(103.06, AsaasTarifaCartao::ajustarPeloLiquidoSimulado(103.06, 100.0, 100.05, 2.49));
    }

    public function test_para_cima_em_centavos(): void
    {
        $this->assertSame(0.84, AsaasTarifaCartao::paraCima(0.83559));
        $this->assertSame(0.0, AsaasTarifaCartao::paraCima(0.0));
    }

    public function test_valor_parcela_e_rotulo(): void
    {
        $this->assertSame(20.78, AsaasTarifaCartao::valorParcela(62.35, 3));
        $this->assertSame('À vista · R$ 62,35', AsaasTarifaCartao::rotulo(1, 62.35));
        $this->assertSame('3x de R$ 20,78', AsaasTarifaCartao::rotulo(3, 20.78));
        $this->assertTrue(AsaasTarifaCartao::cabeEmParcelas(60.0, 12));
        $this->assertFalse(AsaasTarifaCartao::cabeEmParcelas(20.0, 6));
    }
}
