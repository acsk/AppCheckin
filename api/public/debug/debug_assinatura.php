<?php
/**
 * Debug de Assinatura Mercado Pago
 * 
 * Este script:
 * 1. Busca status da assinatura no Mercado Pago
 * 2. Atualiza a matrícula e pagamento se necessário
 * 
 * Uso: curl "https://seudominio.com/debug_assinatura.php?matricula_id=31"
 * ou:  curl "https://seudominio.com/debug_assinatura.php?preapproval_id=xxx"
 * 
 * Para forçar ativação: curl "https://seudominio.com/debug_assinatura.php?matricula_id=31&force_activate=1"
 */

header('Content-Type: application/json');

$matriculaId = $_GET['matricula_id'] ?? null;
$preapprovalId = $_GET['preapproval_id'] ?? null;
$forceActivate = isset($_GET['force_activate']) && $_GET['force_activate'] == '1';

if (!$matriculaId && !$preapprovalId) {
    echo json_encode([
        'error' => 'Informe matricula_id ou preapproval_id',
        'uso' => [
            'Por matrícula' => '/debug_assinatura.php?matricula_id=31',
            'Por preapproval' => '/debug_assinatura.php?preapproval_id=xxx',
            'Forçar ativação' => '/debug_assinatura.php?matricula_id=31&force_activate=1'
        ]
    ], JSON_PRETTY_PRINT);
    exit;
}

