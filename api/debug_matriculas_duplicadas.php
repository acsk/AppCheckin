<?php
/**
 * Debug: matrículas duplicadas por aluno/modalidade
 *
 * Detecta alunos com mais de uma matrícula na mesma modalidade
 * em status que não deveriam coexistir (ativa, pendente, vencida, cancelada).
 *
 * Uso:
 *   php debug_matriculas_duplicadas.php
 *   php debug_matriculas_duplicadas.php --tenant=3
 *   php debug_matriculas_duplicadas.php --aluno=201
 *   php debug_matriculas_duplicadas.php --corrigir     ← finaliza as duplicatas antigas
 */

$db = require __DIR__ . '/config/database.php';

$args     = array_slice($argv ?? [], 1);
$corrigir = in_array('--corrigir', $args);
$tenantFiltro = null;
$alunoFiltro  = null;

foreach ($args as $arg) {
    if (preg_match('/--tenant=(\d+)/', $arg, $m)) $tenantFiltro = (int) $m[1];
    if (preg_match('/--aluno=(\d+)/', $arg, $m))  $alunoFiltro  = (int) $m[1];
}

echo "====== DEBUG MATRÍCULAS DUPLICADAS ======\n";
echo "Data BRT : " . date('Y-m-d H:i:s') . "\n";
echo "Modo     : " . ($corrigir ? '⚠️  CORRIGIR (finalizará duplicatas antigas)' : 'apenas leitura') . "\n";
if ($tenantFiltro) echo "Tenant   : $tenantFiltro\n";
if ($alunoFiltro)  echo "Aluno    : $alunoFiltro\n";
echo "\n";

// ─── Buscar grupos com mais de 1 matrícula não-finalizada na mesma modalidade ─
$whereTenant = $tenantFiltro ? "AND m.tenant_id = $tenantFiltro" : "";
$whereAluno  = $alunoFiltro  ? "AND m.aluno_id = $alunoFiltro"  : "";

$stmt = $db->prepare("
    SELECT
        m.tenant_id,
        m.aluno_id,
        a.nome       AS aluno_nome,
        p.modalidade_id,
        mo.nome      AS modalidade,
        COUNT(*)     AS total
    FROM matriculas m
    INNER JOIN planos      p  ON p.id  = m.plano_id
    INNER JOIN alunos      a  ON a.id  = m.aluno_id
    INNER JOIN modalidades mo ON mo.id = p.modalidade_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    WHERE sm.codigo NOT IN ('finalizada')
    $whereTenant
    $whereAluno
    GROUP BY m.tenant_id, m.aluno_id, p.modalidade_id
    HAVING COUNT(*) > 1
    ORDER BY m.tenant_id ASC, a.nome ASC
");
$stmt->execute();
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$grupos) {
    echo "✅ Nenhuma matrícula duplicada encontrada.\n";
    exit(0);
}

echo "Encontrados " . count($grupos) . " caso(s) de duplicata:\n";
echo str_repeat('=', 80) . "\n\n";

$totalCorrigidos = 0;

