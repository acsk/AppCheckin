<?php

namespace Tests\Unit;

use App\Repositories\DashboardRepository;
use App\Services\Admin\AdminDashboardService;
use Mockery;
use Tests\TestCase;

class AdminDashboardServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cards_inclui_matriculas_por_status(): void
    {
        $repo = Mockery::mock(DashboardRepository::class);
        $repo->shouldReceive('totalAlunosCards')->with(3)->andReturn(['total' => 355, 'ativos' => 355, 'inativos' => 0]);
        $repo->shouldReceive('receitaMensalCards')->with(3)->andReturn(['valor' => 0, 'valor_formatado' => 'R$ 0,00', 'contas_pendentes' => 0]);
        $repo->shouldReceive('checkinsCards')->with(3)->andReturn(['hoje' => 10, 'no_mes' => 335]);
        $repo->shouldReceive('planosVencendoCards')->with(3)->andReturn(['vencendo' => 13, 'novos_este_mes' => 19]);
        $repo->shouldReceive('matriculasCards')->with(3)->andReturn([
            'alunos_com_matricula_ativa' => 120, 'ativas' => 124, 'vencidas' => 30, 'pendentes' => 5, 'bloqueadas' => 2,
        ]);

        $result = (new AdminDashboardService($repo))->cards(3);

        $this->assertSame(200, $result['status']);
        $this->assertSame(120, $result['body']['data']['matriculas']['alunos_com_matricula_ativa']);
        $this->assertSame(355, $result['body']['data']['total_alunos']['ativos']);
    }

    public function test_cards_sem_tenant_traz_matriculas_zeradas(): void
    {
        $repo = Mockery::mock(DashboardRepository::class);

        $result = (new AdminDashboardService($repo))->cards(null);

        $this->assertSame(0, $result['body']['data']['matriculas']['ativas']);
        $this->assertSame(0, $result['body']['data']['matriculas']['alunos_com_matricula_ativa']);
    }
}
