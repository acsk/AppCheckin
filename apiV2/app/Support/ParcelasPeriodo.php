<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Evita duas cobranças para o mesmo período de uma matrícula.
 *
 * A parcela vence no início do período que ela paga: duas parcelas válidas (não canceladas) com o
 * mesmo vencimento cobram o mesmo período duas vezes. Ex.: matrícula refeita no dia em que vencia,
 * com a renovação ainda em aberto, gerava a "Primeiro pagamento" no mesmo dia da renovação.
 */
final class ParcelasPeriodo
{
    /** Cancelado. */
    private const CANCELADO = 4;

    /**
     * Parcelas válidas que já cobrem o período a partir de $inicio (vencimento >= $inicio).
     *
     * @return list<array{id: int, valor: float, data_vencimento: string, status: string}>
     */
    public static function existentesAPartirDe(int $tenantId, int $matriculaId, string $inicio): array
    {
        $rows = DB::table('pagamentos_plano as pp')
            ->leftJoin('status_pagamento as sp', 'sp.id', '=', 'pp.status_pagamento_id')
            ->where('pp.tenant_id', $tenantId)
            ->where('pp.matricula_id', $matriculaId)
            ->where('pp.status_pagamento_id', '!=', self::CANCELADO)
            ->where('pp.data_vencimento', '>=', $inicio)
            ->orderBy('pp.data_vencimento')
            ->orderBy('pp.id')
            ->get(['pp.id', 'pp.valor', 'pp.data_vencimento', 'pp.status_pagamento_id', 'sp.nome as status_nome']);

        return $rows->map(static fn ($r) => [
            'id' => (int) $r->id,
            'valor' => (float) $r->valor,
            'data_vencimento' => substr((string) $r->data_vencimento, 0, 10),
            'status' => (string) ($r->status_nome ?? ((int) $r->status_pagamento_id === 2 ? 'Pago' : 'Aguardando')),
        ])->all();
    }

    /**
     * Outra parcela válida já cobre o período seguinte à parcela paga (vencimento entre o da paga e o
     * próximo). Nesse caso a baixa não gera mais uma.
     */
    public static function periodoSeguinteJaCobrado(int $tenantId, int $matriculaId, int $pagaId, string $vencimentoPaga, string $proximoVencimento): bool
    {
        return DB::table('pagamentos_plano')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->where('id', '!=', $pagaId)
            ->where('status_pagamento_id', '!=', self::CANCELADO)
            ->where('data_vencimento', '>=', $vencimentoPaga)
            ->where('data_vencimento', '<=', $proximoVencimento)
            ->exists();
    }

    /**
     * Vencimentos com mais de uma parcela válida (cobrança duplicada no mesmo período).
     *
     * @param  list<array<string, mixed>>  $pagamentos  como vêm no detalhe da matrícula
     * @return list<array{data_vencimento: string, parcelas: list<array{id: int, valor: float, status: string}>, mensagem: string}>
     */
    public static function duplicadas(array $pagamentos): array
    {
        $porData = [];
        foreach ($pagamentos as $p) {
            if ((int) ($p['status_pagamento_id'] ?? 0) === self::CANCELADO || ! empty($p['simulacao_troca'])) {
                continue;
            }
            $data = substr((string) ($p['data_vencimento'] ?? ''), 0, 10);
            if ($data === '') {
                continue;
            }
            $porData[$data][] = [
                'id' => (int) ($p['id'] ?? 0),
                'valor' => (float) ($p['valor'] ?? 0),
                'status' => (string) ($p['status'] ?? ''),
            ];
        }

        $alertas = [];
        foreach ($porData as $data => $parcelas) {
            if (count($parcelas) < 2) {
                continue;
            }
            $lista = implode(' e ', array_map(
                static fn ($x) => '#'.$x['id'].' '.self::brl($x['valor']).' ('.($x['status'] ?: 'Aguardando').')',
                $parcelas
            ));
            $alertas[] = [
                'data_vencimento' => $data,
                'parcelas' => $parcelas,
                'mensagem' => count($parcelas).' cobranças com vencimento em '.self::dataBr($data).': '.$lista
                    .'. Confira se uma delas é duplicada e cancele a que sobrou.',
            ];
        }

        return $alertas;
    }

    /** @param  list<array{id: int, valor: float, data_vencimento: string, status: string}>  $parcelas */
    public static function descrever(array $parcelas): string
    {
        return implode('; ', array_map(
            static fn ($x) => '#'.$x['id'].' de '.self::brl($x['valor']).' com vencimento em '.self::dataBr($x['data_vencimento']).' ('.$x['status'].')',
            $parcelas
        ));
    }

    private static function brl(float $v): string
    {
        return 'R$ '.number_format($v, 2, ',', '.');
    }

    private static function dataBr(string $ymd): string
    {
        $p = explode('-', substr($ymd, 0, 10));

        return count($p) === 3 ? "{$p[2]}/{$p[1]}/{$p[0]}" : $ymd;
    }
}
