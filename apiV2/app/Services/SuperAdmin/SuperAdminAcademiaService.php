<?php

namespace App\Services\SuperAdmin;

use App\Repositories\AdminProfessorRepository;
use App\Repositories\PlanoSistemaRepository;
use App\Repositories\TenantPlanoRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UsuarioRepository;
use App\Services\EntidadeJuridica\EntidadeJuridicaException;
use App\Services\EntidadeJuridica\EntidadeJuridicaService;
use App\Services\PermissaoService;
use App\Support\TenantInstagram;
use App\Support\TenantWhatsappLinks;
use App\Support\TipoCobrancaPlanoSistema;
use Illuminate\Support\Facades\DB;

class SuperAdminAcademiaService
{
    public function __construct(
        private readonly TenantRepository $tenants,
        private readonly TenantPlanoRepository $contratos,
        private readonly UsuarioRepository $usuarios,
        private readonly SuperAdminPapelService $papeis,
        private readonly PlanoSistemaRepository $planosSistema,
        ?EntidadeJuridicaService $entidades = null,
        ?PermissaoService $permissoes = null,
        ?AdminProfessorRepository $professores = null,
    ) {
        $this->entidades = $entidades ?? app(EntidadeJuridicaService::class);
        $this->permissoes = $permissoes ?? app(PermissaoService::class);
        $this->professores = $professores ?? app(AdminProfessorRepository::class);
    }

    private EntidadeJuridicaService $entidades;

    private PermissaoService $permissoes;

    private AdminProfessorRepository $professores;

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

        $uso = $this->tenants->resumoUsoPorTenant();

