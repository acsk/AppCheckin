<?php

namespace Tests\Feature;

use App\Services\Bi\BiFinanceiroService;
use App\Services\Bi\BiVisaoGeralService;
use Tests\Concerns\BiSchema;
use Tests\TestCase;

/**
 * Fórmulas do Financeiro do BI (SQLite). Cenário em Tests\Concerns\BiSchema.
 */
class BiFinanceiroTest extends TestCase
{
    use BiSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarCenarioBi();
    }

    private function dados(string $preset = 'mes'): array
    {
        $r = app(BiFinanceiroService::class)->financeiro(self::T1, $preset, self::HOJE);
        $this->assertSame(200, $r['status']);

        return $r['body']['data'];
    }

    public function test_indicadores_batem_com_a_visao_geral(): void
    {
        $fin = $this->dados()['indicadores'];
        $visao = app(BiVisaoGeralService::class)->visaoGeral(self::T1, 'mes', self::HOJE)['body']['data']['indicadores'];

        foreach (['receita_prevista', 'receita_recebida', 'valor_vencido', 'inadimplencia', 'ticket_medio'] as $k) {
            $this->assertSame($visao[$k], $fin[$k], $k);
        }
        $this->assertSame(['atual' => 120.0, 'anterior' => 0.0], $fin['a_vencer']);
        $this->assertSame(['atual' => 20.0, 'anterior' => 5.0], $fin['descontos']);
    }

    public function test_recebido_por_plano_e_por_forma_sem_outra_academia(): void
    {
        $d = $this->dados();

        // Plano da parcela (Trimestral) vale sobre o da matrícula (Mensal)
        $this->assertSame([
            ['nome' => 'Trimestral', 'valor' => 150.0, 'parcelas' => 1],
            ['nome' => 'Mensal', 'valor' => 100.0, 'parcelas' => 1],
        ], $d['por_plano']);
        $this->assertSame([
            ['nome' => 'Não informada', 'valor' => 150.0, 'parcelas' => 1],
            ['nome' => 'Pix', 'valor' => 100.0, 'parcelas' => 1],
        ], $d['por_forma']);
    }

    public function test_historico_mensal(): void
    {
        $mensal = $this->dados()['mensal'];
        $this->assertCount(12, $mensal);
        $porMes = array_column($mensal, null, 'mes');

        $this->assertSame(
            ['previsto' => 350.0, 'a_vencer' => 120.0, 'vencido' => 130.0, 'recebido' => 250.0, 'pagantes' => 2, 'mes' => '2026-10', 'inadimplencia' => 37.1, 'ticket_medio' => 125.0],
            $porMes['2026-10'],
        );
        // Setembro: a parcela de 28/09 foi paga em outubro, então entra no previsto de setembro e no recebido de outubro
        $this->assertSame(350.0, $porMes['2026-09']['previsto']);
        $this->assertSame(200.0, $porMes['2026-09']['recebido']);
        $this->assertSame(0.0, $porMes['2026-09']['inadimplencia']);
        $this->assertNull($porMes['2025-11']['inadimplencia'], 'sem previsto, sem taxa');
    }

    public function test_detalhamento_a_vencer(): void
    {
        $d = app(BiVisaoGeralService::class)->detalhe(self::T1, 'a_vencer', 'mes', self::HOJE)['body']['data'];

        $this->assertSame(120.0, $d['valor_total']);
        $this->assertSame([['ANA', '2026-10-20', 'Vence em']], array_map(fn ($x) => [$x['aluno_nome'], $x['data'], $x['detalhe']], $d['itens']));
    }
}
