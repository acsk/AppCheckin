<?php

namespace App\Services;

use App\Repositories\TenantRepository;
use App\Repositories\UsuarioRepository;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthService
{
    private const PASSWORD_RECOVERY_MESSAGE = 'Se o email existe em nossa base de dados, você receberá um link de recuperação';

    public function __construct(
        private readonly JwtService $jwt,
        private readonly UsuarioRepository $usuarios,
        private readonly TenantRepository $tenants,
        private readonly PasswordRecoveryMailer $passwordRecoveryMailer,
        private readonly WelcomeAlunoMailer $welcomeAlunoMailer,
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
            try {
                $token = $this->jwt->encode([
                    'user_id' => (int) $usuario->id,
                    'email' => $usuario->email,
                    'tenant_id' => null,
                    'is_super_admin' => true,
                ]);
            } catch (\RuntimeException $e) {
                report($e);

                return ApiError::json(
                    'Configuração JWT inválida no servidor. Verifique JWT_SECRET no .env da apiV2.',
                    'JWT_CONFIG_ERROR',
                    500,
                );
            }
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

                try {
                    $token = $this->jwt->encode([
                        'user_id' => (int) $usuario->id,
                        'email' => $usuario->email,
                        'tenant_id' => $tenantId,
                        'aluno_id' => $alunoId,
                    ]);
                } catch (\RuntimeException $e) {
                    report($e);

                    return ApiError::json(
                        'Configuração JWT inválida no servidor. Verifique JWT_SECRET no .env da apiV2.',
                        'JWT_CONFIG_ERROR',
                        500,
                    );
                }
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

        $usuario = $this->usuarios->findAuthContext($userId);

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
        $usuario = $this->usuarios->findAuthContext($userId);

        if (! $usuario) {
            return ApiError::json('Usuário não encontrado', 'USER_NOT_FOUND', 404);
        }

        $papelId = (int) ($usuario['papel_id'] ?? 0);
        $alunoId = $papelId === 1
            ? $this->usuarios->findAlunoIdInTenant($userId, $tenantId)
            : null;

        try {
            $token = $this->jwt->encode([
                'user_id' => (int) $usuario['id'],
                'email' => $usuario['email'],
                'tenant_id' => $tenantId,
                'aluno_id' => $alunoId,
            ]);
        } catch (\RuntimeException $e) {
            report($e);

            return ApiError::json(
                'Configuração JWT inválida no servidor. Verifique JWT_SECRET no .env da apiV2.',
                'JWT_CONFIG_ERROR',
                500,
            );
        }

        $tenants = $this->usuarios->getTenantsByUsuario($userId);
        $tenantSelecionado = null;

        foreach ($tenants as $t) {
            if ((int) ($t['tenant']['id'] ?? 0) === $tenantId) {
                $tenantSelecionado = $t;
                break;
            }
        }

        $papeis = $this->usuarios->getPapeis($userId);
        $papelId = ! empty($papeis) ? $papeis[0]['id'] : ($usuario['papel_id'] ?? null);

        $payload = [
            'message' => 'Academia selecionada com sucesso',
            'token' => $token,
            'user' => [
                'id' => (int) $usuario['id'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
                'email_global' => $usuario['email_global'] ?? $usuario['email'],
                'foto_base64' => $usuario['foto_base64'] ?? null,
                'papel_id' => $papelId,
                'papeis' => $papeis,
            ],
            'tenant' => $tenantSelecionado,
        ];

        if ($includeAllTenants) {
            $payload['tenants'] = $tenants;
        }

        return response()->json($payload, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function register(string $nome, string $email, string $senha, int $tenantId, ?string $cpf = null): JsonResponse
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

        $cpfLimpo = null;
        if ($cpf !== null && trim($cpf) !== '') {
            $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf) ?: '';
            if (strlen($cpfLimpo) !== 11) {
                return ApiError::json(
                    'CPF deve ter 11 dígitos',
                    'INVALID_CPF',
                    422,
                );
            }
            if ($this->usuarios->cpfExists($cpfLimpo)) {
                return ApiError::json(
                    'CPF já cadastrado',
                    'CPF_ALREADY_EXISTS',
                    422,
                );
            }
        }

        $payload = [
            'nome' => $nome,
            'email' => $emailNorm,
            'senha' => $senha,
            'papel_id' => 1,
        ];
        if ($cpfLimpo !== null) {
            $payload['cpf'] = $cpfLimpo;
        }

        $usuarioId = $this->usuarios->createUsuario($payload, $tenantId, 1);

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

    public function requestPasswordRecovery(Request $request): JsonResponse
    {
        $email = (string) $request->input('email', '');

        if (trim($email) === '') {
            return ApiError::json('Email é obrigatório', 'MISSING_EMAIL', 422);
        }

        $rateLimiter = new RateLimiter(
            (int) config('appcheckin.rate_limit_password_recovery_max', 3),
            (int) config('appcheckin.rate_limit_password_recovery_decay', 15),
        );

        $clientIp = $this->clientIp($request);
        $emailNorm = mb_strtolower(trim($email), 'UTF-8');

        foreach ([[$clientIp, 'password-recovery'], ['email:'.md5($emailNorm), 'password-recovery']] as [$key, $action]) {
            $rateLimitResult = $rateLimiter->attempt($key, $action);
            if (! $rateLimitResult['allowed']) {
                $retryAfter = (int) ($rateLimitResult['retryAfter'] ?? 0);

                return response()->json([
                    'type' => 'error',
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Muitas tentativas. Tente novamente em '.max(1, (int) ceil($retryAfter / 60)).' minutos',
                    'retryAfter' => $retryAfter,
                ], 429, ['Retry-After' => (string) $retryAfter], JSON_UNESCAPED_UNICODE);
            }
        }

        $recaptchaToken = $request->input('recaptcha_token');
        $requireRecaptcha = (bool) config('appcheckin.password_recovery_require_recaptcha', false);

        if ($requireRecaptcha || ! empty($recaptchaToken)) {
            if (empty($recaptchaToken)) {
                return ApiError::json(
                    'Validação de segurança obrigatória',
                    'RECAPTCHA_REQUIRED',
                    403,
                );
            }

            $recaptcha = new ReCaptchaService(
                (string) config('appcheckin.recaptcha_secret', ''),
                (float) config('appcheckin.recaptcha_min_score', 0.5),
            );
            $recaptchaResult = $recaptcha->verify((string) $recaptchaToken, $clientIp);

            if (! $recaptchaResult['success']) {
                return ApiError::json(
                    'Falha na validação de segurança. Por favor, tente novamente',
                    'RECAPTCHA_VALIDATION_FAILED',
                    403,
                );
            }
        }

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

    public function tenantsPublic(): JsonResponse
    {
        try {
            $tenants = $this->tenants->listPublicActive();

            return response()->json([
                'success' => true,
                'data' => [
                    'tenants' => $tenants,
                    'total' => count($tenants),
                ],
            ], 200, [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'type' => 'error',
                'code' => 'TENANTS_PUBLIC_INTERNAL_ERROR',
                'message' => 'Erro ao listar academias ativas',
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    public function registerMobile(Request $request): JsonResponse
    {
        $rateLimiter = new RateLimiter(
            (int) config('appcheckin.rate_limit_register_max', 5),
            (int) config('appcheckin.rate_limit_register_decay', 15),
        );

        $clientIp = $this->clientIp($request);
        $rateLimitResult = $rateLimiter->attempt($clientIp, 'register-mobile');

        if (! $rateLimitResult['allowed']) {
            $retryAfter = (int) ($rateLimitResult['retryAfter'] ?? 0);

            return response()->json([
                'type' => 'error',
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => 'Muitas tentativas de cadastro. Tente novamente em '.ceil($retryAfter / 60).' minutos',
                'retryAfter' => $retryAfter,
            ], 429, ['Retry-After' => (string) $retryAfter], JSON_UNESCAPED_UNICODE);
        }

        $data = $request->all();
        $recaptchaToken = $data['recaptcha_token'] ?? null;

        if (! empty($recaptchaToken)) {
            $recaptcha = new ReCaptchaService(
                (string) config('appcheckin.recaptcha_secret', ''),
                (float) config('appcheckin.recaptcha_min_score', 0.5),
            );
            $recaptchaResult = $recaptcha->verify($recaptchaToken, $clientIp);

            if (! $recaptchaResult['success']) {
                return ApiError::json(
                    'Falha na validação de segurança. Por favor, tente novamente',
                    'RECAPTCHA_VALIDATION_FAILED',
                    403,
                );
            }
        }

        $nome = trim((string) ($data['nome'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $emailNorm = $email !== '' ? mb_strtolower($email, 'UTF-8') : '';
        $cpf = trim((string) ($data['cpf'] ?? ''));
        $dataNascimento = trim((string) ($data['data_nascimento'] ?? ''));
        $tenantId = isset($data['tenant_id']) ? (int) $data['tenant_id'] : null;
        if ($tenantId !== null && $tenantId <= 0) {
            $tenantId = null;
        }

        $telefone = isset($data['telefone']) ? preg_replace('/[^0-9]/', '', (string) $data['telefone']) : null;
        $whatsapp = isset($data['whatsapp']) ? preg_replace('/[^0-9]/', '', (string) $data['whatsapp']) : null;

        $erros = [];
        if ($nome === '') {
            $erros[] = 'nome é obrigatório';
        }
        if ($email === '') {
            $erros[] = 'email é obrigatório';
        }
        if ($cpf === '') {
            $erros[] = 'cpf é obrigatório';
        }
        if ($dataNascimento === '') {
            $erros[] = 'data_nascimento é obrigatória';
        }

        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf) ?: '';
        if ($cpfLimpo === '' || strlen($cpfLimpo) !== 11) {
            $erros[] = 'cpf inválido (use 11 dígitos)';
        }

        if ($dataNascimento !== '') {
            $dt = \DateTime::createFromFormat('Y-m-d', $dataNascimento);
            $dtErros = \DateTime::getLastErrors();
            if (
                ! $dt
                || $dt->format('Y-m-d') !== $dataNascimento
                || ($dtErros['warning_count'] ?? 0) > 0
                || ($dtErros['error_count'] ?? 0) > 0
            ) {
                $erros[] = 'data_nascimento inválida (use YYYY-MM-DD)';
            }
        }

        if ($erros !== []) {
            return response()->json([
                'type' => 'error',
                'code' => 'VALIDATION_ERROR',
                'errors' => $erros,
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        if ($this->usuarios->emailExists($emailNorm)) {
            return ApiError::json('Email já cadastrado', 'EMAIL_ALREADY_EXISTS', 409);
        }

        if ($this->usuarios->cpfExists($cpfLimpo)) {
            return ApiError::json('CPF já cadastrado', 'CPF_ALREADY_EXISTS', 409);
        }

        $payload = array_merge([
            'nome' => $nome,
            'email' => $emailNorm,
            'senha' => $cpfLimpo,
            'cpf' => $cpfLimpo,
            'data_nascimento' => $dataNascimento,
            'telefone' => $telefone,
            'whatsapp' => $whatsapp,
            'ativo' => 1,
            'papel_id' => 1,
        ], [
            'cep' => $data['cep'] ?? null,
            'logradouro' => $data['logradouro'] ?? null,
            'numero' => $data['numero'] ?? null,
            'complemento' => $data['complemento'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'cidade' => $data['cidade'] ?? null,
            'estado' => $data['estado'] ?? null,
        ]);

        try {
            $usuarioId = $this->usuarios->createMobileAluno($payload, $tenantId);
        } catch (\Throwable $e) {
            report($e);
            $usuarioId = null;
        }

        if (! $usuarioId) {
            $body = [
                'type' => 'error',
                'code' => 'USER_CREATION_FAILED',
                'message' => 'Não foi possível criar o usuário',
            ];
            if (app()->environment(['local', 'development'])) {
                $body['debug'] = 'createMobileAluno returned null';
            }

            return response()->json($body, 500, [], JSON_UNESCAPED_UNICODE);
        }

        $alunoId = $this->usuarios->findAlunoId($usuarioId);

        try {
            $token = $this->jwt->encode([
                'user_id' => $usuarioId,
                'email' => $emailNorm,
                'tenant_id' => $tenantId,
                'aluno_id' => $alunoId,
            ]);
        } catch (\RuntimeException $e) {
            report($e);

            return ApiError::json(
                'Configuração JWT inválida no servidor. Verifique JWT_SECRET no .env da apiV2.',
                'JWT_CONFIG_ERROR',
                500,
            );
        }

        $rateLimiter->reset($clientIp, 'register-mobile');

        $this->welcomeAlunoMailer->send($emailNorm, $nome, $cpfLimpo);

        return response()->json([
            'message' => 'Cadastro realizado com sucesso',
            'token' => $token,
            'user' => [
                'id' => $usuarioId,
                'nome' => mb_strtoupper($nome, 'UTF-8'),
                'email' => $email,
                'telefone' => $telefone,
                'whatsapp' => $whatsapp,
                'cpf' => $cpfLimpo,
                'data_nascimento' => $dataNascimento,
                'tenant_id' => $tenantId,
            ],
        ], 201, [], JSON_UNESCAPED_UNICODE);
    }

    private function clientIp(Request $request): string
    {
        foreach (['CF-Connecting-IP', 'X-Real-IP', 'X-Forwarded-For'] as $header) {
            $value = $request->header($header);
            if (! $value) {
                continue;
            }
            $ip = trim(explode(',', $value)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return $request->ip() ?? '0.0.0.0';
    }
}
