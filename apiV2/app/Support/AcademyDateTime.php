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

    private static ?DateTimeZone $tz = null;

    /** Fuso da academia (instância única: DateTimeZone é imutável, pode ser compartilhada). */
    public static function tz(): DateTimeZone
    {
        return self::$tz ??= new DateTimeZone(self::TZ);
    }

    public static function now(): DateTime
    {
        return new DateTime('now', self::tz());
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

    /** Hoje à meia-noite no fuso da academia. */
    public static function todayStart(): DateTime
    {
        return new DateTime('today', self::tz());
    }

    /**
     * Interpreta uma data/hora livre (ex.: "2026-09-01", "2026-09-01 10:00") no fuso da academia.
     * Lança \Exception para valores inválidos, como new DateTime().
     */
    public static function parse(string $value): DateTime
    {
        return new DateTime($value, self::tz());
    }

    /**
     * Normaliza data/datetime para Y-m-d (no fuso da academia), para comparar com segurança.
     * Aceita string, \Stringable ou \DateTimeInterface. Strings aceitas: Y-m-d, Y-m-d H:i:s ou
     * ISO-8601 com offset (frações de segundo opcionais, até 6 dígitos). Formatos relativos e numéricos são rejeitados.
     * Retorna null para vazio, "0000-00-00" ou valor inválido.
     * Ex.: "2026-10-01 00:00:00" → "2026-10-01" (como string pura, "2026-10-01 00:00:00" > "2026-10-01").
     */
    public static function dateOnly(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value)
                ->setTimezone(self::tz())
                ->format('Y-m-d');
        }

        // Só string/Stringable: (string) em array/stdClass lançaria Error fora do try; números são rejeitados.
        if (! (is_string($value) || $value instanceof \Stringable)) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return self::fromYmd($value)?->format('Y-m-d');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1) {
            $dt = DateTime::createFromFormat('!Y-m-d H:i:s', $value, self::tz());
            $errors = DateTime::getLastErrors();

            return $dt !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                ? $dt->format('Y-m-d')
                : null;
        }

        if (preg_match(
            '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?(Z|[+-]\d{2}:\d{2})$/',
            $value,
            $parts,
        ) !== 1) {
            return null;
        }

        $offset = preg_match('/([+-])(\d{2}):(\d{2})$/', $parts[3], $offsetParts) === 1
            ? [(int) $offsetParts[2], (int) $offsetParts[3]]
            : [0, 0];
        if ($offset[0] > 14 || $offset[1] > 59 || ($offset[0] === 14 && $offset[1] !== 0)) {
            return null;
        }

        $fraction = str_pad($parts[2] ?? '', 6, '0');
        $offsetValue = $parts[3] === 'Z' ? '+00:00' : $parts[3];
        $isoValue = $parts[1].'.'.$fraction.$offsetValue;
        $dt = DateTime::createFromFormat('!Y-m-d\TH:i:s.uP', $isoValue, self::tz());
        $errors = DateTime::getLastErrors();

        return $dt !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            ? $dt->setTimezone(self::tz())->format('Y-m-d')
            : null;
    }

    /**
     * Valida estritamente "Y-m-d" (sem hora, sem outros formatos) e devolve meia-noite no fuso da academia.
     * Retorna null se inválido (ex.: "13/07/2026", "2026-02-30", "2026-07-13 10:00").
     */
    public static function fromYmd(string $value): ?DateTime
    {
        // "!" zera hora/minuto/segundo (sem ele, createFromFormat usa a hora atual).
        $dt = DateTime::createFromFormat('!Y-m-d', $value, self::tz());

        return $dt && $dt->format('Y-m-d') === $value ? $dt : null;
    }

    /**
     * Data vinda do banco em formato estrito: "Y-m-d" ou "Y-m-d H:i:s" (DATE/DATETIME do MySQL).
     * Devolve meia-noite do dia no fuso da academia, ou null para qualquer outra coisa —
     * inclusive formatos relativos que o DateTime aceitaria ("tomorrow", "2026", "+1 week").
     */
    public static function fromDbDate(string $value): ?DateTime
    {
        $value = trim($value);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})(?: (\d{2}):(\d{2}):(\d{2}))?$/', $value, $m) !== 1) {
            return null;
        }

        if (isset($m[2]) && ((int) $m[2] > 23 || (int) $m[3] > 59 || (int) $m[4] > 59)) {
            return null;
        }

        return self::fromYmd($m[1]);
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
            self::tz(),
        );

        return $dt ?: null;
    }
}
