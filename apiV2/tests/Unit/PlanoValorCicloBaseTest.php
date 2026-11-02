<?php

namespace Tests\Unit;

use App\Repositories\AdminPlanoCicloRepository;
use App\Repositories\AdminPlanoRepository;
use App\Services\Admin\AdminPlanoCicloService;
use App\Services\Recorrencia\RecorrenciaService;
use App\Services\ReajustePrecoService;
use Mockery;
use Tests\TestCase;

/**
 * Caso real (plano 19, Diária): o ciclo diário foi para R$ 25 e planos.valor, que o app mostra, ficou em R$ 20,
 * porque só o ciclo mensal sincronizava o valor do plano.
 */
class PlanoValorCicloBaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(ReajustePrecoService::class, Mockery::mock(ReajustePrecoService::class, ['reajustarCiclo' => ['matriculas' => 0, 'parcelas' => 0]]));
        $this->app->instance(RecorrenciaService::class, Mockery::mock(RecorrenciaService::class, ['reajustarContratos' => []]));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function atualizar(string $frequencia, int $duracaoDias, AdminPlanoRepository $planos): array
    {
        $ciclos = Mockery::mock(AdminPlanoCicloRepository::class);
        $ciclos->shouldReceive('findCiclo')->andReturn([
            'id' => 61, 'plano_id' => 19, 'valor' => 20.0, 'tipo_meses' => 1, 'tipo_codigo' => $frequencia,
            'plano_valor' => 20.0, 'plano_duracao_dias' => $duracaoDias, 'permite_recorrencia' => 0, 'permite_reposicao' => 1, 'ativo' => 1,
        ]);
        $ciclos->shouldReceive('update')->once();
        $ciclos->shouldReceive('listarPorPlano')->andReturn([['id' => 61, 'meses' => 1, 'valor' => 25.0]]);
        $ciclos->shouldReceive('updateDesconto');

        return (new AdminPlanoCicloService($ciclos, $planos))->atualizar(19, 61, 3, ['valor' => 25]);
    }

    public function test_ciclo_diario_do_plano_diaria_atualiza_o_valor_do_plano(): void
    {
        $planos = Mockery::mock(AdminPlanoRepository::class);
        $planos->shouldReceive('updateValor')->once()->with(19, 3, 25.0);

        $this->assertSame(200, $this->atualizar('diario', 1, $planos)['status']);
    }

    public function test_ciclo_diario_de_plano_mensal_nao_muda_o_valor_do_plano(): void
    {
        $planos = Mockery::mock(AdminPlanoRepository::class);
        $planos->shouldNotReceive('updateValor');

        $this->assertSame(200, $this->atualizar('diario', 30, $planos)['status']);
    }

    public function test_ciclo_mensal_de_plano_mensal_continua_atualizando(): void
    {
        $planos = Mockery::mock(AdminPlanoRepository::class);
        $planos->shouldReceive('updateValor')->once()->with(19, 3, 25.0);

        $this->assertSame(200, $this->atualizar('mensal', 30, $planos)['status']);
    }
}
