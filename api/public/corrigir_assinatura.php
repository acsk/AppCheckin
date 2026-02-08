<?php
/**
 * Corrigir Assinatura - Gravar dados do Mercado Pago
 * 
 * Este script cria o registro na tabela assinaturas_mercadopago
 * para matrículas que foram criadas sem esse registro.
 * 
 * Uso: curl "https://seudominio.com/corrigir_assinatura.php?matricula_id=31&preapproval_id=xxx"
 */

header('Content-Type: application/json');

$matriculaId = $_GET['matricula_id'] ?? null;
$preapprovalId = $_GET['preapproval_id'] ?? null;

if (!$matriculaId) {
    echo json_encode([
        'error' => 'Informe matricula_id',
        'uso' => '/corrigir_assinatura.php?matricula_id=31&preapproval_id=ddb5c80b9389408fadce18befc7a9283'
    ], JSON_PRETTY_PRINT);
    exit;
}

try {
    $db = require __DIR__ . '/../config/database.php';
    $result = ['timestamp' => date('Y-m-d H:i:s')];
    
    // 1. Buscar dados da matrícula
    $stmt = $db->prepare("
        SELECT m.*, 
               p.nome as plano_nome, p.valor as plano_valor,
               pc.valor as ciclo_valor, af.nome as ciclo_nome, af.meses,
               a.nome as aluno_nome, a.email as aluno_email
        FROM matriculas m
        INNER JOIN planos p ON p.id = m.plano_id
        LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
        LEFT JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
        INNER JOIN alunos a ON a.id = m.aluno_id
        WHERE m.id = ?
    ");
    $stmt->execute([$matriculaId]);
    $matricula = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$matricula) {
        throw new Exception("Matrícula {$matriculaId} não encontrada");
    }
    
    $result['matricula'] = [
        'id' => $matricula['id'],
        'tenant_id' => $matricula['tenant_id'],
        'aluno_id' => $matricula['aluno_id'],
        'plano_nome' => $matricula['plano_nome'],
        'ciclo_nome' => $matricula['ciclo_nome'] ?? 'Mensal',
        'valor' => $matricula['ciclo_valor'] ?? $matricula['plano_valor'],
        'tipo_cobranca' => $matricula['tipo_cobranca'] ?? 'avulso'
    ];
    
    // 2. Verificar se já existe assinatura
    $stmtCheck = $db->prepare("
        SELECT id, mp_preapproval_id, status 
        FROM assinaturas_mercadopago 
        WHERE matricula_id = ?
    ");
    $stmtCheck->execute([$matriculaId]);
    $assinaturaExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($assinaturaExistente) {
        $result['assinatura_existente'] = $assinaturaExistente;
        
        // Se não tem preapproval_id no banco mas foi informado, atualizar
        if (!$assinaturaExistente['mp_preapproval_id'] && $preapprovalId) {
            $stmtUpdate = $db->prepare("
                UPDATE assinaturas_mercadopago 
                SET mp_preapproval_id = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmtUpdate->execute([$preapprovalId, $assinaturaExistente['id']]);
            $result['preapproval_atualizado'] = true;
        }
        
        // Se informou preapproval_id diferente, atualizar
        if ($preapprovalId && $assinaturaExistente['mp_preapproval_id'] !== $preapprovalId) {
            $stmtUpdate = $db->prepare("
                UPDATE assinaturas_mercadopago 
                SET mp_preapproval_id = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmtUpdate->execute([$preapprovalId, $assinaturaExistente['id']]);
            $result['preapproval_atualizado'] = true;
        }
        
    } else {
        // 3. Criar registro da assinatura
        $stmtInsert = $db->prepare("
            INSERT INTO assinaturas_mercadopago
            (tenant_id, matricula_id, aluno_id, plano_ciclo_id,
             mp_preapproval_id, status, valor,
             dia_cobranca, data_inicio, proxima_cobranca, created_at)
            VALUES (?, ?, ?, ?, ?, 'authorized', ?, ?, ?, ?, NOW())
        ");
        
        $valor = $matricula['ciclo_valor'] ?? $matricula['plano_valor'];
        $diaCobranca = (int) date('d');
        $dataInicio = $matricula['data_inicio'] ?? date('Y-m-d');
        $proximaCobranca = date('Y-m-d', strtotime('+1 month', strtotime($dataInicio)));
        
        $stmtInsert->execute([
            $matricula['tenant_id'],
            $matriculaId,
            $matricula['aluno_id'],
            $matricula['plano_ciclo_id'],
            $preapprovalId,
            $valor,
            $diaCobranca,
            $dataInicio,
            $proximaCobranca
        ]);
        
        $assinaturaId = (int) $db->lastInsertId();
        $result['assinatura_criada'] = [
            'id' => $assinaturaId,
            'mp_preapproval_id' => $preapprovalId,
            'status' => 'authorized',
            'valor' => $valor
        ];
    }
    
    // 4. Garantir que matrícula está como recorrente
    $stmtUpdateMat = $db->prepare("
        UPDATE matriculas 
        SET tipo_cobranca = 'recorrente', updated_at = NOW()
        WHERE id = ? AND (tipo_cobranca IS NULL OR tipo_cobranca = 'avulso')
    ");
    $stmtUpdateMat->execute([$matriculaId]);
    if ($stmtUpdateMat->rowCount() > 0) {
        $result['matricula_atualizada_recorrente'] = true;
    }
    
    // 5. Buscar status final
    $stmtFinal = $db->prepare("SELECT * FROM assinaturas_mercadopago WHERE matricula_id = ?");
    $stmtFinal->execute([$matriculaId]);
    $result['assinatura_final'] = $stmtFinal->fetch(PDO::FETCH_ASSOC);
    
    $result['success'] = true;
    $result['message'] = 'Assinatura corrigida/criada com sucesso';
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
