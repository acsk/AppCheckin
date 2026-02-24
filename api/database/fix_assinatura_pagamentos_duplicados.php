<?php
/**
 * Corrige pagamentos duplicados de uma assinatura mantendo apenas 1 pendente.
 *
 * Uso:
 *   php database/fix_assinatura_pagamentos_duplicados.php --assinatura=30
 *   php database/fix_assinatura_pagamentos_duplicados.php --assinatura=30 --apply
 *
 * Padrão: DRY-RUN (não altera nada)
 */

$db = require __DIR__ . '/../config/database.php';

$options = getopt('', ['assinatura:', 'apply']);
$assinaturaId = isset($options['assinatura']) ? (int)$options['assinatura'] : 0;
$apply = array_key_exists('apply', $options);

if ($assinaturaId <= 0) {
    echo "❌ Informe a assinatura. Ex.: --assinatura=30\n";
    exit(1);
}

try {
    $stmtAss = $db->prepare(
        "SELECT id, tenant_id, matricula_id, aluno_id, plano_id
         FROM assinaturas
         WHERE id = ?
         LIMIT 1"
    );
    $stmtAss->execute([$assinaturaId]);
    $assinatura = $stmtAss->fetch(PDO::FETCH_ASSOC);

    if (!$assinatura) {
        echo "❌ Assinatura {$assinaturaId} não encontrada.\n";
        exit(1);
    }

    if (empty($assinatura['matricula_id'])) {
        echo "❌ Assinatura {$assinaturaId} não possui matrícula vinculada.\n";
        exit(1);
    }

    $tenantId = (int)$assinatura['tenant_id'];
    $matriculaId = (int)$assinatura['matricula_id'];

    echo "\n📌 Assinatura #{$assinaturaId} | Tenant {$tenantId} | Matrícula {$matriculaId}\n";

    $stmtPag = $db->prepare(
        "SELECT id, status_pagamento_id, valor, data_vencimento, data_pagamento, observacoes, created_at
         FROM pagamentos_plano
         WHERE tenant_id = ? AND matricula_id = ?
         ORDER BY id DESC"
    );
    $stmtPag->execute([$tenantId, $matriculaId]);
    $pagamentos = $stmtPag->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pagamentos)) {
        echo "ℹ️ Nenhum pagamento encontrado para a matrícula.\n";
        exit(0);
    }

    echo "\n📋 Pagamentos encontrados:\n";
    foreach ($pagamentos as $p) {
        echo sprintf(
            "- ID:%d | status:%d | venc:%s | pagto:%s | valor:%.2f\n",
            (int)$p['id'],
            (int)$p['status_pagamento_id'],
            (string)($p['data_vencimento'] ?? 'NULL'),
            (string)($p['data_pagamento'] ?? 'NULL'),
            (float)$p['valor']
        );
    }

    $pendentes = array_values(array_filter($pagamentos, static function (array $p): bool {
        return (int)$p['status_pagamento_id'] === 1 && empty($p['data_pagamento']);
    }));

    if (count($pendentes) <= 1) {
        echo "\n✅ Não há duplicidade de pendentes para corrigir.\n";
        exit(0);
    }

    usort($pendentes, static function (array $a, array $b): int {
        $da = $a['data_vencimento'] ?? '9999-12-31';
        $dbv = $b['data_vencimento'] ?? '9999-12-31';

        if ($da === $dbv) {
            return ((int)$a['id']) <=> ((int)$b['id']);
        }

        return strcmp($da, $dbv);
    });

    $manter = $pendentes[0];
    $cancelar = array_slice($pendentes, 1);

    echo "\n🎯 Manter pendente: ID {$manter['id']} (vencimento {$manter['data_vencimento']})\n";
    echo "🧹 Cancelar duplicados: " . implode(', ', array_map(static fn(array $p) => (string)$p['id'], $cancelar)) . "\n";

    if (!$apply) {
        echo "\n⚠️ DRY-RUN: nada foi alterado.\n";
        echo "Para aplicar, execute com --apply\n\n";
        exit(0);
    }

    $db->beginTransaction();

    $stmtUpd = $db->prepare(
        "UPDATE pagamentos_plano
         SET status_pagamento_id = 4,
             tipo_baixa_id = COALESCE(tipo_baixa_id, 4),
             observacoes = CONCAT(COALESCE(observacoes, ''), ' | DUPLICADO_ASSINATURA_', ?, '_MANTIDO_', ?),
             updated_at = NOW()
         WHERE id = ? AND tenant_id = ? AND matricula_id = ?"
    );

    foreach ($cancelar as $p) {
        $stmtUpd->execute([
            $assinaturaId,
            (int)$manter['id'],
            (int)$p['id'],
            $tenantId,
            $matriculaId,
        ]);
    }

    $db->commit();

    echo "\n✅ Correção aplicada com sucesso.\n";
    echo "- Assinatura: {$assinaturaId}\n";
    echo "- Pagamento mantido: {$manter['id']}\n";
    echo "- Pagamentos cancelados: " . count($cancelar) . "\n\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
