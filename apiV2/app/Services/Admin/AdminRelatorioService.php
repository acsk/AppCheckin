<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Relatórios admin (paridade RelatorioController::planosCiclos).
 */
class AdminRelatorioService
{
    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function planosCiclos(int $tenantId, array $query): array
    {
        try {
            $filtroAtivo = null;
            if (isset($query['ativo']) && $query['ativo'] !== '') {
                $filtroAtivo = (int) $query['ativo'];
            }

            $filtroModalidade = null;
            if (isset($query['modalidade_id']) && $query['modalidade_id'] !== '') {
                $filtroModalidade = (int) $query['modalidade_id'];
            }

            $tenant = DB::selectOne('SELECT id, nome FROM tenants WHERE id = ?', [$tenantId]);
            $tenant = $tenant ? (array) $tenant : ['id' => $tenantId, 'nome' => ''];

            $sqlPlanos = '
                SELECT p.id, p.nome, p.valor, p.checkins_semanais, p.duracao_dias, p.ativo, p.atual,
                       p.created_at, p.updated_at,
                       m.id as modalidade_id, m.nome as modalidade_nome, m.cor as modalidade_cor, m.icone as modalidade_icone
                FROM planos p
                LEFT JOIN modalidades m ON m.id = p.modalidade_id
                WHERE p.tenant_id = ?
            ';
            $params = [$tenantId];

            if ($filtroAtivo !== null) {
                $sqlPlanos .= ' AND p.ativo = ?';
                $params[] = $filtroAtivo;
            }

            if ($filtroModalidade !== null) {
                $sqlPlanos .= ' AND p.modalidade_id = ?';
                $params[] = $filtroModalidade;
            }

            $sqlPlanos .= ' ORDER BY m.nome ASC, p.nome ASC, p.valor ASC';
            $planos = array_map(fn ($r) => (array) $r, DB::select($sqlPlanos, $params));

            $todosCiclos = array_map(
                fn ($r) => (array) $r,
                DB::select('
                    SELECT pc.id, pc.plano_id, pc.meses, pc.valor, pc.valor_mensal_equivalente,
                           pc.desconto_percentual, pc.permite_recorrencia, pc.permite_reposicao, pc.ativo,
                           af.nome as frequencia_nome, af.codigo as frequencia_codigo, af.ordem
                    FROM plano_ciclos pc
                    INNER JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
                    WHERE pc.tenant_id = ?
                    ORDER BY pc.plano_id, af.ordem ASC
                ', [$tenantId])
            );

            $ciclosPorPlano = [];
            foreach ($todosCiclos as $ciclo) {
                $planoId = $ciclo['plano_id'];
                $ciclo['id'] = (int) $ciclo['id'];
                $ciclo['meses'] = (int) $ciclo['meses'];
                $ciclo['valor'] = (float) $ciclo['valor'];
                $ciclo['valor_mensal_equivalente'] = (float) $ciclo['valor_mensal_equivalente'];
                $ciclo['desconto_percentual'] = (float) $ciclo['desconto_percentual'];
                $ciclo['permite_recorrencia'] = (bool) $ciclo['permite_recorrencia'];
                $ciclo['permite_reposicao'] = (bool) $ciclo['permite_reposicao'];
                $ciclo['ativo'] = (bool) $ciclo['ativo'];
                $ciclo['valor_formatado'] = 'R$ '.number_format($ciclo['valor'], 2, ',', '.');
                $ciclo['valor_mensal_formatado'] = 'R$ '.number_format($ciclo['valor_mensal_equivalente'], 2, ',', '.');
                unset($ciclo['plano_id']);
                $ciclosPorPlano[$planoId][] = $ciclo;
            }

            $resultado = [];
            $resumo = [
                'total_planos' => 0,
                'planos_ativos' => 0,
                'planos_inativos' => 0,
                'total_ciclos' => 0,
                'ciclos_ativos' => 0,
                'ciclos_inativos' => 0,
                'planos_sem_ciclos' => 0,
                'modalidades' => [],
            ];

            foreach ($planos as $plano) {
                $planoId = (int) $plano['id'];
                $ciclos = $ciclosPorPlano[$planoId] ?? [];

                $planoFormatado = [
                    'id' => $planoId,
                    'nome' => $plano['nome'],
                    'valor' => (float) $plano['valor'],
                    'valor_formatado' => 'R$ '.number_format($plano['valor'], 2, ',', '.'),
                    'checkins_semanais' => (int) $plano['checkins_semanais'],
                    'duracao_dias' => (int) $plano['duracao_dias'],
                    'ativo' => (bool) $plano['ativo'],
                    'atual' => (bool) $plano['atual'],
                    'modalidade' => [
                        'id' => $plano['modalidade_id'] ? (int) $plano['modalidade_id'] : null,
                        'nome' => $plano['modalidade_nome'],
                        'cor' => $plano['modalidade_cor'],
                        'icone' => $plano['modalidade_icone'],
                    ],
                    'ciclos' => $ciclos,
                    'total_ciclos' => count($ciclos),
                    'created_at' => $plano['created_at'],
                    'updated_at' => $plano['updated_at'],
                ];

                $resultado[] = $planoFormatado;

                $resumo['total_planos']++;
                if ($plano['ativo']) {
                    $resumo['planos_ativos']++;
                } else {
                    $resumo['planos_inativos']++;
                }

                $resumo['total_ciclos'] += count($ciclos);
                foreach ($ciclos as $c) {
                    if ($c['ativo']) {
                        $resumo['ciclos_ativos']++;
                    } else {
                        $resumo['ciclos_inativos']++;
                    }
                }

                if ($ciclos === []) {
                    $resumo['planos_sem_ciclos']++;
                }

                $modalidadeNome = $plano['modalidade_nome'] ?? 'Sem modalidade';
                $resumo['modalidades'][$modalidadeNome] = ($resumo['modalidades'][$modalidadeNome] ?? 0) + 1;
            }

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'tenant' => $tenant,
                    'planos' => $resultado,
                    'resumo' => $resumo,
                    'filtros_aplicados' => [
                        'ativo' => $filtroAtivo,
                        'modalidade_id' => $filtroModalidade,
                    ],
                    'gerado_em' => date('Y-m-d H:i:s'),
                ],
            ];
        } catch (Throwable $e) {
            error_log('[AdminRelatorioService::planosCiclos] Erro: '.$e->getMessage());

            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'error' => 'Erro ao gerar relatório: '.$e->getMessage(),
                ],
            ];
        }
    }
}
