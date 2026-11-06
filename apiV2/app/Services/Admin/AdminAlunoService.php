<?php

namespace App\Services\Admin;

use App\Repositories\AdminAlunoRepository;
use App\Repositories\UsuarioRepository;
use App\Support\Documento;
use App\Support\Pessoa;
use App\Support\ResponsavelLegal;
use App\Support\SexoAluno;
use Illuminate\Support\Facades\DB;

class AdminAlunoService
{
    public function __construct(
        private readonly AdminAlunoRepository $alunos,
        private readonly UsuarioRepository $usuarios,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(int $tenantId, array $query): array
    {
        try {
            $apenasAtivos = ($query['apenas_ativos'] ?? null) === 'true';
            $busca = $query['busca'] ?? null;
            $pagina = (int) ($query['pagina'] ?? 1);
            $porPagina = (int) ($query['por_pagina'] ?? 50);

            // Lista do painel: paginada, filtrada e contada no banco (só a página é enriquecida)
            if (isset($query['pagina'])) {
                $pagina = max(1, $pagina);
                $porPagina = max(5, min(100, $porPagina));
                $r = $this->alunos->listarPainel($tenantId, (string) ($busca ?? ''), (string) ($query['filtro'] ?? 'todos'), $pagina, $porPagina);

                return [
                    'status' => 200,
                    'body' => [
                        'alunos' => $this->enriquecerPagina($r['rows'], $tenantId),
                        'total' => $r['total'],
                        'contagens' => $r['contagens'],
                        'pagina' => $pagina,
                        'por_pagina' => $porPagina,
                        'total_paginas' => (int) ceil($r['total'] / $porPagina),
                    ],
                ];
            }

            if ($busca) {
                $lista = $this->alunos->listarPaginado($tenantId, max(1, $pagina), $porPagina, $busca);

                return ['status' => 200, 'body' => ['alunos' => $this->enriquecerAlunos($lista, $tenantId), 'total' => count($lista)]];
            }

            $lista = $this->alunos->listarPorTenant($tenantId, $apenasAtivos);

            return [
                'status' => 200,
                'body' => [
                    'alunos' => $this->enriquecerAlunos($lista, $tenantId),
                    'total' => count($lista),
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'body' => [
                    'status' => 'error',
                    'message' => 'Erro ao listar alunos',
                    'details' => [
                        'type' => $e::class,
                        'error' => $e->getMessage(),
                    ],
                ],
            ];
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarBasico(int $tenantId): array
    {
        $lista = $this->alunos->listarBasico($tenantId);

        return [
            'status' => 200,
            'body' => [
                'alunos' => $lista,
                'total' => count($lista),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function show(int $id, int $tenantId): array
    {
        $aluno = $this->alunos->findById($id, $tenantId);
        if (! $aluno) {
            return $this->error('Aluno não encontrado', 404);
        }

        $aluno = $this->enriquecerAluno($aluno, $tenantId);
        $aluno['responsavel_conta'] = $this->contaDoResponsavel((int) $aluno['usuario_id'], $tenantId);
        $aluno['dependentes'] = $this->dependentesDe((int) $aluno['usuario_id'], $tenantId);

        return [
            'status' => 200,
            'body' => ['aluno' => $aluno],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function create(int $tenantId, array $data): array
    {
        $errors = $this->validarDados($data, null, $tenantId, false);
        if ($errors !== []) {
            return [
                'status' => 422,
                'body' => [
                    'type' => 'error',
                    'message' => 'Erro de validação',
                    'errors' => $errors,
                ],
            ];
        }

        try {
            $aluno = DB::transaction(function () use ($tenantId, $data) {
                $usuarioId = $this->usuarios->createUsuario($data, $tenantId, 1);
                if (! $usuarioId) {
                    throw new \RuntimeException('Erro ao criar usuário');
                }

                // Completar campos de perfil que createUsuario não grava
                $alunoCriado = $this->alunos->findByUsuarioId($usuarioId);
                if ($alunoCriado) {
                    $extra = array_intersect_key($data, array_flip([
                        'data_nascimento', 'sexo', 'whatsapp', 'logradouro', 'numero',
                        'complemento', 'bairro', 'cidade', 'estado', ...ResponsavelLegal::CAMPOS,
                    ]));
                    if ($extra !== []) {
                        $this->alunos->update((int) $alunoCriado['id'], $extra);
                    }
                }

                $this->usuarios->ligarAoResponsavel($usuarioId);

                return $this->alunos->findByUsuarioId($usuarioId);
            });

            return [
                'status' => 201,
                'body' => [
                    'type' => 'success',
                    'message' => 'Aluno criado com sucesso',
                    'aluno' => $this->enriquecerAluno($aluno ?? [], $tenantId),
                ],
            ];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate') !== false || str_contains($msg, '1062')) {
                $duplicado = 'Email ou CPF já cadastrado';
                if (stripos($msg, 'cpf') !== false) {
                    $duplicado = 'CPF já cadastrado';
                } elseif (stripos($msg, 'email') !== false) {
                    $duplicado = 'Email já cadastrado';
                }

                return [
                    'status' => 409,
                    'body' => [
                        'type' => 'error',
                        'code' => 'DUPLICATE_IDENTITY',
                        'message' => $duplicado,
                        'errors' => [$duplicado],
                    ],
                ];
            }

            return $this->error('Erro ao criar aluno: '.$msg, 500);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function update(int $id, int $tenantId, array $data): array
    {
        $aluno = $this->alunos->findById($id, $tenantId);
        if (! $aluno) {
            return $this->error('Aluno não encontrado', 404);
        }

        $errors = $this->validarDados($data, (int) $aluno['usuario_id'], $tenantId, true, $aluno);
        if ($errors !== []) {
            return [
                'status' => 422,
                'body' => [
                    'type' => 'error',
                    'message' => 'Erro de validação',
                    'errors' => $errors,
                ],
            ];
        }

        try {
            DB::transaction(function () use ($id, $aluno, $data) {
                $this->alunos->update($id, $data);

                if (isset($data['email']) || isset($data['senha']) || isset($data['nome']) || array_key_exists('cpf', $data)) {
                    $usuarioData = [];
                    if (isset($data['email'])) {
                        $usuarioData['email'] = $data['email'];
                    }
                    if (! empty($data['senha'])) {
                        $usuarioData['senha'] = $data['senha'];
                    }
                    if (isset($data['nome'])) {
                        $usuarioData['nome'] = $data['nome'];
                    }
                    if (array_key_exists('cpf', $data)) {
                        $usuarioData['cpf'] = $data['cpf'];
                    }
                    $this->usuarios->updateAuthFields((int) $aluno['usuario_id'], $usuarioData);
                }
                if (array_key_exists('responsavel_cpf', $data)) {
                    $this->usuarios->ligarAoResponsavel((int) $aluno['usuario_id']);
                }
            });

            $atualizado = $this->alunos->findById($id, $tenantId);

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Aluno atualizado com sucesso',
                    'aluno' => $this->enriquecerAluno($atualizado ?? [], $tenantId),
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao atualizar aluno: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function delete(int $id, int $tenantId): array
    {
        $aluno = $this->alunos->findById($id, $tenantId);
        if (! $aluno) {
            return $this->error('Aluno não encontrado', 404);
        }

        try {
            DB::transaction(function () use ($id, $aluno, $tenantId) {
                $this->alunos->softDelete($id);
                $this->alunos->desativarPapelAluno((int) $aluno['usuario_id'], $tenantId);
            });

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Aluno desativado com sucesso',
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao desativar aluno: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function deletePreview(int $id, int $tenantId): array
    {
        $aluno = $this->alunos->findById($id, $tenantId);
        if (! $aluno) {
            return $this->error('Aluno não encontrado', 404);
        }

        return [
            'status' => 200,
            'body' => $this->alunos->getDeletePreview($id),
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function hardDelete(int $id, int $tenantId): array
    {
        $aluno = $this->alunos->findById($id, $tenantId);
        if (! $aluno) {
            return $this->error('Aluno não encontrado', 404);
        }

        try {
            if ($this->alunos->hardDelete($id)) {
                return [
                    'status' => 200,
                    'body' => [
                        'type' => 'success',
                        'message' => 'Aluno e dados associados deletados permanentemente',
                        'warning' => 'Esta operação é irreversível',
                    ],
                ];
            }

            return $this->error('Falha ao deletar aluno', 500);
        } catch (\Throwable $e) {
            return $this->error('Erro ao deletar aluno: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function historicoPlanos(int $id, int $tenantId): array
    {
        $aluno = $this->alunos->findById($id, $tenantId);
        if (! $aluno) {
            return $this->error('Aluno não encontrado', 404);
        }

        return [
            'status' => 200,
            'body' => [
                'historico' => $this->alunos->historicoPlanos((int) $aluno['usuario_id'], $tenantId),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buscarPorCpf(string $cpf, int $tenantId): array
    {
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf) ?? '';
        if (strlen($cpfLimpo) !== 11) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'CPF deve conter 11 dígitos'],
            ];
        }
        if (! $this->validarCpf($cpfLimpo)) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'CPF inválido'],
            ];
        }

        $aluno = $this->alunos->findByCpf($cpfLimpo);
        if (! $aluno) {
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'found' => false,
                    'message' => 'Aluno não encontrado. Você pode cadastrar um novo aluno.',
                ],
            ];
        }

        $usuarioId = (int) ($aluno['usuario_id'] ?? 0);
        $jaAssociado = $usuarioId > 0 && $this->alunos->jaAssociadoAoTenant($usuarioId, $tenantId);
        $tenants = $usuarioId > 0 ? $this->alunos->tenantsDoAluno($usuarioId) : [];

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'found' => true,
                'aluno' => [
                    'id' => (int) $aluno['id'],
                    'usuario_id' => $usuarioId,
                    'nome' => $aluno['nome'],
                    'email' => $aluno['email'] ?? null,
                    'telefone' => $aluno['telefone'],
                    'cpf' => $aluno['cpf'],
                ],
                'tenants' => $tenants,
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
        if (empty($data['aluno_id'])) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'ID do aluno é obrigatório'],
            ];
        }

        $alunoId = (int) $data['aluno_id'];
        $aluno = $this->alunos->findRawById($alunoId);
        if (! $aluno) {
            return [
                'status' => 404,
                'body' => ['success' => false, 'error' => 'Aluno não encontrado'],
            ];
        }

        $usuarioId = (int) $aluno['usuario_id'];
        if ($this->alunos->jaAssociadoAoTenant($usuarioId, $tenantId)) {
            return [
                'status' => 409,
                'body' => ['success' => false, 'error' => 'Aluno já está associado a esta academia'],
            ];
        }

        try {
            $this->alunos->garantirVinculoAluno($usuarioId, $tenantId);
            $atualizado = $this->alunos->findById($alunoId, $tenantId);

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Aluno associado com sucesso',
                    'aluno' => $atualizado,
                ],
            ];
        } catch (\Throwable) {
            return [
                'status' => 500,
                'body' => ['success' => false, 'error' => 'Erro ao associar aluno'],
            ];
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function checkins(int $id, int $tenantId, array $params): array
    {
        $modalidadeId = isset($params['modalidade_id']) ? (int) $params['modalidade_id'] : null;
        $mes = isset($params['mes']) ? (int) $params['mes'] : null;
        $ano = isset($params['ano']) ? (int) $params['ano'] : null;

        if ($mes && $ano) {
            $checkins = $this->alunos->checkinsDoMes($id, $tenantId, $mes, $ano, $modalidadeId);

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'mes' => $mes,
                    'ano' => $ano,
                    'total' => count($checkins),
                    'checkins' => $checkins,
                ],
            ];
        }

        $meses = $this->alunos->checkinsResumoMensal($id, $tenantId, $modalidadeId);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'total_geral' => array_sum(array_map(fn ($m) => (int) $m['total'], $meses)),
                'meses' => $meses,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $alunos
     * @return list<array<string, mixed>>
     */
    /**
     * Mesmo formato de enriquecerAluno, com três consultas para a página inteira.
     *
     * @param  list<array<string, mixed>>  $alunos  linhas de listarPainel (já trazem em_dia)
     * @return list<array<string, mixed>>
     */
    private function enriquecerPagina(array $alunos, int $tenantId): array
    {
        $lote = $this->alunos->resumoEmLote(array_map(fn ($a) => (int) $a['id'], $alunos), $tenantId);

        return array_map(function ($a) use ($lote) {
            $id = (int) $a['id'];
            $m = $lote['matriculas'][$id] ?? null;
            $a['plano'] = $m ? [
                'id' => $m['plano_id'], 'nome' => $m['plano_nome'], 'valor' => $m['plano_valor'],
                'modalidade_nome' => $m['modalidade_nome'] ?? null, 'modalidade_icone' => $m['modalidade_icone'] ?? null,
            ] : null;
            $a['matricula_id'] = $m['id'] ?? null;
            $a['matricula_valor'] = $m['valor'] ?? null;
            $a['acesso_ate'] = $m ? ($m['proxima_data_vencimento'] ?? ($m['data_fim'] ?? null)) : null;
            $a['pagamento_ativo'] = $m ? (bool) $a['em_dia'] : null;
            $a['total_checkins'] = $lote['checkins'][$id]['total'] ?? 0;
            $a['ultimo_checkin'] = $lote['checkins'][$id]['ultimo'] ?? null;
            unset($a['em_dia'], $a['tem_plano']);

            return $a;
        }, $alunos);
    }

    private function enriquecerAlunos(array $alunos, int $tenantId): array
    {
        return array_map(fn ($a) => $this->enriquecerAluno($a, $tenantId), $alunos);
    }

    /**
     * @param  array<string, mixed>  $aluno
     * @return array<string, mixed>
     */
    private function enriquecerAluno(array $aluno, int $tenantId): array
    {
        if ($aluno === [] || empty($aluno['id'])) {
            return $aluno;
        }

        $alunoId = (int) $aluno['id'];
        $matricula = $this->alunos->matriculaAtiva($alunoId, $tenantId);

        if ($matricula) {
            $aluno['plano'] = [
                'id' => $matricula['plano_id'],
                'nome' => $matricula['plano_nome'],
                'valor' => $matricula['plano_valor'],
                'modalidade_nome' => $matricula['modalidade_nome'] ?? null,
                'modalidade_icone' => $matricula['modalidade_icone'] ?? null,
            ];
            $aluno['matricula_id'] = $matricula['id'];
            $aluno['matricula_valor'] = $matricula['valor'] ?? null;
            $aluno['acesso_ate'] = $matricula['proxima_data_vencimento'] ?? ($matricula['data_fim'] ?? null);
            $aluno['pagamento_ativo'] = $this->alunos->temPagamentoAtivo($alunoId, $tenantId);
        } else {
            $aluno['plano'] = null;
            $aluno['matricula_id'] = null;
            $aluno['matricula_valor'] = null;
            $aluno['acesso_ate'] = null;
            $aluno['pagamento_ativo'] = null;
        }

        $checkins = $this->alunos->resumoCheckins($alunoId, $tenantId);
        $aluno['total_checkins'] = $checkins['total'];
        $aluno['ultimo_checkin'] = $checkins['ultimo'];

        return $aluno;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function validarDados(array $data, ?int $usuarioIdExcluir, int $tenantId, bool $isUpdate, array $atual = []): array
    {
        $errors = [];
        // Menor de idade com responsável pode ficar sem e-mail, CPF e senha: entra pelo perfil do responsável
        $dependente = ! $isUpdate && ResponsavelLegal::ehMenor(isset($data['data_nascimento']) ? (string) $data['data_nascimento'] : null)
            && ! empty($data['responsavel_cpf']);

        if (! $isUpdate && empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }

        if (! $isUpdate && ! ($dependente && empty($data['email']))) {
            if (empty($data['email'])) {
                $errors[] = 'Email é obrigatório';
            } elseif (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email inválido';
            } elseif ($this->usuarios->emailExists((string) $data['email'], $usuarioIdExcluir)) {
                $errors[] = 'Email já cadastrado';
            }
        } elseif (isset($data['email']) && $data['email'] === '' && $this->semEmailPermitido(array_merge($atual, $data))) {
            // Menor com responsável pode ficar sem e-mail (perfil de dependente)
        } elseif (isset($data['email'])) {
            if (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email inválido';
            } elseif ($this->usuarios->emailExists((string) $data['email'], $usuarioIdExcluir)) {
                $errors[] = 'Email já cadastrado';
            }
        }

        if (! $isUpdate && ! ($dependente && empty($data['email']))) {
            if (empty($data['senha'])) {
                $errors[] = 'Senha é obrigatória';
            } elseif (strlen((string) $data['senha']) < 6) {
                $errors[] = 'Senha deve ter no mínimo 6 caracteres';
            }
        } elseif (isset($data['senha']) && $data['senha'] !== '' && strlen((string) $data['senha']) < 6) {
            $errors[] = 'Senha deve ter no mínimo 6 caracteres';
        }

        if (! $isUpdate && ! ($dependente && empty($data['cpf']))) {
            if (empty($data['cpf'])) {
                $errors[] = 'CPF é obrigatório';
            } else {
                $cpfLimpo = preg_replace('/[^0-9]/', '', (string) $data['cpf']);
                if (strlen((string) $cpfLimpo) !== 11) {
                    $errors[] = 'CPF deve ter 11 dígitos';
                } elseif ($this->usuarios->cpfExists((string) $cpfLimpo, $usuarioIdExcluir)) {
                    $errors[] = 'CPF já cadastrado';
                }
            }
        } elseif (array_key_exists('cpf', $data) && $data['cpf'] !== null && $data['cpf'] !== '') {
            $cpfLimpo = preg_replace('/[^0-9]/', '', (string) $data['cpf']);
            if (strlen((string) $cpfLimpo) !== 11) {
                $errors[] = 'CPF deve ter 11 dígitos';
            } elseif ($this->usuarios->cpfExists((string) $cpfLimpo, $usuarioIdExcluir)) {
                $errors[] = 'CPF já cadastrado';
            }
        }

        if (isset($data['sexo']) && $data['sexo'] !== '' && SexoAluno::normalizar($data['sexo']) === null) {
            $errors[] = 'Sexo inválido';
        }

        foreach (['telefone', 'whatsapp'] as $campo) {
            if (! empty($data[$campo]) && ! \App\Support\Telefone::valido($data[$campo])) {
                $errors[] = \App\Support\Telefone::ERRO;
            }
        }

        // Menor de idade precisa de responsável. Na edição vale o que fica gravado (o que veio por cima do atual),
        // e só quando a requisição mexe no nascimento ou no responsável: desativar um menor antigo sem
        // responsável não deve travar.
        $efetivo = array_merge($atual, $data);
        $tocaResponsavel = ! $isUpdate
            || array_intersect_key($data, array_flip(['data_nascimento', ...ResponsavelLegal::CAMPOS])) !== [];
        if ($tocaResponsavel && ResponsavelLegal::ehMenor(isset($efetivo['data_nascimento']) ? (string) $efetivo['data_nascimento'] : null)) {
            $responsavel = array_intersect_key($efetivo, array_flip(ResponsavelLegal::CAMPOS));
            array_push($errors, ...ResponsavelLegal::validar($responsavel, isset($efetivo['cpf']) ? (string) $efetivo['cpf'] : null));
        }

        return $errors;
    }

    /**
     * Situação do menor no app: ligado (ou não) à conta do responsável e com PIN de login próprio.
     * Tenta ligar de novo pelo CPF antes de responder.
     *
     * @return array<string, mixed>|null
     */
    private function contaDoResponsavel(int $usuarioId, int $tenantId): ?array
    {
        if (! \App\Support\Pessoa::existe('usuarios', 'responsavel_usuario_id')) {
            return null;
        }
        $u = DB::table('usuarios')->where('id', $usuarioId)->first(['responsavel_cpf', 'responsavel_usuario_id', 'email']);
        if (! $u || (! $u->responsavel_cpf && ! $u->responsavel_usuario_id)) {
            return null;
        }
        if (! $u->responsavel_usuario_id) {
            $this->usuarios->ligarAoResponsavel($usuarioId);
            $u->responsavel_usuario_id = DB::table('usuarios')->where('id', $usuarioId)->value('responsavel_usuario_id');
        }
        $temPin = \App\Support\Pessoa::existe('usuarios', 'dependente_pin_hash')
            && DB::table('usuarios')->where('id', $usuarioId)->whereNotNull('dependente_pin_hash')->exists();
        if (! $u->responsavel_usuario_id) {
            return ['ligado' => false, 'tem_pin' => false, 'tem_email' => (bool) $u->email];
        }
        $r = DB::table('usuarios')->where('id', $u->responsavel_usuario_id)->first(['id', 'nome', 'email']);

        return [
            'ligado' => true,
            'usuario_id' => (int) $u->responsavel_usuario_id,
            'nome' => $r->nome ?? null,
            'email' => $r->email ?? null,
            'aluno_id' => $r ? $this->alunos->findByUsuarioIdAndTenant((int) $r->id, $tenantId)['id'] ?? null : null,
            'tem_pin' => $temPin,
            'tem_email' => (bool) $u->email,
        ];
    }

    /**
     * Define ou troca, pelo painel, o PIN do login próprio do menor (e-mail do responsável + PIN).
     * O responsável recebe um aviso no app.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function definirPinDependente(int $alunoId, int $tenantId, string $pin): array
    {
        $dep = $this->dependenteDoTenant($alunoId, $tenantId);
        if (isset($dep['erro'])) {
            return $dep['erro'];
        }
        $r = app(\App\Services\Mobile\MobileDependenteService::class)->definirPin((int) $dep['responsavel_usuario_id'], (int) $dep['usuario_id'], $pin);
        if ($r['status'] !== 200) {
            return ['status' => $r['status'], 'body' => ['type' => 'error', 'message' => $r['body']['message'] ?? 'Não foi possível salvar o PIN']];
        }
        $this->avisarResponsavel((int) $dep['responsavel_usuario_id'], $tenantId, (int) $dep['usuario_id'],
            'PIN de '.\App\Services\DependenteLoginService::primeiroNome((string) $dep['nome']).' definido pela academia',
            'A academia definiu o PIN de acesso de '.\App\Services\DependenteLoginService::primeiroNome((string) $dep['nome']).'. Ele entra em "Sou dependente" com o seu e-mail. Você pode trocar o PIN em Dependentes.');

        return ['status' => 200, 'body' => ['type' => 'success', 'message' => 'PIN salvo. O responsável foi avisado no app.']];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function removerPinDependente(int $alunoId, int $tenantId): array
    {
        $dep = $this->dependenteDoTenant($alunoId, $tenantId);
        if (isset($dep['erro'])) {
            return $dep['erro'];
        }
        app(\App\Services\Mobile\MobileDependenteService::class)->removerPin((int) $dep['responsavel_usuario_id'], (int) $dep['usuario_id']);

        return ['status' => 200, 'body' => ['type' => 'success', 'message' => 'Acesso próprio removido. Sessões abertas com o PIN foram encerradas.']];
    }

    /** @return array<string, mixed> */
    private function dependenteDoTenant(int $alunoId, int $tenantId): array
    {
        $aluno = $this->alunos->findById($alunoId, $tenantId);
        if (! $aluno) {
            return ['erro' => $this->error('Aluno não encontrado', 404)];
        }
        if (! Pessoa::existe('usuarios', 'dependente_pin_hash')) {
            return ['erro' => $this->error('Recurso indisponível: rode as migrations de dependentes.', 409)];
        }
        $this->usuarios->ligarAoResponsavel((int) $aluno['usuario_id']);
        $u = DB::table('usuarios')->where('id', $aluno['usuario_id'])->first(['id', 'nome', 'data_nascimento', 'responsavel_usuario_id']);
        if (! $u || ! $u->responsavel_usuario_id) {
            return ['erro' => $this->error('Este aluno não está ligado à conta de um responsável. Confira o CPF do responsável no cadastro.', 422)];
        }
        if (! ResponsavelLegal::ehMenor($u->data_nascimento ? (string) $u->data_nascimento : null)) {
            return ['erro' => $this->error('O PIN é só para menores de idade. Maiores entram com o próprio e-mail.', 422)];
        }

        return ['usuario_id' => (int) $u->id, 'nome' => (string) $u->nome, 'responsavel_usuario_id' => (int) $u->responsavel_usuario_id];
    }

    private function avisarResponsavel(int $responsavelId, int $tenantId, int $dependenteId, string $titulo, string $mensagem): void
    {
        try {
            DB::table('notificacoes')->insert([
                'tenant_id' => $tenantId, 'usuario_id' => $responsavelId, 'tipo' => 'acesso_dependente',
                'titulo' => $titulo, 'mensagem' => $mensagem, 'dados' => json_encode(['dependente_id' => $dependenteId]),
                'lida' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Dependentes ligados a esta pessoa (para o detalhe do responsável no painel).
     *
     * @return list<array<string, mixed>>
     */
    private function dependentesDe(int $usuarioId, int $tenantId): array
    {
        if (! Pessoa::existe('usuarios', 'responsavel_usuario_id')) {
            return [];
        }
        $this->usuarios->ligarDependentesPorCpf($usuarioId);
        $temPin = Pessoa::existe('usuarios', 'dependente_pin_hash');

        return DB::table('usuarios as u')
            ->leftJoin('alunos as a', 'a.usuario_id', '=', 'u.id')
            ->leftJoin('tenant_usuario_papel as tup', function ($j) use ($tenantId) {
                $j->on('tup.usuario_id', '=', 'u.id')->where('tup.tenant_id', $tenantId)->where('tup.papel_id', 1);
            })
            ->where('u.responsavel_usuario_id', $usuarioId)
            ->orderBy('u.nome')
            ->get(['u.id', 'u.nome', 'u.data_nascimento', 'u.responsavel_parentesco', 'a.id as aluno_id', 'tup.ativo as na_academia',
                $temPin ? DB::raw('CASE WHEN u.dependente_pin_hash IS NULL THEN 0 ELSE 1 END as tem_pin') : DB::raw('0 as tem_pin')])
            ->map(fn ($d) => [
                'usuario_id' => (int) $d->id,
                'aluno_id' => (int) ($d->na_academia ?? 0) === 1 ? (int) $d->aluno_id : null,
                'nome' => $d->nome,
                'idade' => ResponsavelLegal::idade($d->data_nascimento ? (string) $d->data_nascimento : null),
                'parentesco' => $d->responsavel_parentesco,
                'tem_pin' => (int) $d->tem_pin === 1,
            ])->all();
    }

    /** @param  array<string, mixed>  $efetivo */
    private function semEmailPermitido(array $efetivo): bool
    {
        return ResponsavelLegal::ehMenor(isset($efetivo['data_nascimento']) ? (string) $efetivo['data_nascimento'] : null)
            && ! empty($efetivo['responsavel_cpf']);
    }

    /**
     * Responsável já conhecido pelo CPF, para preencher o cadastro de outro menor (ex.: irmão).
     * Procura quem é responsável de algum aluno e, se não houver, a própria pessoa com conta no Check-in.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buscarResponsavelPorCpf(string $cpf, int $tenantId): array
    {
        $cpf = Documento::digitos($cpf);
        if (! Documento::cpfValido($cpf)) {
            return $this->error('CPF inválido', 422);
        }

        $achado = $this->alunos->responsavelPorCpf($cpf, $tenantId);
        if (! $achado) {
            return ['status' => 200, 'body' => ['found' => false]];
        }

        return ['status' => 200, 'body' => ['found' => true, 'responsavel' => $achado]];
    }

    private function validarCpf(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
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
