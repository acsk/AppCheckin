<?php

namespace App\Services\SuperAdmin;

use App\Repositories\UsuarioRepository;

class SuperAdminUsuarioService
{
    public function __construct(
        private readonly UsuarioRepository $usuarios,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(array $query): array
    {
        $apenasAtivos = isset($query['ativos']) && $query['ativos'] === 'true';
        $usuarios = $this->usuarios->listarTodos(true, null, $apenasAtivos);

        return [
            'status' => 200,
            'body' => ['total' => count($usuarios), 'usuarios' => $usuarios],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function show(int $id): array
    {
        $usuario = $this->usuarios->findById($id, null);
        if (! $usuario) {
            return ['status' => 404, 'body' => ['error' => 'Usuário não encontrado']];
        }

        return ['status' => 200, 'body' => $usuario];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function update(int $id, array $data): array
    {
        $usuario = $this->usuarios->findById($id, null);
        if (! $usuario) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Usuário não encontrado']];
        }

        $errors = $this->validarDadosUsuario($data, (int) ($usuario['tenant_id'] ?? 0), $id);
        if ($errors !== []) {
            return [
                'status' => 422,
                'body' => ['type' => 'error', 'message' => implode(', ', $errors)],
            ];
        }

        if (! $this->usuarios->atualizarPerfil($id, $data)) {
            return ['status' => 400, 'body' => ['type' => 'error', 'message' => 'Nenhum dado foi atualizado']];
        }

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'Usuário atualizado com sucesso',
                'usuario' => $this->usuarios->findById($id, null),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function delete(int $id): array
    {
        $usuario = $this->usuarios->findById($id, null);
        if (! $usuario) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Usuário não encontrado']];
        }

        $papelId = (int) ($usuario['papel_id'] ?? 0);
        if ($papelId === 4) {
            return ['status' => 403, 'body' => ['type' => 'error', 'message' => 'Não é permitido alterar status de usuários SuperAdmin']];
        }
        if ($papelId === 3) {
            return [
                'status' => 403,
                'body' => ['type' => 'error', 'message' => 'Não é permitido alterar status de administradores de academias/tenants'],
            ];
        }

        if (! $this->usuarios->toggleStatusUsuarioTenant($id, (int) $usuario['tenant_id'])) {
            return ['status' => 500, 'body' => ['type' => 'error', 'message' => 'Erro ao alterar status do usuário']];
        }

        $estavaAtivo = (bool) ($usuario['ativo'] ?? true);
        $acao = $estavaAtivo ? 'desativado' : 'ativado';

        return [
            'status' => 200,
            'body' => ['type' => 'success', 'message' => "Usuário {$acao} com sucesso"],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function validarDadosUsuario(array $data, int $tenantId, ?int $excludeId = null): array
    {
        $errors = [];

        if (isset($data['nome']) && empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }

        if (isset($data['email'])) {
            if (empty($data['email'])) {
                $errors[] = 'Email é obrigatório';
            } elseif (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email inválido';
            } elseif ($this->usuarios->emailExists((string) $data['email'], $excludeId)) {
                $errors[] = 'Email já cadastrado';
            }
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
}
