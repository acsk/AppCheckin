<?php

namespace Tests\Feature;

use App\Services\Bi\BiFrequenciaService;
use App\Services\Bi\BiVisaoGeralService;
use Tests\Concerns\BiSchema;
use Tests\TestCase;

/**
 * Fórmulas de Frequência e ocupação do BI (SQLite). Cenário em Tests\Concerns\BiSchema.
 */
class BiFrequenciaTest extends TestCase
{
    use BiSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarCenarioBi();
    }

    private function dados(string $preset = 'mes'): array
    {
        $r = app(BiFrequenciaService::class)->frequencia(self::T1, $preset, self::HOJE);
        $this->assertSame(200, $r['status']);

        return $r['body']['data'];
    }

    public function test_checkins_participacao_e_frequencia(): void
    {
        $i = $this->dados()['indicadores'];

        $this->assertSame(['atual' => 3, 'anterior' => 1], $i['checkins']);
        $this->assertSame(['atual' => 1, 'anterior' => 1], $i['participantes']);
        $this->assertSame(25.0, $i['participacao']['atual']);   // 1 de 4 ativos
        $this->assertSame(round(3 / (20 / 7), 1), $i['frequencia_semanal']['atual']);
    }

    public function test_ocupacao_so_das_aulas_com_limite_e_sem_outra_academia(): void
    {
        $d = $this->dados();

        // Natação 06:00: 1 check-in em 10 vagas (06/10) e 0 em 10 (08/10). Hidro sem limite e aula desativada ficam fora.
        $this->assertSame(['atual' => 5.0, 'anterior' => null], $d['indicadores']['ocupacao']);

        $this->assertSame([
            ['modalidade' => 'Natação', 'horario' => '06:00', 'professor' => 'Rafael Lima', 'aulas' => 2, 'checkins' => 1, 'media_por_aula' => 0.5, 'limite' => 10, 'ocupacao' => 5.0],
            ['modalidade' => 'Hidroginástica', 'horario' => '19:00', 'professor' => 'Carla Souza', 'aulas' => 1, 'checkins' => 1, 'media_por_aula' => 1.0, 'limite' => null, 'ocupacao' => null],
        ], $d['turmas']);
        $this->assertSame(['Rafael Lima' => 1, 'Carla Souza' => 1], array_column($d['por_professor'], 'checkins', 'nome'));
    }

    public function test_mapa_pela_media_de_cada_dia_da_semana(): void
    {
        $mapa = [];
        foreach ($this->dados()['mapa'] as $m) {
            $mapa[$m['dia_semana'].'-'.$m['hora']] = $m['media'];
        }

        // Terça 06h (aula de 06/10): 1 check-in ÷ 3 terças no período. Quinta 07h: check-in sem aula.
        $this->assertSame(['2-6' => 0.3, '4-7' => 0.3, '5-19' => 0.3], $mapa);
    }

    public function test_alunos_que_pedem_atencao(): void
    {
        $d = $this->dados();

        // B e G nunca fizeram check-in e começaram há 14 dias ou mais; D começou a matrícula atual em 12/10
        $this->assertSame(['atual' => 2, 'anterior' => null], $d['indicadores']['sem_checkin']);
        $this->assertSame(['BRUNO', 'GUSTAVO'], array_column($d['atencao']['sem_checkin'], 'aluno_nome'));
        $this->assertSame('Nunca fez check-in', $d['atencao']['sem_checkin'][0]['detalhe']);

        // D: 8 check-ins nas 12 semanas antes (0,7 por semana) e nenhum nas últimas 4
        $this->assertSame(['atual' => 1, 'anterior' => null], $d['indicadores']['em_queda']);
        $queda = $d['atencao']['em_queda'][0];
        $this->assertSame(['DIEGO', 0.0, 0.7, -100.0, '0x por semana, antes 0,7x'], [$queda['aluno_nome'], $queda['semana_recente'], $queda['semana_antes'], $queda['variacao'], $queda['detalhe']]);
    }

    public function test_detalhamento_das_listas_de_atencao(): void
    {
        $s = app(BiVisaoGeralService::class);
        $this->assertSame(2, $s->detalhe(self::T1, 'sem_checkin', 'mes', self::HOJE)['body']['data']['quantidade']);
        $this->assertSame(['DIEGO'], array_column($s->detalhe(self::T1, 'em_queda', 'mes', self::HOJE)['body']['data']['itens'], 'aluno_nome'));
    }
}
