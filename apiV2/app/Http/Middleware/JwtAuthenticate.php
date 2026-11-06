<?php

namespace App\Http\Middleware;

use App\Repositories\UsuarioRepository;
use App\Services\AcessoAlunoService;
use App\Services\JwtService;
use App\Services\SuperAdmin\AcessoSuporteService;
use App\Support\ApiError;
use App\Support\Papel;
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

        // "Acessar como academia": a sessão é conferida a cada requisição (encerrar/expirar corta na
        // hora). Durante o acesso o super admin age como admin desta academia, e só dela.
        $acessoSuporte = null;
        if (! empty($payload['acesso_suporte_id'])) {
            $acessoSuporte = app(AcessoSuporteService::class)->sessaoValida((int) $payload['acesso_suporte_id'], $userId, (int) $resolvedTenantId);
            if (! $acessoSuporte) {
                return ApiError::json('O acesso de suporte a esta academia terminou. Entre de novo pelo Super Admin.', 'ACESSO_SUPORTE_ENCERRADO', 401);
            }
            $usuario['papel_id'] = Papel::ADMIN;
            $usuario['tenant_id'] = $resolvedTenantId;
        }

        // "Ver app como aluno": token do aluno, conferido a cada requisição.
        $acessoAluno = null;
        if (! empty($payload['acesso_aluno_id'])) {
            $acessoAluno = app(AcessoAlunoService::class)->sessaoValida((int) $payload['acesso_aluno_id'], $userId, (int) $resolvedTenantId);
            if (! $acessoAluno) {
                return ApiError::json('A visualização como aluno terminou. Abra de novo pelo painel.', 'ACESSO_ALUNO_ENCERRADO', 401);
            }
            // A troca de tenant emite um token comum sem acesso_aluno_id e escaparia da sessão assistida.
            if ($request->path() === 'v2/auth/select-tenant') {
                return ApiError::json('Não é possível trocar de academia durante o acesso assistido.', 'ACESSO_ALUNO_TROCA_TENANT_BLOQUEADA', 403);
            }
            $usuario['papel_id'] = Papel::ALUNO;
        }

        // Perfil de dependente (token emitido para o responsável): o vínculo é conferido a cada requisição
        $responsavelId = (int) ($payload['responsavel_id'] ?? 0);
        if ($responsavelId > 0) {
            $vinculado = \Illuminate\Support\Facades\DB::table('usuarios')->where('id', $userId)
                ->where('responsavel_usuario_id', $responsavelId)->exists();
            if (! $vinculado) {
                return ApiError::json('Este perfil não está mais ligado à sua conta. Entre de novo.', 'DEPENDENTE_DESVINCULADO', 401);
            }
            // Trocar de academia emitiria um token comum do dependente, sem o vínculo com o responsável
            if ($request->path() === 'v2/auth/select-tenant') {
                return ApiError::json('Volte para o seu perfil para trocar de academia.', 'DEPENDENTE_TROCA_TENANT_BLOQUEADA', 403);
            }
        }

        // Login próprio do dependente (PIN): cai se o responsável tirar o acesso ou desfizer o vínculo.
        // Compras e pagamentos ficam com o responsável (é o CPF dele na cobrança).
        $dependenteLogin = (int) ($payload['dependente_login'] ?? 0);
        if ($dependenteLogin > 0) {
            $liberado = \Illuminate\Support\Facades\DB::table('usuarios')->where('id', $userId)
                ->where('responsavel_usuario_id', $dependenteLogin)->whereNotNull('dependente_pin_hash')->exists();
            if (! $liberado) {
                return ApiError::json('O acesso deste perfil foi encerrado pelo responsável.', 'DEPENDENTE_ACESSO_ENCERRADO', 401);
            }
            if ($request->path() === 'v2/auth/select-tenant') {
                return ApiError::json('Peça ao responsável para trocar de academia.', 'DEPENDENTE_TROCA_TENANT_BLOQUEADA', 403);
            }
            if (! $request->isMethod('GET') && self::ehCompraOuPagamento($request->path())) {
                return ApiError::json('Compras e pagamentos são feitos pelo responsável, no perfil dele.', 'COMPRA_SO_RESPONSAVEL', 403);
            }
        }

        $request->attributes->set('userId', $userId);
        $request->attributes->set('dependente_login', $dependenteLogin > 0 ? $dependenteLogin : null);
        $request->attributes->set('responsavel_id', $responsavelId > 0 ? $responsavelId : null);
        $request->attributes->set('userEmail', $payload['email'] ?? $usuario['email']);
        $request->attributes->set('tenantId', $resolvedTenantId ?: null);
        $request->attributes->set('tenant_id', $resolvedTenantId ?: null);
        $request->attributes->set('aluno_id', $payload['aluno_id'] ?? null);
        $request->attributes->set('jwt_payload', $payload);
        $request->attributes->set('usuario', $usuario);
        $request->attributes->set('is_super_admin', $acessoSuporte ? false : (bool) ($payload['is_super_admin'] ?? false));
        $request->attributes->set('acesso_suporte', $acessoSuporte);
        $request->attributes->set('acesso_aluno', $acessoAluno);

        $response = $next($request);

        $this->renovarSessaoDoApp($request, $response, $payload, $acessoSuporte || $acessoAluno);

        if ($acessoSuporte && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && ! $request->is('v2/suporte/acesso/encerrar')) {
            app(AcessoSuporteService::class)->registrarAcao((int) $acessoSuporte['id'], $request->method(), '/'.$request->path(), $response->getStatusCode());
        }

        return $response;
    }

    /**
     * Sessão deslizante do app: com X-Session-Renew: 1 o token volta renovado em X-Refresh-Token
     * (no máximo uma vez por dia). Quem abre o app pelo menos uma vez a cada N dias não desloga.
     *
     * @param  array<string, mixed>  $payload
     */
    private function renovarSessaoDoApp(Request $request, Response $response, array $payload, bool $acessoSuporte): void
    {
        $dias = (int) config('appcheckin.jwt_app_session_days', 30);
        if ($acessoSuporte || $dias <= 0 || $request->header('X-Session-Renew') !== '1' || $response->getStatusCode() === 401) {
            return;
        }

        $ttl = $dias * 86400;
        if ((int) ($payload['exp'] ?? 0) > time() + $ttl - 86400) {
            return;
        }

        try {
            unset($payload['iat'], $payload['exp']);
            $response->headers->set('X-Refresh-Token', $this->jwt->encode($payload, $ttl));
        } catch (\RuntimeException) {
            // Sem renovar: o token atual continua valendo até expirar
        }
    }

    /** Rotas do app que contratam, pagam ou cancelam (bloqueadas no login próprio do dependente). */
    public static function ehCompraOuPagamento(string $path): bool
    {
        return preg_match('#^v2/mobile/(comprar-plano|migrar-plano|pagamento/|pacotes/(\d+/contratar|contratos/\d+/(pagar|cobranca|renovar))|recorrencia/(contratar|contratos/\d+/)|assinatura/\d+/cancelar|diaria/\d+/cancelar)#', $path) === 1;
    }
}
