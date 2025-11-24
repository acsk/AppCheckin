<?php

namespace App\Middlewares;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as SlimResponse;
use App\Models\Tenant;

class TenantMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // Obter tenant_id do header ou usar tenant padrão
        $tenantSlug = $request->getHeaderLine('X-Tenant-Slug');
        
        if (!$tenantSlug) {
            // Usar tenant padrão
            $tenantId = 1;
        } else {
            // Buscar tenant por slug
            $db = require __DIR__ . '/../../config/database.php';
            $tenantModel = new Tenant($db);
            $tenant = $tenantModel->findBySlug($tenantSlug);
            
            if (!$tenant) {
                return $this->errorResponse('Tenant não encontrado ou inativo');
            }
            
            $tenantId = $tenant['id'];
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
