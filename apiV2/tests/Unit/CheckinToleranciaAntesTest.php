<?php

namespace Tests\Unit;

use App\Models\Parametro;
use App\Support\CheckinToleranciaAntes;
use Mockery;
use Tests\TestCase;

class CheckinToleranciaAntesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sem_teto_usa_valor_da_turma(): void
    {
        $param = Mockery::mock(Parametro::class);
        $param->shouldReceive('getInt')->with(2, CheckinToleranciaAntes::PARAM_CODIGO, 0)->andReturn(0);

        $svc = new CheckinToleranciaAntes($param);

        $this->assertSame(480, $svc->effectiveAntesMinutos(2, 480));
        $this->assertSame(30, $svc->effectiveAntesMinutos(2, 30));
    }

    public function test_com_teto_aplica_minimo(): void
    {
        $param = Mockery::mock(Parametro::class);
        $param->shouldReceive('getInt')->with(1, CheckinToleranciaAntes::PARAM_CODIGO, 0)->andReturn(30);

        $svc = new CheckinToleranciaAntes($param);

        $this->assertSame(30, $svc->effectiveAntesMinutos(1, 480));
        $this->assertSame(15, $svc->effectiveAntesMinutos(1, 15));
        $this->assertSame(30, $svc->clampParaTurma(1, 600));
    }
}
