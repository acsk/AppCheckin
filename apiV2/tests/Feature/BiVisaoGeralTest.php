<?php

namespace Tests\Feature;

use App\Services\Bi\BiVisaoGeralService;
use App\Support\Bi\Periodo;
use Tests\Concerns\BiSchema;
use Tests\TestCase;

/**
 * Fórmulas da Visão geral do BI (SQLite). Cenário em Tests\Concerns\BiSchema.
 */
class BiVisaoGeralTest extends TestCase
{
    use BiSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarCenarioBi();
    }

    private function dados(?string $preset = 'mes'): array
    {
        $r = app(BiVisaoGeralService::class)->visaoGeral(self::T1, $preset, self::HOJE);
        $this->assertSame(200, $r['status']);

        return $r['body']['data'];
    }

    public function test_alunos_novos_e_saidas(): void
    {
        $i = $this->dados()['indicadores'];

        $this->assertSame(['atual' => 3, 'anterior' => 3], $i['alunos_ativos']); // A, B, D hoje; A, C, D em 20/09
        $this->assertSame(['atual' => 2, 'anterior' => 0], $i['novos_alunos']);  // B e E (diária também é aluno novo)
        $this->assertSame(['atual' => 1, 'anterior' => 0], $i['saidas']);        // só C
    }

    public function test_financeiro_separa_recebido_previsto_e_vencido(): void
    {
        $d = $this->dados();
        $i = $d['indicadores'];

        $this->assertSame(250.0, $i['receita_recebida']['atual']);
        $this->assertSame(200.0, $i['receita_recebida']['anterior']);
        $this->assertSame(125.0, $i['ticket_medio']['atual']);   // 250 / 2 pagantes
        $this->assertSame(350.0, $i['receita_prevista']['atual']); // 100 + 120 + 80 + 50
        $this->assertSame(130.0, $i['valor_vencido']['atual']);
        $this->assertSame(37.1, $i['inadimplencia']['atual']);
        $this->assertSame(['previsto' => 350.0, 'pago' => 100.0, 'a_vencer' => 120.0, 'vencido' => 130.0, 'vencido_qtd' => 2], $d['parcelas_periodo']);

        $out = end($d['receita_mensal']);
        $this->assertSame(['mes' => '2026-10', 'previsto' => 350.0, 'recebido' => 250.0], $out);
        $this->assertCount(12, $d['receita_mensal']);
    }

    public function test_checkins_pelo_dia_da_aula_sem_ausentes_e_sem_outra_academia(): void
    {
        $d = $this->dados();

        $this->assertSame(['atual' => 3, 'anterior' => 1], $d['indicadores']['checkins']);
        $this->assertSame('dia', $d['checkins_serie']['agrupamento']);
        $this->assertCount(20, $d['checkins_serie']['pontos']);
        $porDia = array_column($d['checkins_serie']['pontos'], 'total', 'data');
        $this->assertSame(1, $porDia['2026-10-06']);
        $this->assertSame(0, $porDia['2026-10-07']);
    }

    public function test_doze_meses_agrupa_checkins_por_mes(): void
    {
        $d = $this->dados('12m');

        $this->assertSame('mes', $d['checkins_serie']['agrupamento']);
        $this->assertSame(4, $d['indicadores']['checkins']['atual']);
    }

    private function detalhe(string $indicador, string $preset = 'mes'): array
    {
        $r = app(BiVisaoGeralService::class)->detalhe(self::T1, $indicador, $preset, self::HOJE);
        $this->assertSame(200, $r['status']);

        return $r['body']['data'];
    }

    public function test_detalhamento_bate_com_os_cards(): void
    {
        $i = $this->dados()['indicadores'];

        foreach (['alunos_ativos', 'novos_alunos', 'saidas'] as $k) {
            $this->assertSame($i[$k]['atual'], $this->detalhe($k)['quantidade'], $k);
        }
        $this->assertSame($i['receita_recebida']['atual'], $this->detalhe('receita_recebida')['valor_total']);
        $this->assertSame($i['valor_vencido']['atual'], $this->detalhe('valor_vencido')['valor_total']);
    }

    public function test_detalhamento_mostra_quem_compoe_e_nada_de_outra_academia(): void
    {
        $this->assertSame(['ANA', 'BRUNO', 'DIEGO'], array_column($this->detalhe('alunos_ativos')['itens'], 'aluno_nome'));
        $this->assertSame(['BRUNO', 'EVA'], array_column($this->detalhe('novos_alunos')['itens'], 'aluno_nome'));

        $saida = $this->detalhe('saidas')['itens'];
        $this->assertSame([['CARLA', '2026-10-10']], array_map(fn ($x) => [$x['aluno_nome'], $x['data']], $saida));

        $recebido = $this->detalhe('receita_recebida')['itens'];
        $this->assertSame(['2026-10-05', '2026-10-02'], array_column($recebido, 'data'));
        $this->assertSame('BRUNO', $recebido[1]['aluno_nome'], 'aluno pela matrícula quando a parcela não tem aluno');

        $this->assertSame([80.0, 50.0], array_column($this->detalhe('valor_vencido')['itens'], 'valor'));
        $this->assertNotContains('OUTRA ACADEMIA', array_column($this->detalhe('saidas')['itens'], 'aluno_nome'));
    }

    public function test_detalhamento_recusa_indicador_desconhecido(): void
    {
        $this->assertSame(422, app(BiVisaoGeralService::class)->detalhe(self::T1, 'tenants', 'mes', self::HOJE)['status']);
    }

    public function test_periodo_compara_intervalos_equivalentes(): void
    {
        $p = Periodo::resolver('mes', '2026-03-31');
        $this->assertSame(['2026-03-01', '2026-03-31', '2026-02-01', '2026-02-28'], [$p->inicio, $p->fim, $p->anteriorInicio, $p->anteriorFim]);

        $p = Periodo::resolver('mes_anterior', '2026-03-15');
        $this->assertSame(['2026-02-01', '2026-02-28', '2026-01-01', '2026-01-31'], [$p->inicio, $p->fim, $p->anteriorInicio, $p->anteriorFim]);

        $p = Periodo::resolver('3m', self::HOJE);
        $this->assertSame(90, $p->dias());
        $this->assertSame('2026-07-23', $p->inicio);
        $this->assertSame('2026-07-22', $p->anteriorFim);
        $this->assertSame(89, (int) (new \DateTime($p->anteriorInicio))->diff(new \DateTime($p->anteriorFim))->days);

        $this->assertSame('mes', Periodo::resolver('qualquer', self::HOJE)->preset);
    }
}
