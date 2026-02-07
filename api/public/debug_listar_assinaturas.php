<?php
/**
 * Debug: Listar todas as assinaturas do banco
 * 
 * Uso: curl "https://api.appcheckin.com.br/debug_listar_assinaturas.php?tenant_id=2"
 */

header('Content-Type: application/json');

$tenantId = $_GET['tenant_id'] ?? null;

try {
    $db = require __DIR__ . '/../config/database.php';
    $result = [];
    
    // 1. Listar TODAS as assinaturas
    $sql = "
        SELECT 
            asm.*,
            a.nome as aluno_nome,
            a.usuario_id,
            u.nome as usuario_nome,
            u.email as usuario_email,
            mat.id as matricula_id,
            mat.status_id as matricula_status,
            p.nome as plano_nome
        FROM assinaturas_mercadopago asm
        LEFT JOIN alunos a ON a.id = asm.aluno_id
        LEFT JOIN usuarios u ON u.id = a.usuario_id
        LEFT JOIN matriculas mat ON mat.id = asm.matricula_id
        LEFT JOIN planos p ON p.id = mat.plano_id
    ";
    
    if ($tenantId) {
        $sql .= " WHERE asm.tenant_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$tenantId]);
    } else {
        $stmt = $db->query($sql);
    }
    
    $result['assinaturas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result['total'] = count($result['assinaturas']);
    
    // 2. Se não tem assinaturas, verificar se tabela existe e estrutura
    if ($result['total'] === 0) {
        $stmtCheck = $db->query("SHOW TABLES LIKE 'assinaturas_mercadopago'");
        $result['tabela_existe'] = $stmtCheck->rowCount() > 0;
        
        if ($result['tabela_existe']) {
            $stmtCount = $db->query("SELECT COUNT(*) as total FROM assinaturas_mercadopago");
            $result['total_geral'] = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        }
    }
    
    // 3. Diagnóstico adicional
    $result['dica'] = 'Se assinatura existe mas não aparece, verifique se aluno_id e usuario_id estão corretos';
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
