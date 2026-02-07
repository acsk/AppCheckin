<?php

namespace App\Services;

use Exception;

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
 * Documentação Mercado Pago: https://www.mercadopago.com.br/developers
 */
class MercadoPagoService
{
    private string $accessToken;
    private string $publicKey;
    private string $baseUrl;
    private bool $isProduction;
    
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
    
    public function __construct()
    {
        // Carregar credenciais das variáveis de ambiente
        // Usar $_ENV pois o Dotenv não popula getenv() por padrão
        $this->isProduction = ($_ENV['MP_ENVIRONMENT'] ?? $_SERVER['MP_ENVIRONMENT'] ?? 'sandbox') === 'production';
        
        if ($this->isProduction) {
            $this->accessToken = $_ENV['MP_ACCESS_TOKEN_PROD'] ?? $_SERVER['MP_ACCESS_TOKEN_PROD'] ?? '';
            $this->publicKey = $_ENV['MP_PUBLIC_KEY_PROD'] ?? $_SERVER['MP_PUBLIC_KEY_PROD'] ?? '';
        } else {
            $this->accessToken = $_ENV['MP_ACCESS_TOKEN_TEST'] ?? $_SERVER['MP_ACCESS_TOKEN_TEST'] ?? '';
            $this->publicKey = $_ENV['MP_PUBLIC_KEY_TEST'] ?? $_SERVER['MP_PUBLIC_KEY_TEST'] ?? '';
        }
        
        // LOG de inicialização para debug
        error_log("[MercadoPagoService] ========================================");
        error_log("[MercadoPagoService] 🚀 INICIALIZANDO MERCADO PAGO");
        error_log("[MercadoPagoService] 🌍 Ambiente: " . ($this->isProduction ? 'PRODUÇÃO' : 'SANDBOX'));
        error_log("[MercadoPagoService] 🔑 Token prefix: " . substr($this->accessToken, 0, 10) . "...");
        error_log("[MercadoPagoService] ✅ Token começa com TEST-? " . (str_starts_with($this->accessToken, 'TEST-') ? 'SIM' : 'NÃO'));
        error_log("[MercadoPagoService] ========================================");
        
        $this->baseUrl = 'https://api.mercadopago.com';
        
        // URLs de callback
        // URL do webhook - usar API diretamente
        $apiUrl = $_ENV['API_URL'] ?? $_SERVER['API_URL'] ?? 'https://api.appcheckin.com.br';
        $this->notificationUrl = $apiUrl . '/api/webhooks/mercadopago';
        
        // URLs de retorno - redirecionar para o mobile app
        $mobileUrl = $_ENV['MOBILE_URL'] ?? $_SERVER['MOBILE_URL'] ?? 'https://mobile.appcheckin.com.br';
        $this->successUrl = $mobileUrl . '/pagamento/sucesso';
        $this->failureUrl = $mobileUrl . '/pagamento/falha';
        $this->pendingUrl = $mobileUrl . '/pagamento/pendente';
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
     * Criar assinatura para um plano
     * 
     * @param array $data Dados da assinatura
     * @return array Assinatura criada com link de pagamento
     */
    public function criarAssinatura(array $data): array
    {
        $this->validarCredenciais();
        
        $subscription = [
            'preapproval_plan_id' => $data['plan_id'],
            'reason' => $data['descricao'] ?? 'Assinatura Mensal',
            'external_reference' => "MAT-{$data['matricula_id']}",
            'payer_email' => $data['aluno_email'],
            'card_token_id' => $data['card_token'] ?? null,
            'auto_recurring' => [
                'frequency' => 1,
                'frequency_type' => 'months',
                'start_date' => $data['data_inicio'] ?? date('Y-m-d\TH:i:s.000P'),
                'end_date' => $data['data_fim'] ?? null,
                'transaction_amount' => (float) $data['valor'],
                'currency_id' => 'BRL'
            ],
            'back_url' => $this->successUrl,
            'status' => 'pending'
        ];
        
        return $this->fazerRequisicao('POST', '/preapproval', $subscription);
    }
    
    /**
     * Buscar informações de uma assinatura
     * 
     * @param string $subscriptionId ID da assinatura
     * @return array Informações da assinatura
     */
    public function buscarAssinatura(string $subscriptionId): array
    {
        $this->validarCredenciais();
        
        $response = $this->fazerRequisicao('GET', "/preapproval/{$subscriptionId}");
        
        return [
            'id' => $response['id'],
            'status' => $response['status'], // pending, authorized, paused, cancelled
            'external_reference' => $response['external_reference'] ?? null,
            'payer_email' => $response['payer_email'] ?? null,
            'next_payment_date' => $response['next_payment_date'] ?? null,
            'date_created' => $response['date_created'],
            'raw' => $response
        ];
    }
    
    /**
     * Cancelar assinatura
     * 
     * @param string $subscriptionId ID da assinatura
     * @return bool Sucesso
     */
    public function cancelarAssinatura(string $subscriptionId): bool
    {
        $this->validarCredenciais();
        
        $response = $this->fazerRequisicao('PUT', "/preapproval/{$subscriptionId}", [
            'status' => 'cancelled'
        ]);
        
        return $response['status'] === 'cancelled';
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
}
