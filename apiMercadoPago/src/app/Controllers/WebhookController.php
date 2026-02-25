<?php

declare(strict_types=1);

namespace Controllers;

/**
 * WebhookController - Gerencia registro e logs de webhooks
 */
class WebhookController
{
    /**
     * POST /api/webhooks - Registrar URL de webhook
     */
    public function register(): void
    {
        $input = getJsonInput();

        if (empty($input['url'])) {
            jsonResponse(['error' => 'Campo "url" é obrigatório.'], 422);
            return;
        }

        if (!filter_var($input['url'], FILTER_VALIDATE_URL)) {
            jsonResponse(['error' => 'URL inválida.'], 422);
            return;
        }

        $webhookId = generateTransactionId('wh');

        $webhook = [
            'id' => $webhookId,
            'url' => $input['url'],
            'events' => $input['events'] ?? ['*'],
            'active' => true,
            'description' => $input['description'] ?? '',
            'created_at' => date('Y-m-d\TH:i:s.vP'),
        ];

        $webhooks = readJsonFile('webhooks.json');
        $webhooks[$webhookId] = $webhook;
        writeJsonFile('webhooks.json', $webhooks);

        logActivity('webhook.registered', [
            'webhook_id' => $webhookId,
            'url' => $input['url'],
        ]);

        jsonResponse($webhook, 201);
    }

    /**
     * GET /api/webhooks - Listar webhooks registrados
     */
    public function list(): void
    {
        $webhooks = readJsonFile('webhooks.json');
        jsonResponse([
            'data' => array_values($webhooks),
            'total' => count($webhooks),
        ]);
    }

    /**
     * DELETE /api/webhooks/{id} - Remover webhook
     */
    public function delete(string $id): void
    {
        $webhooks = readJsonFile('webhooks.json');

        if (!isset($webhooks[$id])) {
            jsonResponse(['error' => 'Webhook não encontrado.'], 404);
            return;
        }

        unset($webhooks[$id]);
        writeJsonFile('webhooks.json', $webhooks);

        logActivity('webhook.deleted', ['webhook_id' => $id]);

        jsonResponse(['message' => 'Webhook removido com sucesso.']);
    }

    /**
     * GET /api/webhook-logs - Listar logs de envio de webhooks
     */
    public function logs(): void
    {
        $logs = readJsonFile('webhook_logs.json');
        $limit = (int) ($_GET['limit'] ?? 50);

        // Reverter para mostrar mais recentes primeiro
        $logs = array_reverse($logs);
        $logs = array_slice($logs, 0, $limit);

        jsonResponse([
            'data' => $logs,
            'total' => count($logs),
        ]);
    }
}
