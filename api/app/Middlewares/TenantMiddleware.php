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
        $tenantId = null;
        
        // 1. Tentar obter tenant_id do JWT (usuário já autenticado e tenant selecionado)
        $authHeader = $request->getHeaderLine('Authorization');
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            $jwtService = new JWTService($_ENV['JWT_SECRET']);
            
            try {
                $payload = $jwtService->decode($token);
                // Converter objeto para array se necessário
                if (is_object($payload)) {
                    $payload = (array) $payload;
                }
                if (isset($payload['tenant_id'])) {
                    $tenantId = $payload['tenant_id'];
                }
            } catch (\Exception $e) {
                // Token inválido, continuar com outras opções
            }
        }
        
        // 2. Se não tem tenant no JWT, tentar obter do header X-Tenant-Slug
        if (!$tenantId) {
            $tenantSlug = $request->getHeaderLine('X-Tenant-Slug');
            
            if ($tenantSlug) {
                $db = require __DIR__ . '/../../config/database.php';
                $tenantModel = new Tenant($db);
                $tenant = $tenantModel->findBySlug($tenantSlug);
                
                if (!$tenant) {
                    return $this->errorResponse('Tenant não encontrado ou inativo');
                }
                
                $tenantId = $tenant['id'];
            }
        }
        
        // 3. Se ainda não tem tenant, usar padrão
        if (!$tenantId) {
            $tenantId = 1;
        }

        // Adicionar tenant_id ao request
        $request = $request->withAttribute('tenantId', $tenantId);

        return $handler->handle($request);
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
