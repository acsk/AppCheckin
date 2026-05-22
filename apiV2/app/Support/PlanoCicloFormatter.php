<?php

namespace App\Support;

final class PlanoCicloFormatter
{
    /**
     * @param  list<array<string, mixed>>  $ciclos
     * @return array<int, list<array<string, mixed>>>
     */
    public static function agruparPorPlano(array $ciclos): array
    {
        $valorMensalRefPlano = [];
        foreach ($ciclos as $ciclo) {
            $pid = (int) $ciclo['plano_id'];
            $meses = (int) $ciclo['meses'];
            $vme = (float) $ciclo['valor_mensal_equivalente'];
            if ($meses === 1) {
                $valorMensalRefPlano[$pid] = $vme;
            }
            if (! isset($valorMensalRefPlano[$pid]) || $meses < ($valorMensalRefPlano[$pid.'_meses'] ?? PHP_INT_MAX)) {
                if (! isset($valorMensalRefPlano[$pid])) {
                    $valorMensalRefPlano[$pid] = $vme;
                }
                $valorMensalRefPlano[$pid.'_meses'] = $meses;
            }
        }

        $porPlano = [];
        foreach ($ciclos as $ciclo) {
            $planoId = (int) $ciclo['plano_id'];
            $valorMensalEquivalente = (float) $ciclo['valor_mensal_equivalente'];
            $valorMensalBase = $valorMensalRefPlano[$planoId] ?? 0;
            $economiaPercentual = 0;
            $economiaValor = 0;
            if ($valorMensalBase > 0 && $valorMensalEquivalente < $valorMensalBase) {
                $economiaPercentual = round((($valorMensalBase - $valorMensalEquivalente) / $valorMensalBase) * 100, 0);
                $economiaValor = round(($valorMensalBase - $valorMensalEquivalente) * (int) $ciclo['meses'], 2);
            }

            $porPlano[$planoId][] = [
                'id' => (int) $ciclo['id'],
                'nome' => $ciclo['nome'],
                'codigo' => $ciclo['codigo'],
                'meses' => (int) $ciclo['meses'],
                'valor' => (float) $ciclo['valor'],
                'valor_formatado' => 'R$ '.number_format((float) $ciclo['valor'], 2, ',', '.'),
                'valor_mensal' => $valorMensalEquivalente,
                'valor_mensal_formatado' => 'R$ '.number_format($valorMensalEquivalente, 2, ',', '.'),
                'desconto_percentual' => $economiaPercentual,
                'permite_recorrencia' => (bool) $ciclo['permite_recorrencia'],
                'permite_reposicao' => (bool) $ciclo['permite_reposicao'],
                'pix_disponivel' => ! (bool) $ciclo['permite_recorrencia'],
                'metodos_pagamento' => self::metodosPagamento((bool) $ciclo['permite_recorrencia']),
                'economia' => $economiaPercentual > 0 ? 'Economize '.$economiaPercentual.'%' : null,
                'economia_valor' => $economiaValor > 0
                    ? 'R$ '.number_format($economiaValor, 2, ',', '.').' de economia'
                    : null,
            ];
        }

        return $porPlano;
    }

    /**
     * @param  list<array<string, mixed>>  $ciclosRaw
     * @return list<array<string, mixed>>
     */
    public static function formatarLista(array $ciclosRaw): array
    {
        $valorMensalBase = 0.0;
        foreach ($ciclosRaw as $c) {
            if ((int) $c['meses'] === 1) {
                $valorMensalBase = (float) $c['valor_mensal_equivalente'];
                break;
            }
        }
        if ($valorMensalBase === 0.0 && $ciclosRaw !== []) {
            $valorMensalBase = (float) $ciclosRaw[0]['valor_mensal_equivalente'];
        }

        $result = [];
        foreach ($ciclosRaw as $c) {
            $vme = (float) $c['valor_mensal_equivalente'];
            $economiaPercentual = 0;
            $economiaValor = 0;
            if ($valorMensalBase > 0 && $vme < $valorMensalBase) {
                $economiaPercentual = round((($valorMensalBase - $vme) / $valorMensalBase) * 100, 0);
                $economiaValor = round(($valorMensalBase - $vme) * (int) $c['meses'], 2);
            }
            $result[] = [
                'id' => (int) $c['id'],
                'nome' => $c['nome'],
                'codigo' => $c['codigo'],
                'meses' => (int) $c['meses'],
                'valor' => (float) $c['valor'],
                'valor_formatado' => 'R$ '.number_format((float) $c['valor'], 2, ',', '.'),
                'valor_mensal' => $vme,
                'valor_mensal_formatado' => 'R$ '.number_format($vme, 2, ',', '.'),
                'desconto_percentual' => $economiaPercentual,
                'permite_recorrencia' => (bool) $c['permite_recorrencia'],
                'permite_reposicao' => (bool) $c['permite_reposicao'],
                'pix_disponivel' => ! (bool) $c['permite_recorrencia'],
                'metodos_pagamento' => self::metodosPagamento((bool) $c['permite_recorrencia']),
                'economia' => $economiaPercentual > 0 ? 'Economize '.$economiaPercentual.'%' : null,
                'economia_valor' => $economiaValor > 0
                    ? 'R$ '.number_format($economiaValor, 2, ',', '.').' de economia'
                    : null,
            ];
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public static function metodosPagamento(bool $permiteRecorrencia): array
    {
        return $permiteRecorrencia ? ['checkout'] : ['checkout', 'pix'];
    }

    public static function formatarDuracao(int $dias): string
    {
        if ($dias <= 0) {
            return 'Indefinido';
        }
        if ($dias === 1) {
            return '1 dia';
        }
        if ($dias < 30) {
            return "{$dias} dias";
        }
        if ($dias === 30) {
            return '1 mês';
        }
        if ($dias < 365) {
            $meses = (int) round($dias / 30);

            return $meses === 1 ? '1 mês' : "{$meses} meses";
        }

        $anos = (int) round($dias / 365);

        return $anos === 1 ? '1 ano' : "{$anos} anos";
    }
}
