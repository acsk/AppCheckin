<?php

namespace App\Services;

use Exception;
use PDO;

/**
 * Serviço de Integração com Mercado Pago
 * 
 * Este serviço gerencia pagamentos via Mercado Pago incluindo:
 * - Criação de preferências de pagamento
 * - Processamento de webhooks (notificações)
 * - Consulta de status de pagamentos
 * - Geração de links de pagamento
 * - Assinaturas recorrentes (planos)
 * 
 * Suporta credenciais por tenant (multi-tenant)
 * 
 * Documentação Mercado Pago: https://www.mercadopago.com.br/developers
 */
class MercadoPagoService
{
    private string $accessToken = '';
    private string $publicKey = '';
    private string $baseUrl;
    private bool $isProduction = false;
    private ?int $tenantId = null;
    private ?PDO $db = null;
    private ?EncryptionService $encryption = null;
    
    /**
     * URLs de Notificação (Webhooks)
     */
    private string $notificationUrl;
    
    /**
     * URLs de Retorno
     */
    private string $successUrl;
    private string $failureUrl;
    private string $pendingUrl;
    
    /**
     * Construtor
     * 
     * @param int|null $tenantId ID do tenant para buscar credenciais específicas
     */
    public function __construct(?int $tenantId = null)
    {
        $this->tenantId = $tenantId;
        $this->baseUrl = 'https://api.mercadopago.com';
        
        // Inicializar URLs de callback
        $apiUrl = $_ENV['API_URL'] ?? $_SERVER['API_URL'] ?? 'https://api.appcheckin.com.br';
        $this->notificationUrl = $apiUrl . '/api/webhooks/mercadopago';
        
        $mobileUrl = $_ENV['MOBILE_URL'] ?? $_SERVER['MOBILE_URL'] ?? 'https://mobile.appcheckin.com.br';
        $this->successUrl = $mobileUrl . '/pagamento/sucesso';
        $this->failureUrl = $mobileUrl . '/pagamento/falha';
        $this->pendingUrl = $mobileUrl . '/pagamento/pendente';
        
        // Carregar credenciais
        $this->carregarCredenciais();
    }
    
    /**
     * Carregar credenciais do tenant ou fallback para variáveis de ambiente
     */
    private function carregarCredenciais(): void
    {
        // Tentar carregar do banco se tiver tenant_id
        if ($this->tenantId) {
            $credenciaisCarregadas = $this->carregarCredenciaisDoBanco();
            if ($credenciaisCarregadas) {
                $this->logInicializacao('BANCO (tenant)');
                return;
            }
        }
        
        // Fallback: variáveis de ambiente (credenciais globais/padrão)
        $this->carregarCredenciaisDoEnv();
        $this->logInicializacao('ENV (global)');
    }
    
    /**
     * Carregar credenciais do banco de dados
     */
    private function carregarCredenciaisDoBanco(): bool
    {
        try {
            $this->db = require __DIR__ . '/../../config/database.php';
            
            $stmt = $this->db->prepare("
                SELECT * FROM tenant_payment_credentials 
                WHERE tenant_id = ? AND provider = 'mercadopago' AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$this->tenantId]);
            $credentials = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$credentials) {
                return false;
            }
            
            $this->encryption = new EncryptionService();
            $this->isProduction = $credentials['environment'] === 'production';
            
            if ($this->isProduction) {
                $this->accessToken = $this->decryptIfNeeded($credentials['access_token_prod'] ?? '');
                $this->publicKey = $credentials['public_key_prod'] ?? '';
            } else {
                $this->accessToken = $this->decryptIfNeeded($credentials['access_token_test'] ?? '');
                $this->publicKey = $credentials['public_key_test'] ?? '';
            }
            
