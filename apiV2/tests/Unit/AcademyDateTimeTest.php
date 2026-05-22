<?php

namespace Tests\Unit;

use App\Support\AcademyDateTime;
use DateTimeZone;
use Tests\TestCase;

class AcademyDateTimeTest extends TestCase
{
    public function test_now_uses_sao_paulo_timezone(): void
    {
        $now = AcademyDateTime::now();

        $this->assertSame(AcademyDateTime::TZ, $now->getTimezone()->getName());
    }

    public function test_from_date_and_time_uses_sao_paulo_timezone(): void
    {
        $dt = AcademyDateTime::fromDateAndTime('2026-05-22', '18:00:00');

        $this->assertNotNull($dt);
        $this->assertSame(AcademyDateTime::TZ, $dt->getTimezone()->getName());
        $this->assertSame('2026-05-22 18:00:00', $dt->format('Y-m-d H:i:s'));
    }

    public function test_checkin_window_matches_horarios_logic(): void
    {
        $data = '2026-05-22';
        $horario = '18:00:00';
        $toleranciaAntes = 480;
        $toleranciaDepois = 10;

        $dataHoraTurma = AcademyDateTime::fromDateAndTime($data, $horario);
        $abertura = clone $dataHoraTurma;
        $abertura->modify("-{$toleranciaAntes} minutes");
        $fechamento = clone $dataHoraTurma;
        $fechamento->modify("+{$toleranciaDepois} minutes");

        $agoraSp = AcademyDateTime::fromDateAndTime($data, '17:00:00');
        $this->assertTrue($agoraSp >= $abertura && $agoraSp <= $fechamento);

        $antesAbertura = AcademyDateTime::fromDateAndTime($data, '09:00:00');
        $inicioAula = AcademyDateTime::fromDateAndTime($data, $horario);
        $this->assertTrue($antesAbertura < $inicioAula);
        $this->assertSame(
            (new DateTimeZone(AcademyDateTime::TZ))->getName(),
            $antesAbertura->getTimezone()->getName(),
        );
    }
}
