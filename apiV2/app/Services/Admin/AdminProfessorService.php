<?php

namespace App\Services\Admin;

use App\Repositories\AdminProfessorRepository;
use App\Repositories\UsuarioRepository;
use Illuminate\Support\Facades\DB;

class AdminProfessorService
{
    public function __construct(
        private readonly AdminProfessorRepository $professores,
        private readonly UsuarioRepository $usuarios,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(int $tenantId, bool $apenasAtivos): array
    {
        return [
            'status' => 200,
            'body' => [
                'professores' => $this->professores->listarPorTenant($tenantId, $apenasAtivos),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function show(int $id, int $tenantId): array
    {
        $professor = $this->professores->findById($id, $tenantId);
        if (! $professor) {
            return $this->error('Professor não encontrado', 404);
        }

        return [
            'status' => 200,
            'body' => ['professor' => $professor],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buscarPorCpf(string $cpf, int $tenantId): array
    {
        $cpfLimpo = $this->limparCpf($cpf);
        if (strlen($cpfLimpo) !== 11) {
            return $this->error('CPF inválido. Deve conter 11 dígitos.', 400);
        }

        $professor = $this->professores->findByCpf($cpfLimpo, $tenantId);
        if (! $professor) {
            return $this->error('Professor não encontrado com este CPF', 404);
        }

        return [
            'status' => 200,
            'body' => ['professor' => $professor],
        ];
    }

    /**
     * Busca cross-tenant: usada para associar professor já existente em outra academia.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buscarPorCpfGlobal(string $cpf, int $tenantId): array
    {
        $cpfLimpo = $this->limparCpf($cpf);
        if (strlen($cpfLimpo) !== 11) {
            return $this->error('CPF inválido. Deve conter 11 dígitos.', 400);
        }

        $professor = $this->professores->findByCpfGlobal($cpfLimpo);
        if (! $professor) {
            return $this->error('Professor não encontrado no sistema', 404);
        }

        $professor['vinculado_ao_tenant_atual'] = $this->professores->pertenceAoTenant(
            (int) $professor['id'],
            $tenantId,
        );

        return [
            'status' => 200,
            'body' => ['professor' => $professor],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function create(int $tenantId, array $data): array
    {
        if (empty($data['nome'])) {
            return $this->error('Nome do professor é obrigatório', 400);
        }

        if (empty($data['email'])) {
            return $this->error('Email é obrigatório para criar professor', 400);
        }

        if (empty($data['cpf'])) {
            return $this->error('CPF é obrigatório para criar professor', 400);
        }

        $cpfLimpo = $this->limparCpf((string) $data['cpf']);
        if (strlen($cpfLimpo) !== 11) {
            return $this->error('CPF inválido. Deve conter 11 dígitos', 400);
        }

        $usuarioExistente = $this->usuarios->findByEmailGlobal((string) $data['email']);

        if (! $usuarioExistente && $this->professores->findUsuarioByCpfGlobal($cpfLimpo)) {
            return $this->error('CPF já cadastrado para outro usuário no sistema', 409);
        }

        $professorExistente = null;
        if ($usuarioExistente) {
            $professorExistente = $this->professores->findByUsuarioId((int) $usuarioExistente->id);

            if ($professorExistente && $this->professores->pertenceAoTenant((int) $professorExistente['id'], $tenantId)) {
                return $this->error('Professor já está vinculado a este tenant', 409);
            }
        }

        try {
            $senhaTemporaria = $usuarioExistente ? null : $this->gerarSenhaTemporaria();

            $resultado = DB::transaction(function () use ($tenantId, $data, $cpfLimpo, $usuarioExistente, $professorExistente, $senhaTemporaria) {
                if ($usuarioExistente) {
                    $usuarioId = (int) $usuarioExistente->id;
                    $usuarioCriado = false;
                } else {
                    $usuarioId = $this->usuarios->createUsuario([
                        'nome' => $data['nome'],
                        'email' => $data['email'],
                        'senha' => $senhaTemporaria,
                        'telefone' => $data['telefone'] ?? null,
                        'cpf' => $cpfLimpo,
                        'ativo' => 1,
                    ], $tenantId, 1);

                    if (! $usuarioId) {
                        throw new \RuntimeException('Erro ao criar usuário');
                    }

                    $usuarioCriado = true;
                }

                if ($professorExistente) {
                    $professorId = (int) $professorExistente['id'];
                } else {
                    $professorId = $this->professores->criar([
                        'usuario_id' => $usuarioId,
                        'nome' => $data['nome'],
                        'cpf' => $cpfLimpo,
                        'email' => $data['email'],
                        'foto_url' => $data['foto_url'] ?? null,
                        'ativo' => 1,
                    ]);
                }

                $this->professores->associarAoTenant($professorId, $tenantId);

                return [
                    'usuario_id' => $usuarioId,
                    'usuario_criado' => $usuarioCriado,
                    'professor' => $this->professores->findById($professorId, $tenantId),
                ];
            });

            $body = [
                'type' => 'success',
                'message' => $professorExistente
                    ? 'Professor existente associado ao tenant com sucesso'
                    : 'Professor criado com sucesso',
                'professor' => $resultado['professor'],
                'usuario' => [
                    'id' => $resultado['usuario_id'],
                    'criado' => $resultado['usuario_criado'],
                    'vinculado_ao_tenant' => true,
                    'papel' => 'professor',
                ],
                'professor_existia' => $professorExistente !== null,
            ];

            if ($resultado['usuario_criado'] && $senhaTemporaria) {
                $body['credenciais'] = [
                    'email' => $data['email'],
                    'senha_temporaria' => $senhaTemporaria,
                    'mensagem' => 'Informe estas credenciais ao professor. Recomende trocar a senha no primeiro acesso.',
                ];
            }

            return ['status' => 201, 'body' => $body];
        } catch (\Throwable $e) {
            return $this->error('Erro ao criar professor: '.$e->getMessage(), 500);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function update(int $id, int $tenantId, array $data): array
    {
        $professor = $this->professores->findById($id, $tenantId);
        if (! $professor) {
            return $this->error('Professor não encontrado', 404);
        }

        if (! empty($data['email'])
            && $this->professores->emailEmUsoPorOutroUsuario((string) $data['email'], (int) $professor['usuario_id'])
        ) {
            return $this->error('Email já está em uso por outro usuário', 422);
        }

        try {
            DB::transaction(function () use ($id, $professor, $data) {
                $this->professores->atualizar($id, $data);

                if (isset($data['email']) || isset($data['senha'])) {
                    $usuarioData = [];
                    if (isset($data['email'])) {
                        $usuarioData['email'] = $data['email'];
                    }
                    if (! empty($data['senha'])) {
                        $usuarioData['senha'] = $data['senha'];
                    }
                    if ($usuarioData !== []) {
                        $this->usuarios->updateAuthFields((int) $professor['usuario_id'], $usuarioData);
                    }
                }
            });

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Professor atualizado com sucesso',
                    'professor' => $this->professores->findById($id, $tenantId),
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao atualizar professor: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function delete(int $id, int $tenantId): array
    {
        $professor = $this->professores->findById($id, $tenantId);
        if (! $professor) {
            return $this->error('Professor não encontrado', 404);
        }

        try {
            $this->professores->softDelete($id);

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Professor deletado com sucesso',
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao deletar professor: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function turmas(int $professorId, int $tenantId): array
    {
        if (! $this->professores->pertenceAoTenant($professorId, $tenantId)) {
            return $this->error('Professor não encontrado', 404);
        }

        return [
            'status' => 200,
            'body' => [
                'turmas' => $this->professores->listarTurmas($professorId, $tenantId),
            ],
        ];
    }

    private function limparCpf(string $cpf): string
    {
        return preg_replace('/[^0-9]/', '', $cpf) ?? '';
    }

    private function gerarSenhaTemporaria(int $tamanho = 8): string
    {
        $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $senha = '';
        for ($i = 0; $i < $tamanho; $i++) {
            $senha .= $caracteres[random_int(0, strlen($caracteres) - 1)];
        }

        return $senha;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function error(string $message, int $status): array
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
