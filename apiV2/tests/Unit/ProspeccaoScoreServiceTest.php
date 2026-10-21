<?php

namespace Tests\Unit;

use App\Services\Admin\Prospeccao\ProspeccaoScoreService;
use PHPUnit\Framework\TestCase;

class ProspeccaoScoreServiceTest extends TestCase
{
    /** @return array<string, mixed> */
    private function dados(array $extra = []): array
    {
        return $extra + [
            'checkins' => 36,
            'dias_matricula' => 84, // 12 semanas x 3 = 36 possíveis
            'checkins_semanais' => 3,
            'dias_desde_cancelamento' => 10,
            'parcelas_em_dia' => 3,
            'parcelas_avaliadas' => 3,
            'dias_permanencia' => 400,
            'motivo_categoria' => 'horario',
            'interesse_recente' => true,
        ];
    }

    public function test_aluno_ideal_tem_score_maximo_e_confianca_total(): void
    {
        $r = ProspeccaoScoreService::calcular($this->dados(), ProspeccaoScoreService::CONFIG_PADRAO);

        $this->assertSame(100, $r['score']);
        $this->assertSame(100, $r['confianca']);
        $this->assertSame('alto', ProspeccaoScoreService::classificacao($r['score']));
    }

    public function test_dados_ausentes_nao_pontuam_e_reduzem_a_confianca_sem_inventar_valor(): void
    {
        $r = ProspeccaoScoreService::calcular($this->dados([
            'dias_matricula' => 5,          // frequência indisponível (25)
            'parcelas_avaliadas' => 0,      // pagamentos indisponível (20)
            'parcelas_em_dia' => 0,
            'motivo_categoria' => null,     // motivo indisponível (10)
        ]), ProspeccaoScoreService::CONFIG_PADRAO);

        // Disponíveis: recência 20 + permanência 15 + interesse 10 = 45, todos com fator 1
        $this->assertSame(100, $r['score']);
        $this->assertSame(45, $r['confianca']);
        $porCriterio = array_column($r['criterios'], null, 'criterio');
        $this->assertNull($porCriterio['frequencia']['pontos']);
        $this->assertNull($porCriterio['pagamentos']['fator']);
    }

    public function test_pesos_configuraveis_e_motivo_nao_reversivel(): void
    {
        $config = ProspeccaoScoreService::normalizarConfig(['pesos' => ['frequencia' => 0, 'interesse' => 0], 'motivos_reversiveis' => ['financeiro']]);
        $r = ProspeccaoScoreService::calcular($this->dados(['motivo_categoria' => 'mudanca', 'dias_desde_cancelamento' => 200]), $config);

        // recência 0/20, pagamentos 20/20, permanência 15/15, motivo 0/10 → 35/65
        $this->assertSame(54, $r['score']);
        $this->assertSame('medio', ProspeccaoScoreService::classificacao($r['score']));
    }

    public function test_frequencia_proporcional_e_limitada_a_um(): void
    {
        $metade = ProspeccaoScoreService::calcular($this->dados(['checkins' => 18]), ProspeccaoScoreService::CONFIG_PADRAO);
        $acima = ProspeccaoScoreService::calcular($this->dados(['checkins' => 99]), ProspeccaoScoreService::CONFIG_PADRAO);

        $this->assertSame(0.5, array_column($metade['criterios'], null, 'criterio')['frequencia']['fator']);
        $this->assertEquals(1.0, array_column($acima['criterios'], null, 'criterio')['frequencia']['fator']);
    }

    public function test_config_invalida_cai_no_padrao(): void
    {
        $this->assertSame(ProspeccaoScoreService::CONFIG_PADRAO, ProspeccaoScoreService::normalizarConfig('{lixo'));
        $this->assertSame(100, ProspeccaoScoreService::normalizarConfig(['pesos' => ['recencia' => 900]])['pesos']['recencia']);
        $this->assertSame([], ProspeccaoScoreService::normalizarConfig(['motivos_reversiveis' => ['inexistente']])['motivos_reversiveis']);
        $this->assertNull(ProspeccaoScoreService::classificacao(null));
    }
}
