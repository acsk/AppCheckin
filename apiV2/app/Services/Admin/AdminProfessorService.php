<?php

namespace App\Services\Admin;

use App\Repositories\AdminProfessorRepository;
use App\Repositories\UsuarioRepository;
use App\Support\Documento;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AdminProfessorService
{
    public function __construct(
        private readonly AdminProfessorRepository $professores,
        private readonly UsuarioRepository $usuarios,
        ?AdminEquipeService $equipe = null,
    ) {
        $this->equipe = $equipe ?? app(AdminEquipeService::class);
    }

    private AdminEquipeService $equipe;

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

        $data = $this->normalizar($data);
        if (($invalido = $this->validar($data, $tenantId)) !== null) {
            return $invalido;
        }

        // Uma pessoa = um usuário: o admin que também dá aula vira professor no mesmo cadastro.
        // Antes só o e-mail era procurado e o mesmo CPF com outro e-mail era recusado (ou duplicado).
        $usuarioExistente = $this->usuarios->findByEmailGlobal((string) $data['email']);
        $porCpf = $this->professores->findUsuarioByCpfGlobal($cpfLimpo);
        if ($usuarioExistente && $porCpf && (int) $porCpf['id'] !== (int) $usuarioExistente->id) {
            return $this->error('Este CPF e este e-mail pertencem a cadastros diferentes no Check-in. Use o e-mail do cadastro desta pessoa ou peça ao suporte para unificar.', 409);
        }
        $usuarioExistente ??= $porCpf ? (object) $porCpf : null;

        // Limite do plano: conta toda a equipe; quem já é da equipe (ex.: admin) não ocupa vaga nova
        if (($limite = $this->equipe->verificarVaga($tenantId, $usuarioExistente ? (int) $usuarioExistente->id : null)) !== null) {
            return $limite;
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
                $this->salvarComplementos($professorId, $tenantId, $data);

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

        $data = $this->normalizar($data);
        if (($invalido = $this->validar($data, $tenantId)) !== null) {
            return $invalido;
        }

        try {
            DB::transaction(function () use ($id, $tenantId, $professor, $data) {
                // Ativo/inativo é nesta academia (papel Professor), não no perfil que vale para todas
                if (array_key_exists('ativo', $data)) {
                    $this->professores->associarAoTenant($id, $tenantId, (bool) $data['ativo']);
                    unset($data['ativo']);
                }
                $this->professores->atualizar($id, array_diff_key($data, array_flip(self::DA_ACADEMIA)));
                $this->salvarDadosNaAcademia($id, $tenantId, $data);

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

        $turmas = $this->professores->contarTurmasAtivas($id, $tenantId);
        if ($turmas > 0) {
            return $this->error("Este professor tem {$turmas} ".($turmas === 1 ? 'aula ativa' : 'aulas ativas').'. Troque o professor dessas aulas ou desative o cadastro.', 409);
        }

        try {
            // Sai só desta academia: o perfil e o login continuam valendo nas outras
            DB::transaction(fn () => $this->professores->removerDoTenant($id, $tenantId));

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Professor removido da academia',
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao deletar professor: '.$e->getMessage(), 500);
        }
    }

    /**
     * Tipos de vínculo (CLT, PJ…) para o formulário.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function vinculos(): array
    {
        return ['status' => 200, 'body' => ['vinculos' => $this->professores->listarVinculos()]];
    }

    /**
     * E-mail livre para o professor? Ocupado por outro cadastro devolve o nome dele, para o aviso no formulário.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function emailDisponivel(string $email, ?int $usuarioId): array
    {
        $email = mb_strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('E-mail inválido', 422);
        }

        $dono = $this->professores->usuarioPorEmail($email);
        $livre = ! $dono || ($usuarioId !== null && $dono['id'] === $usuarioId);

        return ['status' => 200, 'body' => ['disponivel' => $livre, 'nome' => $livre ? null : $dono['nome']]];
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

    /** Campos de professor_tenant / professor_modalidades (por academia). */
    private const DA_ACADEMIA = ['vinculo_id', 'admitido_em', 'observacoes', 'modalidade_ids'];

    /** Campos do cadastro completo além de nome/e-mail/CPF/telefone. */
    private const COMPLEMENTO = ['data_nascimento', 'whatsapp', 'cref', 'cref_validade', 'emergencia_nome', 'emergencia_telefone', 'emergencia_parentesco',
        'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado'];

    private const SO_DIGITOS = ['cpf', 'telefone', 'whatsapp', 'emergencia_telefone', 'cep'];

    /** Limites de tamanho (iguais às colunas). */
    private const TAMANHOS = ['nome' => 255, 'emergencia_nome' => 120, 'emergencia_parentesco' => 60, 'logradouro' => 255,
        'numero' => 20, 'complemento' => 100, 'bairro' => 100, 'cidade' => 100, 'observacoes' => 500];

    /** Idade mínima para dar aula. */
    private const IDADE_MINIMA = 16;

    /**
     * Texto vazio vira null (o formulário limpa o campo), documentos e telefones só dígitos, CREF e UF no formato.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizar(array $data): array
    {
        foreach ($data as $campo => $valor) {
            if (is_string($valor)) {
                $valor = trim($valor);
                $data[$campo] = $valor === '' && $campo !== 'senha' ? null : $valor;
            }
        }
        foreach (self::SO_DIGITOS as $campo) {
            if (isset($data[$campo])) {
                $data[$campo] = Documento::digitos((string) $data[$campo]) ?: null;
            }
        }
        if (isset($data['email'])) {
            $data['email'] = mb_strtolower((string) $data['email']);
        }
        if (isset($data['cref'])) {
            $data['cref'] = Documento::normalizarCref((string) $data['cref']);
        }
        if (isset($data['estado'])) {
            $data['estado'] = strtoupper((string) $data['estado']);
        }
        if (array_key_exists('vinculo_id', $data)) {
            $data['vinculo_id'] = $data['vinculo_id'] !== null ? (int) $data['vinculo_id'] : null;
        }
        if (array_key_exists('modalidade_ids', $data)) {
            $data['modalidade_ids'] = array_values(array_unique(array_map('intval', array_filter((array) $data['modalidade_ids'], 'is_numeric'))));
        }

        return $data;
    }

    /**
     * Valida só o que veio (o PUT pode mandar um campo só). Devolve 422 com a lista e o erro de cada campo.
     *
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}|null
     */
    private function validar(array $data, int $tenantId): ?array
    {
        $erros = [];
        $hoje = CarbonImmutable::today();
        $tem = fn (string $c) => isset($data[$c]);

        if (array_key_exists('nome', $data) && mb_strlen((string) $data['nome']) < 3) {
            $erros['nome'] = 'Informe o nome completo';
        }
        if ($tem('cpf') && ! Documento::cpfValido((string) $data['cpf'])) {
            $erros['cpf'] = 'CPF inválido';
        }
        if ($tem('email') && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $erros['email'] = 'E-mail inválido';
        }
        foreach (['telefone' => 'Celular', 'emergencia_telefone' => 'Telefone de emergência'] as $campo => $rotulo) {
            if ($tem($campo) && ! in_array(strlen((string) $data[$campo]), [10, 11], true)) {
                $erros[$campo] = "{$rotulo} inválido: use DDD + número";
            }
        }
        if ($tem('whatsapp') && ! preg_match('/^\d{2}9\d{8}$/', (string) $data['whatsapp'])) {
            $erros['whatsapp'] = 'WhatsApp inválido: use DDD + celular com 9 dígitos';
        }

        if ($tem('data_nascimento')) {
            $nascimento = $this->data($data['data_nascimento']);
            if (! $nascimento || $nascimento->isAfter($hoje)) {
                $erros['data_nascimento'] = 'Data de nascimento inválida';
            } elseif ($nascimento->diffInYears($hoje) < self::IDADE_MINIMA) {
                $erros['data_nascimento'] = 'O professor precisa ter pelo menos '.self::IDADE_MINIMA.' anos';
            } elseif ($nascimento->diffInYears($hoje) > 100) {
                $erros['data_nascimento'] = 'Data de nascimento inválida';
            }
        }

        if ($tem('cref') && ! Documento::crefValido((string) $data['cref'])) {
            $erros['cref'] = 'CREF inválido: use o formato 000000-G/UF';
        }
        if ($tem('cref_validade') && ! $this->data($data['cref_validade'])) {
            $erros['cref_validade'] = 'Validade da carteira inválida';
        }
        if ($tem('admitido_em')) {
            $desde = $this->data($data['admitido_em']);
            if (! $desde || $desde->isAfter($hoje)) {
                $erros['admitido_em'] = '"Na academia desde" não pode ser uma data futura';
            }
        }

        if ($tem('vinculo_id') && ! $this->professores->vinculoExiste((int) $data['vinculo_id'])) {
            $erros['vinculo_id'] = 'Tipo de vínculo inválido';
        }
        if (! empty($data['modalidade_ids']) && ! $this->professores->modalidadesSaoDoTenant($data['modalidade_ids'], $tenantId)) {
            $erros['modalidade_ids'] = 'Modalidade não encontrada nesta academia';
        }

        // Contato de emergência: nome e telefone andam juntos
        if ($tem('emergencia_nome') && array_key_exists('emergencia_telefone', $data) && ! $tem('emergencia_telefone')) {
            $erros['emergencia_telefone'] ??= 'Informe o telefone do contato de emergência';
        }
        if ($tem('emergencia_telefone') && array_key_exists('emergencia_nome', $data) && ! $tem('emergencia_nome')) {
            $erros['emergencia_nome'] = 'Informe o nome do contato de emergência';
        }

        if ($tem('cep') && ! Documento::cepValido((string) $data['cep'])) {
            $erros['cep'] = 'CEP inválido: são 8 dígitos';
        }
        if ($tem('estado') && ! Documento::ufValida((string) $data['estado'])) {
            $erros['estado'] = 'UF inválida';
        }

        foreach (self::TAMANHOS as $campo => $max) {
            if ($tem($campo) && mb_strlen((string) $data[$campo]) > $max) {
                $erros[$campo] ??= "Máximo de {$max} caracteres";
            }
        }

        if ($erros === []) {
            return null;
        }

        return [
            'status' => 422,
            'body' => [
                'type' => 'error',
                'message' => reset($erros),
                'errors' => array_values($erros),
                'campos' => $erros,
            ],
        ];
    }

    /** Aceita Y-m-d (ou d/m/Y) e devolve null se não for uma data de verdade. */
    private function data(mixed $valor): ?CarbonImmutable
    {
        $valor = (string) $valor;
        foreach (['Y-m-d', 'd/m/Y'] as $formato) {
            $d = CarbonImmutable::createFromFormat('!'.$formato, $valor);
            if ($d && $d->format($formato) === $valor) {
                return $d;
            }
        }

        return null;
    }

    /**
     * Depois de criar/associar: cadastro completo e dados da academia, se vieram.
     *
     * @param  array<string, mixed>  $data
     */
    private function salvarComplementos(int $professorId, int $tenantId, array $data): void
    {
        $complemento = array_intersect_key($data, array_flip(self::COMPLEMENTO));
        if ($complemento !== []) {
            $this->professores->atualizar($professorId, $complemento);
        }
        $this->salvarDadosNaAcademia($professorId, $tenantId, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function salvarDadosNaAcademia(int $professorId, int $tenantId, array $data): void
    {
        $daAcademia = array_intersect_key($data, array_flip(self::DA_ACADEMIA));
        if ($daAcademia !== []) {
            $this->professores->salvarDadosNaAcademia($professorId, $tenantId, $daAcademia);
        }
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
