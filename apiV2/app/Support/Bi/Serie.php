<?php

namespace App\Support\Bi;

/** Séries dos gráficos do BI */
final class Serie
{
    /** Acima disso a série vem por mês, não por dia */
    public const MAX_DIAS_DIARIA = 62;

    /**
     * Valores por dia do período completados com zero; em períodos longos, somados por mês.
     *
     * @param  array<string, int|float>  $porDia  'Y-m-d' => valor
     * @return array{agrupamento: string, pontos: list<array{data: string, total: int|float}>}
     */
    public static function diariaOuMensal(array $porDia, Periodo $p): array
    {
        if ($p->dias() <= self::MAX_DIAS_DIARIA) {
            $pontos = [];
            for ($d = $p->inicio; $d <= $p->fim; $d = date('Y-m-d', strtotime($d.' +1 day'))) {
                $pontos[] = ['data' => $d, 'total' => $porDia[$d] ?? 0];
            }

            return ['agrupamento' => 'dia', 'pontos' => $pontos];
        }

        $meses = [];
        foreach ($porDia as $d => $v) {
            $meses[substr($d, 0, 7)] = ($meses[substr($d, 0, 7)] ?? 0) + $v;
        }
        $pontos = [];
        for ($m = substr($p->inicio, 0, 7); $m <= substr($p->fim, 0, 7); $m = date('Y-m', strtotime($m.'-01 +1 month'))) {
            $pontos[] = ['data' => $m, 'total' => $meses[$m] ?? 0];
        }

        return ['agrupamento' => 'mes', 'pontos' => $pontos];
    }
}
