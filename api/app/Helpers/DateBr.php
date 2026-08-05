<?php

namespace App\Helpers;

/**
 * Formatação de datas sem ambiguidade de locale (evita 07/11 vs 11/07).
 */
final class DateBr
{
    /**
     * Converte Y-m-d (ou datetime iniciando com Y-m-d) para dd/mm/YYYY.
     */
    public static function format(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim($value);
        if ($raw === '' || $raw === '0000-00-00' || str_starts_with($raw, '0000-00-00')) {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m) === 1) {
            return $m[3] . '/' . $m[2] . '/' . $m[1];
        }

        // Já em dd/mm/YYYY
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m) === 1) {
            return $m[1] . '/' . $m[2] . '/' . $m[3];
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return date('d/m/Y', $ts);
    }

    /**
     * Trecho " Vencimento: dd/mm/YYYY." para mensagens de check-in/matrícula.
     */
    public static function vencimentoSuffix(?string $value): string
    {
        $formatted = self::format($value);

        return $formatted !== null ? ' Vencimento: ' . $formatted . '.' : '';
    }
}
