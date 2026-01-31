<?php

namespace App\Middlewares;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as SlimResponse;
use App\Services\JWTService;

class AuthMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader) {
            return $this->unauthorizedResponse(
                'Token não fornecido',
                'MISSING_TOKEN'
            );
        }

        // Formato: Bearer <token>
        $parts = explode(' ', $authHeader);
        
        if (count($parts) !== 2 || $parts[0] !== 'Bearer') {
            return $this->unauthorizedResponse(
                'Formato de token inválido',
                'INVALID_TOKEN_FORMAT'
            );
        }

        $token = $parts[1];

        // Validar token
        $jwtService = new JWTService($_ENV['JWT_SECRET']);
        $decoded = $jwtService->decode($token);

        if (!$decoded) {
            return $this->unauthorizedResponse(
                'Token inválido ou expirado',
                'TOKEN_EXPIRED_OR_INVALID'
            );
        }

        // Buscar dados completos do usuário
            // Buscar dados completos do usuário com proteção ao banco
            try {
                $db = require __DIR__ . '/../../config/database.php';
                $stmt = $db->prepare("
                    SELECT 
                        u.id, 
                        u.nome, 
                        u.email, 
                        u.email_global, 
                        u.foto_base64,
                        ut.tenant_id,
                        ut.status as tenant_status,
                        tup.papel_id
                    FROM usuarios u
                    LEFT JOIN usuario_tenant ut ON ut.usuario_id = u.id AND ut.status = 'ativo'
                    LEFT JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id AND tup.ativo = 1
                    WHERE u.id = :user_id
                    ORDER BY tup.papel_id DESC
                    LIMIT 1
                ");
                $stmt->execute(['user_id' => $decoded->user_id]);
                $usuario = $stmt->fetch(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                error_log('[AuthMiddleware] DB error: ' . $e->getMessage());
                $resp = new SlimResponse();
                $resp->getBody()->write(json_encode([
                    'type' => 'error',
                    'code' => 'DATABASE_CONNECTION_FAILED',
                    'message' => 'Falha ao conectar ao banco de dados'
                ]));
                return $resp->withHeader('Content-Type', 'application/json')->withStatus(503);
            }

        if (!$usuario) {
            return $this->unauthorizedResponse(
                'Usuário não existe ou foi removido',
                'USER_NOT_FOUND'
            );
        }

        // Adicionar dados do usuário ao request
        $request = $request->withAttribute('userId', $decoded->user_id);
        $request = $request->withAttribute('userEmail', $decoded->email);

        // Se TenantMiddleware já definiu tenantId (via cabeçalho/slug), respeitar esse valor
        $existingTenantId = $request->getAttribute('tenantId');
        $resolvedTenantId = $existingTenantId ? (int)$existingTenantId : (int)$usuario['tenant_id'];
        $request = $request->withAttribute('tenantId', $resolvedTenantId);
        $request = $request->withAttribute('tenant_id', $resolvedTenantId);

        $request = $request->withAttribute('aluno_id', $decoded->aluno_id ?? null);
        $request = $request->withAttribute('usuario', $usuario);

        return $handler->handle($request);
    }

    private function unauthorizedResponse(string $message, string $code = 'UNAUTHORIZED'): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'type' => 'error',
            'code' => $code,
            'message' => $message
        ]));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}
