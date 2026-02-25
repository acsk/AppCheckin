<?php

declare(strict_types=1);

namespace Controllers;

/**
 * SimulatorController - Controla simulações manuais e regras
 */
class SimulatorController
{
    private const VALID_STATUSES = [
        'approved',
        'rejected',
        'pending',
        'in_process',
        'cancelled',
        'refunded',
        'charged_back',
        'error',
    ];

    /**
     * POST /api/simulate - Forçar mudança de status de um pagamento
     */
    public function simulate(): void
    {
        $input = getJsonInput();

        if (empty($input['payment_id'])) {
            jsonResponse(['error' => 'Campo "payment_id" é obrigatório.'], 422);
            return;
        }

        if (empty($input['status']) || !in_array($input['status'], self::VALID_STATUSES)) {
            jsonResponse([
                'error' => 'Campo "status" é obrigatório e deve ser um dos valores válidos.',
                'valid_statuses' => self::VALID_STATUSES,
            ], 422);
            return;
        }

        $payments = readJsonFile('payments.json');

        if (!isset($payments[$input['payment_id']])) {
            jsonResponse(['error' => 'Pagamento não encontrado.'], 404);
            return;
        }

        $payment = &$payments[$input['payment_id']];
        $oldStatus = $payment['status'];

        $payment['status'] = $input['status'];
        $payment['status_detail'] = $input['status_detail'] ?? $input['status'];
        $payment['updated_at'] = date('Y-m-d\TH:i:s.vP');

        if ($input['status'] === 'approved') {
            $payment['captured'] = true;
        }
        if ($input['status'] === 'refunded') {
            $payment['refunded'] = true;
            $payment['refund_amount'] = $payment['amount'];
        }

        writeJsonFile('payments.json', $payments);

        logActivity('payment.simulated', [
            'payment_id' => $input['payment_id'],
            'old_status' => $oldStatus,
            'new_status' => $input['status'],
        ]);

        // Disparar webhook com o novo status
        $this->dispatchWebhookForPayment('payment.updated', $payment);

        jsonResponse([
            'message' => 'Status alterado com sucesso.',
            'old_status' => $oldStatus,
            'new_status' => $input['status'],
            'payment' => $payment,
        ]);
    }

    /**
     * POST /api/rules - Criar regra de simulação automática
     */
    public function createRule(): void
    {
        $input = getJsonInput();

        if (empty($input['status']) || !in_array($input['status'], self::VALID_STATUSES)) {
            jsonResponse([
                'error' => 'Campo "status" é obrigatório.',
                'valid_statuses' => self::VALID_STATUSES,
            ], 422);
            return;
        }

        if (empty($input['conditions']) || !is_array($input['conditions'])) {
            jsonResponse(['error' => 'Campo "conditions" é obrigatório e deve ser um objeto.'], 422);
            return;
        }

        $ruleId = generateTransactionId('rule');

        $rule = [
            'id' => $ruleId,
            'name' => $input['name'] ?? 'Regra ' . $ruleId,
            'status' => $input['status'],
            'status_detail' => $input['status_detail'] ?? $input['status'],
            'conditions' => $input['conditions'],
            'priority' => (int) ($input['priority'] ?? 0),
            'active' => true,
            'created_at' => date('Y-m-d\TH:i:s.vP'),
        ];

        $rules = readJsonFile('simulation_rules.json');
        $rules[$ruleId] = $rule;

        // Ordenar por prioridade
        uasort($rules, fn($a, $b) => ($b['priority'] ?? 0) - ($a['priority'] ?? 0));

        writeJsonFile('simulation_rules.json', $rules);

        logActivity('rule.created', ['rule_id' => $ruleId]);

        jsonResponse($rule, 201);
    }

    /**
     * GET /api/rules - Listar regras de simulação
     */
    public function listRules(): void
    {
        $rules = readJsonFile('simulation_rules.json');
        jsonResponse([
            'data' => array_values($rules),
            'total' => count($rules),
        ]);
    }

    /**
     * DELETE /api/rules/{id} - Remover regra
     */
    public function deleteRule(string $id): void
    {
        $rules = readJsonFile('simulation_rules.json');

        if (!isset($rules[$id])) {
            jsonResponse(['error' => 'Regra não encontrada.'], 404);
            return;
        }

        unset($rules[$id]);
        writeJsonFile('simulation_rules.json', $rules);

        logActivity('rule.deleted', ['rule_id' => $id]);

        jsonResponse(['message' => 'Regra removida com sucesso.']);
    }

    /**
     * Dispara webhook para o pagamento
     */
    private function dispatchWebhookForPayment(string $event, array $payment): void
    {
        $webhooks = readJsonFile('webhooks.json');
        $notificationUrl = $payment['notification_url'] ?? null;

        $urls = [];
        foreach ($webhooks as $wh) {
            if (!empty($wh['url']) && ($wh['active'] ?? true)) {
                $urls[] = $wh['url'];
            }
        }

        if ($notificationUrl && !in_array($notificationUrl, $urls)) {
            $urls[] = $notificationUrl;
        }

        if (empty($urls)) {
            return;
        }

        $payload = [
            'id' => generateTransactionId('evt'),
            'type' => $event,
            'api_version' => 'v1',
            'date_created' => date('Y-m-d\TH:i:s.vP'),
            'data' => ['id' => $payment['id']],
            'payment' => $payment,
        ];

        foreach ($urls as $url) {
            $this->sendWebhook($url, $payload, $event);
        }
    }

    private function sendWebhook(string $url, array $payload, string $event): void
    {
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $jsonPayload, 'gateway_simulator_secret');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Gateway-Event: ' . $event,
                'X-Gateway-Signature: ' . $signature,
                'User-Agent: PaymentGatewaySimulator/1.0',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $webhookLogs = readJsonFile('webhook_logs.json');
        $webhookLogs[] = [
            'id' => generateTransactionId('whl'),
            'url' => $url,
            'event' => $event,
            'payment_id' => $payload['data']['id'],
            'http_status' => $httpCode,
            'success' => $httpCode >= 200 && $httpCode < 300,
            'error' => $error ?: null,
            'response_body' => substr((string) $response, 0, 500),
            'sent_at' => date('Y-m-d\TH:i:s.vP'),
        ];

        $webhookLogs = array_slice($webhookLogs, -200);
        writeJsonFile('webhook_logs.json', $webhookLogs);
    }
}