        $academias = array_map(
            fn (array $academia) => TenantWhatsappLinks::attachToTenant($academia) + [
                'contrato' => $this->resumoContrato($this->contratos->buscarContratoVigente((int) $academia['id'])),
                'alunos_ativos' => $uso[(int) $academia['id']]['alunos_ativos'] ?? 0,
                'checkins_mes' => $uso[(int) $academia['id']]['checkins_mes'] ?? 0,
            ],
            $this->tenants->getAll($filtros),
        );

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
     * @param  array<string, mixed>|null  $contrato
     * @return array<string, mixed>|null
     */
    private function resumoContrato(?array $contrato): ?array
    {
        if (! $contrato) {
            return null;
        }

        return [
            'id' => (int) $contrato['id'],
            'plano_sistema_id' => (int) $contrato['plano_sistema_id'],
            'plano_nome' => $contrato['plano_nome'] ?? null,
            'plano_codigo' => $contrato['plano_codigo'] ?? null,
            'tipo_cobranca' => $contrato['tipo_cobranca'] ?? null,
            'status_id' => (int) $contrato['status_id'],
            'status_nome' => $contrato['status_nome'] ?? null,
            'data_inicio' => $contrato['data_inicio'] ?? null,
            'data_fim' => $contrato['data_fim'] ?? null,
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
            'body' => ['academia' => TenantWhatsappLinks::attachToTenant($academia) + [
                'contrato' => $this->resumoContrato($this->contratos->buscarContratoVigente($id)),
                'cnpj_vinculo' => $this->entidades->dadosDoTenant($id),
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criarAcademia(array $data, ?int $usuarioId = null): array
    {
        $errors = [];

        if (empty($data['nome'])) {
            $errors[] = 'Nome da academia é obrigatório';
        }

        if (empty($data['email']) || ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email válido é obrigatório';
        }

        $cnpj = EntidadeJuridicaService::normalizar($data['cnpj'] ?? null);
        if ($cnpj !== '' && ($erroCnpj = EntidadeJuridicaService::erroCnpj($cnpj)) !== null) {
            $errors[] = $erroCnpj;
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

        if (($instagramError = TenantInstagram::validar($data['instagram_url'] ?? null)) !== null) {
            $errors[] = $instagramError;
        }

        if (($whatsappError = TenantWhatsappLinks::validateForSave($data['whatsapp_links'] ?? null)) !== null) {
            $errors[] = $whatsappError;
        }

        $errors = array_merge($errors, $this->validarContratoInicial($data));

        if ($errors !== []) {
            return ['status' => 422, 'body' => ['errors' => $errors]];
        }

        // Política de CNPJ duplicado: recusa cedo; vincular() decide de novo com a entidade travada
        if ($cnpj !== '' && ($recusa = $this->entidades->avaliarCompartilhamento($cnpj)) !== null) {
            return ['status' => 409, 'body' => ['errors' => [$recusa['message']], 'code' => $recusa['code']]];
        }

        $academiaData = [
            'nome' => $data['nome'],
            'slug' => $slug,
            'email' => $data['email'],
            'telefone' => isset($data['telefone']) ? preg_replace('/[^0-9]/', '', (string) $data['telefone']) : null,
            'whatsapp_links' => TenantWhatsappLinks::parse($data['whatsapp_links'] ?? null),
            'instagram_url' => TenantInstagram::normalizar($data['instagram_url'] ?? null),
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
            // Tenant e vínculo do CNPJ juntos: se o CNPJ for recusado, a academia não fica criada
            $tenantId = DB::transaction(function () use ($academiaData, $cnpj, $usuarioId) {
                $tenantId = $this->tenants->create($academiaData);
                if ($tenantId && $cnpj !== '') {
                    $this->entidades->vincular($tenantId, $cnpj, $usuarioId, 'cadastro_academia');
                }

                return $tenantId;
            }, 3);
        } catch (EntidadeJuridicaException $e) {
            return ['status' => $e->status, 'body' => ['errors' => [$e->getMessage()], 'code' => $e->codigo]];
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

        $contratoId = $this->criarContratoInicial($tenantId, $data);

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
                'contrato' => $contratoId ? $this->contratos->buscarPorId($contratoId) : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function validarContratoInicial(array $data): array
    {
        $errors = [];

        if (! empty($data['plano_sistema_id'])) {
            $plano = $this->planosSistema->buscarPorId((int) $data['plano_sistema_id']);
            if (! $plano || ! (int) ($plano['ativo'] ?? 0)) {
                $errors[] = 'Plano do sistema não encontrado ou inativo';
            }
        }

        if (! empty($data['data_fim'])) {
            $dataFim = \DateTime::createFromFormat('!Y-m-d', (string) $data['data_fim']);
            if (! $dataFim || $dataFim->format('Y-m-d') !== $data['data_fim']) {
                $errors[] = 'Data de fim do contrato inválida (use AAAA-MM-DD)';
            } elseif ($data['data_fim'] < date('Y-m-d')) {
                $errors[] = 'Data de fim do contrato não pode ser anterior a hoje';
            }
        }

        return $errors;
    }

    /**
     * Toda academia nasce com contrato: o plano informado ou, por padrão, o Free (prazo indeterminado).
     * Planos pagos começam Pendente até a primeira cobrança ser registrada em Contratos.
     *
     * @param  array<string, mixed>  $data
     */
    private function criarContratoInicial(int $tenantId, array $data): ?int
    {
        $plano = ! empty($data['plano_sistema_id'])
            ? $this->planosSistema->buscarPorId((int) $data['plano_sistema_id'])
            : $this->planosSistema->buscarPorCodigo(TipoCobrancaPlanoSistema::CODIGO_FREE);

        if (! $plano) {
            error_log('Contrato inicial não criado: plano do sistema não encontrado para o tenant '.$tenantId);

            return null;
        }

        $gratuito = TipoCobrancaPlanoSistema::isGratuito($plano);

        try {
            return $this->contratos->criar([
                'tenant_id' => $tenantId,
                'plano_sistema_id' => $plano['id'],
                'status_id' => $gratuito ? 1 : 2,
                'data_inicio' => date('Y-m-d'),
                'data_fim' => ! empty($data['data_fim']) ? $data['data_fim'] : null,
                'observacoes' => $gratuito
                    ? 'Contrato Free criado junto com a academia'
                    : 'Contrato criado junto com a academia',
            ]);
        } catch (\Throwable $e) {
            error_log('Erro ao criar contrato inicial: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizarAcademia(int $tenantId, array $data, ?int $usuarioId = null): array
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

        // CNPJ só muda quando vem no payload e difere do atual (o vínculo segue a política de duplicidade)
        $cnpj = EntidadeJuridicaService::normalizar($data['cnpj'] ?? null);
        $trocarCnpj = array_key_exists('cnpj', $data) && $cnpj !== EntidadeJuridicaService::normalizar($academia['cnpj'] ?? null);
        if ($trocarCnpj && $cnpj !== '' && ($erroCnpj = EntidadeJuridicaService::erroCnpj($cnpj)) !== null) {
            $errors[] = $erroCnpj;
        }

        if (($instagramError = TenantInstagram::validar($data['instagram_url'] ?? null)) !== null) {
            $errors[] = $instagramError;
        }

        if (($whatsappError = TenantWhatsappLinks::validateForSave($data['whatsapp_links'] ?? null)) !== null) {
            $errors[] = $whatsappError;
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
            'telefone' => isset($data['telefone']) ? preg_replace('/[^0-9]/', '', (string) $data['telefone']) : $academia['telefone'],
            'instagram_url' => array_key_exists('instagram_url', $data)
                ? TenantInstagram::normalizar($data['instagram_url'])
                : ($academia['instagram_url'] ?? null),
            'whatsapp_links' => array_key_exists('whatsapp_links', $data)
                ? TenantWhatsappLinks::parse($data['whatsapp_links'])
                : TenantWhatsappLinks::parse($academia['whatsapp_links'] ?? null),
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

        try {
            $ok = DB::transaction(function () use ($tenantId, $academiaData, $trocarCnpj, $cnpj, $usuarioId) {
                if (! $this->tenants->update($tenantId, $academiaData)) {
                    return false;
                }
                if ($trocarCnpj) {
                    $this->entidades->definirCnpjDoTenant($tenantId, $cnpj, $usuarioId, 'superadmin_academia');
                }

                return true;
            }, 3);
        } catch (EntidadeJuridicaException $e) {
            return [
                'status' => $e->status,
                'body' => ['type' => 'error', 'code' => $e->codigo, 'message' => $e->getMessage()],
            ];
        }

        if (! $ok) {
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
            $admin['permissoes'] = $this->permissoes->concedidas($tenantId, (int) $admin['id'], true);
            $admin['admin_principal'] = (int) ($tenant['admin_principal_id'] ?? 0) === (int) $admin['id'];
        }
        unset($admin);

        return [
            'status' => 200,
            'body' => [
                'academia' => $tenant,
                'admins' => $admins,
                'total' => count($admins),
                // Aqui só as sensíveis (só o super admin concede); o resto é da tela de equipe da academia
                'permissoes_disponiveis' => $this->permissoes->catalogo(true),
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

        // Uma pessoa = um usuário: procura por e-mail e por CPF (ex.: o professor que vira admin)
        $usuarioExistente = null;
        if (! empty($data['email'])) {
            $usuarioExistente = $this->usuarios->findByEmailGlobal((string) $data['email']);
        }
        $cpfInformado = preg_replace('/[^0-9]/', '', (string) ($data['cpf'] ?? ''));
        $porCpf = $cpfInformado !== '' ? DB::table('usuarios')->where('cpf', $cpfInformado)->first() : null;
        if ($usuarioExistente && $porCpf && (int) $porCpf->id !== (int) $usuarioExistente->id) {
            return ['status' => 422, 'body' => ['errors' => ['Este CPF e este e-mail pertencem a cadastros diferentes. Unifique os cadastros antes.']]];
        }
        $achadoPeloCpf = ! $usuarioExistente && $porCpf;
        $usuarioExistente ??= $porCpf;

        $adminId = null;

        if ($usuarioExistente) {
            $adminId = (int) $usuarioExistente->id;

            $updateData = [];
            if (! empty($data['nome'])) {
                $updateData['nome'] = $data['nome'];
            }
            // Achado pelo CPF: o e-mail de login da pessoa não é trocado por este cadastro
            if (! empty($data['email']) && ! $achadoPeloCpf) {
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

            // Já na academia como professor/aluno: ganha o papel Admin (antes era recusado e
            // levava a cadastrar a mesma pessoa de novo com outro e-mail)
            if (in_array(3, $this->tenants->listarPapelIdsAtivos($tenantId, $adminId), true)) {
                return [
                    'status' => 422,
                    'body' => [
                        'error' => 'Esta pessoa já é admin desta academia. Edite-a na lista de administradores.',
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
        $this->garantirPerfilProfessor($adminId, $papeis);
        // Academia sem titular: o primeiro admin vira o admin principal
        DB::table('tenants')->where('id', $tenantId)->whereNull('admin_principal_id')->update(['admin_principal_id' => $adminId]);

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
            $this->garantirPerfilProfessor($adminId, $papeis);
        }

        // Permissões pontuais (ex.: ações financeiras avançadas): só o super admin concede
        if (isset($data['permissoes']) && is_array($data['permissoes']) && (int) ($usuarioLogado['papel_id'] ?? 0) === 4) {
            $sensiveis = array_column($this->permissoes->catalogo(true), 'codigo');
            $this->permissoes->definirConcessoes($tenantId, $adminId, array_values($data['permissoes']), $sensiveis, $userId ?: null);
        }

        $adminAtualizado = $this->usuarios->findById($adminId, null);
        $adminAtualizado['papeis'] = $this->tenants->listarPapelIdsAtivos($tenantId, $adminId);
        $adminAtualizado['permissoes'] = $this->permissoes->concedidas($tenantId, $adminId, true);

        return [
            'status' => 200,
            'body' => [
                'message' => 'Admin atualizado com sucesso',
                'admin' => $adminAtualizado,
            ],
        ];
    }

    /**
     * Define/transfere o admin principal (titular). Só o super admin; o alvo precisa ser admin ativo.
     *
     * @param  array<string, mixed>|null  $usuarioLogado
     * @return array{status: int, body: array<string, mixed>}
     */
    public function definirAdminPrincipal(int $tenantId, int $adminId, ?array $usuarioLogado, int $userId): array
    {
        if ((int) ($usuarioLogado['papel_id'] ?? 0) !== 4) {
            return ['status' => 403, 'body' => ['error' => 'Apenas o Super Admin define o admin principal']];
        }
        $tenant = $this->tenants->findById($tenantId);
        if (! $tenant) {
            return ['status' => 404, 'body' => ['error' => 'Academia não encontrada']];
        }
        if (! in_array(3, $this->tenants->listarPapelIdsAtivos($tenantId, $adminId), true)) {
            return ['status' => 422, 'body' => ['error' => 'O admin principal precisa ser um admin ativo desta academia']];
        }

        $anterior = $tenant['admin_principal_id'] ?? null;
        DB::table('tenants')->where('id', $tenantId)->update(['admin_principal_id' => $adminId, 'updated_at' => now()]);
        \Illuminate\Support\Facades\Log::info('academia.admin_principal', ['tenant_id' => $tenantId, 'anterior' => $anterior, 'novo' => $adminId, 'por' => $userId]);

        return ['status' => 200, 'body' => ['message' => 'Admin principal definido', 'admin_principal_id' => $adminId]];
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
            if ((int) ($tenant['admin_principal_id'] ?? 0) === $adminId) {
                return [
                    'status' => 400,
                    'body' => [
                        'error' => 'Este é o admin principal da academia. Defina outro admin principal antes de desativá-lo.',
                    ],
                ];
            }
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

    /**
     * Admin que também é professor precisa do perfil em professores: é por ele que a
     * lista de professores e as turmas enxergam a pessoa (só o papel não basta).
     *
     * @param  array<int|string>  $papeis
     */
    private function garantirPerfilProfessor(int $usuarioId, array $papeis): void
    {
        if (in_array(2, array_map('intval', $papeis), true)) {
            $this->professores->garantirPerfil($usuarioId);
        }
    }
}
