<?php

namespace Tests\Feature;

use App\Services\Bi\BiFinanceiroService;
use App\Services\Bi\BiVisaoGeralService;
use App\Services\PagamentoPlanoService;
use App\Support\StatusPagamentoPlano;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BiSchema;
use Tests\TestCase;

/**
 * Estorno de parcela paga: parcial acumula, total vira status 5; no BI a receita fica no mês do
 * pagamento e o estorno sai no mês da devolução.
 */
class EstornoParcelaTest extends TestCase
{
    use BiSchema;

    private int $parcela;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarCenarioBi();
        // Paga em setembro, devolvida em outubro
        $this->parcela(900, 1, 100, '2026-09-10', 2, '2026-09-10');
        $this->parcela = (int) DB::table('pagamentos_plano')->max('id');
    }

    private function service(int $recalculos): PagamentoPlanoService
    {
        return $this->partialMock(PagamentoPlanoService::class, fn ($m) => $m->shouldReceive('atualizarStatusMatricula')->times($recalculos));
    }

    private function parcelaAtual(): object
    {
        return DB::table('pagamentos_plano')->find($this->parcela);
    }

    public function test_parcial_acumula_e_o_total_estorna_e_recalcula_a_matricula(): void
    {
        $s = $this->service(1);

        $r = $s->registrarEstorno(self::T1, $this->parcela, 30, '2026-10-05', 'Desistiu da aula experimental', 7);
        $this->assertTrue($r['ok']);
        $this->assertFalse($r['total']);
        $this->assertSame(StatusPagamentoPlano::PAGO, (int) $this->parcelaAtual()->status_pagamento_id);

        $r = $s->registrarEstorno(self::T1, $this->parcela, null, '2026-10-06', 'Restante', 7);
        $this->assertTrue($r['total']);
        $p = $this->parcelaAtual();
        $this->assertSame(StatusPagamentoPlano::ESTORNADO, (int) $p->status_pagamento_id);
        $this->assertEquals(100, (float) $p->valor_estornado);
        $this->assertSame('2026-09-10', $p->data_pagamento, 'a data do pagamento não muda');
    }

    public function test_recusa_valor_maior_que_o_restante_data_anterior_e_parcela_nao_paga(): void
    {
        $s = $this->service(0);

        $this->assertFalse($s->registrarEstorno(self::T1, $this->parcela, 100.01, '2026-10-05', 'x', null)['ok']);
        $this->assertFalse($s->registrarEstorno(self::T1, $this->parcela, 10, '2026-09-01', 'x', null)['ok']);
        $this->assertFalse($s->registrarEstorno(self::T2, $this->parcela, 10, '2026-10-05', 'x', null)['ok'], 'outra academia');
        $this->parcela(901, 1, 50, '2026-10-10', 1);
        $this->assertFalse($s->registrarEstorno(self::T1, (int) DB::table('pagamentos_plano')->max('id'), null, '2026-10-12', 'x', null)['ok']);
        $this->assertNull($this->parcelaAtual()->valor_estornado);
    }

    public function test_bi_mantem_a_receita_no_mes_do_pagamento_e_desconta_no_mes_do_estorno(): void
    {
        $antes = app(BiFinanceiroService::class)->financeiro(self::T1, '12m', self::HOJE)['body']['data'];
        $this->service(1)->registrarEstorno(self::T1, $this->parcela, null, '2026-10-05', 'Cobrança em duplicidade', 7);
        Cache::flush();

        $depois = app(BiFinanceiroService::class)->financeiro(self::T1, '12m', self::HOJE)['body']['data'];
        $porMes = array_column($depois['mensal'], null, 'mes');
        $this->assertSame($antes['indicadores']['receita_recebida']['atual'], $depois['indicadores']['receita_recebida']['atual']);
        $this->assertSame(100.0, $depois['indicadores']['estornos']['atual']);
        $this->assertSame(round($depois['indicadores']['receita_recebida']['atual'] - 100, 2), $depois['indicadores']['receita_liquida']['atual']);
        $this->assertSame(100.0, $porMes['2026-10']['estornado']);
        $this->assertSame(0.0, $porMes['2026-09']['estornado']);

        $lista = app(BiVisaoGeralService::class)->detalhe(self::T1, 'estornos', 'mes', self::HOJE)['body']['data'];
        $this->assertSame(100.0, $lista['valor_total']);
        $this->assertSame(['2026-10-05', 'Estornado em'], [$lista['itens'][0]['data'], $lista['itens'][0]['detalhe']]);
    }
}
