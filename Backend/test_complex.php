<?php
$db = new PDO('mysql:host=mysql;dbname=appcheckin', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$turmaId = 187;
$tenantId = 5;

$sql = "
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
        d.data as dia_data
    FROM turmas t
    LEFT JOIN usuarios p ON t.professor_id = p.id
    LEFT JOIN modalidades m ON t.modalidade_id = m.id
    LEFT JOIN dias d ON t.dia_id = d.id
    WHERE t.id = ? AND t.tenant_id = ?
";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute([$turmaId, $tenantId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "✅ Query turma OK!\n";
        
        // Contar alunos
        $stmt2 = $db->prepare("SELECT COUNT(DISTINCT usuario_id) as total FROM matriculas WHERE turma_id = ? AND ativo = 1");
        $stmt2->execute([$turmaId]);
        $alunos = $stmt2->fetch(PDO::FETCH_ASSOC);
        $result['total_alunos_matriculados'] = $alunos['total'];
        
        // Contar check-ins
        $stmt3 = $db->prepare("SELECT COUNT(*) as total FROM checkins WHERE turma_id = ?");
        $stmt3->execute([$turmaId]);
        $checkins = $stmt3->fetch(PDO::FETCH_ASSOC);
        $result['total_checkins'] = $checkins['total'];
        
        echo "Turma ID: " . $result['id'] . "\n";
        echo "Nome: " . $result['nome'] . "\n";
        echo "Professor: " . ($result['professor_nome'] ?? 'NULL') . "\n";
        echo "Horário: " . $result['horario_inicio'] . " - " . $result['horario_fim'] . "\n";
        echo "Alunos matriculados: " . $result['total_alunos_matriculados'] . "\n";
        echo "Check-ins: " . $result['total_checkins'] . "\n";
        echo "Dia: " . ($result['dia_data'] ?? 'NULL') . "\n";
    } else {
        echo "❌ Turma não encontrada\n";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
