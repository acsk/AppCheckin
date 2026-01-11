<?php
$db = new PDO('mysql:host=mysql;dbname=appcheckin', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$turmaId = 187;
$tenantId = 5;

// Query de turma
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
        echo "✅ Turma OK!\n";
        echo "ID: " . $result['id'] . "\n";
        echo "Nome: " . $result['nome'] . "\n";
        
        // Contar alunos por check-ins
        $stmt2 = $db->prepare("SELECT COUNT(DISTINCT usuario_id) as total FROM checkins WHERE turma_id = ?");
        $stmt2->execute([$turmaId]);
        $alunos = $stmt2->fetch(PDO::FETCH_ASSOC);
        echo "Alunos (por check-ins): " . $alunos['total'] . "\n";
        
        // Contar check-ins
        $stmt3 = $db->prepare("SELECT COUNT(*) as total FROM checkins WHERE turma_id = ?");
        $stmt3->execute([$turmaId]);
        $checkins = $stmt3->fetch(PDO::FETCH_ASSOC);
        echo "Total check-ins: " . $checkins['total'] . "\n";
        
        // Listar alunos
        $stmt4 = $db->prepare("
            SELECT 
                u.id,
                u.nome,
                u.email,
                COUNT(c.id) as checkins_do_aluno
            FROM usuarios u
            INNER JOIN checkins c ON u.id = c.usuario_id
            WHERE c.turma_id = ?
            GROUP BY u.id, u.nome, u.email
            ORDER BY u.nome ASC
        ");
        $stmt4->execute([$turmaId]);
        $alunosList = $stmt4->fetchAll(PDO::FETCH_ASSOC);
        echo "\nAlunos:\n";
        foreach ($alunosList as $a) {
            echo "- " . $a['nome'] . " (" . $a['checkins_do_aluno'] . " check-ins)\n";
        }
        
    } else {
        echo "❌ Turma não encontrada\n";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
