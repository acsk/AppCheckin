<?php

namespace App\Services;

use App\Models\Parametro;
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
        
        // Em ambiente local, usar fake API do Mercado Pago
        $fakeApiUrl = $_ENV['MP_FAKE_API_URL'] ?? $_SERVER['MP_FAKE_API_URL'] ?? null;
        $this->baseUrl = $fakeApiUrl ?: 'https://api.mercadopago.com';
        
        if ($fakeApiUrl) {
            error_log("[MercadoPagoService] 🧪 Usando FAKE API: {$fakeApiUrl}");
        }
        
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
     * Obtém os métodos de pagamento excluídos baseado nos parâmetros do sistema
     * 
     * Lê os parâmetros:
     * - habilitar_pix (boolean)
     * - habilitar_cartao_credito (boolean)
     * - habilitar_cartao_debito (boolean)
     * - habilitar_boleto (boolean)
     * 
     * @param int|null $tenantId ID do tenant
     * @return array Lista de tipos de pagamento a excluir
     */
    private function getExcludedPaymentTypes(?int $tenantId = null): array
    {
        $excluded = [];
        
        // Se não tem tenant, permitir todos
        $effectiveTenantId = $tenantId ?? $this->tenantId;
        if (!$effectiveTenantId) {
            error_log("[MercadoPagoService] ⚠️ Sem tenant_id - permitindo todos os métodos de pagamento");
            return [];
        }
        
        try {
            // Inicializar DB se necessário
            if (!$this->db) {
                $this->db = require __DIR__ . '/../../config/database.php';
            }
            
            $parametro = new Parametro($this->db);
            
            // Verificar quais métodos estão DESABILITADOS
            $habilitarPix = $parametro->isEnabled($effectiveTenantId, 'habilitar_pix');
            $habilitarCartaoCredito = $parametro->isEnabled($effectiveTenantId, 'habilitar_cartao_credito');
            $habilitarCartaoDebito = $parametro->isEnabled($effectiveTenantId, 'habilitar_cartao_debito');
            $habilitarBoleto = $parametro->isEnabled($effectiveTenantId, 'habilitar_boleto');
            
            error_log("[MercadoPagoService] 💳 Parâmetros de pagamento tenant #{$effectiveTenantId}:");
            error_log("[MercadoPagoService] - PIX: " . ($habilitarPix ? '✅' : '❌'));
            error_log("[MercadoPagoService] - Cartão Crédito: " . ($habilitarCartaoCredito ? '✅' : '❌'));
            error_log("[MercadoPagoService] - Cartão Débito: " . ($habilitarCartaoDebito ? '✅' : '❌'));
            error_log("[MercadoPagoService] - Boleto: " . ($habilitarBoleto ? '✅' : '❌'));
            
            // PIX = bank_transfer no Mercado Pago
            if (!$habilitarPix) {
                $excluded[] = ['id' => 'bank_transfer'];
            }
            
            // Cartão de crédito
            if (!$habilitarCartaoCredito) {
                $excluded[] = ['id' => 'credit_card'];
            }
            
            // Cartão de débito
            if (!$habilitarCartaoDebito) {
                $excluded[] = ['id' => 'debit_card'];
            }
            
            // Boleto
            if (!$habilitarBoleto) {
                $excluded[] = ['id' => 'ticket'];
            }
            
            // Sempre excluir métodos que não usamos
            $excluded[] = ['id' => 'atm'];              // Caixa eletrônico
            $excluded[] = ['id' => 'prepaid_card'];     // Cartão pré-pago
            $excluded[] = ['id' => 'digital_currency']; // Moeda digital
            $excluded[] = ['id' => 'digital_wallet'];   // Carteira digital (exceto MP)
            
            error_log("[MercadoPagoService] 🚫 Métodos excluídos: " . json_encode(array_column($excluded, 'id')));
            
        } catch (\Exception $e) {
            error_log("[MercadoPagoService] ⚠️ Erro ao ler parâmetros: " . $e->getMessage());
            // Em caso de erro, permitir PIX e cartão de crédito (default seguro)
            $excluded = [
                ['id' => 'ticket'],
                ['id' => 'debit_card'],
                ['id' => 'atm'],
                ['id' => 'prepaid_card'],
                ['id' => 'digital_currency'],
                ['id' => 'digital_wallet']
            ];
        }
        
        return $excluded;
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
            'id' => $data['item_id'] ?? ($data['matricula_id'] ?? null),
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
        $cpf = isset($data['aluno_cpf']) ? preg_replace('/[^0-9]/', '', $data['aluno_cpf']) : '';
        if (strlen($cpf) === 11) {
            $payer['identification'] = [
                'type' => 'CPF',
                'number' => $cpf
            ];
        }
        
        // Metadados para identificar o pagamento (guardar email real aqui)
        $metadata = [
            'tenant_id' => $data['tenant_id'] ?? null,
            'matricula_id' => $data['matricula_id'] ?? null,
            'aluno_id' => $data['aluno_id'] ?? null,
            'usuario_id' => $data['usuario_id'] ?? null,
            'tipo' => 'matricula',
            'email_real' => $data['aluno_email'] ?? '' // Guardar email real nos metadados
        ];

        if (!empty($data['metadata_extra']) && is_array($data['metadata_extra'])) {
            $metadata = array_merge($metadata, $data['metadata_extra']);
        }
        
        // Montar preferência
        $preference = [
            'items' => $items,
            'payer' => $payer,
            'metadata' => $metadata,
            'external_reference' => $data['external_reference'] ?? ("MAT-{$data['matricula_id']}-" . time()),
            'notification_url' => $this->notificationUrl,
            'back_urls' => [
                'success' => $this->successUrl,
                'failure' => $this->failureUrl,
                'pending' => $this->pendingUrl
            ],
            'auto_return' => 'approved',
            'payment_methods' => [
                // Filtrar métodos de pagamento baseado nos parâmetros do sistema
                'excluded_payment_types' => $this->getExcludedPaymentTypes($data['tenant_id'] ?? null),
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
     * Criar pagamento PIX e retornar QR Code
     *
     * @param array $data Dados da matrícula e aluno
     * @return array Resposta com dados PIX (qr_code, qr_code_base64, ticket_url)
     */
    public function criarPagamentoPix(array $data): array
    {
        $this->validarCredenciais();

        // Verificar se PIX está habilitado nos parâmetros
        $tenantId = $data['tenant_id'] ?? null;
        if ($tenantId && $this->db) {
            $parametroModel = new Parametro($this->db);
            if (!$parametroModel->isEnabled($tenantId, 'habilitar_pix')) {
                error_log("[MercadoPagoService] criarPagamentoPix: PIX desabilitado para tenant {$tenantId}");
                throw new Exception('Pagamento via PIX não está disponível no momento.');
            }
        } elseif ($tenantId) {
            // Se não tem conexão ainda, carregar para verificar parâmetro
            $db = require __DIR__ . '/../../config/database.php';
            $parametroModel = new Parametro($db);
            if (!$parametroModel->isEnabled($tenantId, 'habilitar_pix')) {
                error_log("[MercadoPagoService] criarPagamentoPix: PIX desabilitado para tenant {$tenantId}");
                throw new Exception('Pagamento via PIX não está disponível no momento.');
            }
        }

        $cpf = isset($data['aluno_cpf']) ? preg_replace('/[^0-9]/', '', $data['aluno_cpf']) : '';
        if (strlen($cpf) !== 11) {
            throw new Exception('CPF válido é obrigatório para pagamento PIX');
        }

        $payerEmail = $data['aluno_email'] ?? '';
        if (!$this->isProduction) {
            $payerEmail = 'test_user_' . ($data['aluno_id'] ?? rand(100000, 999999)) . '@testuser.com';
        }

        $nomeCompleto = trim($data['aluno_nome'] ?? '');
        $partesNome = $nomeCompleto !== '' ? explode(' ', $nomeCompleto, 2) : [];
        $firstName = $partesNome[0] ?? 'Test';
        $lastName = $partesNome[1] ?? 'User';

        $metadata = [
            'tenant_id' => $data['tenant_id'] ?? null,
            'matricula_id' => $data['matricula_id'] ?? null,
            'aluno_id' => $data['aluno_id'] ?? null,
            'usuario_id' => $data['usuario_id'] ?? null,
            'tipo' => 'matricula_pix',
            'email_real' => $data['aluno_email'] ?? ''
        ];

        $externalReference = "MAT-{$data['matricula_id']}-" . time();

        $payment = [
            'transaction_amount' => (float) $data['valor'],
            'description' => $data['descricao'] ?? $data['plano_nome'] ?? 'Pagamento PIX',
            'payment_method_id' => 'pix',
            'payer' => [
                'email' => $payerEmail,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'identification' => [
                    'type' => 'CPF',
                    'number' => $cpf
                ]
            ],
            'external_reference' => $externalReference,
            'notification_url' => $this->notificationUrl,
            'metadata' => $metadata
        ];

        $response = $this->fazerRequisicao('POST', '/v1/payments', $payment);

        $tx = $response['point_of_interaction']['transaction_data'] ?? $response['transaction_data'] ?? null;

        return [
            'id' => $response['id'] ?? null,
            'status' => $response['status'] ?? null,
            'status_detail' => $response['status_detail'] ?? null,
            'external_reference' => $response['external_reference'] ?? $externalReference,
            'date_of_expiration' => $response['date_of_expiration'] ?? null,
            'qr_code' => $tx['qr_code'] ?? null,
            'qr_code_base64' => $tx['qr_code_base64'] ?? null,
            'ticket_url' => $tx['ticket_url'] ?? null,
            'raw' => $response
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
    public function buscarPagamento($paymentId): array
    {
        $paymentId = (string) $paymentId;
        $this->validarCredenciais();

        try {
            $response = $this->fazerRequisicao('GET', "/v1/payments/{$paymentId}");

            return $this->normalizarRespostaPagamento($response, $paymentId);
        } catch (\Exception $e) {
            if (!str_contains($e->getMessage(), '[404]')) {
                throw $e;
            }

            // Fallback local para simulador (evita consultar API real para IDs fake)
            $simulador = $this->buscarPagamentoNoSimuladorLocal($paymentId);
            if ($simulador !== null) {
                error_log("[MercadoPagoService] ✅ Pagamento {$paymentId} encontrado no simulador local");
                return $this->normalizarRespostaPagamento($simulador, $paymentId);
            }

            // Fallback para eventos de assinatura recorrente (subscription_authorized_payment)
            error_log("[MercadoPagoService] ⚠️ /v1/payments/{$paymentId} retornou 404, tentando /authorized_payments/{id}");

            $authorized = $this->fazerRequisicao('GET', "/authorized_payments/{$paymentId}");

            $externalReference = $authorized['external_reference'] ?? null;
            $metadata = is_array($authorized['metadata'] ?? null) ? $authorized['metadata'] : [];

            // Quando vier apenas preapproval_id, buscar assinatura para recuperar external_reference
            $preapprovalId = $authorized['preapproval_id'] ?? null;
            if (!$externalReference && !empty($preapprovalId)) {
                try {
                    $assinatura = $this->buscarAssinatura((string)$preapprovalId);
                    $externalReference = $assinatura['external_reference'] ?? $externalReference;

                    if (is_array($assinatura['metadata'] ?? null)) {
                        $metadata = array_merge($metadata, $assinatura['metadata']);
                    }
                } catch (\Exception $fallbackException) {
                    error_log("[MercadoPagoService] ⚠️ Não foi possível recuperar external_reference pelo preapproval_id {$preapprovalId}: " . $fallbackException->getMessage());
                }
            }

            $statusRaw = strtolower((string)($authorized['status'] ?? 'authorized'));
            $statusNormalized = $statusRaw === 'authorized' ? 'approved' : $statusRaw;

            return [
                'id' => (string)($authorized['id'] ?? $paymentId),
                'status' => $statusNormalized,
                'status_detail' => $authorized['status_detail'] ?? ($authorized['reason'] ?? null),
                'external_reference' => $externalReference,
                'preference_id' => $authorized['preference_id'] ?? null,
                'metadata' => $metadata,
                'transaction_amount' => (float)($authorized['transaction_amount'] ?? ($authorized['amount'] ?? 0)),
                'date_approved' => $authorized['date_approved'] ?? ($authorized['last_modified'] ?? null),
                'date_created' => $authorized['date_created'] ?? date('c'),
                'payer' => [
                    'email' => $authorized['payer']['email'] ?? null,
                    'identification' => $authorized['payer']['identification'] ?? null
                ],
                'payment_method_id' => $authorized['payment_method_id'] ?? ($authorized['payment_method']['id'] ?? 'account_money'),
                'payment_type_id' => $authorized['payment_type_id'] ?? ($authorized['payment_type'] ?? 'account_money'),
                'installments' => (int)($authorized['installments'] ?? 1),
                'raw' => $authorized
            ];
        }
    }

    /**
     * Normalizar payload de pagamento para formato interno
     */
    private function normalizarRespostaPagamento(array $response, string $paymentId): array
    {
        return [
            'id' => $response['id'] ?? $paymentId,
            'status' => $response['status'] ?? 'pending',
            'status_detail' => $response['status_detail'] ?? null,
            'external_reference' => $response['external_reference'] ?? null,
            'preference_id' => $response['preference_id'] ?? null,
            'metadata' => is_array($response['metadata'] ?? null) ? $response['metadata'] : [],
            'transaction_amount' => (float)($response['transaction_amount'] ?? $response['amount'] ?? 0),
            'date_approved' => $response['date_approved'] ?? null,
            'date_created' => $response['date_created'] ?? date('c'),
            'payer' => [
                'email' => $response['payer']['email'] ?? null,
                'identification' => $response['payer']['identification'] ?? null
            ],
            'payment_method_id' => $response['payment_method_id'] ?? null,
            'payment_type_id' => $response['payment_type_id'] ?? null,
            'installments' => (int)($response['installments'] ?? 1),
            'raw' => $response
        ];
    }

    /**
     * Em ambiente local/dev, tenta buscar pagamento no simulador antes de falhar o webhook.
     */
    private function buscarPagamentoNoSimuladorLocal(string $paymentId): ?array
    {
        if (!$this->isAppEnvLocal()) {
            return null;
        }

        error_log("[MercadoPagoService] 🧪 Tentando fallback local para payment_id={$paymentId}");

        foreach ($this->getSimuladorBaseUrls() as $baseUrl) {
            $baseUrl = rtrim($baseUrl, '/');

            foreach (["/v1/payments/{$paymentId}", "/api/payments/{$paymentId}"] as $path) {
                $url = $baseUrl . $path;
                $result = $this->fazerRequisicaoDireta($url);

                if ($result !== null) {
                    error_log("[MercadoPagoService] 🧪 Fallback simulador OK: {$url}");
                    return $result;
                }
            }
        }

        error_log("[MercadoPagoService] ⚠️ Fallback local sem sucesso para payment_id={$paymentId}");

        return null;
    }

    /**
     * Detecta APP_ENV local/development
     */
    private function isAppEnvLocal(): bool
    {
        $appEnv = strtolower((string)($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production'));
        return in_array($appEnv, ['local', 'development', 'dev', 'test'], true);
    }

    /**
     * URLs candidatas para simulador do MP em dev local
     */
    private function getSimuladorBaseUrls(): array
    {
        $urls = [];

        $configured = $_ENV['MP_FAKE_API_URL'] ?? $_SERVER['MP_FAKE_API_URL'] ?? null;
        if (!empty($configured)) {
            $urls[] = (string)$configured;
        }

        $urls[] = 'http://host.docker.internal:8085';
        $urls[] = 'http://localhost:8085';

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * Requisição simples para fallback do simulador local (timeout curto)
     */
    private function fazerRequisicaoDireta(string $url): ?array
    {
        error_log("[MercadoPagoService] 🔎 Fallback GET: {$url}");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ]);

        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log("[MercadoPagoService] 🔎 Fallback HTTP {$httpCode} para {$url}");
        if ($curlError !== '') {
            error_log("[MercadoPagoService] ⚠️ Fallback cURL erro em {$url}: {$curlError}");
        }
        if (is_string($body)) {
            error_log("[MercadoPagoService] 🔎 Fallback resposta bruta: {$body}");
        }

        if ($body === false || $httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        $decoded = json_decode((string)$body, true);
        return is_array($decoded) ? $decoded : null;
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
     * 
     * IMPORTANTE: Assinaturas de planos/pacotes SEMPRE usam preapproval (sem fallback)
     * PIX e pagamentos avulsos usam preference ou criarPagamentoPix
     * 
     * @param array $data Dados da assinatura
     * @param int $duracaoMeses Duração do ciclo em meses (1=mensal, 2=bimestral, etc)
     * @return array Resposta com init_point para assinatura (sempre preapproval)
     * @throws Exception Se preapproval falhar
     */
    public function criarPreferenciaAssinatura(array $data, int $duracaoMeses = 1): array
    {
        $this->validarCredenciais();
        
        // Assinaturas recorrentes exigem cartão de crédito
        $tenantId = $data['tenant_id'] ?? null;
        if ($tenantId) {
            $db = $this->db ?? require __DIR__ . '/../../config/database.php';
            $parametroModel = new Parametro($db);
            if (!$parametroModel->isEnabled($tenantId, 'habilitar_cartao_credito')) {
                error_log("[MercadoPagoService] criarPreferenciaAssinatura: Cartão de crédito desabilitado para tenant {$tenantId}");
                throw new Exception('Assinaturas recorrentes não estão disponíveis no momento (requer cartão de crédito habilitado).');
            }
        }
        
        // Criar assinatura via preapproval (sem fallback para preservar o comportamento correto)
        return $this->tentarCriarPreapproval($data, $duracaoMeses);
    }
    
    /**
     * Tentar criar assinatura via API de preapproval_plan (SEMPRE usado para planos/pacotes)
     * API mais estável que /preapproval para assinaturas do Mercado Pago
     * 
     * @param array $data Dados da assinatura
     * @param int $duracaoMeses Frequência em meses (1=mensal, 2=bimestral)
     * @return array Resposta com id e init_point da preapproval
     * @throws Exception Se a criação falhar
     */
    private function tentarCriarPreapproval(array $data, int $duracaoMeses = 1): array
    {
        // Em ambiente SANDBOX, usar email de teste
        $payerEmail = $data['aluno_email'] ?? '';
        if (!$this->isProduction) {
            $payerEmail = 'test_user_' . ($data['aluno_id'] ?? rand(100000, 999999)) . '@testuser.com';
        }
        
        // Preparar external_reference baseado no tipo de pagamento
        $externalRef = $data['external_reference'] ?? '';
        if (empty($externalRef)) {
            // Se não tiver, criar baseado no tipo
            if (!empty($data['metadata_extra']['pacote_contrato_id'])) {
                $externalRef = 'PAC-' . $data['metadata_extra']['pacote_contrato_id'] . '-' . time();
            } else {
                $externalRef = 'MAT-' . ($data['matricula_id'] ?? 'ASSINATURA') . '-' . time();
            }
        }
        
        // Preparar metadados para serem enviados ao MP
        $metadata = [
            'tenant_id' => $data['tenant_id'] ?? null,
            'matricula_id' => $data['matricula_id'] ?? null,
            'aluno_id' => $data['aluno_id'] ?? null,
            'usuario_id' => $data['usuario_id'] ?? null,
            'tipo' => 'matricula'
        ];
        if (!empty($data['metadata_extra']) && is_array($data['metadata_extra'])) {
            $metadata = array_merge($metadata, $data['metadata_extra']);
        }
        
        // Montar payload para /preapproval_plan (API mais estável)
        // Próximo passo: usuário autoriza e faz pagamento no checkout
        $planPayload = [
            'reason' => $data['plano_nome'] . ' - ' . ($data['academia_nome'] ?? 'Academia'),
            'auto_recurring' => [
                'frequency' => $duracaoMeses,
                'frequency_type' => 'months',
                'transaction_amount' => (float) $data['valor'],
                'currency_id' => 'BRL'
            ],
            'back_url' => $this->successUrl,
            'external_reference' => $externalRef,
            'metadata' => $metadata
        ];
        
        error_log("[MercadoPagoService] 🔄 Criando PREAPPROVAL_PLAN (assinatura recorrente)");
        error_log("[MercadoPagoService] 📦 Tipo: " . (strpos($externalRef, 'PAC-') === 0 ? 'PACOTE' : 'MATRICULA'));
        error_log("[MercadoPagoService] 🏢 Tenant: " . ($metadata['tenant_id'] ?? 'N/A'));
        error_log("[MercadoPagoService] Email: {$payerEmail}");
        error_log("[MercadoPagoService] Valor: " . $data['valor'] . " BRL");
        error_log("[MercadoPagoService] Frequência: {$duracaoMeses} mês(es)");
        error_log("[MercadoPagoService] External Reference: {$externalRef}");
        error_log("[MercadoPagoService] Payload: " . json_encode($planPayload, JSON_UNESCAPED_UNICODE));
        
        // Passo 1: Criar plano de assinatura
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/preapproval_plan',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($planPayload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
                'X-Idempotency-Key: ' . uniqid('plan_', true)
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
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
        
        $planData = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMessage = $planData['message'] ?? $planData['error'] ?? 'Erro desconhecido';
            error_log("[MercadoPagoService] ❌ Erro Mercado Pago [{$httpCode}]: {$errorMessage}");
            throw new Exception("Erro Mercado Pago [{$httpCode}]: {$errorMessage}");
        }
        
        $planId = $planData['id'] ?? null;
        if (!$planId) {
            throw new Exception("Plano criado mas sem ID retornado");
        }
        
        error_log("[MercadoPagoService] ✅ Plano criado: {$planId}");
        
        // Passo 2: Criar preapproval vinculada ao plano
        $preapprovalPayload = [
            'reason' => $data['plano_nome'] . ' - ' . ($data['academia_nome'] ?? 'Academia'),
            'external_reference' => $externalRef,
            'payer_email' => $payerEmail,
            'plan_id' => $planId,
            'metadata' => $metadata,
            'auto_recurring' => [
                'frequency' => $duracaoMeses,
                'frequency_type' => 'months',
                'transaction_amount' => (float) $data['valor'],
                'currency_id' => 'BRL'
            ],
            'back_url' => $this->successUrl,
            'status' => 'pending'
        ];
        
        error_log("[MercadoPagoService] 🔄 Criando PREAPPROVAL vinculada ao plano {$planId}");
        error_log("[MercadoPagoService] Payload: " . json_encode($preapprovalPayload, JSON_UNESCAPED_UNICODE));
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/preapproval',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($preapprovalPayload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
                'X-Idempotency-Key: ' . uniqid('sub_', true)
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
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
            error_log("[MercadoPagoService] ❌ Erro Mercado Pago [{$httpCode}]: {$errorMessage}");
            throw new Exception("Erro Mercado Pago [{$httpCode}]: {$errorMessage}");
        }
        
        // Em sandbox, usar sandbox_init_point
        $paymentUrl = $this->isProduction 
            ? $responseData['init_point'] 
            : ($responseData['sandbox_init_point'] ?? $responseData['init_point']);
        
        if (empty($paymentUrl)) {
            error_log("[MercadoPagoService] ⚠️ Response Data: " . json_encode($responseData));
            throw new Exception("Nenhuma URL de pagamento retornada pelo Mercado Pago");
        }
        
        // Validar que é realmente preapproval (URL deve conter subscription)
        if (!str_contains($paymentUrl, 'subscription')) {
            error_log("[MercadoPagoService] ⚠️ URL recebida não é preapproval: " . $paymentUrl);
            error_log("[MercadoPagoService] 🔧 Response completa: " . json_encode($responseData));
        }
        
        error_log("[MercadoPagoService] ✅ Preapproval criado com sucesso!");
        error_log("[MercadoPagoService] 📍 ID: " . ($responseData['id'] ?? 'N/A'));
        error_log("[MercadoPagoService] 📋 Plan ID: " . $planId);
        error_log("[MercadoPagoService] 🔗 URL: " . $paymentUrl);
        error_log("[MercadoPagoService] 🌍 Ambiente: " . ($this->isProduction ? 'PRODUÇÃO' : 'SANDBOX'));
        
        return [
            'id' => $responseData['id'],
            'init_point' => $paymentUrl,
            'sandbox_init_point' => $responseData['sandbox_init_point'] ?? null,
            'external_reference' => $preapprovalPayload['external_reference'],
            'tipo' => 'assinatura',
            'preapproval_id' => $responseData['id'],
            'plan_id' => $planId
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
            'next_payment_date' => $data['next_payment_date'] ?? null,
            'auto_recurring' => $data['auto_recurring'] ?? [],
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
     * Buscar pagamentos por external_reference na API do Mercado Pago
     * 
     * @param string $externalReference External reference (ex: MAT-158-1771524282)
     * @return array Lista de pagamentos encontrados
     */
    public function buscarPagamentosPorExternalReference(string $externalReference): array
    {
        $this->validarCredenciais();

        $query = http_build_query([
            'external_reference' => $externalReference,
            'sort' => 'date_created',
            'criteria' => 'desc'
        ]);

        $response = $this->fazerRequisicao('GET', '/v1/payments/search?' . $query);

        $results = $response['results'] ?? [];

        return [
            'total' => (int) ($response['paging']['total'] ?? count($results)),
            'pagamentos' => array_map(function ($p) {
                return [
                    'id' => $p['id'] ?? null,
                    'status' => $p['status'] ?? null,
                    'status_detail' => $p['status_detail'] ?? null,
                    'external_reference' => $p['external_reference'] ?? null,
                    'transaction_amount' => $p['transaction_amount'] ?? null,
                    'currency_id' => $p['currency_id'] ?? 'BRL',
                    'payment_method_id' => $p['payment_method_id'] ?? null,
                    'payment_type_id' => $p['payment_type_id'] ?? null,
                    'installments' => $p['installments'] ?? 1,
                    'date_created' => $p['date_created'] ?? null,
                    'date_approved' => $p['date_approved'] ?? null,
                    'date_last_updated' => $p['date_last_updated'] ?? null,
                    'payer' => [
                        'email' => $p['payer']['email'] ?? null,
                        'id' => $p['payer']['id'] ?? null,
                    ],
                    'metadata' => $p['metadata'] ?? [],
                ];
            }, $results)
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
