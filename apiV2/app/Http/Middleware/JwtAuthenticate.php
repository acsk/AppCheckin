<?php

namespace App\Http\Middleware;

use App\Repositories\UsuarioRepository;
use App\Services\JwtService;
use App\Support\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly UsuarioRepository $usuarios,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $authHeader = $request->header('Authorization', '');

        if ($authHeader === '') {
            return ApiError::json('Token não fornecido', 'MISSING_TOKEN', 401);
        }

        if (! preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return ApiError::json('Formato de token inválido', 'INVALID_TOKEN_FORMAT', 401);
        }

        $decoded = $this->jwt->decode($matches[1]);

        if (! $decoded) {
            return ApiError::json('Token inválido ou expirado', 'TOKEN_EXPIRED_OR_INVALID', 401);
        }

        $payload = (array) $decoded;
        $userId = (int) ($payload['user_id'] ?? 0);

        if ($userId <= 0) {
            return ApiError::json('Token inválido ou expirado', 'TOKEN_EXPIRED_OR_INVALID', 401);
        }

        try {
            $usuario = $this->usuarios->findAuthContext($userId);
        } catch (\Throwable) {
            return ApiError::json(
                'Falha ao conectar ao banco de dados',
                'DATABASE_CONNECTION_FAILED',
                503,
            );
        }

        if (! $usuario) {
            return ApiError::json(
                'Usuário não existe ou foi removido',
                'USER_NOT_FOUND',
                401,
            );
        }

        $tenantFromJwt = isset($payload['tenant_id']) ? (int) $payload['tenant_id'] : null;
        $resolvedTenantId = $tenantFromJwt ?: (int) ($usuario['tenant_id'] ?? 0);

        $request->attributes->set('userId', $userId);
        $request->attributes->set('userEmail', $payload['email'] ?? $usuario['email']);
        $request->attributes->set('tenantId', $resolvedTenantId ?: null);
        $request->attributes->set('tenant_id', $resolvedTenantId ?: null);
        $request->attributes->set('aluno_id', $payload['aluno_id'] ?? null);
        $request->attributes->set('jwt_payload', $payload);
        $request->attributes->set('usuario', $usuario);
        $request->attributes->set('is_super_admin', (bool) ($payload['is_super_admin'] ?? false));

        return $next($request);
    }
}
