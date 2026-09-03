<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Queries do dashboard admin (paridade AdminController::dashboard + DashboardController::cards).
 */
class DashboardRepository
{
    /**
     * @return array<string, int|float>
     */
    public function statsAdmin(int $tenantId): array
    {
        $totalAlunos = (int) (DB::selectOne('
            SELECT COUNT(DISTINCT tup.usuario_id) as total
            FROM tenant_usuario_papel tup
            INNER JOIN usuarios u ON u.id = tup.usuario_id
            WHERE tup.tenant_id = ? AND tup.papel_id = 1 AND tup.ativo = 1
        ', [$tenantId])->total ?? 0);

        $statusAlunos = DB::selectOne('
            SELECT
                COUNT(DISTINCT tup.usuario_id) as total,
                SUM(CASE WHEN tup.ativo = 1 THEN 1 ELSE 0 END) as ativos,
                SUM(CASE WHEN tup.ativo = 0 THEN 1 ELSE 0 END) as inativos
            FROM tenant_usuario_papel tup
            INNER JOIN usuarios u ON u.id = tup.usuario_id
            WHERE tup.tenant_id = ? AND tup.papel_id = 1
        ', [$tenantId]);

        $checkinsHoje = (int) (DB::selectOne('
            SELECT COUNT(*) as total
            FROM checkins c
            WHERE c.tenant_id = ?
            AND DATE(c.created_at) = CURDATE()
        ', [$tenantId])->total ?? 0);

        $checkinsMes = (int) (DB::selectOne('
            SELECT COUNT(*) as total
            FROM checkins c
            WHERE c.tenant_id = ?
            AND YEAR(c.created_at) = YEAR(CURDATE())
            AND MONTH(c.created_at) = MONTH(CURDATE())
        ', [$tenantId])->total ?? 0);

        $planosVencendo = (int) (DB::selectOne('
            SELECT COUNT(DISTINCT cr.usuario_id) as total
            FROM contas_receber cr
            INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = cr.usuario_id AND tup.ativo = 1
            WHERE tup.tenant_id = ?
            AND cr.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND cr.status IN (\'pendente\', \'vencido\')
        ', [$tenantId])->total ?? 0);

        $receitaMensal = (float) (DB::selectOne('
            SELECT SUM(cr.valor) as receita
            FROM contas_receber cr
            WHERE cr.tenant_id = ?
            AND cr.referencia_mes = DATE_FORMAT(CURDATE(), \'%Y-%m\')
        ', [$tenantId])->receita ?? 0);

        $novosAlunos = (int) (DB::selectOne('
            SELECT COUNT(DISTINCT tup.usuario_id) as total
            FROM tenant_usuario_papel tup
            INNER JOIN usuarios u ON u.id = tup.usuario_id
            WHERE tup.tenant_id = ?
            AND tup.papel_id = 1
            AND YEAR(tup.created_at) = YEAR(CURDATE())
            AND MONTH(tup.created_at) = MONTH(CURDATE())
        ', [$tenantId])->total ?? 0);

        $contasPendentes = DB::selectOne('
            SELECT COUNT(*) as quantidade, SUM(valor) as total
            FROM contas_receber
            WHERE tenant_id = ? AND status = \'pendente\'
        ', [$tenantId]);

        $contasVencidas = DB::selectOne('
            SELECT COUNT(*) as quantidade, SUM(valor) as total
            FROM contas_receber
            WHERE tenant_id = ?
            AND status IN (\'pendente\', \'vencido\')
            AND data_vencimento < CURDATE()
        ', [$tenantId]);

        return [
            'total_alunos' => $totalAlunos,
            'alunos_ativos' => (int) ($statusAlunos->ativos ?? 0),
            'alunos_inativos' => (int) ($statusAlunos->inativos ?? 0),
            'novos_alunos_mes' => $novosAlunos,
            'total_checkins_hoje' => $checkinsHoje,
            'total_checkins_mes' => $checkinsMes,
            'planos_vencendo' => $planosVencendo,
            'receita_mensal' => $receitaMensal,
            'contas_pendentes_qtd' => (int) ($contasPendentes->quantidade ?? 0),
            'contas_pendentes_valor' => (float) ($contasPendentes->total ?? 0),
            'contas_vencidas_qtd' => (int) ($contasVencidas->quantidade ?? 0),
            'contas_vencidas_valor' => (float) ($contasVencidas->total ?? 0),
        ];
    }

    /**
     * @return array{total: int, ativos: int, inativos: int}
     */
    public function totalAlunosCards(int $tenantId): array
    {
        $row = DB::selectOne('
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN tup.ativo = 1 THEN 1 ELSE 0 END) as ativos,
                SUM(CASE WHEN tup.ativo = 0 THEN 1 ELSE 0 END) as inativos
            FROM tenant_usuario_papel tup
            WHERE tup.tenant_id = ?
            AND tup.papel_id = 1
        ', [$tenantId]);

        return [
            'total' => (int) ($row->total ?? 0),
            'ativos' => (int) ($row->ativos ?? 0),
            'inativos' => (int) ($row->inativos ?? 0),
        ];
    }

    /**
     * @return array{valor: float, valor_formatado: string, contas_pendentes: int}
     */
    public function receitaMensalCards(int $tenantId): array
    {
        $ano = (int) date('Y');
        $mes = (int) date('m');

        $valorPago = (float) (DB::selectOne('
            SELECT COALESCE(SUM(valor), 0) as total
            FROM pagamentos_plano
            WHERE tenant_id = ?
            AND status_pagamento_id = 2
            AND YEAR(data_pagamento) = ?
            AND MONTH(data_pagamento) = ?
        ', [$tenantId, $ano, $mes])->total ?? 0);

        $contasPendentes = (int) (DB::selectOne('
            SELECT COUNT(*) as total
            FROM pagamentos_plano
            WHERE tenant_id = ?
            AND status_pagamento_id IN (1, 3)
            AND YEAR(data_vencimento) = ?
            AND MONTH(data_vencimento) = ?
        ', [$tenantId, $ano, $mes])->total ?? 0);

        return [
            'valor' => $valorPago,
            'valor_formatado' => 'R$ '.number_format($valorPago, 2, ',', '.'),
            'contas_pendentes' => $contasPendentes,
        ];
    }

    /**
     * @return array{hoje: int, no_mes: int}
     */
    public function checkinsCards(int $tenantId): array
    {
        $hoje = date('Y-m-d');
        $ano = (int) date('Y');
        $mes = (int) date('m');

        $checkinsHoje = (int) (DB::selectOne('
            SELECT COUNT(*) as total
            FROM checkins c
            LEFT JOIN turmas t ON t.id = c.turma_id
            LEFT JOIN dias d ON d.id = t.dia_id
            WHERE c.tenant_id = ?
            AND DATE(COALESCE(d.data, c.data_checkin)) = ?
        ', [$tenantId, $hoje])->total ?? 0);

        $checkinsMes = (int) (DB::selectOne('
            SELECT COUNT(*) as total
            FROM checkins c
            LEFT JOIN turmas t ON t.id = c.turma_id
            LEFT JOIN dias d ON d.id = t.dia_id
            WHERE c.tenant_id = ?
            AND YEAR(COALESCE(d.data, c.data_checkin)) = ?
            AND MONTH(COALESCE(d.data, c.data_checkin)) = ?
        ', [$tenantId, $ano, $mes])->total ?? 0);

        return [
            'hoje' => $checkinsHoje,
            'no_mes' => $checkinsMes,
        ];
    }

    /**
     * @return array{vencendo: int, novos_este_mes: int}
     */
    public function planosVencendoCards(int $tenantId): array
    {
        $hoje = date('Y-m-d');
        $em7Dias = date('Y-m-d', strtotime('+7 days'));
        $ano = (int) date('Y');
        $mes = (int) date('m');

        $vencendo = (int) (DB::selectOne('
            SELECT COUNT(*) as total
            FROM matriculas
            WHERE tenant_id = ?
            AND status_id = 1
            AND data_vencimento BETWEEN ? AND ?
        ', [$tenantId, $hoje, $em7Dias])->total ?? 0);

        $novosEsteMes = (int) (DB::selectOne('
            SELECT COUNT(*) as total
            FROM matriculas
            WHERE tenant_id = ?
            AND YEAR(data_matricula) = ?
            AND MONTH(data_matricula) = ?
        ', [$tenantId, $ano, $mes])->total ?? 0);

        return [
            'vencendo' => $vencendo,
            'novos_este_mes' => $novosEsteMes,
        ];
    }
}
