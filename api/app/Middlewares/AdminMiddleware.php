<?php

namespace App\Middlewares;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

/**
 * Middleware para validar que apenas Admins (papel_id = 3) 
 * e SuperAdmins (papel_id = 4) acessem rotas administrativas
 * 
 * Papéis (tabela papeis via tenant_usuario_papel):
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

        // Verificar se é admin (papel_id = 3) ou superadmin (papel_id = 4)
        $papelId = isset($usuario['papel_id']) ? (int)$usuario['papel_id'] : null;
        
        if ($papelId !== 3 && $papelId !== 4) {
            $response = new Response();
            $response->getBody()->write(json_encode([
                'erro' => 'Acesso negado. Apenas administradores podem acessar este recurso.',
                'papel_necessario' => 'admin ou super_admin',
                'papel_atual' => $papelId
            ]));
            return $response
                ->withStatus(403)
                ->withHeader('Content-Type', 'application/json');
        }

        // Usuário é admin ou superadmin, continuar
        return $handler->handle($request);
    }
}
