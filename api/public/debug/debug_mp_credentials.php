<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php';
    
    // Verificar credenciais do tenant 2
    $tenantId = 2;
    
    $stmt = $pdo->prepare("
        SELECT id, tenant_id, provider, environment, 
               CASE WHEN access_token_test IS NOT NULL AND access_token_test != '' THEN 'SIM' ELSE 'NAO' END as has_test_token,
               CASE WHEN access_token_prod IS NOT NULL AND access_token_prod != '' THEN 'SIM' ELSE 'NAO' END as has_prod_token,
               public_key_test, public_key_prod, is_active
        FROM tenant_payment_credentials 
        WHERE tenant_id = ? AND provider = 'mercadopago'
    ");
    $stmt->execute([$tenantId]);
    $credentials = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar variáveis de ambiente
    $envVars = [
        'MP_ENVIRONMENT' => $_ENV['MP_ENVIRONMENT'] ?? $_SERVER['MP_ENVIRONMENT'] ?? 'N/A',
        'MP_ACCESS_TOKEN_TEST' => isset($_ENV['MP_ACCESS_TOKEN_TEST']) || isset($_SERVER['MP_ACCESS_TOKEN_TEST']) ? 'CONFIGURADO' : 'N/A',
        'MP_PUBLIC_KEY_TEST' => isset($_ENV['MP_PUBLIC_KEY_TEST']) || isset($_SERVER['MP_PUBLIC_KEY_TEST']) ? 'CONFIGURADO' : 'N/A'
    ];
    
    echo json_encode([
        'success' => true,
        'tenant_id' => $tenantId,
        'credentials_in_db' => $credentials ?: 'Nenhuma credencial encontrada no banco',
        'env_vars' => $envVars
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
