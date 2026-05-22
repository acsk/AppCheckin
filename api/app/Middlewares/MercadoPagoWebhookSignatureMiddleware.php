<?php

namespace App\Middlewares;

use App\Services\MercadoPago\WebhookSignatureValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Valida x-signature nos webhooks do Mercado Pago antes do controller.
 */
class MercadoPagoWebhookSignatureMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            return $handler->handle($request);
        }

        try {
            $db = require __DIR__ . '/../../config/database.php';
            $validator = new WebhookSignatureValidator();
            $result = $validator->validate($request, $db);

            if (!$result['allowed']) {
                error_log('[MercadoPagoWebhookSignature] Bloqueado: ' . $result['code'] . ' - ' . $result['message']);

                $response = new SlimResponse();
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'code' => $result['code'],
                    'message' => $result['message'],
                ], JSON_UNESCAPED_UNICODE));

                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(401);
            }
        } catch (\Throwable $e) {
            error_log('[MercadoPagoWebhookSignature] Erro na validação: ' . $e->getMessage());

            $response = new SlimResponse();
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'code' => 'WEBHOOK_SIGNATURE_VALIDATION_ERROR',
                'message' => 'Falha ao validar assinatura do webhook',
            ], JSON_UNESCAPED_UNICODE));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(503);
        }

        return $handler->handle($request);
    }
}
