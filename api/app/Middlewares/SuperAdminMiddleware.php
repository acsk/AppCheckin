<?php

namespace App\Middlewares;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

/**
 * Middleware para validar que APENAS SuperAdmins (role_id = 3) 
 * acessem rotas de gestão de academias
 */
class SuperAdminMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $usuario = $request->getAttribute('usuario');

        // Verificar se o usuário está autenticado
        if (!$usuario) {
            $response = new Response();
            $response->getBody()->write(json_encode([
                'erro' => 'Não autenticado'
            ]));
            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        // Verificar se é superadmin (role_id = 3)
        $roleId = $usuario['role_id'] ?? null;
        
        if ($roleId !== 3) {
            $response = new Response();
            $response->getBody()->write(json_encode([
                'erro' => 'Acesso negado. Apenas super administradores podem acessar este recurso.',
                'role_necessaria' => 'super_admin',
                'role_atual' => $roleId
            ]));
            return $response
                ->withStatus(403)
                ->withHeader('Content-Type', 'application/json');
        }

        // Usuário é superadmin, continuar
        return $handler->handle($request);
    }
}
