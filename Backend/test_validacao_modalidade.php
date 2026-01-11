<?php
// Teste: Usuário não pode fazer check-in na mesma modalidade no mesmo dia

$db = require __DIR__ . '/config/database.php';

echo "=== TESTE: Validação de Modalidade Duplicada no Mesmo Dia ===\n\n";

$userId = 11; // Carolina
$tenantId = 4;

// 1. Buscar turmas do dia 2026-01-11
$sqlTurmas = "
    SELECT t.id, t.nome, t.modalidade_id, m.nome as modalidade_nome, d.id as dia_id, d.data
    FROM turmas t
    INNER JOIN dias d ON t.dia_id = d.id
    INNER JOIN modalidades m ON t.modalidade_id = m.id
    WHERE d.data = '2026-01-11' AND t.ativo = 1 AND t.tenant_id = :tenant_id
    ORDER BY t.modalidade_id, t.horario_inicio
";

$stmtTurmas = $db->prepare($sqlTurmas);
$stmtTurmas->execute(['tenant_id' => $tenantId]);
$turmas = $stmtTurmas->fetchAll(\PDO::FETCH_ASSOC);

echo "Turmas disponíveis no dia 2026-01-11:\n\n";

$turmasPorModalidade = [];
foreach ($turmas as $i => $turma) {
    $modalidade = $turma['modalidade_nome'];
    if (!isset($turmasPorModalidade[$modalidade])) {
        $turmasPorModalidade[$modalidade] = [];
    }
    $turmasPorModalidade[$modalidade][] = $turma;
    echo "[" . ($i + 1) . "] {$turma['nome']} (Modalidade: $modalidade, ID: {$turma['id']})\n";
}

echo "\n\n=== TESTE DE VALIDAÇÃO ===\n\n";

// 2. Buscar check-ins do usuário neste dia
$sqlCheckinsUsuario = "
    SELECT c.id, t.id as turma_id, t.nome as turma_nome, m.nome as modalidade_nome, t.modalidade_id
    FROM checkins c
    INNER JOIN turmas t ON c.turma_id = t.id
    INNER JOIN dias d ON t.dia_id = d.id
    INNER JOIN modalidades m ON t.modalidade_id = m.id
    WHERE c.usuario_id = :usuario_id AND d.data = '2026-01-11'
";

$stmtCheckinsUsuario = $db->prepare($sqlCheckinsUsuario);
$stmtCheckinsUsuario->execute(['usuario_id' => $userId]);
$checkinsUsuario = $stmtCheckinsUsuario->fetchAll(\PDO::FETCH_ASSOC);

if (empty($checkinsUsuario)) {
    echo "Usuário ainda não tem check-ins neste dia.\n";
} else {
    echo "Check-ins do usuário neste dia:\n";
    foreach ($checkinsUsuario as $checkin) {
        echo "- {$checkin['turma_nome']} (Modalidade: {$checkin['modalidade_nome']})\n";
    }
}

echo "\n\n=== VALIDAÇÃO: Mesma Modalidade no Mesmo Dia ===\n\n";

// 3. Para cada modalidade, verificar se há mais de um check-in do usuário
$modalidadesComMultiploCheckins = [];

foreach ($turmasPorModalidade as $modalidade => $turmas) {
    echo "Modalidade: $modalidade\n";
    
    // Contar check-ins do usuário nesta modalidade neste dia
    $sqlCheck = "
        SELECT COUNT(DISTINCT c.id) as total
        FROM checkins c
        INNER JOIN turmas t ON c.turma_id = t.id
        INNER JOIN dias d ON t.dia_id = d.id
        INNER JOIN modalidades m ON t.modalidade_id = m.id
        WHERE c.usuario_id = :usuario_id
          AND m.nome = :modalidade
          AND d.data = '2026-01-11'
    ";
    
    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->execute(['usuario_id' => $userId, 'modalidade' => $modalidade]);
    $result = $stmtCheck->fetch(\PDO::FETCH_ASSOC);
    $totalCheckins = (int) ($result['total'] ?? 0);
    
    if ($totalCheckins > 0) {
        echo "  ⚠️  Usuário JÁ TEM $totalCheckins check-in(s) nesta modalidade\n";
        echo "  ❌ Não pode fazer check-in em outra turma de $modalidade no mesmo dia\n";
        $modalidadesComMultiploCheckins[$modalidade] = $totalCheckins;
    } else {
        echo "  ✅ Usuário PODE fazer check-in nesta modalidade\n";
    }
    
    echo "\n";
}

echo "\n=== RESUMO ===\n\n";
if (empty($modalidadesComMultiploCheckins)) {
    echo "✅ Usuário pode fazer check-in em qualquer turma do dia (nenhuma modalidade duplicada)\n";
} else {
    echo "❌ Modalidades com restrição:\n";
    foreach ($modalidadesComMultiploCheckins as $modalidade => $count) {
        echo "  - $modalidade ($count check-in(s) registrado(s))\n";
    }
}
