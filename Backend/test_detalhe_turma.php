<?php
$db = new PDO('mysql:host=mysql;dbname=appcheckin', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// Buscar turmas
$stmt = $db->prepare('SELECT id, nome, tenant_id FROM turmas LIMIT 10');
$stmt->execute();
$turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Primeiras turmas do banco:\n";
foreach ($turmas as $t) {
    echo "- ID: " . $t['id'] . " | Tenant: " . $t['tenant_id'] . " | Nome: " . $t['nome'] . "\n";
}

// Testar a query do detalheTurma
echo "\n\nTestando query detalheTurma com ID 1:\n";

$sqlTurma = "
    SELECT 
        t.id,
        t.nome,
        t.limite_alunos,
        t.horario_inicio,
        t.horario_fim,
        t.ativo,
        p.nome as professor_nome,
        p.email as professor_email,
        m.nome as modalidade_nome,
        d.data as dia_data,
        (SELECT COUNT(DISTINCT usuario_id) FROM matriculas 
         WHERE turma_id = t.id AND ativo = 1) as total_alunos_matriculados,
        (SELECT COUNT(*) FROM checkins 
         WHERE turma_id = t.id) as total_checkins
    FROM turmas t
    LEFT JOIN usuarios p ON t.professor_id = p.id
    LEFT JOIN modalidades m ON t.modalidade_id = m.id
    LEFT JOIN dias d ON t.dia_id = d.id
    WHERE t.id = :turma_id
    LIMIT 1
";

try {
    $stmt = $db->prepare($sqlTurma);
    $stmt->execute(['turma_id' => 187]);
    $turma = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($turma) {
        echo "Sucesso!\n";
        echo "ID: " . $turma['id'] . "\n";
        echo "Nome: " . $turma['nome'] . "\n";
        echo "Professor: " . ($turma['professor_nome'] ?? 'NULL') . "\n";
        echo "Horário: " . $turma['horario_inicio'] . " - " . $turma['horario_fim'] . "\n";
        echo "Alunos matriculados: " . $turma['total_alunos_matriculados'] . "\n";
        echo "Total de check-ins: " . $turma['total_checkins'] . "\n";
    } else {
        echo "Nenhuma turma encontrada com ID 187\n";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
