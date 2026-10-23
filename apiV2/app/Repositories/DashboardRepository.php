<?php

namespace App\Repositories;

use App\Support\Pessoa;
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
     * Matrículas por status: o que vale para "aluno ativo" é ter matrícula ativa (status_id = 1),
     * não o vínculo em tenant_usuario_papel.
     *
     * @return array{alunos_com_matricula_ativa: int, ativas: int, vencidas: int, pendentes: int, bloqueadas: int}
     */
    public function matriculasCards(int $tenantId): array
    {
        $row = DB::selectOne('
            SELECT
                COUNT(DISTINCT CASE WHEN status_id = 1 THEN aluno_id END) as alunos_ativos,
                COALESCE(SUM(status_id = 1), 0) as ativas,
                COALESCE(SUM(status_id = 2), 0) as vencidas,
                COALESCE(SUM(status_id = 5), 0) as pendentes,
                COALESCE(SUM(status_id = 6), 0) as bloqueadas
            FROM matriculas
            WHERE tenant_id = ?
        ', [$tenantId]);

        return [
            'alunos_com_matricula_ativa' => (int) ($row->alunos_ativos ?? 0),
            'ativas' => (int) ($row->ativas ?? 0),
            'vencidas' => (int) ($row->vencidas ?? 0),
            'pendentes' => (int) ($row->pendentes ?? 0),
            'bloqueadas' => (int) ($row->bloqueadas ?? 0),
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

    /**
     * Séries da visão geral do painel: check-ins por dia e por horário, alunos ativos ao longo dos meses,
     * alunos por plano, entradas e saídas do mês e matrículas cujo acesso termina sem renovação.
     *
     * @return array<string, mixed>
     */
    public function visaoGeral(int $tenantId, ?string $hoje = null): array
    {
        $hoje = $hoje ?: date('Y-m-d');
        $inicio30 = date('Y-m-d', strtotime($hoje.' -29 days'));
        $inicio28 = date('Y-m-d', strtotime($hoje.' -28 days'));
        $ontem = date('Y-m-d', strtotime($hoje.' -1 day'));
        $mesInicio = substr($hoje, 0, 8).'01';

        // Mesmo critério de data dos cards: dia da aula quando há turma, senão o dia do check-in
        $dia = 'DATE(COALESCE(d.data, c.data_checkin_date, c.data_checkin))';
        $base = 'FROM checkins c LEFT JOIN turmas t ON t.id = c.turma_id LEFT JOIN dias d ON d.id = t.dia_id WHERE c.tenant_id = ?';

        $porDia = [];
        foreach (DB::select("SELECT {$dia} AS dia, COUNT(*) AS total {$base} AND {$dia} BETWEEN ? AND ? GROUP BY dia", [$tenantId, $inicio30, $hoje]) as $r) {
            $porDia[(string) $r->dia] = (int) $r->total;
        }
        $checkins30 = [];
        for ($i = 0; $i < 30; $i++) {
            $d = date('Y-m-d', strtotime($inicio30." +{$i} days"));
            $checkins30[] = ['data' => $d, 'total' => $porDia[$d] ?? 0];
        }

        // Últimas 4 semanas completas (sem hoje): média por dia da semana (1 = segunda) e hora da aula
        $hora = 'HOUR(COALESCE(t.horario_inicio, c.data_checkin))';
        $horarios = array_map(fn ($r) => [
            'dia_semana' => (int) $r->dia_semana,
            'hora' => (int) $r->hora,
            'media' => round(((int) $r->total) / 4, 1),
        ], DB::select("SELECT WEEKDAY({$dia}) + 1 AS dia_semana, {$hora} AS hora, COUNT(*) AS total {$base} AND {$dia} BETWEEN ? AND ? GROUP BY dia_semana, hora", [$tenantId, $inicio28, $ontem]));

        $diaSemanaHoje = (int) date('N', strtotime($hoje));
        $mesmoDia = 0.0;
        foreach ($horarios as $h) {
            if ($h['dia_semana'] === $diaSemanaHoje) {
                $mesmoDia += $h['media'];
            }
        }

        // Alunos com matrícula vigente no fim de cada mês (o último ponto é hoje)
        $ativosEm = function (string $ref) use ($tenantId): int {
            return (int) (DB::selectOne('
                SELECT COUNT(DISTINCT aluno_id) AS total FROM matriculas
                WHERE tenant_id = ? AND status_id <> 5 AND data_inicio <= ? AND data_vencimento >= ?
                AND (data_cancelamento IS NULL OR DATE(data_cancelamento) > ?)
            ', [$tenantId, $ref, $ref, $ref])->total ?? 0);
        };
        $ativosHistorico = [];
        for ($i = 5; $i >= 1; $i--) {
            $ref = date('Y-m-t', strtotime($mesInicio." -{$i} months"));
            $ativosHistorico[] = ['data' => $ref, 'total' => $ativosEm($ref)];
        }
        $ativosHistorico[] = ['data' => $hoje, 'total' => $ativosEm($hoje)];

        $porPlano = array_map(fn ($r) => ['plano' => (string) $r->plano, 'alunos' => (int) $r->alunos], DB::select("
            SELECT COALESCE(p.nome, 'Sem plano') AS plano, COUNT(DISTINCT m.aluno_id) AS alunos
            FROM matriculas m LEFT JOIN planos p ON p.id = m.plano_id
            WHERE m.tenant_id = ? AND m.status_id = 1
            GROUP BY plano ORDER BY alunos DESC, plano
        ", [$tenantId]));

        $saidas = (int) (DB::selectOne('
            SELECT COUNT(DISTINCT aluno_id) AS total FROM matriculas
            WHERE tenant_id = ? AND status_id IN (3, 4)
            AND DATE(COALESCE(data_cancelamento, updated_at)) BETWEEN ? AND ?
        ', [$tenantId, $mesInicio, $hoje])->total ?? 0);

        // Acesso vigente que termina em até 15 dias, sem outra matrícula do aluno criada depois (renovação)
        $terminando = array_map(fn ($r) => [
            'matricula_id' => (int) $r->id,
            'aluno_id' => (int) $r->aluno_id,
            'aluno_nome' => (string) $r->aluno_nome,
            'plano_nome' => $r->plano_nome,
            'data_vencimento' => (string) $r->data_vencimento,
            'telefone' => $r->whatsapp ?: $r->telefone,
        ], DB::select('
            SELECT m.id, m.aluno_id, m.data_vencimento, a.nome AS aluno_nome,
                '.Pessoa::col('telefone', 'pu')->getValue(DB::getQueryGrammar()).' AS telefone,
                '.Pessoa::col('whatsapp', 'pu')->getValue(DB::getQueryGrammar()).' AS whatsapp, p.nome AS plano_nome
            FROM matriculas m
            INNER JOIN alunos a ON a.id = m.aluno_id
            LEFT JOIN usuarios pu ON pu.id = a.usuario_id
            LEFT JOIN planos p ON p.id = m.plano_id
            WHERE m.tenant_id = ? AND m.status_id = 1 AND m.data_vencimento BETWEEN ? AND ?
            AND NOT EXISTS (
                SELECT 1 FROM matriculas n WHERE n.tenant_id = m.tenant_id AND n.aluno_id = m.aluno_id
                AND n.id > m.id AND n.status_id IN (1, 5)
            )
            ORDER BY m.data_vencimento, a.nome LIMIT 8
        ', [$tenantId, $hoje, date('Y-m-d', strtotime($hoje.' +15 days'))]));

        return [
            'hoje' => $hoje,
            'checkins_30_dias' => $checkins30,
            'checkins_por_horario' => $horarios,
            'checkins_media_dia_semana' => round($mesmoDia, 1),
            'alunos_ativos_historico' => $ativosHistorico,
            'alunos_por_plano' => $porPlano,
            'saidas_mes' => $saidas,
            'acesso_terminando' => $terminando,
        ];
    }
}
