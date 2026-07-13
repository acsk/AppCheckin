<?php

namespace App\Services\Admin;

use App\Repositories\AdminAlunoRepository;
use App\Repositories\UsuarioRepository;
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

            if ($busca || isset($query['pagina'])) {
                $lista = $this->alunos->listarPaginado($tenantId, $pagina, $porPagina, $busca);
                $total = $this->alunos->contarPorTenant($tenantId, true);

                return [
                    'status' => 200,
                    'body' => [
                        'alunos' => $this->enriquecerAlunos($lista, $tenantId),
                        'total' => $total,
                        'pagina' => $pagina,
                        'por_pagina' => $porPagina,
                        'total_paginas' => (int) ceil($total / max(1, $porPagina)),
                    ],
                ];
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

        return [
            'status' => 200,
            'body' => ['aluno' => $this->enriquecerAluno($aluno, $tenantId)],
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
                $usuarioExistente = $this->usuarios->findByEmailGlobal((string) $data['email']);

                if ($usuarioExistente) {
                    $usuarioId = (int) $usuarioExistente->id;
                    if ($this->alunos->findByUsuarioIdAndTenant($usuarioId, $tenantId)) {
                        throw new \RuntimeException('EMAIL_JA_ALUNO_TENANT');
                    }

                    if (! $this->alunos->findByUsuarioId($usuarioId)) {
                        $data['usuario_id'] = $usuarioId;
                        $this->alunos->create($data);
                    }

                    $this->alunos->garantirVinculoAluno($usuarioId, $tenantId);

                    return $this->alunos->findByUsuarioId($usuarioId);
                }

                $usuarioId = $this->usuarios->createUsuario($data, $tenantId, 1);
                if (! $usuarioId) {
                    throw new \RuntimeException('Erro ao criar usuário');
                }

                // Completar campos de perfil que createUsuario não grava
                $alunoCriado = $this->alunos->findByUsuarioId($usuarioId);
                if ($alunoCriado) {
                    $extra = array_intersect_key($data, array_flip([
                        'data_nascimento', 'whatsapp', 'logradouro', 'numero',
                        'complemento', 'bairro', 'cidade', 'estado', 'foto_url', 'foto_base64',
                    ]));
                    if ($extra !== []) {
                        $this->alunos->update((int) $alunoCriado['id'], $extra);
                    }
                }

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
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'EMAIL_JA_ALUNO_TENANT') {
                return $this->error('Este email já está cadastrado como aluno neste tenant', 400);
            }

            return $this->error('Erro ao criar aluno: '.$e->getMessage(), 500);
        } catch (\Throwable $e) {
            return $this->error('Erro ao criar aluno: '.$e->getMessage(), 500);
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

        $errors = $this->validarDados($data, (int) $aluno['usuario_id'], $tenantId, true);
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

                if (isset($data['email']) || isset($data['senha']) || isset($data['nome'])) {
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
                    $this->usuarios->updateAuthFields((int) $aluno['usuario_id'], $usuarioData);
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
            ];
            $aluno['matricula_id'] = $matricula['id'];
            $aluno['pagamento_ativo'] = $this->alunos->temPagamentoAtivo($alunoId, $tenantId);
        } else {
            $aluno['plano'] = null;
            $aluno['matricula_id'] = null;
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
    private function validarDados(array $data, ?int $usuarioIdExcluir, int $tenantId, bool $isUpdate): array
    {
        $errors = [];

        if (! $isUpdate && empty($data['nome'])) {
            $errors[] = 'Nome é obrigatório';
        }

        if (! $isUpdate) {
            if (empty($data['email'])) {
                $errors[] = 'Email é obrigatório';
            } elseif (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email inválido';
            } elseif ($this->usuarios->emailExists((string) $data['email'], $usuarioIdExcluir, $tenantId)) {
                $errors[] = 'Email já cadastrado';
            }
        } elseif (isset($data['email'])) {
            if (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email inválido';
            } elseif ($this->usuarios->emailExists((string) $data['email'], $usuarioIdExcluir, $tenantId)) {
                $errors[] = 'Email já cadastrado';
            }
        }

        if (! $isUpdate) {
            if (empty($data['senha'])) {
                $errors[] = 'Senha é obrigatória';
            } elseif (strlen((string) $data['senha']) < 6) {
                $errors[] = 'Senha deve ter no mínimo 6 caracteres';
            }
        } elseif (isset($data['senha']) && $data['senha'] !== '' && strlen((string) $data['senha']) < 6) {
            $errors[] = 'Senha deve ter no mínimo 6 caracteres';
        }

        if (! empty($data['cpf'])) {
            $cpfLimpo = preg_replace('/[^0-9]/', '', (string) $data['cpf']);
            if (strlen((string) $cpfLimpo) !== 11) {
                $errors[] = 'CPF deve ter 11 dígitos';
            }
        }

        if (! empty($data['telefone'])) {
            $tel = preg_replace('/[^0-9]/', '', (string) $data['telefone']);
            $len = strlen((string) $tel);
            if ($len < 10 || $len > 11) {
                $errors[] = 'Telefone deve ter 10 ou 11 dígitos';
            }
        }

        return $errors;
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
