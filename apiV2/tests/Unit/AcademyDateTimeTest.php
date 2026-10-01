<?php

namespace Tests\Unit;

use App\Support\AcademyDateTime;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_current_month_year_uses_sao_paulo(): void
    {
        $periodo = AcademyDateTime::currentMonthYear();
        $now = AcademyDateTime::now();

        $this->assertSame((int) $now->format('n'), $periodo['mes']);
        $this->assertSame((int) $now->format('Y'), $periodo['ano']);
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

    public function test_today_e_now_formatted_no_fuso_da_academia_mesmo_com_processo_em_outro_fuso(): void
    {
        $original = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');

        try {
            $tz = new DateTimeZone(AcademyDateTime::TZ);
            // Cada valor é lido uma vez, entre duas marcações do relógio de São Paulo: mesmo que
            // vire o segundo/hora/dia no meio, ele tem de estar dentro do intervalo [antes, depois].
            $antes = new \DateTime('now', $tz);
            $hoje = AcademyDateTime::today();
            $agora = AcademyDateTime::nowFormatted();
            $mes = AcademyDateTime::currentMonth();
            $ano = AcademyDateTime::currentYear();
            $depois = new \DateTime('now', $tz);

            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $agora);
            $this->assertGreaterThanOrEqual($antes->format('Y-m-d H:i:s'), $agora);
            $this->assertLessThanOrEqual($depois->format('Y-m-d H:i:s'), $agora);
            $this->assertContains($hoje, [$antes->format('Y-m-d'), $depois->format('Y-m-d')]);
            $this->assertContains($mes, [(int) $antes->format('n'), (int) $depois->format('n')]);
            $this->assertContains($ano, [(int) $antes->format('Y'), (int) $depois->format('Y')]);
        } finally {
            date_default_timezone_set($original);
        }
    }

    public function test_from_date_and_time_aceita_hora_sem_segundos(): void
    {
        $dt = AcademyDateTime::fromDateAndTime('2026-05-22', '18:30');

        $this->assertNotNull($dt);
        $this->assertSame('2026-05-22 18:30:00', $dt->format('Y-m-d H:i:s'));
        $this->assertSame(AcademyDateTime::TZ, $dt->getTimezone()->getName());
    }

    public function test_from_date_and_time_invalido_retorna_null(): void
    {
        $this->assertNull(AcademyDateTime::fromDateAndTime('22/05/2026', '18:00:00'));
        $this->assertNull(AcademyDateTime::fromDateAndTime('2026-05-22', 'manhã'));
    }

    /**
     * @return array<string, array{mixed, ?string}>
     */
    public static function valoresDateOnly(): array
    {
        return [
            'data simples' => ['2026-10-01', '2026-10-01'],
            'datetime meia-noite' => ['2026-10-01 00:00:00', '2026-10-01'],
            'datetime com hora' => ['2026-10-01 23:59:59', '2026-10-01'],
            'espaços nas pontas' => ['  2026-10-01  ', '2026-10-01'],
            'null' => [null, null],
            'vazio' => ['', null],
            'só espaços' => ['   ', null],
            'zero date' => ['0000-00-00', null],
            'zero datetime' => ['0000-00-00 00:00:00', null],
            'inválido' => ['amanhã cedo', null],
            'relativo tomorrow' => ['tomorrow', null],
            'relativo +1 week' => ['+1 week', null],
            'stdClass' => [new \stdClass, null],
            'array' => [['2026-10-01'], null],
            'bool' => [true, null],
            'int parecendo data' => [20261001, null],
            'timestamp' => [1727740800, null],
            'float' => [20261001.0, null],
            'string numérica YYYYMMDD' => ['20261001', null],
            'string "2026" (viraria 20:26 de hoje)' => ['2026', null],
            'string timestamp' => ['1727740800', null],
            'string id curto' => ['123', null],
            'string 8 dígitos inválida' => ['20261345', null],
            'ISO com offset' => ['2026-10-02T01:00:00+00:00', '2026-10-01'],
            'ISO com milissegundos e offset' => ['2026-10-02T01:00:00.000-03:00', '2026-10-02'],
            'ISO com microssegundos e UTC Z' => ['2026-10-02T01:00:00.123456Z', '2026-10-01'],
            'ISO com mais de 6 casas decimais' => ['2026-10-02T01:00:00.1234567Z', null],
            'ISO com UTC Z' => ['2026-10-02T01:00:00Z', '2026-10-01'],
            'datetime com hora inválida' => ['2026-10-01 24:00:00', null],
            'ISO com data inválida' => ['2026-02-30T01:00:00+00:00', null],
            'ISO com offset inválido' => ['2026-10-01T01:00:00+14:30', null],
            'Stringable' => [new class implements \Stringable
            {
                public function __toString(): string
                {
                    return '2026-10-01 08:00:00';
                }
            }, '2026-10-01'],
            'DateTimeImmutable' => [new \DateTimeImmutable('2026-10-01 10:00:00', new \DateTimeZone('America/Sao_Paulo')), '2026-10-01'],
            // 01:00 UTC de 02/10 = 22:00 de 01/10 em São Paulo.
            'DateTime em UTC' => [new \DateTime('2026-10-02 01:00:00', new \DateTimeZone('UTC')), '2026-10-01'],
        ];
    }

    #[DataProvider('valoresDateOnly')]
    public function test_date_only_normaliza(mixed $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, AcademyDateTime::dateOnly($entrada));
    }

    public function test_date_only_permite_comparacao_correta_com_hoje(): void
    {
        $hoje = AcademyDateTime::today();

        // Como string crua, "Y-m-d 00:00:00" > "Y-m-d" — a normalização evita isso.
        $this->assertTrue($hoje.' 00:00:00' > $hoje);
        $this->assertFalse(AcademyDateTime::dateOnly($hoje.' 00:00:00') > $hoje);
        $this->assertTrue(AcademyDateTime::dateOnly($hoje.' 00:00:00') <= $hoje);
    }

    public function test_date_only_usa_fuso_da_academia_para_offset_explicito(): void
    {
        // 01:00 UTC de 02/10 = 22:00 de 01/10 em São Paulo.
        $this->assertSame('2026-10-01', AcademyDateTime::dateOnly('2026-10-02T01:00:00+00:00'));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function valoresFromYmd(): array
    {
        return [
            'válido' => ['2026-07-13', '2026-07-13'],
            'bissexto' => ['2028-02-29', '2028-02-29'],
            'formato BR' => ['13/07/2026', null],
            'dia inexistente' => ['2026-02-30', null],
            'com hora' => ['2026-07-13 10:00:00', null],
            'sem zero à esquerda' => ['2026-7-13', null],
            'vazio' => ['', null],
        ];
    }

    #[DataProvider('valoresFromYmd')]
    public function test_from_ymd_e_estrito(string $entrada, ?string $esperado): void
    {
        $dt = AcademyDateTime::fromYmd($entrada);

        $this->assertSame($esperado, $dt?->format('Y-m-d'));
    }

    public function test_from_ymd_meia_noite_no_fuso_da_academia_mesmo_com_processo_em_utc(): void
    {
        $original = date_default_timezone_get();
        date_default_timezone_set('UTC');

        try {
            $dt = AcademyDateTime::fromYmd('2026-07-13');

            $this->assertSame(AcademyDateTime::TZ, $dt->getTimezone()->getName());
            $this->assertSame('2026-07-13 00:00:00', $dt->format('Y-m-d H:i:s'));
        } finally {
            date_default_timezone_set($original);
        }
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function valoresFromDbDate(): array
    {
        return [
            'date' => ['2026-10-01', '2026-10-01'],
            'datetime' => ['2026-10-01 23:59:59', '2026-10-01'],
            'só ano' => ['2026', null],
            'relativo' => ['tomorrow', null],
            'relativo +1 week' => ['+1 week', null],
            'sem zero à esquerda' => ['2026-7-13', null],
            'dia inexistente' => ['2026-02-30', null],
            'hora inválida' => ['2026-10-01 24:00:00', null],
            'ISO com T' => ['2026-10-01T10:00:00', null],
            'vazio' => ['', null],
        ];
    }

    #[DataProvider('valoresFromDbDate')]
    public function test_from_db_date_e_estrito(string $entrada, ?string $esperado): void
    {
        $dt = AcademyDateTime::fromDbDate($entrada);

        $this->assertSame($esperado, $dt?->format('Y-m-d'));
        if ($dt !== null) {
            $this->assertSame('00:00:00', $dt->format('H:i:s'));
        }
    }

    public function test_tz_e_reutilizado(): void
    {
        $this->assertSame(AcademyDateTime::TZ, AcademyDateTime::tz()->getName());
        $this->assertSame(AcademyDateTime::tz(), AcademyDateTime::tz());
    }

    public function test_parse_e_today_start_no_fuso_da_academia(): void
    {
        $this->assertSame(AcademyDateTime::TZ, AcademyDateTime::parse('2026-10-01')->getTimezone()->getName());
        $this->assertSame('00:00:00', AcademyDateTime::todayStart()->format('H:i:s'));
        $this->assertSame(AcademyDateTime::today(), AcademyDateTime::todayStart()->format('Y-m-d'));
    }
}
