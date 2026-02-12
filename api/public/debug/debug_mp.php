<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../vendor/autoload.php';
    
    // Testar criar assinatura diretamente
    $tenantId = 2;
    $mpService = new \App\Services\MercadoPagoService($tenantId);
    
    $dados = [
        'tenant_id' => $tenantId,
        'matricula_id' => 999,
        'aluno_id' => 6,
        'usuario_id' => 10,
        'aluno_nome' => 'Ana Paula',
        'aluno_email' => 'ana.paula@teste.com',
        'aluno_telefone' => '11999999999',
        'plano_nome' => 'Plano Teste',
        'descricao' => 'Teste de assinatura',
        'valor' => 0.01,
        'academia_nome' => 'Aqua Masters'
    ];
    
    echo "Testando criarPreferenciaAssinatura...\n\n";
    $resultado = $mpService->criarPreferenciaAssinatura($dados);
    
    echo json_encode([
        'success' => true,
        'resultado' => $resultado
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
