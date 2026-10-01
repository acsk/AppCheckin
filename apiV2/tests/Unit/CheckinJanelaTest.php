<?php

namespace Tests\Unit;

use App\Support\AcademyDateTime;
use App\Support\CheckinJanela;
use Tests\TestCase;

class CheckinJanelaTest extends TestCase
{
    private function inicio(): \DateTime
    {
        return AcademyDateTime::fromDateAndTime('2026-10-01', '18:00:00');
    }

    public function test_sem_prazo_fecha_apos_inicio_com_tolerancia_depois(): void
    {
        $turma = ['tolerancia_minutos' => 10, 'tolerancia_antes_checkin_minutos' => null];

        $this->assertNull(CheckinJanela::fechamentoAntesMinutos($turma));
        $this->assertSame('2026-10-01 18:10:00', CheckinJanela::fechamento($this->inicio(), $turma)->format('Y-m-d H:i:s'));
    }

    public function test_com_prazo_fecha_antes_do_inicio(): void
    {
        $turma = ['tolerancia_minutos' => 10, 'tolerancia_antes_checkin_minutos' => 30];

        $this->assertSame(30, CheckinJanela::fechamentoAntesMinutos($turma));
        $this->assertSame('2026-10-01 17:30:00', CheckinJanela::fechamento($this->inicio(), $turma)->format('Y-m-d H:i:s'));
    }

    public function test_prazo_zero_fecha_no_inicio(): void
    {
        $turma = ['tolerancia_antes_checkin_minutos' => '0'];

        $this->assertSame('2026-10-01 18:00:00', CheckinJanela::fechamento($this->inicio(), $turma)->format('Y-m-d H:i:s'));
    }

    public function test_cancelamento_padrao_e_configurado(): void
    {
        $this->assertSame(
            '2026-10-01 18:00:00',
            CheckinJanela::limiteCancelamento($this->inicio(), [])->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            '2026-10-01 16:00:00',
            CheckinJanela::limiteCancelamento($this->inicio(), ['tolerancia_cancelamento_minutos' => 120])->format('Y-m-d H:i:s'),
        );
    }

    public function test_nao_altera_o_horario_de_inicio(): void
    {
        $inicio = $this->inicio();
        CheckinJanela::fechamento($inicio, ['tolerancia_antes_checkin_minutos' => 30]);
        CheckinJanela::limiteCancelamento($inicio, ['tolerancia_cancelamento_minutos' => 60]);

        $this->assertSame('18:00', $inicio->format('H:i'));
    }
}
