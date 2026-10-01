<?php

namespace App\Support;

use DateTime;

/**
 * Prazos do aluno em relação ao início da aula, a partir dos campos da turma.
 * Campos NULL mantêm o comportamento anterior.
 */
final class CheckinJanela
{
    public const CAMPO_FECHAMENTO = 'tolerancia_antes_checkin_minutos';

    public const CAMPO_CANCELAMENTO = 'tolerancia_cancelamento_minutos';

    /** Minutos antes do início em que o check-in fecha; null = não configurado. */
    public static function fechamentoAntesMinutos(array $turma): ?int
    {
        return self::minutosOpcionais($turma, self::CAMPO_FECHAMENTO);
    }

    /** Minutos antes do início até quando o aluno pode desfazer; null = até o início. */
    public static function cancelamentoAntesMinutos(array $turma): ?int
    {
        return self::minutosOpcionais($turma, self::CAMPO_CANCELAMENTO);
    }

    /**
     * Configurado: início − tolerancia_antes_checkin_minutos.
     * Não configurado: início + tolerancia_minutos (padrão 10).
     */
    public static function fechamento(DateTime $inicio, array $turma): DateTime
    {
        $fechamento = clone $inicio;
        $antes = self::fechamentoAntesMinutos($turma);

        if ($antes !== null) {
            return $fechamento->modify("-{$antes} minutes");
        }

        $depois = (int) ($turma['tolerancia_minutos'] ?? 10);

        return $fechamento->modify("+{$depois} minutes");
    }

    public static function limiteCancelamento(DateTime $inicio, array $turma): DateTime
    {
        $limite = clone $inicio;
        $antes = self::cancelamentoAntesMinutos($turma) ?? 0;

        return $limite->modify("-{$antes} minutes");
    }

    private static function minutosOpcionais(array $turma, string $campo): ?int
    {
        $valor = $turma[$campo] ?? null;
        if ($valor === null || $valor === '') {
            return null;
        }

        return max(0, (int) $valor);
    }
}
