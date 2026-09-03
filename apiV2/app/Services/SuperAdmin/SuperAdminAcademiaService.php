<?php

namespace App\Services\SuperAdmin;

use App\Repositories\TenantPlanoRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UsuarioRepository;
use Illuminate\Support\Facades\DB;

class SuperAdminAcademiaService
{
    public function __construct(
        private readonly TenantRepository $tenants,
        private readonly TenantPlanoRepository $contratos,
        private readonly UsuarioRepository $usuarios,
        private readonly SuperAdminPapelService $papeis,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarAcademias(array $query): array
    {
        $semContratoAtivo = isset($query['sem_contrato_ativo']) && $query['sem_contrato_ativo'] === 'true';

        $filtros = [];
        if (! empty($query['busca'])) {
            $filtros['busca'] = $query['busca'];
        }
        if (isset($query['ativo'])) {
            $filtros['ativo'] = $query['ativo'] === 'true' || $query['ativo'] === '1';
        }

        $academias = $this->tenants->getAll($filtros);

        if ($semContratoAtivo) {
            $academias = array_values(array_filter(
                $academias,
                fn ($academia) => ! $this->contratos->buscarContratoAtivo((int) $academia['id']),
            ));
        }

        return [
            'status' => 200,
            'body' => ['academias' => $academias],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buscarAcademia(int $id): array
    {
        $academia = $this->tenants->findById($id);

        if (! $academia) {
            return [
                'status' => 404,
                'body' => ['error' => 'Academia não encontrada'],
            ];
        }

        return [
            'status' => 200,
            'body' => ['academia' => $academia],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criarAcademia(array $data): array
    {
        $errors = [];

        if (empty($data['nome'])) {
            $errors[] = 'Nome da academia é obrigatório';
        }

        if (empty($data['email']) || ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email válido é obrigatório';
        }

        if (! empty($data['cnpj'])) {
            $cnpj = preg_replace('/[^0-9]/', '', (string) $data['cnpj']);
            if (strlen($cnpj) != 14) {
                $errors[] = 'CNPJ inválido. Deve conter 14 dígitos';
            }
        }

        if (empty($data['senha_admin'])) {
            $errors[] = 'Senha do administrador é obrigatória';
        } elseif (strlen((string) $data['senha_admin']) < 6) {
            $errors[] = 'Senha do administrador deve ter no mínimo 6 caracteres';
        }

        $slug = $this->generateSlug((string) ($data['nome'] ?? ''));
        if ($this->tenants->findBySlug($slug)) {
            $errors[] = 'Já existe uma academia com este nome';
        }

        if ($this->usuarios->emailExists((string) ($data['email'] ?? ''), null, null)) {
            $errors[] = 'Email já está sendo utilizado por outro usuário';
        }

        if ($errors !== []) {
            return ['status' => 422, 'body' => ['errors' => $errors]];
        }

        $academiaData = [
            'nome' => $data['nome'],
            'slug' => $slug,
            'email' => $data['email'],
            'cnpj' => isset($data['cnpj']) ? preg_replace('/[^0-9]/', '', (string) $data['cnpj']) : null,
            'telefone' => isset($data['telefone']) ? preg_replace('/[^0-9]/', '', (string) $data['telefone']) : null,
            'responsavel_nome' => $data['responsavel_nome'] ?? null,
            'responsavel_cpf' => isset($data['responsavel_cpf']) ? preg_replace('/[^0-9]/', '', (string) $data['responsavel_cpf']) : null,
            'responsavel_telefone' => isset($data['responsavel_telefone']) ? preg_replace('/[^0-9]/', '', (string) $data['responsavel_telefone']) : null,
            'responsavel_email' => $data['responsavel_email'] ?? null,
            'cep' => $data['cep'] ?? null,
            'logradouro' => $data['logradouro'] ?? null,
            'numero' => $data['numero'] ?? null,
            'complemento' => $data['complemento'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'cidade' => $data['cidade'] ?? null,
            'estado' => $data['estado'] ?? null,
            'endereco' => $data['endereco'] ?? null,
        ];

        try {
            $tenantId = $this->tenants->create($academiaData);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() == 409 ? 409 : 500;

            return [
                'status' => $statusCode,
                'body' => ['error' => $e->getMessage()],
            ];
        }

        if (! $tenantId) {
            return [
                'status' => 500,
                'body' => ['error' => 'Erro ao criar academia'],
            ];
        }

        if (! empty($data['plano_sistema_id'])) {
            $contratoData = [
                'tenant_id' => $tenantId,
                'plano_sistema_id' => $data['plano_sistema_id'],
                'data_inicio' => date('Y-m-d'),
                'data_vencimento' => date('Y-m-d', strtotime('+1 month')),
                'forma_pagamento' => $data['forma_pagamento'] ?? 'pix',
                'observacoes' => 'Contrato criado junto com a academia',
            ];

            try {
                $this->contratos->criar($contratoData);
            } catch (\Exception $e) {
                error_log('Erro ao criar contrato inicial: '.$e->getMessage());
            }
        }

        $adminData = [
            'nome' => $data['responsavel_nome'] ?? $data['nome'],
            'email' => $data['responsavel_email'] ?? $data['email'],
            'senha' => $data['senha_admin'],
            'telefone' => isset($data['responsavel_telefone']) ? preg_replace('/[^0-9]/', '', (string) $data['responsavel_telefone']) : null,
            'cpf' => isset($data['responsavel_cpf']) ? preg_replace('/[^0-9]/', '', (string) $data['responsavel_cpf']) : null,
            'cep' => $data['cep'] ?? null,
            'logradouro' => $data['logradouro'] ?? null,
            'numero' => $data['numero'] ?? null,
            'complemento' => $data['complemento'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'cidade' => $data['cidade'] ?? null,
            'estado' => $data['estado'] ?? null,
            'papel_id' => 3,
        ];

        $adminId = $this->usuarios->criarUsuarioCompleto($adminData, $tenantId);

        if (! $adminId) {
            $this->tenants->delete($tenantId);

            return [
                'status' => 500,
                'body' => ['error' => 'Erro ao criar usuário administrador da academia'],
            ];
        }

        return [
            'status' => 201,
            'body' => [
                'message' => 'Academia e administrador criados com sucesso',
                'academia' => $this->tenants->findById($tenantId),
                'admin' => [
                    'id' => $adminId,
                    'nome' => $adminData['nome'],
                    'email' => $adminData['email'],
                    'papel_id' => 3,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizarAcademia(int $tenantId, array $data): array
    {
        $academia = $this->tenants->findById($tenantId);
        if (! $academia) {
            return [
                'status' => 404,
                'body' => [
                    'type' => 'error',
                    'message' => 'Academia não encontrada',
                ],
            ];
        }

        $errors = [];

        if (empty($data['nome'])) {
            $errors[] = 'Nome da academia é obrigatório';
        }

        if (empty($data['email']) || ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email válido é obrigatório';
        }

        $slug = $this->generateSlug((string) ($data['nome'] ?? ''));
        $existingAcademia = $this->tenants->findBySlug($slug);
        if ($existingAcademia && (int) $existingAcademia['id'] != $tenantId) {
            $errors[] = 'Já existe outra academia com este nome';
        }

        if (! empty($errors)) {
            return [
                'status' => 422,
                'body' => [
                    'type' => 'error',
                    'message' => implode(', ', $errors),
                ],
            ];
        }

        if (! empty($data['cnpj'])) {
            $cnpj = preg_replace('/[^0-9]/', '', (string) $data['cnpj']);
            if (strlen($cnpj) != 14) {
                $errors[] = 'CNPJ inválido. Deve conter 14 dígitos';
            }
        }

        if ($errors !== []) {
            return [
                'status' => 422,
                'body' => [
                    'type' => 'error',
                    'message' => implode(', ', $errors),
                ],
            ];
        }

        $academiaData = [
            'nome' => $data['nome'],
            'slug' => $slug,
            'email' => $data['email'],
            'cnpj' => isset($data['cnpj']) ? preg_replace('/[^0-9]/', '', (string) $data['cnpj']) : $academia['cnpj'],
            'telefone' => isset($data['telefone']) ? preg_replace('/[^0-9]/', '', (string) $data['telefone']) : $academia['telefone'],
            'responsavel_nome' => $data['responsavel_nome'] ?? $academia['responsavel_nome'],
            'responsavel_cpf' => isset($data['responsavel_cpf']) ? preg_replace('/[^0-9]/', '', (string) $data['responsavel_cpf']) : $academia['responsavel_cpf'],
            'responsavel_telefone' => isset($data['responsavel_telefone']) ? preg_replace('/[^0-9]/', '', (string) $data['responsavel_telefone']) : $academia['responsavel_telefone'],
            'responsavel_email' => $data['responsavel_email'] ?? $academia['responsavel_email'],
            'cep' => $data['cep'] ?? $academia['cep'],
            'logradouro' => $data['logradouro'] ?? $academia['logradouro'],
            'numero' => $data['numero'] ?? $academia['numero'],
            'complemento' => $data['complemento'] ?? $academia['complemento'],
            'bairro' => $data['bairro'] ?? $academia['bairro'],
            'cidade' => $data['cidade'] ?? $academia['cidade'],
            'estado' => $data['estado'] ?? $academia['estado'],
            'endereco' => $data['endereco'] ?? null,
            'ativo' => isset($data['ativo']) ? (filter_var($data['ativo'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 1,
        ];

        if (! $this->tenants->update($tenantId, $academiaData)) {
            return [
                'status' => 500,
                'body' => [
                    'type' => 'error',
                    'message' => 'Erro ao atualizar academia',
                ],
            ];
        }

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'Academia atualizada com sucesso',
                'academia' => $this->tenants->findById($tenantId),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function excluirAcademia(int $tenantId): array
    {
        $academia = $this->tenants->findById($tenantId);
        if (! $academia) {
            return [
                'status' => 404,
                'body' => [
                    'type' => 'error',
                    'message' => 'Academia não encontrada',
                ],
            ];
        }

        if ($tenantId == 1) {
            return [
                'status' => 400,
                'body' => [
                    'type' => 'error',
                    'message' => 'Não é possível excluir a academia do sistema',
                ],
            ];
        }

        if (! $this->tenants->delete($tenantId)) {
            return [
                'status' => 500,
                'body' => [
                    'type' => 'error',
                    'message' => 'Erro ao excluir academia',
                ],
            ];
        }

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'Academia desativada com sucesso',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $usuarioLogado
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarAdminsAcademia(int $tenantId, ?array $usuarioLogado, int $userId): array
    {
        $denied = $this->verificarPermissaoAdminAcademia($tenantId, $usuarioLogado, $userId, 'listar admins');
        if ($denied) {
            return $denied;
        }

        $tenant = $this->tenants->findById($tenantId);
        if (! $tenant) {
            return [
                'status' => 404,
                'body' => ['error' => 'Academia não encontrada'],
            ];
        }

        $admins = $this->tenants->listarAdmins($tenantId);
        $papeisMapa = $this->papeis->getPapeisMapa();

        foreach ($admins as &$admin) {
            $papelIds = $this->tenants->listarPapelIdsAtivos($tenantId, (int) $admin['id']);
            $admin['papeis'] = array_map(
                fn ($papelId) => [
                    'id' => $papelId,
                    'nome' => $papeisMapa[$papelId] ?? 'Desconhecido',
                ],
                $papelIds,
            );
        }
        unset($admin);

        return [
            'status' => 200,
            'body' => [
                'academia' => $tenant,
                'admins' => $admins,
                'total' => count($admins),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $usuarioLogado
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criarAdminAcademia(int $tenantId, array $data, ?array $usuarioLogado, int $userId): array
    {
        $denied = $this->verificarPermissaoAdminAcademia($tenantId, $usuarioLogado, $userId, 'criar admins');
        if ($denied) {
            return $denied;
        }

        $tenant = $this->tenants->findById($tenantId);
        if (! $tenant) {
            return [
                'status' => 404,
                'body' => ['error' => 'Academia não encontrada'],
            ];
        }

        $errors = [];

        if (empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }

        if (empty($data['email']) || ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email válido é obrigatório';
        }

        $papeis = isset($data['papeis']) && is_array($data['papeis']) ? $data['papeis'] : [3];
        if (! in_array(3, $papeis)) {
            $errors[] = 'Usuário deve ter pelo menos o papel de Admin';
        }
        foreach ($papeis as $papel) {
            if (! in_array($papel, [1, 2, 3])) {
                $errors[] = 'Papel inválido: '.$papel.'. Valores válidos: 1 (aluno), 2 (professor), 3 (admin)';
            }
        }

        if ($errors !== []) {
            return ['status' => 422, 'body' => ['errors' => $errors]];
        }

        $usuarioExistente = null;
        if (! empty($data['email'])) {
            $usuarioExistente = $this->usuarios->findByEmailGlobal((string) $data['email']);
        }

        $adminId = null;

        if ($usuarioExistente) {
            $adminId = (int) $usuarioExistente->id;

            $updateData = [];
            if (! empty($data['nome'])) {
                $updateData['nome'] = $data['nome'];
            }
            if (! empty($data['email'])) {
                $updateData['email'] = $data['email'];
            }
            if (isset($data['telefone'])) {
                $updateData['telefone'] = $data['telefone'];
            }
            if (isset($data['cpf'])) {
                $updateData['cpf'] = $data['cpf'];
            }

            if (! empty($data['senha'])) {
                if (strlen((string) $data['senha']) < 6) {
                    return [
                        'status' => 422,
                        'body' => ['errors' => ['Senha deve ter no mínimo 6 caracteres']],
                    ];
                }
                $updateData['senha'] = $data['senha'];
            }

            if ($updateData !== []) {
                $this->usuarios->atualizarPerfil($adminId, $updateData);
            }

            if ($this->tenants->usuarioTemVinculoTenant($tenantId, $adminId)) {
                return [
                    'status' => 422,
                    'body' => [
                        'error' => 'Usuário já está vinculado a esta academia. Use o endpoint de atualização para modificar os papéis.',
                    ],
                ];
            }
        } else {
            if (empty($data['senha']) || strlen((string) $data['senha']) < 6) {
                return [
                    'status' => 422,
                    'body' => ['errors' => ['Senha deve ter no mínimo 6 caracteres']],
                ];
            }

            $adminData = [
                'nome' => $data['nome'],
                'email' => $data['email'],
                'senha' => $data['senha'],
                'telefone' => isset($data['telefone']) ? preg_replace('/[^0-9]/', '', (string) $data['telefone']) : null,
                'cpf' => isset($data['cpf']) ? preg_replace('/[^0-9]/', '', (string) $data['cpf']) : null,
                'papel_id' => 3,
            ];

            $adminId = $this->usuarios->createUsuario($adminData, $tenantId, 3);

            if (! $adminId) {
                return [
                    'status' => 500,
                    'body' => [
                        'error' => 'Erro ao criar admin',
                        'details' => 'Erro desconhecido',
                    ],
                ];
            }
        }

        $this->tenants->atribuirPapeis($tenantId, $adminId, $papeis);

        $admin = $this->usuarios->findById($adminId, null);

        return [
            'status' => 201,
            'body' => [
                'message' => 'Admin criado com sucesso',
                'admin' => [
                    'id' => $admin['id'] ?? $adminId,
                    'nome' => $admin['nome'] ?? $data['nome'],
                    'email' => $admin['email'] ?? $data['email'],
                    'papeis' => $papeis,
                    'tenant' => $tenant,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $usuarioLogado
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizarAdminAcademia(int $tenantId, int $adminId, array $data, ?array $usuarioLogado, int $userId): array
    {
        $denied = $this->verificarPermissaoAdminAcademia($tenantId, $usuarioLogado, $userId, 'atualizar admins');
        if ($denied) {
            return $denied;
        }

        $tenant = $this->tenants->findById($tenantId);
        if (! $tenant) {
            return [
                'status' => 404,
                'body' => ['error' => 'Academia não encontrada'],
            ];
        }

        $admin = $this->usuarios->findById($adminId, null);
        if (! $admin) {
            return [
                'status' => 404,
                'body' => ['error' => 'Admin não encontrado'],
            ];
        }

        if (! $this->tenants->vinculoAdminExiste($tenantId, $adminId)) {
            return [
                'status' => 404,
                'body' => ['error' => 'Usuário não é admin desta academia'],
            ];
        }

        $errors = [];

        if (isset($data['nome']) && empty($data['nome'])) {
            $errors[] = 'Nome não pode ser vazio';
        }

        if (isset($data['email'])) {
            if (empty($data['email']) || ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email válido é obrigatório';
            }
            $existingUser = $this->usuarios->findByEmailGlobal((string) $data['email']);
            if ($existingUser && (int) $existingUser->id != $adminId) {
                $errors[] = 'Email já cadastrado por outro usuário';
            }
        }

        if ($errors !== []) {
            return ['status' => 422, 'body' => ['errors' => $errors]];
        }

        $updateData = [];
        if (isset($data['nome'])) {
            $updateData['nome'] = $data['nome'];
        }
        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }
        if (isset($data['telefone'])) {
            $updateData['telefone'] = preg_replace('/[^0-9]/', '', (string) $data['telefone']);
        }
        if (isset($data['cpf'])) {
            $updateData['cpf'] = preg_replace('/[^0-9]/', '', (string) $data['cpf']);
        }

        if (! empty($data['senha'])) {
            if (strlen((string) $data['senha']) < 6) {
                return [
                    'status' => 422,
                    'body' => ['errors' => ['Senha deve ter no mínimo 6 caracteres']],
                ];
            }
            $updateData['senha'] = $data['senha'];
        }

        if ($updateData !== []) {
            $this->usuarios->atualizarPerfil($adminId, $updateData);
        }

        if (isset($data['papeis']) && is_array($data['papeis'])) {
            $papeis = $data['papeis'];

            if (! in_array(3, $papeis)) {
                return [
                    'status' => 422,
                    'body' => ['errors' => ['Usuário deve manter pelo menos o papel de Admin']],
                ];
            }

            $this->tenants->substituirPapeis($tenantId, $adminId, $papeis);
        }

        $adminAtualizado = $this->usuarios->findById($adminId, null);
        $adminAtualizado['papeis'] = $this->tenants->listarPapelIdsAtivos($tenantId, $adminId);

        return [
            'status' => 200,
            'body' => [
                'message' => 'Admin atualizado com sucesso',
                'admin' => $adminAtualizado,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $usuarioLogado
     * @return array{status: int, body: array<string, mixed>}
     */
    public function desativarAdminAcademia(int $tenantId, int $adminId, array $data, ?array $usuarioLogado, int $userId): array
    {
        $denied = $this->verificarPermissaoAdminAcademia($tenantId, $usuarioLogado, $userId, 'desativar admins');
        if ($denied) {
            return $denied;
        }

        $tenant = $this->tenants->findById($tenantId);
        if (! $tenant) {
            return [
                'status' => 404,
                'body' => ['error' => 'Academia não encontrada'],
            ];
        }

        $papeisDesativar = isset($data['papeis']) && is_array($data['papeis']) ? $data['papeis'] : [3];

        $errors = [];
        foreach ($papeisDesativar as $papel) {
            if (! in_array($papel, [1, 2, 3])) {
                $errors[] = 'Papel inválido: '.$papel;
            }
        }

        if ($errors !== []) {
            return ['status' => 422, 'body' => ['errors' => $errors]];
        }

        if (in_array(3, $papeisDesativar, true)) {
            if ($this->tenants->contarAdminsAtivos($tenantId) <= 1) {
                return [
                    'status' => 400,
                    'body' => [
                        'error' => 'Não é possível desativar o único admin da academia. Crie outro admin primeiro.',
                    ],
                ];
            }
        }

        $desativados = [];
        foreach ($papeisDesativar as $papel) {
            $count = $this->tenants->desativarPapeis($tenantId, $adminId, [(int) $papel]);
            if ($count > 0) {
                $desativados[] = (int) $papel;
            }
        }

        if ($desativados === []) {
            return [
                'status' => 400,
                'body' => [
                    'error' => 'Nenhum papel foi desativado. Verifique se o usuário possui os papéis especificados.',
                ],
            ];
        }

        $papeisMapa = $this->papeis->getPapeisMapa();
        $nomesDesativados = array_map(fn ($p) => $papeisMapa[$p] ?? $p, $desativados);

        return [
            'status' => 200,
            'body' => [
                'message' => 'Papéis desativados com sucesso',
                'papeis_desativados' => $desativados,
                'nomes' => $nomesDesativados,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $usuarioLogado
     * @return array{status: int, body: array<string, mixed>}
     */
    public function reativarAdminAcademia(int $tenantId, int $adminId, ?array $usuarioLogado, int $userId): array
    {
        $denied = $this->verificarPermissaoAdminAcademia($tenantId, $usuarioLogado, $userId, 'reativar admins');
        if ($denied) {
            return $denied;
        }

        $tenant = $this->tenants->findById($tenantId);
        if (! $tenant) {
            return [
                'status' => 404,
                'body' => ['error' => 'Academia não encontrada'],
            ];
        }

        if (! $this->tenants->reativarAdmin($tenantId, $adminId)) {
            return [
                'status' => 500,
                'body' => ['error' => 'Erro ao reativar admin ou admin não encontrado'],
            ];
        }

        return [
            'status' => 200,
            'body' => ['message' => 'Admin reativado com sucesso'],
        ];
    }

    public function generateSlug(string $text): string
    {
        $text = strtolower($text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = preg_replace('/-+/', '-', $text);

        return trim($text, '-');
    }

    /**
     * @param  array<string, mixed>|null  $usuarioLogado
     * @return array{status: int, body: array<string, mixed>}|null
     */
    private function verificarPermissaoAdminAcademia(
        int $tenantId,
        ?array $usuarioLogado,
        int $userId,
        string $acao,
    ): ?array {
        $papelId = isset($usuarioLogado['papel_id']) ? (int) $usuarioLogado['papel_id'] : null;

        if (! in_array($papelId, [3, 4], true)) {
            return [
                'status' => 403,
                'body' => ['error' => "Acesso negado. Apenas Admin ou Super Admin podem {$acao}"],
            ];
        }

        if ($papelId === 3) {
            $count = DB::table('tenant_usuario_papel')
                ->where('tenant_id', $tenantId)
                ->where('usuario_id', $userId)
                ->where('papel_id', 3)
                ->where('ativo', 1)
                ->count();

            if ($count === 0) {
                return [
                    'status' => 403,
                    'body' => ['error' => "Você não tem permissão para {$acao} nesta academia"],
                ];
            }
        }

        return null;
    }
}
