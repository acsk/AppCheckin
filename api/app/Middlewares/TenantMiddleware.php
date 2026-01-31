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
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-Tenant-Id, X-Tenant-Slug')
                ->withHeader('Access-Control-Max-Age', '86400')
                ->withStatus(204);
        }

        $tenantId = null;
        $tenantSource = 'none';
        $path = $request->getUri()->getPath();
        
        // 1. Priorizar X-Tenant-Id (permite troca dinâmica pelo front sem reemitir token)
        $tenantIdHeader = $request->getHeaderLine('X-Tenant-Id');
        if ($tenantIdHeader !== '') {
            $db = require __DIR__ . '/../../config/database.php';
            $tenantModel = new Tenant($db);
            $tenant = $tenantModel->findById((int)$tenantIdHeader);
            if (!$tenant) {
                return $this->errorResponse('Tenant não encontrado ou inativo');
            }
            $tenantId = (int)$tenant['id'];
            $tenantSource = 'header-id';
        }

        // 2. Se não tem header id, tentar X-Tenant-Slug
        if (!$tenantId) {
            $tenantSlug = $request->getHeaderLine('X-Tenant-Slug');
            if ($tenantSlug) {
                $db = require __DIR__ . '/../../config/database.php';
                $tenantModel = new Tenant($db);
                $tenant = $tenantModel->findBySlug($tenantSlug);
                if (!$tenant) {
                    return $this->errorResponse('Tenant não encontrado ou inativo');
                }
                $tenantId = (int)$tenant['id'];
                $tenantSource = 'header-slug';
            }
        }

        // 3. Se ainda não tem tenant, tentar obter do JWT
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

        // 4. Se ainda não tem tenant, decidir conforme a rota
        if (!$tenantId) {
            // Allowlist de rotas que não exigem tenant
            $allowlistPrefixes = [
                '/auth',            // login, register, select-tenant-initial, etc.
                '/swagger',         // documentação
                '/php-test',        // utilidades públicas
                '/health',          // health e health/basic
                '/status',          // status público
                '/ping',            // ping simples
                '/uploads/fotos',   // servir fotos públicas
                '/test-simple',
                '/diagnose',
                '/diagnostico.php',
                '/index.php'
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
                return $this->errorResponse('Tenant não informado. Informe X-Tenant-Id ou X-Tenant-Slug, ou utilize um token com tenant_id.');
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
