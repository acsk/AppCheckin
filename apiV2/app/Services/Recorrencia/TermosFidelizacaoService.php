<?php

namespace App\Services\Recorrencia;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Termos adicionais da fidelização no nível da academia. Ciclo com fidelização sem texto próprio herda este;
 * ciclo com texto próprio (plano_ciclos.termos) usa o dele. Mudar o texto herdado sobe a versão dos termos
 * dos ciclos que herdam (contratos já aceitos não mudam: guardam o texto aceito).
 */
class TermosFidelizacaoService
{
    public const MAX = 5000;

    /** Texto herdado pelos ciclos da academia ('' quando não há). */
    public function textoDaAcademia(int $tenantId): string
    {
        if (! Schema::hasTable('termos_fidelizacao')) {
            return '';
        }

        return trim((string) DB::table('termos_fidelizacao')->where('tenant_id', $tenantId)->value('texto'));
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function obter(int $tenantId): array
    {
        $row = DB::table('termos_fidelizacao')->where('tenant_id', $tenantId)->first();
        $ciclos = DB::table('plano_ciclos as pc')->join('planos as p', 'p.id', '=', 'pc.plano_id')
            ->join('assinatura_frequencias as af', 'af.id', '=', 'pc.assinatura_frequencia_id')
            ->where('pc.tenant_id', $tenantId)->where('pc.cobranca_mensal', 1)
            ->orderBy('p.nome')->orderBy('pc.meses')
            ->get(['pc.id', 'pc.plano_id', 'p.nome as plano_nome', 'af.nome as ciclo_nome', 'pc.termos', 'pc.ativo']);

        return ['status' => 200, 'body' => ['success' => true,
            'texto' => (string) ($row->texto ?? ''),
            'versao' => (int) ($row->versao ?? 0),
            'atualizado_em' => $row->updated_at ?? null,
            'ciclos' => $ciclos->map(fn ($c) => [
                'id' => (int) $c->id, 'plano_id' => (int) $c->plano_id, 'plano_nome' => $c->plano_nome, 'ciclo_nome' => $c->ciclo_nome,
                'ativo' => (bool) $c->ativo, 'herda' => trim((string) $c->termos) === '',
                'termos' => trim((string) $c->termos) !== '' ? (string) $c->termos : null,
            ])->all(),
        ]];
    }

    /**
     * @param  array{texto?: mixed, aplicar_a_todos?: mixed}  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function salvar(int $tenantId, array $data, ?int $usuarioId): array
    {
        $texto = trim((string) ($data['texto'] ?? ''));
        if (mb_strlen($texto) > self::MAX) {
            return ['status' => 422, 'body' => ['success' => false, 'error' => 'Os termos aceitam até 5.000 caracteres.']];
        }
        $todos = filter_var($data['aplicar_a_todos'] ?? false, FILTER_VALIDATE_BOOLEAN);

        DB::transaction(function () use ($tenantId, $texto, $todos, $usuarioId) {
            $atual = DB::table('termos_fidelizacao')->where('tenant_id', $tenantId)->first();
            $mudou = trim((string) ($atual->texto ?? '')) !== $texto;
            if ($atual) {
                DB::table('termos_fidelizacao')->where('tenant_id', $tenantId)->update([
                    'texto' => $texto !== '' ? $texto : null, 'versao' => (int) $atual->versao + ($mudou ? 1 : 0),
                    'atualizado_por' => $usuarioId, 'updated_at' => now(),
                ]);
            } else {
                DB::table('termos_fidelizacao')->insert([
                    'tenant_id' => $tenantId, 'texto' => $texto !== '' ? $texto : null, 'versao' => 1,
                    'atualizado_por' => $usuarioId, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $ciclos = DB::table('plano_ciclos')->where('tenant_id', $tenantId)->where('cobranca_mensal', 1);
            // "Aplicar a todos": ciclos personalizados voltam a herdar (o texto deles muda, então sobe a versão)
            $alvo = $todos ? (clone $ciclos) : (clone $ciclos)->where(fn ($q) => $q->whereNull('termos')->orWhere('termos', ''));
            if ($mudou || $todos) {
                $alvo->update(['termos' => null, 'termos_versao' => DB::raw('termos_versao + 1'), 'updated_at' => now()]);
            }
        });

        return ['status' => 200, 'body' => ['message' => $todos ? 'Termos salvos e aplicados a todos os ciclos.' : 'Termos da academia salvos.'] + $this->obter($tenantId)['body']];
    }
}
