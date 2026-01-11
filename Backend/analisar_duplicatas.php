<?php
// Verificar estrutura de matrículas com duplicatas

$db = require __DIR__ . '/config/database.php';

echo "=== ANÁLISE DE MATRÍCULAS COM DUPLICATAS ===\n\n";

// Buscar tenants
$sqlTenants = "SELECT id, nome FROM tenants WHERE ativo = 1 ORDER BY id";
$stmt = $db->prepare($sqlTenants);
$stmt->execute();
$tenants = $stmt->fetchAll(\PDO::FETCH_ASSOC);

foreach ($tenants as $tenant) {
    $tId = $tenant['id'];
    echo "Tenant #{$tId}: {$tenant['nome']}\n";
    
    // Buscar usuários com múltiplas matrículas
    $sqlUsuarios = "
        SELECT DISTINCT m.usuario_id, u.nome
        FROM matriculas m
        INNER JOIN usuarios u ON m.usuario_id = u.id
        WHERE m.tenant_id = :tenant_id
        AND m.status IN ('ativa', 'pendente', 'vencida')
        GROUP BY m.usuario_id
        HAVING COUNT(*) > 1
    ";
    
    $stmt = $db->prepare($sqlUsuarios);
    $stmt->execute(['tenant_id' => $tId]);
    $usuarios = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    if (empty($usuarios)) {
        echo "  Nenhum usuário com duplicatas\n\n";
        continue;
    }
    
    foreach ($usuarios as $usr) {
        echo "  👤 Usuário: {$usr['nome']} (ID: {$usr['usuario_id']})\n";
        
        $sqlMatriculas = "
            SELECT m.id, m.data_matricula, m.status,
                   p.nome as plano_nome, mo.nome as modalidade_nome, mo.id as modalidade_id
            FROM matriculas m
            INNER JOIN planos p ON m.plano_id = p.id
            INNER JOIN modalidades mo ON p.modalidade_id = mo.id
            WHERE m.usuario_id = :usuario_id
            AND m.tenant_id = :tenant_id
            AND m.status IN ('ativa', 'pendente', 'vencida')
            ORDER BY mo.id, m.data_matricula DESC
        ";
        
        $stmt = $db->prepare($sqlMatriculas);
        $stmt->execute(['usuario_id' => $usr['usuario_id'], 'tenant_id' => $tId]);
        $matriculas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $matriculasPorModalidade = [];
        foreach ($matriculas as $m) {
            $modId = $m['modalidade_id'];
            if (!isset($matriculasPorModalidade[$modId])) {
                $matriculasPorModalidade[$modId] = [
                    'nome' => $m['modalidade_nome'],
                    'matriculas' => []
                ];
            }
            $matriculasPorModalidade[$modId]['matriculas'][] = $m;
        }
        
        foreach ($matriculasPorModalidade as $modId => $mod) {
            echo "    📚 Modalidade: {$mod['nome']}\n";
            foreach ($mod['matriculas'] as $m) {
                echo "      - {$m['plano_nome']} (Data: {$m['data_matricula']}, Status: {$m['status']})\n";
            }
        }
        
        echo "\n";
    }
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
}
