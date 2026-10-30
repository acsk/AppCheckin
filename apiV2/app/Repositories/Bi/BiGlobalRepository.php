<?php

namespace App\Repositories\Bi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consultas do BI Global (superadmin): somas entre academias e a receita da própria plataforma.
 * Os números por academia vêm de BiTenantRepository, para baterem com o BI de cada uma.
 * O tenant do sistema (a plataforma) fica de fora de tudo.
 */
class BiGlobalRepository
{
    /** @return list<object{id: int, nome: string, ativo: int}> */
    public function academias(int $sistemaId): array
    {
        return DB::table('tenants')->where('id', '<>', $sistemaId)->orderBy('nome')->get(['id', 'nome', 'ativo'])->all();
    }

    /** Alunos ativos somando as academias, no fim de cada mês (o último mês no fim do período) */
    public function alunosAtivosNoFimDoMes(int $sistemaId, array $refs): array
    {
        $out = [];
        foreach ($refs as $mes => $ref) {
            $out[$mes] = (int) DB::selectOne('
                SELECT COUNT(*) AS total FROM (
                    SELECT DISTINCT m.tenant_id, m.aluno_id FROM matriculas m JOIN alunos a ON a.id = m.aluno_id
                    WHERE m.tenant_id <> ? AND m.status_id <> 5 AND m.data_inicio <= ? AND m.data_vencimento >= ?
                      AND (m.data_cancelamento IS NULL OR m.data_cancelamento > ?)
                ) x', [$sistemaId, $ref, $ref, $ref])->total;
        }

        return $out;
    }

    /** @return array<string, float> mês => recebido pelas academias (pagas e estornadas, pela data do pagamento) */
    public function recebidoPorMes(int $sistemaId, string $inicio, string $fim): array
    {
        return collect(DB::select('
            SELECT SUBSTR(data_pagamento, 1, 7) AS mes, SUM(valor) AS total FROM pagamentos_plano
            WHERE tenant_id <> ? AND status_pagamento_id IN (2, 5) AND data_pagamento BETWEEN ? AND ?
            GROUP BY SUBSTR(data_pagamento, 1, 7)
        ', [$sistemaId, $inicio, $fim]))->mapWithKeys(fn ($r) => [(string) $r->mes => round((float) $r->total, 2)])->all();
    }

    /** @return array<string, int> mês => check-ins (pelo dia da aula, sem ausentes) */
    public function checkinsPorMes(int $sistemaId, string $inicio, string $fim): array
    {
        $dia = 'DATE(COALESCE(d.data, c.data_checkin_date, c.data_checkin))';

        return collect(DB::select("
            SELECT SUBSTR({$dia}, 1, 7) AS mes, COUNT(*) AS total
            FROM checkins c LEFT JOIN turmas t ON t.id = c.turma_id LEFT JOIN dias d ON d.id = t.dia_id
            WHERE c.tenant_id <> ? AND (c.presente IS NULL OR c.presente = 1) AND {$dia} BETWEEN ? AND ?
            GROUP BY SUBSTR({$dia}, 1, 7)
        ", [$sistemaId, $inicio, $fim]))->mapWithKeys(fn ($r) => [(string) $r->mes => (int) $r->total])->all();
    }

    /**
     * Mensalidades das academias para a plataforma (pagamentos_contrato): recebido pela data do pagamento,
     * vencido = em aberto com vencimento no período antes de hoje.
     *
     * @return array{recebido: float, vencido: float, academias_pagantes: int}
     */
    public function receitaPlataforma(string $inicio, string $fim, string $hoje): array
    {
        if (! Schema::hasTable('pagamentos_contrato') || ! Schema::hasTable('tenant_planos_sistema')) {
            return ['recebido' => 0.0, 'vencido' => 0.0, 'academias_pagantes' => 0];
        }
        $r = DB::selectOne('
            SELECT
                COALESCE(SUM(CASE WHEN pc.status_pagamento_id = 2 AND pc.data_pagamento BETWEEN ? AND ? THEN pc.valor ELSE 0 END), 0) AS recebido,
                COALESCE(SUM(CASE WHEN pc.status_pagamento_id IN (1, 3) AND pc.data_vencimento BETWEEN ? AND ? AND pc.data_vencimento < ? THEN pc.valor ELSE 0 END), 0) AS vencido,
                COUNT(DISTINCT CASE WHEN pc.status_pagamento_id = 2 AND pc.data_pagamento BETWEEN ? AND ? THEN tp.tenant_id END) AS pagantes
            FROM pagamentos_contrato pc JOIN tenant_planos_sistema tp ON tp.id = pc.tenant_plano_id
        ', [$inicio, $fim, $inicio, $fim, $hoje, $inicio, $fim]);

        return ['recebido' => round((float) $r->recebido, 2), 'vencido' => round((float) $r->vencido, 2), 'academias_pagantes' => (int) $r->pagantes];
    }
}
