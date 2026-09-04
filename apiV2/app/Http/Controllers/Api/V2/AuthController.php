<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Repositories\UsuarioRepository;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly UsuarioRepository $usuarios,
    ) {}

    public function login(Request $request): JsonResponse
    {
        return $this->auth->login(
            (string) $request->input('email', ''),
            (string) $request->input('senha', ''),
        );
    }

    public function logout(): JsonResponse
    {
        return response()->json([
            'message' => 'Logout realizado com sucesso',
        ]);
    }

    public function tenants(Request $request): JsonResponse
    {
        $userId = (int) $request->attributes->get('userId');
        $tenants = $this->usuarios->getTenantsByUsuario($userId);

        return response()
            ->json([
                'tenants' => $tenants,
                'requires_tenant_selection' => count($tenants) > 1,
                'current_tenant_id' => $request->attributes->get('tenantId'),
            ])
            ->header('Cache-Control', 'private, max-age=300')
            ->header('Vary', 'Authorization');
    }

    public function selectTenant(Request $request): JsonResponse
    {
        return $this->auth->selectTenant(
            (int) $request->attributes->get('userId'),
            (int) $request->input('tenant_id', 0),
        );
    }

    public function selectTenantPublic(Request $request): JsonResponse
    {
        return $this->auth->selectTenantPublic(
            (int) $request->input('user_id', 0),
            (string) $request->input('email', ''),
            (int) $request->input('tenant_id', 0),
        );
    }

    public function register(Request $request): JsonResponse
    {
        $cpf = $request->input('cpf');

        return $this->auth->register(
            (string) $request->input('nome', ''),
            (string) $request->input('email', ''),
            (string) $request->input('senha', ''),
            (int) $request->input('tenant_id', 0),
            $cpf !== null ? (string) $cpf : null,
        );
    }

    public function tenantsPublic(): JsonResponse
    {
        return $this->auth->tenantsPublic();
    }

    public function registerMobile(Request $request): JsonResponse
    {
        try {
            return $this->auth->registerMobile($request);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'type' => 'error',
                'code' => 'REGISTER_INTERNAL_ERROR',
                'message' => 'Erro interno ao realizar cadastro',
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    public function requestPasswordRecovery(Request $request): JsonResponse
    {
        return $this->auth->requestPasswordRecovery($request);
    }

    public function validatePasswordToken(Request $request): JsonResponse
    {
        return $this->auth->validatePasswordToken((string) $request->input('token', ''));
    }

    public function resetPassword(Request $request): JsonResponse
    {
        return $this->auth->resetPassword(
            (string) $request->input('token', ''),
            (string) $request->input('nova_senha', ''),
            (string) $request->input('confirmacao_senha', ''),
        );
    }
}