try {
    $db = require __DIR__ . '/../config/database.php';
    $result = ['timestamp' => date('Y-m-d H:i:s')];
    
    // 1. Buscar dados da matrícula
    if ($matriculaId) {
        $stmt = $db->prepare("
            SELECT m.*, 
                   p.nome as plano_nome, p.valor as plano_valor,
                   pc.valor as ciclo_valor, af.nome as ciclo_nome, af.meses,
                   a.nome as aluno_nome, a.email as aluno_email,
                   sp.nome as status_nome
            FROM matriculas m
            INNER JOIN planos p ON p.id = m.plano_id
            LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
            LEFT JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
            INNER JOIN alunos a ON a.id = m.aluno_id
            INNER JOIN status_padrao sp ON sp.id = m.status_id
            WHERE m.id = ?
        ");
        $stmt->execute([$matriculaId]);
        $result['matricula'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result['matricula']) {
            throw new Exception("Matrícula {$matriculaId} não encontrada");
        }
    }
    
    // 2. Buscar assinatura no banco
    $stmtAssinatura = $db->prepare("
        SELECT * FROM assinaturas_mercadopago 
        WHERE " . ($matriculaId ? "matricula_id = ?" : "mp_preapproval_id = ?") . "
        ORDER BY id DESC LIMIT 1
    ");
    $stmtAssinatura->execute([$matriculaId ?? $preapprovalId]);
    $result['assinatura_db'] = $stmtAssinatura->fetch(PDO::FETCH_ASSOC);
    
    // 3. Buscar assinatura no Mercado Pago
    $preapprovalIdToSearch = $preapprovalId ?? ($result['assinatura_db']['mp_preapproval_id'] ?? null);
    
    if ($preapprovalIdToSearch) {
        // Carregar credenciais do MP
        $accessToken = $_ENV['MERCADOPAGO_ACCESS_TOKEN'] ?? $_SERVER['MERCADOPAGO_ACCESS_TOKEN'] ?? null;
        
        if ($accessToken) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.mercadopago.com/preapproval/{$preapprovalIdToSearch}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer {$accessToken}",
                    "Content-Type: application/json"
                ],
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $mpData = json_decode($response, true);
            $result['assinatura_mp'] = [
                'http_code' => $httpCode,
                'data' => $mpData
            ];
            
            // Interpretar status
            if ($httpCode == 200 && $mpData) {
                $mpStatus = $mpData['status'] ?? 'unknown';
                $result['status_interpretado'] = [
                    'status_mp' => $mpStatus,
                    'significa' => match($mpStatus) {
                        'authorized' => '✅ AUTORIZADA - Assinatura ativa, primeiro pagamento realizado',
                        'pending' => '⏳ PENDENTE - Aguardando confirmação do pagamento',
                        'paused' => '⏸️ PAUSADA - Assinatura pausada temporariamente',
                        'cancelled' => '❌ CANCELADA - Assinatura cancelada',
                        default => "❓ Status desconhecido: {$mpStatus}"
                    }
                ];
                
                // Se autorizada e forceActivate, ou se autorizada e matrícula pendente
                if ($mpStatus === 'authorized') {
                    $result['acao_necessaria'] = 'Assinatura autorizada! Matrícula deve ser ativada.';
                    
                    if ($forceActivate || ($result['matricula']['status_id'] ?? 0) != 1) {
                        // Ativar matrícula
                        $stmtUpdate = $db->prepare("
                            UPDATE matriculas 
                            SET status_id = 1, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmtUpdate->execute([$matriculaId ?? $result['assinatura_db']['matricula_id']]);
                        $result['matricula_ativada'] = $stmtUpdate->rowCount() > 0;
                        
                        // Atualizar assinatura no banco
                        if ($result['assinatura_db']) {
                            $stmtUpdateAss = $db->prepare("
                                UPDATE assinaturas_mercadopago 
                                SET status = 'authorized', updated_at = NOW() 
                                WHERE id = ?
                            ");
                            $stmtUpdateAss->execute([$result['assinatura_db']['id']]);
                            $result['assinatura_atualizada'] = true;
                        }
                        
                        // Baixar pagamento
                        $matId = $matriculaId ?? $result['assinatura_db']['matricula_id'];
                        $stmtPag = $db->prepare("
                            SELECT id FROM pagamentos_plano 
                            WHERE matricula_id = ? AND status_pagamento_id = 1
                            ORDER BY data_vencimento ASC LIMIT 1
                        ");
                        $stmtPag->execute([$matId]);
                        $pagPendente = $stmtPag->fetch(PDO::FETCH_ASSOC);
                        
                        if ($pagPendente) {
                            $stmtBaixa = $db->prepare("
                                UPDATE pagamentos_plano 
                                SET status_pagamento_id = 2, 
                                    data_pagamento = NOW(),
                                    forma_pagamento_id = 9,
                                    tipo_baixa_id = 2,
                                    observacoes = CONCAT(IFNULL(observacoes,''), ' | Baixa via debug_assinatura - Assinatura Autorizada'),
                                    updated_at = NOW()
                                WHERE id = ?
                            ");
                            $stmtBaixa->execute([$pagPendente['id']]);
                            $result['pagamento_baixado'] = $pagPendente['id'];
                        }
                    }
                }
            }
        } else {
            $result['assinatura_mp'] = ['error' => 'MERCADOPAGO_ACCESS_TOKEN não configurado'];
        }
    }
    
    // 4. Buscar pagamentos da matrícula
    $matIdPag = $matriculaId ?? ($result['assinatura_db']['matricula_id'] ?? null);
    if ($matIdPag) {
        $stmtPagamentos = $db->prepare("
            SELECT pp.*, sp.nome as status_nome, fp.nome as forma_nome
            FROM pagamentos_plano pp
            LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
            LEFT JOIN formas_pagamento fp ON fp.id = pp.forma_pagamento_id
            WHERE pp.matricula_id = ?
            ORDER BY pp.created_at DESC
        ");
        $stmtPagamentos->execute([$matIdPag]);
        $result['pagamentos_plano'] = $stmtPagamentos->fetchAll(PDO::FETCH_ASSOC);
        
        // Pagamentos MP
        $stmtPagMP = $db->prepare("
            SELECT * FROM pagamentos_mercadopago 
            WHERE matricula_id = ? ORDER BY created_at DESC
        ");
        $stmtPagMP->execute([$matIdPag]);
        $result['pagamentos_mercadopago'] = $stmtPagMP->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 5. Resumo
    $result['resumo'] = [
        'matricula_status' => $result['matricula']['status_nome'] ?? 'N/A',
        'assinatura_status_db' => $result['assinatura_db']['status'] ?? 'N/A',
        'assinatura_status_mp' => $result['assinatura_mp']['data']['status'] ?? 'N/A',
        'total_pagamentos' => count($result['pagamentos_plano'] ?? []),
        'pagamentos_pendentes' => count(array_filter($result['pagamentos_plano'] ?? [], fn($p) => $p['status_pagamento_id'] == 1)),
        'pagamentos_pagos' => count(array_filter($result['pagamentos_plano'] ?? [], fn($p) => $p['status_pagamento_id'] == 2))
    ];
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
