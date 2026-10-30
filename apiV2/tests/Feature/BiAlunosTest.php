<?php

namespace Tests\Feature;

use App\Services\Bi\BiAlunosService;
use App\Services\Bi\BiVisaoGeralService;
use Tests\Concerns\BiSchema;
use Tests\TestCase;

/**
 * Fórmulas de Alunos e matrículas do BI (SQLite). Cenário em Tests\Concerns\BiSchema.
 */
class BiAlunosTest extends TestCase
{
    use BiSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarCenarioBi();
    }

    private function dados(string $preset = 'mes'): array
    {
        $r = app(BiAlunosService::class)->alunos(self::T1, $preset, self::HOJE);
        $this->assertSame(200, $r['status']);

        return $r['body']['data'];
    }

    public function test_entradas_retornos_saidas_e_saldo(): void
    {
        $i = $this->dados()['indicadores'];

        $this->assertSame(['atual' => 4, 'anterior' => 3], $i['alunos_ativos']);
        $this->assertSame(['atual' => 2, 'anterior' => 0], $i['novos_alunos']);
        // Só G: renovação (A) e troca de plano (D) emendam na matrícula anterior
        $this->assertSame(['atual' => 1, 'anterior' => 0], $i['reativados']);
        $this->assertSame(['atual' => 1, 'anterior' => 0], $i['saidas']);
        $this->assertSame(['atual' => 2, 'anterior' => 0], $i['saldo']);
    }

    public function test_retencao_e_permanencia(): void
    {
        $d = $this->dados();

        // Ativos em 30/09: A, C, D. Em 20/10 seguem A e D.
        $this->assertSame(['atual' => 66.7, 'anterior' => 100.0], $d['indicadores']['retencao']);
        // C: primeira matrícula em 01/07, cancelou em 10/10 (101 dias)
        $this->assertSame(['atual' => 3.3, 'anterior' => null], $d['indicadores']['permanencia_meses']);
        $this->assertSame(1, array_column($d['permanencia_saidas'], 'alunos', 'faixa')['3 a 6 meses']);
        $this->assertSame(1, array_sum(array_column($d['permanencia_saidas'], 'alunos')));
    }

    public function test_distribuicao_dos_ativos_por_plano_e_modalidade(): void
    {
        $d = $this->dados();

        $this->assertSame([['nome' => 'Mensal', 'alunos' => 3], ['nome' => 'Trimestral', 'alunos' => 1]], $d['por_plano']);
        $this->assertSame([['nome' => 'Natação', 'alunos' => 3], ['nome' => 'Hidroginástica', 'alunos' => 1]], $d['por_modalidade']);
    }

    public function test_historico_mensal(): void
    {
        $porMes = array_column($this->dados()['mensal'], null, 'mes');

        $this->assertCount(12, $porMes);
        $this->assertSame(['mes' => '2026-10', 'ativos' => 4, 'novos' => 2, 'reativados' => 1, 'saidas' => 1], $porMes['2026-10']);
        $this->assertSame(1, $porMes['2026-03']['novos'], 'G entrou pela primeira vez em março');
    }

    public function test_detalhamento_de_quem_voltou(): void
    {
        $d = app(BiVisaoGeralService::class)->detalhe(self::T1, 'reativados', 'mes', self::HOJE)['body']['data'];

        $this->assertSame(1, $d['quantidade']);
        $this->assertSame(
            ['GUSTAVO', 'Trimestral', 'Hidroginástica', '2026-10-06', 'Voltou em'],
            [$d['itens'][0]['aluno_nome'], $d['itens'][0]['plano_nome'], $d['itens'][0]['modalidade_nome'], $d['itens'][0]['data'], $d['itens'][0]['detalhe']],
        );
    }
}
