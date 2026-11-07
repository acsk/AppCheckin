<?php

namespace App\Support;

use DateTime;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regra de turma fixa: o ciclo de cobrança é ancorado no dia de vencimento da matrícula.
 * Pagar antes ou depois não move o vencimento: a parcela paga cobre [vencimento, vencimento + ciclo]
 * e a seguinte vence no mesmo dia do mês (dia 31 em mês curto cai no último dia).
 */
final class VencimentoTurmaFixa
{
    /**
     * Matrícula de turma fixa e seu dia de vencimento; null quando não é de turma fixa ou não há como saber.
     *
     * @return array{dia_vencimento: ?int}|null
     */
    public static function daMatricula(int $matriculaId): ?array
    {
        try {
            if (! Schema::hasColumn('matriculas', 'tipo_acesso')) {
                return null;
            }
            $m = DB::table('matriculas')->where('id', $matriculaId)->first(['tipo_acesso', 'dia_vencimento']);
        } catch (\Throwable) {
            return null;
        }
        if (! $m || ($m->tipo_acesso ?? null) !== 'turma_fixa') {
            return null;
        }
        $dia = (int) ($m->dia_vencimento ?? 0);

        return ['dia_vencimento' => $dia >= 1 && $dia <= 31 ? $dia : null];
    }

    /**
     * Vencimento seguinte ao informado, somando o ciclo do plano.
     *
     * Ciclo em meses, ou plano de 28 a 31 dias (mensal), vai para o mesmo dia do mês seguinte; outras
     * durações somam dias. Sem dia de vencimento, usa o dia da própria data.
     */
    public static function proximo(DateTimeInterface $vencimento, ?int $meses, int $duracaoDias, ?int $diaVencimento): DateTime
    {
        $base = DateTime::createFromInterface($vencimento);
        $base->setTime(0, 0);
        $meses = $meses && $meses > 0 ? $meses : (($duracaoDias >= 28 && $duracaoDias <= 31) ? 1 : null);
        if ($meses === null) {
            return $base->modify('+'.max(1, $duracaoDias).' days');
        }
        $dia = $diaVencimento ?: (int) $base->format('j');
        $alvo = (clone $base)->modify('first day of this month')->modify("+{$meses} months");

        return $alvo->setDate((int) $alvo->format('Y'), (int) $alvo->format('n'), min($dia, (int) $alvo->format('t')));
    }

    /** Ocorrência do dia de vencimento mais recente até hoje (hoje, se for o próprio dia). */
    public static function ultimaOcorrencia(string $hoje, int $diaVencimento): string
    {
        $dia = new DateTime($hoje);
        $noMes = static fn (DateTime $mes): DateTime => (clone $mes)->setDate(
            (int) $mes->format('Y'),
            (int) $mes->format('n'),
            min($diaVencimento, (int) $mes->format('t')),
        );
        $candidato = $noMes((clone $dia)->modify('first day of this month'));
        if ($candidato > $dia) {
            $candidato = $noMes((clone $dia)->modify('first day of last month'));
        }

        return $candidato->format('Y-m-d');
    }
}
