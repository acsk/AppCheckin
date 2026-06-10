<?php
/**
 * Diagnóstico: parcelas canceladas após pagamento MP
 * Uso: php debug_matriculas_354_317.php [matricula_id ...] [--fix]
 */

declare(strict_types=1);

$fix = false;
$matriculaIds = [];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--fix') {
        $fix = true;
    } elseif (preg_match('/^\d+$/', $arg)) {
        $matriculaIds[] = $arg;
    }
}
if ($matriculaIds === []) {
    $matriculaIds = ['354', '317'];
}

$host = getenv('PROD_DB_HOST') ?: 'srv1314.hstgr.io';
$dbname = getenv('PROD_DB_NAME') ?: 'u304177849_api';
$user = getenv('PROD_DB_USER') ?: 'u304177849_api';
$pass = getenv('PROD_DB_PASS') ?: '+DEEJ&7t';

try {
    $pdo = new PDO(
        "mysql:host={$host};port=3306;dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "Conectado ao banco remoto\n";
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage() . "\n");
}

$section = static function (string $title): void {
    echo "\n" . str_repeat('═', 72) . "\n{$title}\n" . str_repeat('─', 72) . "\n";
};

foreach ($matriculaIds as $mid) {
    $mid = (int) $mid;
    $section("MATRÍCULA #{$mid}");

    $stmt = $pdo->prepare("
        SELECT m.*, sm.codigo AS status_codigo, sm.nome AS status_nome,
               a.nome AS aluno_nome, p.nome AS plano_nome
        FROM matriculas m
        LEFT JOIN status_matricula sm ON sm.id = m.status_id
        LEFT JOIN alunos a ON a.id = m.aluno_id
        LEFT JOIN planos p ON p.id = m.plano_id
        WHERE m.id = ?
    ");
    $stmt->execute([$mid]);
    $m = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$m) {
        echo "  NÃO ENCONTRADA\n";
        continue;
    }

    echo "  Aluno: {$m['aluno_nome']}\n";
    echo "  Tenant: {$m['tenant_id']} | Status: {$m['status_codigo']} ({$m['status_nome']})\n";
    echo "  Plano: {$m['plano_nome']} | Tipo: {$m['tipo_cobranca']} | Valor: R$ {$m['valor']}\n";
    echo "  data_inicio: {$m['data_inicio']} | data_vencimento: {$m['data_vencimento']}\n";
    echo "  proxima_data_vencimento: {$m['proxima_data_vencimento']}\n";
    echo "  updated_at: {$m['updated_at']}\n";

    $section("pagamentos_plano (todas as parcelas)");
    $stmt2 = $pdo->prepare("
        SELECT pp.id, pp.status_pagamento_id, sp.nome AS status, pp.valor,
               pp.data_vencimento, pp.data_pagamento, pp.observacoes,
               pp.created_at, pp.updated_at
        FROM pagamentos_plano pp
        LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
        WHERE pp.matricula_id = ?
        ORDER BY pp.data_vencimento ASC, pp.id ASC
    ");
    $stmt2->execute([$mid]);
    $parcelas = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo "  Total: " . count($parcelas) . "\n\n";
    foreach ($parcelas as $p) {
        $flag = '';
        if ((int) $p['status_pagamento_id'] === 4) {
            $flag = ' ⚠️ CANCELADA';
        } elseif ((int) $p['status_pagamento_id'] === 1 && $p['data_vencimento'] > date('Y-m-d')) {
            $flag = ' 📅 FUTURA';
        }
        echo sprintf(
            "  #%d | %s | R$ %.2f | venc: %s | pago: %s%s\n",
            $p['id'],
            $p['status'],
            $p['valor'],
            $p['data_vencimento'],
            $p['data_pagamento'] ?: '-',
            $flag
        );
        echo "       criado: {$p['created_at']} | atualizado: {$p['updated_at']}\n";
        if (!empty($p['observacoes'])) {
            echo "       obs: {$p['observacoes']}\n";
        }
        echo "\n";
    }

    $section("assinaturas");
    $stmt3 = $pdo->prepare("
        SELECT id, status_id, status_gateway, gateway_assinatura_id,
               external_reference, payment_url, criado_em, atualizado_em
        FROM assinaturas WHERE matricula_id = ?
    ");
    $stmt3->execute([$mid]);
    foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $a) {
        echo "  #{$a['id']} gateway={$a['status_gateway']} ext={$a['external_reference']}\n";
        echo "       criado: {$a['criado_em']} | atualizado: {$a['atualizado_em']}\n";
    }

    $section("pagamentos_mercadopago (últimos 15)");
    $stmt4 = $pdo->prepare("
        SELECT *
        FROM pagamentos_mercadopago
        WHERE matricula_id = ?
        ORDER BY id DESC LIMIT 15
    ");
    $stmt4->execute([$mid]);
    foreach ($stmt4->fetchAll(PDO::FETCH_ASSOC) as $pm) {
        echo "  #" . ($pm['id'] ?? '?') . " payment=" . ($pm['payment_id'] ?? '-') . " status=" . ($pm['status'] ?? '-') . " ({$pm['created_at']})\n";
    }

    $section("Análise automática");
    $canceladasFuturas = array_filter($parcelas, static function ($p) {
        return (int) $p['status_pagamento_id'] === 4
            && $p['data_vencimento'] > date('Y-m-d')
            && empty($p['data_pagamento']);
    });
    $pendentesFuturas = array_filter($parcelas, static function ($p) {
        return in_array((int) $p['status_pagamento_id'], [1, 3], true)
            && $p['data_vencimento'] > date('Y-m-d')
            && empty($p['data_pagamento']);
    });

    if ($canceladasFuturas !== []) {
        echo "  ⚠️ " . count($canceladasFuturas) . " parcela(s) FUTURA(s) CANCELADA(s):\n";
        foreach ($canceladasFuturas as $p) {
            echo "     #{$p['id']} venc {$p['data_vencimento']} — obs: " . ($p['observacoes'] ?: '(vazio)') . "\n";
            if (str_contains($p['observacoes'] ?? '', 'Duplicata')) {
                echo "     → Provável causa: cancelarParcelasDuplicadasAposBaixa (mesmo valor)\n";
            }
            if (str_contains($p['observacoes'] ?? '', 'Substituída por nova cobrança')) {
                echo "     → Provável causa: garantirParcelaPendenteUnica (nova tentativa de pagamento)\n";
            }
            if (str_contains($p['observacoes'] ?? '', 'alteração de plano') || str_contains($p['observacoes'] ?? '', 'migração')) {
                echo "     → Provável causa: migração/troca de plano\n";
            }
        }
    } else {
        echo "  Nenhuma parcela futura cancelada encontrada.\n";
    }

    if ($pendentesFuturas !== []) {
        echo "  ✅ " . count($pendentesFuturas) . " parcela(s) futura(s) ainda pendente(s).\n";
    } else {
        echo "  ❌ Nenhuma parcela futura pendente — ciclo pode estar quebrado!\n";
    }

    if ($fix && $canceladasFuturas !== []) {
        $section("Correção (--fix)");
        $stmtUltPago = $pdo->prepare("
            SELECT id, data_vencimento FROM pagamentos_plano
            WHERE matricula_id = ? AND status_pagamento_id = 2
            ORDER BY data_pagamento DESC, id DESC LIMIT 1
        ");
        $stmtUltPago->execute([$mid]);
        $ultPago = $stmtUltPago->fetch(PDO::FETCH_ASSOC);
        $vencUltPago = $ultPago['data_vencimento'] ?? null;

        foreach ($canceladasFuturas as $p) {
            if (!$vencUltPago || $p['data_vencimento'] <= $vencUltPago) {
                echo "  ⏭️  #{$p['id']}: venc {$p['data_vencimento']} não é posterior ao último pago ({$vencUltPago})\n";
                continue;
            }
            if (!str_contains($p['observacoes'] ?? '', 'Duplicata')) {
                echo "  ⏭️  #{$p['id']}: cancelamento não é por Duplicata — revisar manualmente\n";
                continue;
            }

            $pdo->prepare("
                UPDATE pagamentos_plano
                SET status_pagamento_id = 1,
                    observacoes = CONCAT(COALESCE(observacoes, ''), ' [Reativada: cancelamento Duplicata incorreto]'),
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$p['id']]);

            $pdo->prepare("
                UPDATE matriculas SET proxima_data_vencimento = ?, updated_at = NOW() WHERE id = ?
            ")->execute([$p['data_vencimento'], $mid]);

            echo "  ✅ Parcela #{$p['id']} reativada (venc {$p['data_vencimento']}); proxima_data_vencimento atualizada\n";
        }
    }
}

echo "\n";
