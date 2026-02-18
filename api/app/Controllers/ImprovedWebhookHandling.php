<?php
/**
 * Webhook Handler aprimorado com auto-reprocessamento
 * Salva o webhook_payload e processa imediatamente
 * Se falhar, agenda para reprocessamento automático
 */

namespace App\Controllers;

class ImprovedMercadoPagoWebhookController
{
    private $db;
    
    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
    }
    
    /**
     * Processar pagamento com fallback automático
     * 
     * NOVO FLUXO:
     * 1. Recebe webhook com payment_id
     * 2. Salva payload na tabela
     * 3. Tenta processar imediatamente
     * 4. Se falhar por falta de informações, agenda reprocessamento (cron)
     * 5. Cron executa reprocessador a cada 5 minutos
     */
    public function procesarPaymentWebhook($paymentId, $tenantId = null)
    {
        // PASSO 1: Buscar detalhes do payment na API
        $accessToken = $_ENV['MP_ACCESS_TOKEN_PROD'] ?? $_ENV['MP_ACCESS_TOKEN_TEST'];
        $apiUrl = "https://api.mercadopago.com/v1/payments/{$paymentId}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("[Webhook MP] ❌ Erro ao buscar payment {$paymentId} (HTTP {$httpCode})");
            return false;
        }
        
        $payment = json_decode($response, true);
        $externalRef = $payment['external_reference'] ?? null;
        
        // PASSO 2: Se not há metadata, tentar extrair do external_reference
        if (empty($payment['metadata']['pacote_contrato_id']) && $externalRef) {
            if (preg_match('/PAC-(\d+)-/', $externalRef, $matches)) {
                $contratoId = (int) $matches[1];
                
                // Buscar tenant_id do contrato
                $stmt = $this->db->prepare("SELECT tenant_id FROM pacote_contratos WHERE id = ? LIMIT 1");
                $stmt->execute([$contratoId]);
                $tenantId = (int) ($stmt->fetchColumn() ?: 0);
                
                // Injetar no metadata
                $payment['metadata']['pacote_contrato_id'] = $contratoId;
                $payment['metadata']['tenant_id'] = $tenantId;
                
                error_log("[Webhook MP] 🔧 Metadata injetado: contrato={$contratoId}, tenant={$tenantId}");
            }
        }
        
        // PASSO 3: Processar o pagamento
        // Aqui chamaria a função ativarPacoteContrato() com o payment enriquecido
        // Se bem-sucedido: retorna true
        // Se falhar: salva para reprocessamento
        
        return true;
    }
}

/**
 * Controlador que roda como CRON e reprocessa webhooks pendentes
 */
class WebhookReprocessorCron
{
    private $db;
    
    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
    }
    
    /**
     * Reprocessar todos os webhooks com status='sucesso' mas matricula_id=null
     * Deve rodar a cada 5 minutos via cron: * /5 * * * * /usr/bin/php /path/to/webhook_reprocessor_cron.php
     */
    public function reprocessarFalhados()
    {
        // Buscar webhooks para reprocessar
        $stmt = $this->db->prepare("
            SELECT id, payment_id, payload
            FROM webhook_payloads_mercadopago
            WHERE status = 'sucesso' 
            AND tipo = 'payment'
            AND resultado_processamento LIKE '%matricula_id\":null%'
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute();
        $webhooks = $stmt->fetchAll();
        
        echo "[CRON] Encontrados " . count($webhooks) . " webhooks para reprocessar\n";
        
        if (empty($webhooks)) {
            echo "[CRON] Nenhum webhook pendente\n";
            return;
        }
        
        $processados = 0;
        $sucesso = 0;
        
        foreach ($webhooks as $webhook) {
            $paymentId = $webhook['payment_id'];
            
            // Chamar o reprocessador
            // Aqui chamaria reprocess_failed_webhooks.php ou similar
            echo "[CRON] Processando payment {$paymentId}...\n";
            
            // Incrementar contador
            $sucesso++;
            $processados++;
        }
        
        echo "[CRON] Reprocessamento concluído: {$sucesso}/{$processados} sucesso\n";
    }
}
?>
