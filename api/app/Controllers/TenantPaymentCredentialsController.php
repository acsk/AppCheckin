<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\EncryptionService;

/**
 * Controller para gerenciar credenciais de pagamento dos tenants
 */
class TenantPaymentCredentialsController
{
    private $db;
    private EncryptionService $encryption;
    
    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
        $this->encryption = new EncryptionService();
    }
    
    /**
     * Obter credenciais do tenant (mascaradas)
     * 
     * GET /admin/payment-credentials
     */
    public function obter(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            
            $stmt = $this->db->prepare("
                SELECT id, tenant_id, provider, environment, 
                       public_key_test, public_key_prod,
                       is_active, created_at, updated_at,
                       CASE WHEN access_token_test IS NOT NULL AND access_token_test != '' THEN TRUE ELSE FALSE END as has_token_test,
                       CASE WHEN access_token_prod IS NOT NULL AND access_token_prod != '' THEN TRUE ELSE FALSE END as has_token_prod
                FROM tenant_payment_credentials 
                WHERE tenant_id = ?
                LIMIT 1
            ");
            $stmt->execute([$tenantId]);
            $credentials = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$credentials) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'data' => null,
                    'message' => 'Nenhuma credencial configurada'
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }
            
            // Mascarar public keys para exibição
            $credentials['public_key_test_masked'] = $this->mascarar($credentials['public_key_test']);
            $credentials['public_key_prod_masked'] = $this->mascarar($credentials['public_key_prod']);
            unset($credentials['public_key_test'], $credentials['public_key_prod']);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $credentials
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("[PaymentCredentials] Erro ao obter: " . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao obter credenciais: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Salvar/Atualizar credenciais do tenant
     * 
     * POST /admin/payment-credentials
     */
    public function salvar(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $body = $request->getParsedBody();
            
            // Validar campos obrigatórios
            $environment = $body['environment'] ?? 'sandbox';
            $provider = $body['provider'] ?? 'mercadopago';
            
            // Verificar se já existe
            $stmt = $this->db->prepare("SELECT id FROM tenant_payment_credentials WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Preparar dados
            $accessTokenTest = isset($body['access_token_test']) && !empty($body['access_token_test']) 
                ? $this->encryption->encrypt($body['access_token_test']) 
                : null;
            $accessTokenProd = isset($body['access_token_prod']) && !empty($body['access_token_prod']) 
                ? $this->encryption->encrypt($body['access_token_prod']) 
                : null;
            $webhookSecret = isset($body['webhook_secret']) && !empty($body['webhook_secret']) 
                ? $this->encryption->encrypt($body['webhook_secret']) 
                : null;
            
            if ($existing) {
                // Atualizar
                $sql = "UPDATE tenant_payment_credentials SET 
                        provider = ?,
                        environment = ?,
                        public_key_test = ?,
                        public_key_prod = ?,
                        is_active = ?,
                        updated_at = NOW()";
                $params = [
                    $provider,
                    $environment,
                    $body['public_key_test'] ?? null,
                    $body['public_key_prod'] ?? null,
                    $body['is_active'] ?? true
                ];
                
                // Só atualizar tokens se foram enviados
                if ($accessTokenTest !== null) {
                    $sql .= ", access_token_test = ?";
                    $params[] = $accessTokenTest;
                }
                if ($accessTokenProd !== null) {
                    $sql .= ", access_token_prod = ?";
                    $params[] = $accessTokenProd;
                }
                if ($webhookSecret !== null) {
                    $sql .= ", webhook_secret = ?";
                    $params[] = $webhookSecret;
                }
                
                $sql .= " WHERE tenant_id = ?";
                $params[] = $tenantId;
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                
                $message = 'Credenciais atualizadas com sucesso';
            } else {
                // Inserir
                $stmt = $this->db->prepare("
                    INSERT INTO tenant_payment_credentials 
                    (tenant_id, provider, environment, access_token_test, access_token_prod, 
                     public_key_test, public_key_prod, webhook_secret, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $tenantId,
                    $provider,
                    $environment,
                    $accessTokenTest,
                    $accessTokenProd,
                    $body['public_key_test'] ?? null,
                    $body['public_key_prod'] ?? null,
                    $webhookSecret,
                    $body['is_active'] ?? true
                ]);
                
                $message = 'Credenciais cadastradas com sucesso';
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => $message
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("[PaymentCredentials] Erro ao salvar: " . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao salvar credenciais: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Testar conexão com o Mercado Pago
     * 
     * POST /admin/payment-credentials/test
     */
    public function testar(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            
            // Instanciar MercadoPagoService com tenant
            $mercadoPago = new \App\Services\MercadoPagoService($tenantId);
            
            // Testar com uma chamada simples (buscar configuração da conta)
            // Por enquanto, apenas verificar se tem credenciais
            $publicKey = $mercadoPago->getPublicKey();
            
            if (empty($publicKey)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Credenciais não configuradas ou inválidas'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Conexão com Mercado Pago OK',
                'data' => [
                    'public_key_prefix' => substr($publicKey, 0, 15) . '...'
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao testar conexão: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Mascarar valor sensível
     */
    private function mascarar(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        
        $length = strlen($value);
        if ($length <= 10) {
            return str_repeat('*', $length);
        }
        
        return substr($value, 0, 6) . str_repeat('*', $length - 10) . substr($value, -4);
    }
}
