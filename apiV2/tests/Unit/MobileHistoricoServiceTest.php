<?php

namespace Tests\Unit;

use App\Repositories\CheckinRepository;
use App\Services\Mobile\MobileHistoricoService;
use App\Services\AppVersionService;
use App\Support\AcademyDateTime;
use Mockery;
use Tests\TestCase;

class MobileHistoricoServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_historico_clamps_limit_to_100(): void
    {
        $checkins = Mockery::mock(CheckinRepository::class);
        $checkins->shouldReceive('historicoCheckins')
            ->once()
            ->with(1, 100, 0)
            ->andReturn(['checkins' => [], 'total' => 0]);

        $service = new MobileHistoricoService($checkins);
        $result = $service->historicoCheckins(1, 500, 0);

        $this->assertSame(100, $result['body']['data']['limit']);
    }

    public function test_ranking_mensal_uses_same_month_year_for_queries_and_response(): void
    {
        $periodo = AcademyDateTime::currentMonthYear();

        $checkins = Mockery::mock(CheckinRepository::class);
        $checkins->shouldReceive('contarCheckinsTenantMes')
            ->once()
            ->with(1, $periodo['mes'], $periodo['ano'])
            ->andReturn(0);
        $checkins->shouldReceive('rankingMesAtual')
            ->once()
            ->with(1, null, null, $periodo['mes'], $periodo['ano'])
            ->andReturn([]);

        $checkins->shouldReceive('rankingMesAtual')->once()
            ->with(1, 3, null, $periodo['mes'] === 1 ? 12 : $periodo['mes'] - 1, $periodo['mes'] === 1 ? $periodo['ano'] - 1 : $periodo['ano'])
            ->andReturn([]);

        $service = new MobileHistoricoService($checkins);
        $result = $service->rankingMensal(1, null);

        $this->assertSame($periodo['mes'], $result['body']['data']['mes']);
        $this->assertSame($periodo['ano'], $result['body']['data']['ano']);
        $this->assertNull($result['body']['data']['minha_posicao']);
        $this->assertSame(0, $result['body']['data']['meus_checkins']);
        $this->assertSame([], $result['body']['data']['campeoes_mes_anterior']['ranking']);
    }

    public function test_ranking_mensal_returns_previous_champions_and_student_beyond_podium(): void
    {
        $p = AcademyDateTime::currentMonthYear();
        $row = fn ($id, $count) => ['aluno_id' => $id, 'nome' => "Aluno {$id}", 'email' => "{$id}@example.com", 'total_checkins' => $count];
        $checkins = Mockery::mock(CheckinRepository::class);
        $checkins->shouldReceive('contarCheckinsTenantMes')->with(7, $p['mes'], $p['ano'])->once()->andReturn(50);
        $checkins->shouldReceive('rankingMesAtual')->with(7, null, 3, $p['mes'], $p['ano'])->once()
            ->andReturn([$row(1, 15), $row(2, 12), $row(3, 10), $row(22, 9)]);
        $checkins->shouldReceive('rankingMesAtual')
            ->with(7, 3, 3, $p['mes'] === 1 ? 12 : $p['mes'] - 1, $p['mes'] === 1 ? $p['ano'] - 1 : $p['ano'])
            ->once()->andReturn([$row(3, 20)]);

        $versions = Mockery::mock(AppVersionService::class);
        $versions->shouldReceive('limiteRankingCheckins')->once()->andReturn(25);
        $this->app->instance(AppVersionService::class, $versions);
        $data = (new MobileHistoricoService($checkins))->rankingMensal(7, 3, 22)['body']['data'];
        $this->assertSame(25, $data['limite_home']);
        $this->assertCount(4, $data['ranking']);
        $this->assertSame(4, $data['minha_posicao']);
        $this->assertSame(9, $data['meus_checkins']);
        $this->assertSame(3, $data['campeoes_mes_anterior']['ranking'][0]['aluno']['id']);
        $this->assertSame(20, $data['campeoes_mes_anterior']['ranking'][0]['total_checkins']);
    }

    public function test_ranking_anual_returns_top_10_and_user_position(): void
    {
        $ano = AcademyDateTime::currentYear();

        $checkins = Mockery::mock(CheckinRepository::class);
        $checkins->shouldReceive('contarCheckinsTenantAno')
            ->once()
            ->with(1, $ano)
            ->andReturn(40);
        $checkins->shouldReceive('rankingAnoAtual')
            ->once()
            ->with(1, 10, 3, $ano)
            ->andReturn([
                [
                    'aluno_id' => 9,
                    'nome' => 'Ana',
                    'email' => 'ana@example.com',
                    'foto_caminho' => null,
                    'total_checkins' => 12,
                ],
            ]);
        $checkins->shouldReceive('posicaoAlunoAno')
            ->once()
            ->with(1, 22, 3, $ano)
            ->andReturn(15);

        $service = new MobileHistoricoService($checkins);
        $result = $service->rankingAnual(1, 3, 22);

        $this->assertSame(200, $result['status']);
        $this->assertSame((string) $ano, $result['body']['data']['periodo']);
        $this->assertSame($ano, $result['body']['data']['ano']);
        $this->assertSame(15, $result['body']['data']['minha_posicao']);
        $this->assertCount(1, $result['body']['data']['ranking']);
        $this->assertSame(1, $result['body']['data']['ranking'][0]['posicao']);
    }

    public function test_checkins_por_modalidade_requires_tenant(): void
    {
        $service = new MobileHistoricoService(Mockery::mock(CheckinRepository::class));

        $result = $service->checkinsPorModalidade(1, null, null, 0);

        $this->assertSame(400, $result['status']);
        $this->assertFalse($result['body']['success']);
    }
}