            return !empty($this->accessToken);
            
        } catch (Exception $e) {
            error_log("[MercadoPagoService] Erro ao carregar credenciais do banco: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Carregar credenciais das variáveis de ambiente
     */
    private function carregarCredenciaisDoEnv(): void
    {
        $this->isProduction = ($_ENV['MP_ENVIRONMENT'] ?? $_SERVER['MP_ENVIRONMENT'] ?? 'sandbox') === 'production';
        
        if ($this->isProduction) {
            $this->accessToken = $_ENV['MP_ACCESS_TOKEN_PROD'] ?? $_SERVER['MP_ACCESS_TOKEN_PROD'] ?? '';
            $this->publicKey = $_ENV['MP_PUBLIC_KEY_PROD'] ?? $_SERVER['MP_PUBLIC_KEY_PROD'] ?? '';
        } else {
            $this->accessToken = $_ENV['MP_ACCESS_TOKEN_TEST'] ?? $_SERVER['MP_ACCESS_TOKEN_TEST'] ?? '';
            $this->publicKey = $_ENV['MP_PUBLIC_KEY_TEST'] ?? $_SERVER['MP_PUBLIC_KEY_TEST'] ?? '';
        }
    }
    
    /**
     * Descriptografar valor se necessário
     */
    private function decryptIfNeeded(string $value): string
    {
        if (empty($value)) {
            return '';
        }
        
        // Se começa com prefixos conhecidos do MP, não está criptografado
        if (str_starts_with($value, 'TEST-') || str_starts_with($value, 'APP_USR-')) {
            return $value;
        }
        
        // Tentar descriptografar
        try {
            if (!$this->encryption) {
                $this->encryption = new EncryptionService();
            }
            return $this->encryption->decrypt($value);
        } catch (Exception $e) {
            // Se falhar, retornar valor original (pode não estar criptografado)
            return $value;
        }
    }
    
    /**
     * Log de inicialização
     */
    private function logInicializacao(string $fonte): void
    {
        error_log("[MercadoPagoService] ========================================");
        error_log("[MercadoPagoService] 🚀 INICIALIZANDO MERCADO PAGO");
        error_log("[MercadoPagoService] 📦 Fonte: {$fonte}");
        error_log("[MercadoPagoService] 🏢 Tenant ID: " . ($this->tenantId ?? 'N/A'));
        error_log("[MercadoPagoService] 🌍 Ambiente: " . ($this->isProduction ? 'PRODUÇÃO' : 'SANDBOX'));
        error_log("[MercadoPagoService] 🔑 Token prefix: " . substr($this->accessToken, 0, 10) . "...");
        error_log("[MercadoPagoService] ========================================");
    }
    
    /**
     * Criar preferência de pagamento para matrícula
     * 
     * @param array $data Dados da matrícula e aluno
     * @return array Resposta com ID da preferência e init_point (link de pagamento)
     */
    public function criarPreferenciaPagamento(array $data): array
    {
        $this->validarCredenciais();
        
        // Montar itens do pagamento
        $items = [[
            'id' => $data['matricula_id'] ?? null,
            'title' => $data['plano_nome'] ?? 'Plano Academia',
            'description' => $data['descricao'] ?? 'Pagamento de matrícula',
            'picture_url' => $data['imagem_url'] ?? null,
            'category_id' => 'services',
            'quantity' => 1,
            'currency_id' => 'BRL',
            'unit_price' => (float) $data['valor']
        ]];
        
        // Em ambiente SANDBOX, usar email de teste do Mercado Pago
        // para evitar erro "Uma das partes é de teste"
        $payerEmail = $data['aluno_email'] ?? '';
        if (!$this->isProduction) {
            // Email genérico de teste do MP (qualquer email fictício funciona no sandbox)
            $payerEmail = 'test_user_' . ($data['aluno_id'] ?? rand(100000, 999999)) . '@testuser.com';
        }
        
        // Dados do pagador
        $payer = [
            'name' => $data['aluno_nome'] ?? '',
            'email' => $payerEmail,
            'phone' => [
                'number' => $data['aluno_telefone'] ?? ''
            ]
        ];
        
        // Metadados para identificar o pagamento (guardar email real aqui)
        $metadata = [
            'tenant_id' => $data['tenant_id'] ?? null,
            'matricula_id' => $data['matricula_id'] ?? null,
            'aluno_id' => $data['aluno_id'] ?? null,
            'usuario_id' => $data['usuario_id'] ?? null,
            'tipo' => 'matricula',
            'email_real' => $data['aluno_email'] ?? '' // Guardar email real nos metadados
        ];
        
        // Montar preferência
        $preference = [
            'items' => $items,
            'payer' => $payer,
            'metadata' => $metadata,
            'external_reference' => "MAT-{$data['matricula_id']}-" . time(),
            'notification_url' => $this->notificationUrl,
            'back_urls' => [
                'success' => $this->successUrl,
                'failure' => $this->failureUrl,
                'pending' => $this->pendingUrl
            ],
            'auto_return' => 'approved',
            'payment_methods' => [
                'excluded_payment_types' => [],
                'installments' => (int) ($data['max_parcelas'] ?? 12)
            ],
            'statement_descriptor' => substr($data['academia_nome'] ?? 'ACADEMIA', 0, 22)
        ];
        
        // Fazer requisição
        $response = $this->fazerRequisicao('POST', '/checkout/preferences', $preference);
        
        // IMPORTANTE: Em ambiente SANDBOX, usar sandbox_init_point
        // Em ambiente PRODUÇÃO, usar init_point
        $paymentUrl = $this->isProduction 
            ? $response['init_point'] 
            : ($response['sandbox_init_point'] ?? $response['init_point']);
        
        error_log("[MercadoPagoService] ========== RESPOSTA PREFERÊNCIA ==========");
        error_log("[MercadoPagoService] 🌍 Ambiente: " . ($this->isProduction ? 'PRODUÇÃO' : 'SANDBOX'));
        error_log("[MercadoPagoService] 🔗 init_point: " . ($response['init_point'] ?? 'N/A'));
        error_log("[MercadoPagoService] 🔗 sandbox_init_point: " . ($response['sandbox_init_point'] ?? 'N/A'));
        error_log("[MercadoPagoService] ✅ URL RETORNADA: " . $paymentUrl);
        error_log("[MercadoPagoService] ✅ URL contém 'sandbox'? " . (str_contains($paymentUrl, 'sandbox') ? 'SIM ✓' : 'NÃO ✗'));
        error_log("[MercadoPagoService] ===========================================");
        
        return [
            'id' => $response['id'],
            'init_point' => $paymentUrl, // Link correto baseado no ambiente
            'sandbox_init_point' => $response['sandbox_init_point'] ?? null,
            'external_reference' => $preference['external_reference']
        ];
    }
    
    /**
     * Processar notificação de webhook do Mercado Pago
     * 
     * @param array $notification Dados da notificação
     * @return array Status do pagamento
     */
    public function processarNotificacao(array $notification): array
    {
        $this->validarCredenciais();
        
        // Tipos de notificação:
        // - payment: Pagamento criado/atualizado
        // - plan: Plano de assinatura
        // - subscription: Assinatura criada/atualizada
        
        $type = $notification['type'] ?? null;
        $dataId = $notification['data']['id'] ?? null;
        
        if (!$type || !$dataId) {
            throw new Exception('Notificação inválida');
        }
        
        // Buscar informações do pagamento
        switch ($type) {
            case 'payment':
                return $this->buscarPagamento($dataId);
                
            case 'subscription':
                return $this->buscarAssinatura($dataId);
                
            default:
                throw new Exception("Tipo de notificação não suportado: {$type}");
        }
    }
    
    /**
     * Buscar informações de um pagamento
     * 
     * @param string $paymentId ID do pagamento no Mercado Pago
     * @return array Informações do pagamento
     */
    public function buscarPagamento(string $paymentId): array
    {
        $this->validarCredenciais();
        
        $response = $this->fazerRequisicao('GET', "/v1/payments/{$paymentId}");
        
        return [
            'id' => $response['id'],
            'status' => $response['status'], // approved, pending, rejected, cancelled, refunded
            'status_detail' => $response['status_detail'],
            'external_reference' => $response['external_reference'] ?? null,
            'metadata' => $response['metadata'] ?? [],
            'transaction_amount' => $response['transaction_amount'],
            'date_approved' => $response['date_approved'] ?? null,
            'date_created' => $response['date_created'],
            'payer' => [
                'email' => $response['payer']['email'] ?? null,
                'identification' => $response['payer']['identification'] ?? null
            ],
            'payment_method_id' => $response['payment_method_id'],
            'payment_type_id' => $response['payment_type_id'],
            'installments' => $response['installments'] ?? 1,
            'raw' => $response
        ];
    }
    
    /**
     * Criar plano de assinatura recorrente
     * 
     * @param array $data Dados do plano
     * @return array Plano criado
     */
    public function criarPlanoAssinatura(array $data): array
    {
        $this->validarCredenciais();
        
        $plan = [
            'reason' => $data['nome'] ?? 'Plano Mensal',
            'auto_recurring' => [
                'frequency' => 1,
                'frequency_type' => $data['frequencia'] ?? 'months', // months, days
                'transaction_amount' => (float) $data['valor'],
                'currency_id' => 'BRL',
                'free_trial' => [
                    'frequency' => $data['trial_dias'] ?? 0,
                    'frequency_type' => 'days'
                ]
            ],
            'back_url' => $this->successUrl,
            'external_reference' => $data['plano_id'] ?? null
        ];
        
        return $this->fazerRequisicao('POST', '/preapproval_plan', $plan);
    }
    
    /**
     * Fazer requisição HTTP para API do Mercado Pago
     * 
     * @param string $method GET, POST, PUT
     * @param string $endpoint Endpoint da API
     * @param array $data Dados do body (opcional)
     * @return array Resposta decodificada
     */
    private function fazerRequisicao(string $method, string $endpoint, array $data = null): array
    {
        $url = $this->baseUrl . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'X-Idempotency-Key: ' . uniqid('mp_', true)
        ];
        
        error_log("[MercadoPagoService] 🔄 Requisição: {$method} {$url}");
        error_log("[MercadoPagoService] Token (primeiros 20 chars): " . substr($this->accessToken, 0, 20) . "...");
        if ($data) {
            error_log("[MercadoPagoService] Body: " . json_encode($data, JSON_UNESCAPED_UNICODE));
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        error_log("[MercadoPagoService] 📥 HTTP Code: {$httpCode}");
        error_log("[MercadoPagoService] 📥 Response: " . $response);
        
        if ($error) {
            error_log("[MercadoPagoService] ❌ CURL Error: {$error}");
            throw new Exception("Erro na requisição: {$error}");
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMessage = $responseData['message'] ?? $responseData['error'] ?? 'Erro desconhecido';
            error_log("[MercadoPagoService] ❌ API Error: {$errorMessage}");
            throw new Exception("Erro Mercado Pago [{$httpCode}]: {$errorMessage}");
        }
        
        error_log("[MercadoPagoService] ✅ Sucesso! Preference ID: " . ($responseData['id'] ?? 'N/A'));
        
        return $responseData;
    }
    
    /**
     * Validar se as credenciais estão configuradas
     */
    private function validarCredenciais(): void
    {
        if (empty($this->accessToken)) {
            throw new Exception('Access Token do Mercado Pago não configurado');
        }
    }
    
    /**
     * Obter Public Key (para frontend)
     * 
     * @return string Public Key
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }
    
    /**
     * Verificar se está em modo produção
     * 
     * @return bool
     */
    public function isProduction(): bool
    {
        return $this->isProduction;
    }
    
    /**
     * Mapear status do Mercado Pago para status interno
     * 
     * @param string $mpStatus Status do MP
     * @return string Status interno
     */
    public static function mapearStatus(string $mpStatus): string
    {
        $statusMap = [
            'approved' => 'pago',
            'pending' => 'pendente',
            'in_process' => 'processando',
            'rejected' => 'recusado',
            'cancelled' => 'cancelado',
            'refunded' => 'reembolsado',
            'charged_back' => 'estornado'
        ];
        
        return $statusMap[$mpStatus] ?? 'pendente';
    }
    
    /**
     * Criar preferência de assinatura recorrente (para ciclos mensais)
     * Mostra ao usuário que será cobrado automaticamente todo mês
     * 
     * NOTA: A API de preapproval do Mercado Pago requer configuração específica
     * e pode não funcionar em todos os ambientes. Como fallback, usamos
     * checkout de preferência com informação de recorrência.
     * 
     * @param array $data Dados da assinatura
     * @return array Resposta com init_point para assinatura
     */
    public function criarPreferenciaAssinatura(array $data): array
    {
        $this->validarCredenciais();
        
        // Tentar criar assinatura via preapproval primeiro
        try {
            return $this->tentarCriarPreapproval($data);
        } catch (Exception $e) {
            error_log("[MercadoPagoService] ⚠️ Preapproval falhou: " . $e->getMessage());
            error_log("[MercadoPagoService] 🔄 Usando checkout de preferência como fallback");
            
            // Fallback: usar checkout de preferência com informação de recorrência
            $data['descricao'] = "Assinatura Mensal - {$data['plano_nome']} (será cobrado automaticamente todo mês)";
            return $this->criarPreferenciaPagamento($data);
        }
    }
    
    /**
     * Tentar criar assinatura via API de preapproval
     */
    private function tentarCriarPreapproval(array $data): array
    {
        // Em ambiente SANDBOX, usar email de teste
        $payerEmail = $data['aluno_email'] ?? '';
        if (!$this->isProduction) {
            $payerEmail = 'test_user_' . ($data['aluno_id'] ?? rand(100000, 999999)) . '@testuser.com';
        }
        
        // Montar payload de assinatura (preapproval)
        $payload = [
            'reason' => $data['plano_nome'] . ' - ' . ($data['academia_nome'] ?? 'Academia'),
            'external_reference' => "MAT-{$data['matricula_id']}-" . time(),
            'payer_email' => $payerEmail,
            'auto_recurring' => [
                'frequency' => 1,
                'frequency_type' => 'months',
                'transaction_amount' => (float) $data['valor'],
                'currency_id' => 'BRL'
            ],
            'back_url' => $this->successUrl,
            'status' => 'pending'
        ];
        
        error_log("[MercadoPagoService] 🔄 Tentando criar PREAPPROVAL (assinatura)");
        error_log("[MercadoPagoService] Payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE));
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/preapproval',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
                'X-Idempotency-Key: ' . uniqid('sub_', true)
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        error_log("[MercadoPagoService] 📥 HTTP Code: {$httpCode}");
        error_log("[MercadoPagoService] 📥 Response: " . $response);
        
        if ($error) {
            throw new Exception("Erro na requisição: {$error}");
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMessage = $responseData['message'] ?? $responseData['error'] ?? 'Erro desconhecido';
            throw new Exception("Erro Mercado Pago [{$httpCode}]: {$errorMessage}");
        }
        
        // Em sandbox, usar sandbox_init_point
        $paymentUrl = $this->isProduction 
            ? $responseData['init_point'] 
            : ($responseData['sandbox_init_point'] ?? $responseData['init_point']);
        
        error_log("[MercadoPagoService] ✅ Preapproval criado! ID: " . ($responseData['id'] ?? 'N/A'));
        error_log("[MercadoPagoService] 🔗 URL: " . $paymentUrl);
        
        return [
            'id' => $responseData['id'],
            'init_point' => $paymentUrl,
            'sandbox_init_point' => $responseData['sandbox_init_point'] ?? null,
            'external_reference' => $payload['external_reference'],
            'tipo' => 'assinatura'
        ];
    }
    
    /**
     * Criar assinatura recorrente (preapproval)
     * 
     * @param array $dados Dados da assinatura
     * @return array Resposta do MP
     */
    public function criarAssinatura(array $dados): array
    {
        $this->validarCredenciais();
        
        $payload = [
            'reason' => $dados['reason'],
            'external_reference' => $dados['external_reference'] ?? null,
            'payer_email' => $dados['payer_email'],
            'card_token_id' => $dados['card_token_id'],
            'auto_recurring' => [
                'frequency' => $dados['auto_recurring']['frequency'] ?? 1,
                'frequency_type' => $dados['auto_recurring']['frequency_type'] ?? 'months',
                'transaction_amount' => $dados['auto_recurring']['transaction_amount'],
                'currency_id' => $dados['auto_recurring']['currency_id'] ?? 'BRL'
            ],
            'back_url' => $dados['back_url'] ?? $this->successUrl,
            'status' => $dados['status'] ?? 'pending'
        ];
        
        error_log("[MercadoPagoService] Criando assinatura: " . json_encode($payload));
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/preapproval',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
                'X-Idempotency-Key: ' . uniqid('sub_', true)
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $responseData = json_decode($response, true);
        
        error_log("[MercadoPagoService] Resposta assinatura [{$httpCode}]: " . $response);
        
        if ($httpCode >= 400) {
            return [
                'error' => true,
                'message' => $responseData['message'] ?? 'Erro ao criar assinatura',
                'status' => $httpCode
            ];
        }
        
        return $responseData;
    }
    
    /**
     * Cancelar assinatura
     * 
     * @param string $preapprovalId ID da assinatura no MP
     * @return array Resposta do MP
     */
    public function cancelarAssinatura(string $preapprovalId): array
    {
        $this->validarCredenciais();
        
        $payload = ['status' => 'cancelled'];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/preapproval/' . $preapprovalId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $responseData = json_decode($response, true);
        
        error_log("[MercadoPagoService] Cancelar assinatura [{$httpCode}]: " . $response);
        
        return $responseData ?? [];
    }
    
    /**
     * Buscar detalhes de uma assinatura
     * 
     * @param string $preapprovalId ID da assinatura no MP
     * @return array Dados da assinatura formatados para o webhook
     */
    public function buscarAssinatura(string $preapprovalId): array
    {
        $this->validarCredenciais();
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/preapproval/' . $preapprovalId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($response, true) ?? [];
        
        error_log("[MercadoPagoService] Assinatura buscada: " . json_encode($data));
        
        // Mapear status da assinatura para status de pagamento
        // Status de assinatura: pending, authorized, paused, cancelled
        $statusMap = [
            'authorized' => 'approved',
            'pending' => 'pending',
            'paused' => 'pending',
            'cancelled' => 'cancelled'
        ];
        
        // Retornar formato compatível com buscarPagamento()
        return [
            'id' => $data['id'] ?? $preapprovalId,
            'type' => 'subscription',
            'status' => $statusMap[$data['status'] ?? 'pending'] ?? 'pending',
            'status_detail' => $data['status'] ?? 'pending',
            'external_reference' => $data['external_reference'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'transaction_amount' => $data['auto_recurring']['transaction_amount'] ?? 0,
            'date_approved' => $data['date_created'] ?? null,
            'date_created' => $data['date_created'] ?? null,
            'payer' => [
                'email' => $data['payer_email'] ?? null,
                'id' => $data['payer_id'] ?? null
            ],
            'payment_method_id' => 'credit_card', // Assinaturas são sempre cartão
            'payment_type_id' => 'credit_card',
            'installments' => 1,
            'preapproval_id' => $data['id'] ?? $preapprovalId,
            'raw' => $data
        ];
    }
    
    /**
     * Pausar assinatura
     * 
     * @param string $preapprovalId ID da assinatura no MP
     * @return array Resposta do MP
     */
    public function pausarAssinatura(string $preapprovalId): array
    {
        $this->validarCredenciais();
        
        $payload = ['status' => 'paused'];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/preapproval/' . $preapprovalId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true) ?? [];
    }
    
    /**
     * Reativar assinatura pausada
     * 
     * @param string $preapprovalId ID da assinatura no MP
     * @return array Resposta do MP
     */
    public function reativarAssinatura(string $preapprovalId): array
    {
        $this->validarCredenciais();
        
        $payload = ['status' => 'authorized'];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/preapproval/' . $preapprovalId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true) ?? [];
    }
}