foreach ($grupos as $grupo) {
    echo "Tenant #{$grupo['tenant_id']} | Aluno #{$grupo['aluno_id']} — {$grupo['aluno_nome']}\n";
    echo "Modalidade: {$grupo['modalidade']} (id={$grupo['modalidade_id']}) | {$grupo['total']} matrículas\n";
    echo str_repeat('-', 80) . "\n";

    // Detalhar as matrículas do grupo
    $stmtDet = $db->prepare("
        SELECT
            m.id, m.plano_id, m.plano_ciclo_id, m.status_id,
            sm.codigo AS status_codigo,
            m.data_matricula, m.data_inicio, m.data_vencimento, m.proxima_data_vencimento,
            m.valor, m.updated_at,
            p.nome AS plano_nome
        FROM matriculas m
        INNER JOIN planos p ON p.id = m.plano_id
        INNER JOIN status_matricula sm ON sm.id = m.status_id
        WHERE m.aluno_id = :aluno_id
          AND m.tenant_id = :tenant_id
          AND p.modalidade_id = :modalidade_id
          AND sm.codigo NOT IN ('finalizada')
        ORDER BY m.id ASC
    ");
    $stmtDet->execute([
        'aluno_id'     => $grupo['aluno_id'],
        'tenant_id'    => $grupo['tenant_id'],
        'modalidade_id'=> $grupo['modalidade_id'],
    ]);
    $mats = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    // Ordenar: manter a mais recente (maior id), finalizar as antigas
    $mais_recente = end($mats);

    foreach ($mats as $m) {
        $atual = $m['id'] === $mais_recente['id'];
        $tag   = $atual ? '★ MANTER' : '↳ DUPLICATA';
        $venc  = $m['proxima_data_vencimento'] ?? $m['data_vencimento'];
        echo "  [{$tag}] #" . $m['id']
            . " | {$m['plano_nome']}"
            . " | status={$m['status_codigo']}"
            . " | inicio={$m['data_inicio']}"
            . " | venc={$venc}"
            . " | R\$" . number_format($m['valor'], 2, ',', '.')
            . "\n";

        // Pagamentos da matrícula
        $stmtPag = $db->prepare("
            SELECT pp.id, pp.valor, pp.data_vencimento, sp.nome AS status_nome
            FROM pagamentos_plano pp
            INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
            WHERE pp.matricula_id = ?
            ORDER BY pp.data_vencimento ASC
        ");
        $stmtPag->execute([$m['id']]);
        $pagamentos = $stmtPag->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pagamentos as $p) {
            echo "           Pag #{$p['id']}: R\$" . number_format($p['valor'], 2, ',', '.')
                . " | venc {$p['data_vencimento']} | {$p['status_nome']}\n";
        }
    }

    // ─── Diagnóstico de causa ─────────────────────────────────────────────
    $statusList = array_column($mats, 'status_codigo');
    $causas = [];

    if (in_array('cancelada', $statusList)) {
        $causas[] = "matrícula cancelada não foi reaproveitada ao criar nova (fix aplicado em 15/04)";
    }
    if (in_array('vencida', $statusList) && in_array('ativa', $statusList)) {
        $causas[] = "matrícula vencida coexiste com ativa — provável falha no job de limpeza";
    }
    if (in_array('pendente', $statusList) && in_array('ativa', $statusList)) {
        $causas[] = "matrícula pendente + ativa — pagamento confirmou e criou nova sem finalizar pendente";
    }
    if (empty($causas)) {
        $causas[] = "causa desconhecida — analisar manualmente";
    }

    echo "\n  ⚠️  Causa provável: " . implode('; ', $causas) . "\n";

    // ─── Correção ─────────────────────────────────────────────────────────
    if ($corrigir) {
        $db->beginTransaction();
        try {
            $finalizadaId = null;
            $stmtFinalId = $db->prepare("SELECT id FROM status_matricula WHERE codigo = 'finalizada' LIMIT 1");
            $stmtFinalId->execute();
            $finalizadaId = (int) $stmtFinalId->fetchColumn();

            $corrigidos = 0;
            foreach ($mats as $m) {
                if ($m['id'] === $mais_recente['id']) continue; // pular a mais recente

                // Cancelar pagamentos pendentes/atrasados da duplicata
                $stmtCancPag = $db->prepare("
                    UPDATE pagamentos_plano
                    SET status_pagamento_id = 4,
                        observacoes = CONCAT(COALESCE(observacoes,''), ' [Cancelado — matrícula duplicada corrigida]'),
                        updated_at = NOW()
                    WHERE matricula_id = ? AND status_pagamento_id IN (1, 3)
                ");
                $stmtCancPag->execute([$m['id']]);

                // Finalizar a matrícula duplicata
                $stmtFin = $db->prepare("
                    UPDATE matriculas
                    SET status_id = ?,
                        observacoes = CONCAT(COALESCE(observacoes,''), ' [Finalizada — duplicata corrigida automaticamente]'),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmtFin->execute([$finalizadaId, $m['id']]);
                $corrigidos++;
                echo "\n  ✅ Matrícula #{$m['id']} finalizada e pagamentos pendentes cancelados.\n";
            }
            $db->commit();
            $totalCorrigidos += $corrigidos;
        } catch (\Exception $e) {
            $db->rollBack();
            echo "\n  ❌ Erro ao corrigir: " . $e->getMessage() . "\n";
        }
    } else {
        echo "\n  ➡️  Rode com --corrigir para finalizar as duplicatas antigas.\n";
    }

    echo "\n";
}

echo str_repeat('=', 80) . "\n";
echo "Total de grupos duplicados : " . count($grupos) . "\n";
if ($corrigir) {
    echo "Matrículas finalizadas     : $totalCorrigidos\n";
}
echo "Fim.\n";
