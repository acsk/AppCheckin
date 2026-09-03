<?php

namespace App\Services\Admin;

use App\Repositories\UsuarioRepository;

class AdminUsuarioService
{
    public function __construct(
        private readonly UsuarioRepository $usuarios,
    ) {}

    /**
     * GET /tenant/usuarios — a Slim devolve um array puro (sem envelope).
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $usuarioLogado
     * @return array{status: int, body: list<array<string, mixed>>}
     */
    public function index(int $tenantId, array $query, ?array $usuarioLogado): array
    {
        $apenasAtivos = ($query['ativos'] ?? null) === 'true';
        $isSuperAdmin = $this->isSuperAdmin($usuarioLogado);

        $usuarios = $isSuperAdmin
            ? $this->usuarios->listarTodos(true, null, $apenasAtivos)
            : $this->usuarios->listarPorTenant($tenantId, $apenasAtivos);

        // Admins não aparecem na tela de usuários (só alunos e professores).
        if (! $isSuperAdmin) {
            $usuarios = array_filter(
                $usuarios,
                fn ($usuario) => in_array((int) ($usuario['papel_id'] ?? 1), [1, 2], true),
            );
        }

        return [
            'status' => 200,
            'body' => array_values($usuarios),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $usuarioLogado
     * @return array{status: int, body: array<string, mixed>}
     */
    public function show(int $id, int $tenantId, ?array $usuarioLogado): array
    {
        $isSuperAdmin = $this->isSuperAdmin($usuarioLogado);
        $usuario = $this->usuarios->findById($id, $isSuperAdmin ? null : $tenantId);

        if (! $usuario) {
            return [
                'status' => 404,
                'body' => ['error' => 'Usuário não encontrado'],
            ];
        }

        if (! $isSuperAdmin && (int) ($usuario['papel_id'] ?? 0) >= 3) {
            return [
                'status' => 403,
                'body' => ['error' => 'Usuários administradores só podem ser visualizados pela tela de Academia'],
            ];
        }

        return ['status' => 200, 'body' => $usuario];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function create(int $tenantId, array $data): array
    {
        $errors = $this->validarDadosUsuario($data);
        if ($errors !== []) {
            return $this->erro(implode(', ', $errors), 422);
        }

        $usuarioId = $this->usuarios->criarUsuarioCompleto($data, $tenantId);
        if (! $usuarioId) {
            return $this->erro('Erro ao criar usuário', 500);
        }

        return [
            'status' => 201,
            'body' => [
                'type' => 'success',
                'message' => 'Usuário criado com sucesso',
                'usuario' => $this->usuarios->findById($usuarioId, $tenantId),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $usuarioLogado
     * @return array{status: int, body: array<string, mixed>}
     */
    public function update(int $id, int $tenantId, array $data, ?array $usuarioLogado): array
    {
        $isSuperAdmin = $this->isSuperAdmin($usuarioLogado);
        $existente = $this->usuarios->findById($id, $isSuperAdmin ? null : $tenantId);

        if (! $existente) {
            return $this->erro('Usuário não encontrado', 404);
        }

        if (! $isSuperAdmin && (int) ($existente['papel_id'] ?? 0) >= 3) {
            return $this->erro('Usuários administradores só podem ser editados pela tela de Academia', 403);
        }

        $errors = $this->validarDadosUsuario($data, $id);
        if ($errors !== []) {
            return $this->erro(implode(', ', $errors), 422);
        }

        if (! $this->usuarios->atualizarPerfil($id, $data)) {
            return $this->erro('Nenhum dado foi atualizado', 400);
        }

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'Usuário atualizado com sucesso',
                'usuario' => $this->usuarios->findById($id, $tenantId),
            ],
        ];
    }

    /**
     * DELETE /tenant/usuarios/{id} — alterna o vínculo com o tenant (soft delete).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function delete(int $id, int $tenantId, int $userId): array
    {
        $userAuth = $this->usuarios->findById($userId, $tenantId);
        $isSuperAdmin = $userAuth !== null && (int) ($userAuth['papel_id'] ?? 0) === 4;

        if ($isSuperAdmin) {
            $usuario = $this->usuarios->findById($id, null);
            if (! $usuario) {
                return $this->erro('Usuário não encontrado', 404);
            }

            $papelId = (int) ($usuario['papel_id'] ?? 0);
            if ($papelId === 4) {
                return $this->erro('Não é permitido alterar status de usuários SuperAdmin', 403);
            }
            if ($papelId === 3) {
                return $this->erro('Não é permitido alterar status de administradores de academias/tenants', 403);
            }

            $toggled = $this->usuarios->toggleStatusUsuarioTenant($id, (int) $usuario['tenant_id']);
        } else {
            $usuario = $this->usuarios->findById($id, $tenantId);
            if (! $usuario) {
                return $this->erro('Usuário não encontrado', 404);
            }

            $toggled = $this->usuarios->toggleStatusUsuarioTenant($id, $tenantId);
        }

        if (! $toggled) {
            return $this->erro('Erro ao alterar status do usuário', 500);
        }

        // findById só retorna vínculo ativo (tup.ativo = 1), então após desativar a
        // segunda busca viria null. A ação é o inverso do estado lido antes do toggle.
        $estavaAtivo = (bool) ($usuario['ativo'] ?? true);
        $acao = $estavaAtivo ? 'desativado' : 'ativado';

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => "Usuário {$acao} com sucesso",
            ],
        ];
    }

    /**
     * GET /tenant/usuarios/buscar-cpf/{cpf} — busca global, fora do tenant atual.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buscarPorCpf(string $cpf, int $tenantId): array
    {
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf) ?? '';

        if (strlen($cpfLimpo) !== 11) {
            return [
                'status' => 400,
                'body' => ['error' => 'CPF deve conter 11 dígitos'],
            ];
        }

        if (! $this->validarCpf($cpfLimpo)) {
            return [
                'status' => 400,
                'body' => ['error' => 'CPF inválido'],
            ];
        }

        $usuario = $this->usuarios->findByCpfGlobal($cpfLimpo);
        if (! $usuario) {
            return [
                'status' => 200,
                'body' => [
                    'found' => false,
                    'message' => 'Usuário não encontrado. Você pode cadastrar um novo usuário.',
                ],
            ];
        }

        $usuarioId = (int) $usuario['id'];
        $jaAssociado = $this->usuarios->isAssociatedWithTenant($usuarioId, $tenantId);

        return [
            'status' => 200,
            'body' => [
                'found' => true,
                'usuario' => [
                    'id' => $usuarioId,
                    'nome' => $usuario['nome'] ?? null,
                    'email' => $usuario['email'] ?? null,
                    'telefone' => $usuario['telefone'] ?? null,
                    'cpf' => $usuario['cpf'] ?? null,
                ],
                'tenants' => $this->usuarios->getTenantsByUsuario($usuarioId),
                'ja_associado' => $jaAssociado,
                'pode_associar' => ! $jaAssociado,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function associar(int $tenantId, array $data): array
    {
        if (empty($data['usuario_id'])) {
            return [
                'status' => 400,
                'body' => ['error' => 'ID do usuário é obrigatório'],
            ];
        }

        $usuarioId = (int) $data['usuario_id'];
        $status = $data['status'] ?? 'ativo';

        if (! $this->usuarios->findById($usuarioId, null)) {
            return [
                'status' => 404,
                'body' => ['error' => 'Usuário não encontrado'],
            ];
        }

        if ($this->usuarios->isAssociatedWithTenant($usuarioId, $tenantId)) {
            return [
                'status' => 409,
                'body' => ['error' => 'Usuário já está associado a esta academia'],
            ];
        }

        if (! $this->usuarios->associateToTenant($usuarioId, $tenantId, (string) $status)) {
            return [
                'status' => 500,
                'body' => ['error' => 'Erro ao associar usuário'],
            ];
        }

        return [
            'status' => 200,
            'body' => [
                'message' => 'Usuário associado com sucesso',
                'usuario' => $this->usuarios->findById($usuarioId, $tenantId),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function estatisticas(int $id, int $tenantId): array
    {
        $estatisticas = $this->usuarios->getEstatisticas($id, $tenantId);

        if (! $estatisticas) {
            return $this->erro('Usuário não encontrado', 404);
        }

        return ['status' => 200, 'body' => $estatisticas];
    }

    /**
     * GET /admin/admins — usada em selects do painel (ex.: autorizado_por).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function admins(int $tenantId): array
    {
        return [
            'status' => 200,
            'body' => ['admins' => $this->usuarios->listarAdminsDoTenant($tenantId)],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $usuarioLogado
     */
    private function isSuperAdmin(?array $usuarioLogado): bool
    {
        return isset($usuarioLogado['papel_id']) && (int) $usuarioLogado['papel_id'] === 4;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function validarDadosUsuario(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if (empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }

        if (empty($data['email'])) {
            $errors[] = 'Email é obrigatório';
        } elseif (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido';
        } elseif ($this->usuarios->emailExists((string) $data['email'], $excludeId)) {
            $errors[] = 'Email já cadastrado';
        }

        if (! $excludeId && empty($data['senha'])) {
            $errors[] = 'Senha é obrigatória';
        }

        if (! empty($data['senha']) && strlen((string) $data['senha']) < 6) {
            $errors[] = 'Senha deve ter no mínimo 6 caracteres';
        }

        if (! empty($data['cpf'])) {
            $cpfLimpo = preg_replace('/[^0-9]/', '', (string) $data['cpf']) ?? '';
            if (strlen($cpfLimpo) !== 11) {
                $errors[] = 'CPF deve conter 11 dígitos';
            } elseif (! $this->validarCpf($cpfLimpo)) {
                $errors[] = 'CPF inválido';
            } elseif ($this->usuarios->cpfExists($cpfLimpo, $excludeId)) {
                $errors[] = 'CPF já cadastrado';
            }
        }

        if (! empty($data['cep'])) {
            $cepLimpo = preg_replace('/[^0-9]/', '', (string) $data['cep']) ?? '';
            if (strlen($cepLimpo) !== 8) {
                $errors[] = 'CEP deve conter 8 dígitos';
            }
        }

        if (! empty($data['estado']) && strlen((string) $data['estado']) !== 2) {
            $errors[] = 'Estado deve ter 2 caracteres (sigla UF)';
        }

        return $errors;
    }

    private function validarCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += (int) $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ((int) $cpf[$c] !== $d) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function erro(string $message, int $status): array
    {
        return [
            'status' => $status,
            'body' => [
                'type' => 'error',
                'message' => $message,
            ],
        ];
    }
}
