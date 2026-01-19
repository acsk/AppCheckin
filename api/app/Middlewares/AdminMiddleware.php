<?php

namespace App\Middlewares;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

/**
 * Middleware para validar que apenas Admins (role_id = 2) 
 * e SuperAdmins (role_id = 3) acessem rotas administrativas
 */
class AdminMiddleware
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

        // Verificar se é admin (role_id = 2) ou superadmin (role_id = 3)
        $roleId = $usuario['role_id'] ?? null;
        
        if ($roleId !== 2 && $roleId !== 3) {
            $response = new Response();
            $response->getBody()->write(json_encode([
                'erro' => 'Acesso negado. Apenas administradores podem acessar este recurso.',
                'role_necessaria' => 'admin ou super_admin',
                'role_atual' => $roleId
            ]));
            return $response
                ->withStatus(403)
                ->withHeader('Content-Type', 'application/json');
        }

        // Usuário é admin ou superadmin, continuar
        return $handler->handle($request);
    }
}
