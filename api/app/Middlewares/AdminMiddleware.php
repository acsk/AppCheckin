<?php

namespace App\Middlewares;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

/**
 * Middleware para validar que apenas Admins (role_id = 3) 
 * e SuperAdmins (role_id = 4) acessem rotas administrativas
 * 
 * Papéis (tabela papeis):
 * - 1: aluno
 * - 2: professor
 * - 3: admin
 * - 4: super_admin
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

        // Verificar se é admin (role_id = 3) ou superadmin (role_id = 4)
        $roleId = $usuario['role_id'] ?? null;
        
        if ($roleId !== 3 && $roleId !== 4) {
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
