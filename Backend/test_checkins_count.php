<?php
// Script para testar contagem de check-ins no endpoint de horários disponíveis

$db = require __DIR__ . '/config/database.php';

// Buscar tenant 4
$tenantId = 4;

// Data de hoje
$data = date('Y-m-d');

echo "=== TESTE: Contagem de Check-ins no Endpoint ===\n\n";

// 1. Buscar dia
$sqlDia = "SELECT id, data FROM dias WHERE data = :data";
$stmtDia = $db->prepare($sqlDia);
$stmtDia->execute(['data' => $data]);
$dia = $stmtDia->fetch(\PDO::FETCH_ASSOC);

if ($dia) {
    echo "✅ Dia encontrado: {$dia['data']} (ID: {$dia['id']})\n\n";
} else {
    echo "❌ Nenhum dia encontrado para {$data}\n";
    exit;
}

// 2. Buscar turmas do dia
$sqlTurmas = "SELECT t.id, t.nome, t.limite_alunos
              FROM turmas t
              INNER JOIN dias d ON t.dia_id = d.id
              WHERE d.id = :dia_id AND t.ativo = 1
              ORDER BY t.horario_inicio ASC";

$stmtTurmas = $db->prepare($sqlTurmas);
$stmtTurmas->execute(['dia_id' => $dia['id']]);
$turmas = $stmtTurmas->fetchAll(\PDO::FETCH_ASSOC);

echo "Total de turmas: " . count($turmas) . "\n\n";

// 3. Para cada turma, contar check-ins
$sqlCheckins = "SELECT COUNT(DISTINCT usuario_id) as total FROM checkins WHERE turma_id = :turma_id";
$stmtCheckins = $db->prepare($sqlCheckins);

foreach ($turmas as $i => $turma) {
    $stmtCheckins->execute(['turma_id' => $turma['id']]);
    $checkinsData = $stmtCheckins->fetch(\PDO::FETCH_ASSOC);
    $checkinsCount = (int) ($checkinsData['total'] ?? 0);
    $vagas = (int) $turma['limite_alunos'] - $checkinsCount;
    
    echo "[" . ($i + 1) . "] {$turma['nome']}\n";
    echo "    Check-ins: $checkinsCount\n";
    echo "    Vagas: $checkinsCount/{$turma['limite_alunos']}\n";
    echo "    Disponíveis: $vagas\n\n";
}

echo "\n✅ Teste completo!\n";
