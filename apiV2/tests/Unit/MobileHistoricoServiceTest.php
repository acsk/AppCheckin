<?php

namespace Tests\Unit;

use App\Repositories\CheckinRepository;
use App\Services\Mobile\MobileHistoricoService;
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
            ->with(1, 3, null, $periodo['mes'], $periodo['ano'])
            ->andReturn([]);

        $service = new MobileHistoricoService($checkins);
        $result = $service->rankingMensal(1, null);

        $this->assertSame($periodo['mes'], $result['body']['data']['mes']);
        $this->assertSame($periodo['ano'], $result['body']['data']['ano']);
    }

    public function test_checkins_por_modalidade_requires_tenant(): void
    {
        $service = new MobileHistoricoService(Mockery::mock(CheckinRepository::class));

        $result = $service->checkinsPorModalidade(1, null, null, 0);

        $this->assertSame(400, $result['status']);
        $this->assertFalse($result['body']['success']);
    }
}
