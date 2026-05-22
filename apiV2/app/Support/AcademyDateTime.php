<?php

namespace App\Support;

use DateTime;
use DateTimeZone;

/**
 * Horários de aula e janelas de check-in usam o fuso da academia (Brasil).
 */
final class AcademyDateTime
{
    public const TZ = 'America/Sao_Paulo';

    public static function now(): DateTime
    {
        return new DateTime('now', new DateTimeZone(self::TZ));
    }

    public static function today(): string
    {
        return self::now()->format('Y-m-d');
    }

    public static function nowFormatted(): string
    {
        return self::now()->format('Y-m-d H:i:s');
    }

    public static function currentMonth(): int
    {
        return (int) self::now()->format('n');
    }

    public static function currentYear(): int
    {
        return (int) self::now()->format('Y');
    }

    /**
     * @return array{mes: int, ano: int}
     */
    public static function currentMonthYear(): array
    {
        $now = self::now();

        return [
            'mes' => (int) $now->format('n'),
            'ano' => (int) $now->format('Y'),
        ];
    }

    /**
     * Interpreta data (Y-m-d) + hora (H:i:s ou H:i) no fuso da academia.
     */
    public static function fromDateAndTime(string $date, string $time): ?DateTime
    {
        $time = strlen($time) === 5 ? $time.':00' : $time;

        $dt = DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $date.' '.$time,
            new DateTimeZone(self::TZ),
        );

        return $dt ?: null;
    }
}
