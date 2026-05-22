<?php

namespace App\Services;

use App\Repositories\UsuarioRepository;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;

class AuthService
{
    private const PASSWORD_RECOVERY_MESSAGE = 'Se o email existe em nossa base de dados, você receberá um link de recuperação';

    public function __construct(
        private readonly JwtService $jwt,
        private readonly UsuarioRepository $usuarios,
        private readonly PasswordRecoveryMailer $passwordRecoveryMailer,
    ) {}

    public function login(string $email, string $senha): JsonResponse
    {
        $email = mb_strtolower(trim($email), 'UTF-8');

        if ($email === '' || $senha === '') {
            return ApiError::json(
                'Email e senha são obrigatórios',
                'MISSING_CREDENTIALS',
                422,
            );
        }

        $usuario = $this->usuarios->findByEmailGlobal($email);

        if (! $usuario || ! password_verify($senha, (string) $usuario->senha_hash)) {
            return ApiError::json(
                'Email ou senha inválidos',
                'INVALID_CREDENTIALS',
                401,
            );
        }

        $papeis = $this->usuarios->getPapeis((int) $usuario->id);
        $papelId = ! empty($papeis) ? $papeis[0]['id'] : null;
        $token = null;
        $tenants = [];

        if ($papelId === 4) {
            $token = $this->jwt->encode([
                'user_id' => (int) $usuario->id,
                'email' => $usuario->email,
                'tenant_id' => null,
                'is_super_admin' => true,
            ]);
        } else {
            $tenants = $this->usuarios->getTenantsByUsuario((int) $usuario->id);

            if (empty($tenants)) {
                return ApiError::json(
                    'Usuário não possui vínculo com nenhuma academia',
                    'NO_TENANT_ACCESS',
                    403,
                );
            }

            if (count($tenants) === 1) {
                $tenantId = (int) ($tenants[0]['tenant']['id'] ?? 0);
                if ($tenantId <= 0) {
                    return ApiError::json(
                        'Vínculo de academia inválido para este usuário',
                        'NO_TENANT_ACCESS',
                        403,
                    );
                }

                $alunoId = $papelId === 1
                    ? $this->usuarios->findAlunoId((int) $usuario->id)
                    : null;

                $token = $this->jwt->encode([
                    'user_id' => (int) $usuario->id,
                    'email' => $usuario->email,
                    'tenant_id' => $tenantId,
                    'aluno_id' => $alunoId,
                ]);
            }
        }

        return response()->json([
            'message' => 'Login realizado com sucesso',
            'token' => $token,
            'user' => [
                'id' => (int) $usuario->id,
                'nome' => $usuario->nome,
                'email' => $usuario->email,
                'email_global' => $usuario->email_global ?? $usuario->email,
                'foto_base64' => $usuario->foto_base64 ?? null,
                'papel_id' => $papelId,
                'papeis' => $papeis,
            ],
            'tenants' => $tenants,
            'requires_tenant_selection' => count($tenants) > 1,
            'api_version' => config('appcheckin.api_version'),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function selectTenant(int $userId, int $tenantId): JsonResponse
    {
        if ($tenantId <= 0) {
            return ApiError::json('tenant_id é obrigatório', 'MISSING_TENANT_ID', 422);
        }

        if (! $this->usuarios->temAcessoTenant($userId, $tenantId)) {
            return ApiError::json(
                'Você não tem acesso a esta academia',
                'TENANT_ACCESS_DENIED',
                403,
            );
        }

        return $this->buildTenantSelectionResponse($userId, $tenantId, includeAllTenants: false);
    }

    public function selectTenantPublic(int $userId, string $email, int $tenantId): JsonResponse
    {
        if ($userId <= 0 || $email === '' || $tenantId <= 0) {
            return ApiError::json(
                'user_id, email e tenant_id são obrigatórios',
                'MISSING_REQUIRED_FIELDS',
                422,
            );
        }

        $usuario = $this->usuarios->findById($userId);

        if (! $usuario) {
            return ApiError::json('Dados inválidos', 'INVALID_USER_DATA', 401);
        }

        $emailNorm = mb_strtolower(trim($email), 'UTF-8');
        $userEmail = mb_strtolower(trim((string) ($usuario['email'] ?? '')), 'UTF-8');
        $userEmailGlobal = mb_strtolower(trim((string) ($usuario['email_global'] ?? '')), 'UTF-8');

        if ($userEmail !== $emailNorm && $userEmailGlobal !== $emailNorm) {
            return ApiError::json('Dados inválidos', 'INVALID_USER_DATA', 401);
        }

        if (! $this->usuarios->temAcessoTenant($userId, $tenantId)) {
            return ApiError::json(
                'Você não tem acesso a esta academia',
                'TENANT_ACCESS_DENIED',
                403,
            );
        }

        return $this->buildTenantSelectionResponse($userId, $tenantId, includeAllTenants: true);
    }

    private function buildTenantSelectionResponse(
        int $userId,
        int $tenantId,
        bool $includeAllTenants,
    ): JsonResponse {
        $usuario = $this->usuarios->findById($userId);

        if (! $usuario) {
            return ApiError::json('Usuário não encontrado', 'USER_NOT_FOUND', 404);
        }

        $alunoId = (($usuario['papel_id'] ?? null) == 1)
            ? $this->usuarios->findAlunoId($userId)
            : null;

        $token = $this->jwt->encode([
            'user_id' => (int) $usuario['id'],
            'email' => $usuario['email'],
            'tenant_id' => $tenantId,
            'aluno_id' => $alunoId,
        ]);

        $tenants = $this->usuarios->getTenantsByUsuario($userId);
        $tenantSelecionado = null;

        foreach ($tenants as $t) {
            if ((int) ($t['tenant']['id'] ?? 0) === $tenantId) {
                $tenantSelecionado = $t;
                break;
            }
        }

        $payload = [
            'message' => 'Academia selecionada com sucesso',
            'token' => $token,
            'user' => [
                'id' => (int) $usuario['id'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
                'email_global' => $usuario['email_global'] ?? $usuario['email'],
                'foto_base64' => $usuario['foto_base64'] ?? null,
                'papel_id' => $usuario['papel_id'] ?? null,
            ],
            'tenant' => $tenantSelecionado,
        ];

        if ($includeAllTenants) {
            $payload['tenants'] = $tenants;
        }

        return response()->json($payload, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function register(string $nome, string $email, string $senha, int $tenantId): JsonResponse
    {
        if ($nome === '' || $email === '' || $senha === '' || $tenantId <= 0) {
            return ApiError::json(
                'Nome, email, senha e tenant_id são obrigatórios',
                'MISSING_FIELDS',
                422,
            );
        }

        if (! $this->usuarios->isTenantActive($tenantId)) {
            return ApiError::json(
                'Academia (tenant) inválida ou inativa',
                'INVALID_TENANT',
                400,
            );
        }

        $emailNorm = mb_strtolower(trim($email), 'UTF-8');

        if ($this->usuarios->findByEmailGlobal($emailNorm)) {
            return ApiError::json(
                'Email já cadastrado',
                'EMAIL_ALREADY_EXISTS',
                422,
            );
        }

        $usuarioId = $this->usuarios->createUsuario([
            'nome' => $nome,
            'email' => $emailNorm,
            'senha' => $senha,
            'papel_id' => 1,
        ], $tenantId, 1);

        if (! $usuarioId) {
            return ApiError::json(
                'Erro ao criar usuário',
                'REGISTRATION_ERROR',
                500,
            );
        }

        $novoUsuario = $this->usuarios->findById($usuarioId, $tenantId);

        if (! $novoUsuario) {
            return ApiError::json(
                'Erro ao criar usuário',
                'REGISTRATION_ERROR',
                500,
            );
        }

        $alunoId = $this->usuarios->findAlunoId($usuarioId);

        $token = $this->jwt->encode([
            'user_id' => (int) $novoUsuario['id'],
            'email' => $novoUsuario['email'],
            'tenant_id' => $tenantId,
            'aluno_id' => $alunoId,
        ]);

        return response()->json([
            'message' => 'Usuário criado com sucesso',
            'token' => $token,
            'user' => [
                'id' => (int) $novoUsuario['id'],
                'nome' => $novoUsuario['nome'],
                'email' => $novoUsuario['email'],
                'tenant_id' => $tenantId,
                'papel_id' => 1,
            ],
        ], 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function requestPasswordRecovery(string $email): JsonResponse
    {
        if (trim($email) === '') {
            return ApiError::json('Email é obrigatório', 'MISSING_EMAIL', 422);
        }

        $emailNorm = mb_strtolower(trim($email), 'UTF-8');
        $usuario = $this->usuarios->findByEmailGlobal($emailNorm);

        if ($usuario) {
            $token = bin2hex(random_bytes(32));
            $this->usuarios->setPasswordResetToken((int) $usuario->id, $token, 60);
            $this->passwordRecoveryMailer->send(
                (string) $usuario->email,
                (string) $usuario->nome,
                $token,
                60,
            );
        }

        return response()->json([
            'message' => self::PASSWORD_RECOVERY_MESSAGE,
        ]);
    }

    public function validatePasswordToken(string $token): JsonResponse
    {
        if ($token === '') {
            return ApiError::json('Token é obrigatório', 'MISSING_TOKEN', 422);
        }

        $usuario = $this->usuarios->findByPasswordResetToken($token);

        if (! $usuario) {
            return ApiError::json(
                'Token inválido ou expirado',
                'INVALID_OR_EXPIRED_TOKEN',
                400,
            );
        }

        return response()->json([
            'message' => 'Token válido',
            'user' => [
                'id' => (int) $usuario['id'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
            ],
        ]);
    }

    public function resetPassword(string $token, string $novaSenha, string $confirmacaoSenha): JsonResponse
    {
        $errors = [];

        if ($token === '') {
            $errors[] = 'Token é obrigatório';
        }

        if ($novaSenha === '' || strlen($novaSenha) < 6) {
            $errors[] = 'Nova senha deve ter no mínimo 6 caracteres';
        }

        if ($confirmacaoSenha === '' || $novaSenha !== $confirmacaoSenha) {
            $errors[] = 'As senhas não coincidem';
        }

        if (! empty($errors)) {
            return ApiError::validation($errors);
        }

        $usuarioId = $this->usuarios->findIdByPasswordResetToken($token);

        if (! $usuarioId) {
            return ApiError::json(
                'Token inválido ou expirado',
                'INVALID_OR_EXPIRED_TOKEN',
                400,
            );
        }

        $this->usuarios->resetPassword($usuarioId, $novaSenha);

        return response()->json([
            'message' => 'Senha alterada com sucesso. Faça login com sua nova senha.',
        ]);
    }
}
