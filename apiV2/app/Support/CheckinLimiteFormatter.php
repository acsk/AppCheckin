<?php

namespace App\Support;

/**
 * Paridade com App\Models\Checkin::formatarDetalhesLimiteMensal (Slim).
 */
final class CheckinLimiteFormatter
{
    /**
     * @param  array<string, mixed>  $detalhes
     * @return array<string, mixed>
     */
    public static function formatarDetalhesLimiteMensal(array $detalhes, bool $paraAluno = true): array
    {
        $direito = (int) ($detalhes['limite_mensal'] ?? 0);
        $usados = (int) ($detalhes['checkins_mes'] ?? 0);
        $excesso = max(0, $usados - $direito);
        $ciclo = (string) ($detalhes['mes_referencia'] ?? '');
        $plano = (string) ($detalhes['plano'] ?? 'seu plano');
        $sujeito = $paraAluno ? 'Você' : 'O aluno';
        $ref = $ciclo !== '' ? $ciclo : $plano;

        $mensagem = sprintf(
            '%s atingiu o limite de check-ins do ciclo do plano (%s). Direito: %d | Usados: %d | Excedeu: %d.',
            $sujeito,
            $ref,
            $direito,
            $usados,
            $excesso
        );

        $datasAulas = self::formatarDatasAulasCiclo($detalhes['dias_checkin'] ?? []);
        $aulasComHorario = self::formatarAulasCicloComHorario($detalhes['dias_checkin'] ?? []);
        $periodoVigente = self::formatarPeriodoVigenteCiclo($detalhes);

        if ($datasAulas !== '') {
            $mensagem .= ' Aulas neste ciclo: '.($aulasComHorario !== '' ? $aulasComHorario : $datasAulas).'.';
        }

        if ($paraAluno) {
            $mensagem .= ' Renove o plano para liberar o próximo ciclo e continuar fazendo check-in.';
        }

        $detalhes['direito'] = $direito;
        $detalhes['usados'] = $usados;
        $detalhes['excesso'] = $excesso;
        $detalhes['datas_aulas'] = $datasAulas;
        $detalhes['aulas_com_horario'] = $aulasComHorario;
        $detalhes['periodo_vigente'] = $periodoVigente;
        $detalhes['mensagem'] = $mensagem;

        return $detalhes;
    }

    /**
     * @param  array<string, mixed>  $detalhes
     */
    public static function formatarPeriodoVigenteCiclo(array $detalhes): string
    {
        $inicioRaw = $detalhes['ciclo_inicio'] ?? $detalhes['periodo_inicio'] ?? null;
        $fimRaw = $detalhes['ciclo_fim'] ?? $detalhes['periodo_fim'] ?? null;

        if (is_string($inicioRaw) && $inicioRaw !== '' && is_string($fimRaw) && $fimRaw !== '') {
            $tsIni = strtotime(substr($inicioRaw, 0, 10));
            $tsFimExcl = strtotime(substr($fimRaw, 0, 10));
            if ($tsIni !== false && $tsFimExcl !== false) {
                $tsFim = strtotime('-1 day', $tsFimExcl);
                if ($tsFim !== false) {
                    return date('d/m/Y', $tsIni).' a '.date('d/m/Y', $tsFim);
                }
            }
        }

        return (string) ($detalhes['mes_referencia'] ?? '');
    }

    /**
     * @param  list<array<string, mixed>|string>  $diasCheckin
     */
    public static function formatarDatasAulasCiclo(array $diasCheckin): string
    {
        $datas = [];
        foreach ($diasCheckin as $dia) {
            $raw = is_array($dia) ? ($dia['data'] ?? null) : $dia;
            if (! is_string($raw) || $raw === '') {
                continue;
            }
            $ts = strtotime(substr($raw, 0, 10));
            if ($ts === false) {
                continue;
            }
            $datas[] = date('d/m', $ts);
        }

        return implode(', ', array_values(array_unique($datas)));
    }

    /**
     * @param  list<array<string, mixed>|string>  $diasCheckin
     */
    public static function formatarAulasCicloComHorario(array $diasCheckin): string
    {
        $itens = [];
        foreach ($diasCheckin as $dia) {
            if (is_string($dia)) {
                $ts = strtotime(substr($dia, 0, 10));
                if ($ts === false) {
                    continue;
                }
                $itens[] = date('d/m', $ts);

                continue;
            }

            if (! is_array($dia)) {
                continue;
            }

            $raw = $dia['data'] ?? null;
            if (! is_string($raw) || $raw === '') {
                continue;
            }
            $ts = strtotime(substr($raw, 0, 10));
            if ($ts === false) {
                continue;
            }
            $horario = $dia['horario'] ?? null;
            if (is_string($horario) && $horario !== '') {
                $itens[] = date('d/m', $ts).' '.substr($horario, 0, 5);
            } else {
                $itens[] = date('d/m', $ts);
            }
        }

        return implode(', ', $itens);
    }
}
