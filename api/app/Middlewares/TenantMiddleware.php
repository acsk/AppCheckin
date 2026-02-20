<?php

namespace App\Middlewares;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as SlimResponse;
use App\Models\Tenant;
use App\Services\JWTService;

class TenantMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // Liberar preflight CORS para qualquer rota
        $method = strtoupper($request->getMethod());
        if ($method === 'OPTIONS') {
            $resp = new SlimResponse();
            return $resp
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, Cache-Control, Pragma, Expires, X-Tenant-Id')
                ->withHeader('Access-Control-Max-Age', '86400')
                ->withStatus(204);
        }

        $tenantId = null;
        $tenantSource = 'none';
        $path = $request->getUri()->getPath();
        
        // Removido suporte a X-Tenant-*; resolver apenas via JWT

        // 1. Tentar obter tenant do JWT
        if (!$tenantId) {
            $authHeader = $request->getHeaderLine('Authorization');
            if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
                $jwtService = new JWTService($_ENV['JWT_SECRET']);
                try {
                    $payload = $jwtService->decode($token);
                    if (is_object($payload)) {
                        $payload = (array) $payload;
                    }
                    if (isset($payload['tenant_id'])) {
                        $tenantId = (int)$payload['tenant_id'];
                        $tenantSource = 'jwt';
                    }
                } catch (\Exception $e) {
                    // Token inválido, continuar com outras opções
                }
            }
        }

        // 2. Se ainda não tem tenant, decidir conforme a rota
        if (!$tenantId) {
            // Allowlist de rotas que não exigem tenant
            $allowlistPrefixes = [
                '/auth',            // login, register, select-tenant-initial, etc.
                '/superadmin',      // rotas de super admin não precisam de tenant
                '/swagger',         // documentação
                '/php-test',        // utilidades públicas
                '/health',          // health e health/basic
                '/status',          // status público
                '/ping',            // ping simples
                '/uploads/fotos',   // servir fotos públicas
                '/ok',              // rota alternativa de health básico
                '/signin',          // rota alternativa de login
                '/v1/status',       // aliases sob /v1
                '/v1/ping',
                '/v1/ok',
                '/v1/auth',
                '/test-simple',
                '/diagnose',
                '/diagnostico.php',
                '/index.php',
                '/cep',             // consulta de CEP (serviço público)
                '/formas-pagamento', // formas de pagamento públicas
                '/api/webhooks'     // webhooks de integrações (Mercado Pago, etc.)
            ];

            $isAllowlisted = false;
            foreach ($allowlistPrefixes as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    $isAllowlisted = true;
                    break;
                }
            }

            // Para rotas sensíveis (ex.: /mobile), exigir tenant
            if (!$isAllowlisted) {
                return $this->errorResponse('Tenant não informado. Utilize um token (JWT) contendo tenant_id.');
            }
        }

        // Adicionar tenant_id ao request
        if ($tenantId) {
            $request = $request->withAttribute('tenantId', $tenantId);
        }

        // Processar e anexar cabeçalhos de depuração
        $response = $handler->handle($request);
        if ($tenantId) {
            $response = $response
                ->withHeader('X-Resolved-Tenant-Id', (string)$tenantId)
                ->withHeader('X-Tenant-Source', $tenantSource);
        }
        return $response;
    }

    private function errorResponse(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'error' => $message
        ]));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400);
    }
}
