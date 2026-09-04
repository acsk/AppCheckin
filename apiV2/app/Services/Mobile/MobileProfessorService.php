<?php

namespace App\Services\Mobile;

use App\Helpers\DateBr;
use App\Repositories\AlunoRepository;
use App\Repositories\CheckinRepository;
use App\Repositories\MatriculaRepository;
use App\Repositories\TurmaRepository;
use App\Support\AcademyDateTime;
use App\Support\AniversarioUtil;
use Illuminate\Support\Facades\DB;

class MobileProfessorService
{
    public function __construct(
        private readonly AlunoRepository $alunos,
        private readonly MatriculaRepository $matriculas,
        private readonly TurmaRepository $turmas,
        private readonly CheckinRepository $checkins,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buscarAlunos(
        int $userId,
        ?int $tenantId,
        ?string $q,
        ?string $nome,
        ?string $cpf,
        ?string $email,
        int $limit,
        int $offset,
    ): array {
        if (! $tenantId) {
            return $this->fail('Nenhum tenant selecionado', 400);
        }

        if ($denied = $this->exigirStaff($userId, $tenantId)) {
            return $denied;
        }

        $q = trim((string) $q);
        $nome = trim((string) $nome);
        $cpf = trim((string) $cpf);
        $email = trim((string) $email);

        if ($q !== '' && $nome === '' && $cpf === '' && $email === '') {
            $nome = $q;
            $cpf = $q;
            $email = $q;
        }

        if ($nome === '' && $cpf === '' && $email === '') {
            return $this->fail('Informe nome, CPF ou email para buscar', 400);
        }

        $limit = min(max($limit, 1), 50);
        $offset = max($offset, 0);

        $alunosRaw = $this->alunos->buscarParaCheckinMobile(
            $tenantId,
            $nome,
            $cpf,
            $email,
            $limit,
            $offset,
        );

        $alunosFormatados = array_map(function (array $a): array {
            $aniversario = AniversarioUtil::payload($a['data_nascimento'] ?? null);

            return [
                'aluno_id' => (int) ($a['aluno_id'] ?? 0),
                'usuario_id' => (int) ($a['usuario_id'] ?? 0),
                'nome' => $a['nome'] ?? '',
                'email' => $a['email'] ?? null,
                'cpf' => $a['cpf'] ?? null,
                'foto_caminho' => $a['foto_caminho'] ?? null,
                'aniversario_hoje' => $aniversario['aniversario_hoje'],
                'idade' => $aniversario['idade'],
            ];
        }, $alunosRaw);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'total' => count($alunosFormatados),
                    'limit' => $limit,
                    'offset' => $offset,
                    'alunos' => $alunosFormatados,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: int, body: array<string, mixed>}
     */
    public function registrarCheckinManual(int $professorId, ?int $tenantId, array $body): array
    {
        if (! $tenantId) {
            return $this->fail('Nenhum tenant selecionado', 400);
        }

        if ($denied = $this->exigirStaff($professorId, $tenantId)) {
            return $denied;
        }

        $turmaId = isset($body['turma_id']) ? (int) $body['turma_id'] : 0;
        $alunoIdInput = isset($body['aluno_id']) ? (int) $body['aluno_id'] : null;
        $usuarioIdInput = isset($body['usuario_id']) ? (int) $body['usuario_id'] : null;

        if ($turmaId <= 0 || (($alunoIdInput === null || $alunoIdInput <= 0) && ($usuarioIdInput === null || $usuarioIdInput <= 0))) {
            return $this->fail('turma_id e aluno_id (ou usuario_id) são obrigatórios', 422);
        }

        $aluno = $alunoIdInput > 0
            ? $this->alunos->findAlunoNoTenantPorId($alunoIdInput, $tenantId)
            : $this->alunos->findAlunoNoTenantPorUsuarioId($usuarioIdInput, $tenantId);

        if (! $aluno) {
            return $this->fail('Aluno não encontrado no tenant', 404);
        }

        $alunoId = (int) $aluno['aluno_id'];
        $usuarioId = (int) $aluno['usuario_id'];

        $this->matriculas->atualizarStatusMatriculasVencidas($usuarioId, $tenantId);

        $matricula = $this->matriculas->findElegivelParaCheckin($alunoId, $tenantId);
        if (! $matricula) {
            $erro = $this->matriculas->montarErroMatriculaIndisponivelCheckin($alunoId, $tenantId);
            $bodyResp = array_merge(['success' => false], $erro);
            if (empty($bodyResp['error']) && ! empty($bodyResp['mensagem'])) {
                $bodyResp['error'] = $bodyResp['mensagem'];
            }
            $bodyResp['debug'] = $this->matriculas->montarDebugSemMatricula(
                $tenantId,
                $alunoId,
                $usuarioId,
                'checkin_manual',
            );

            return ['status' => 403, 'body' => $bodyResp];
        }

        $hoje = AcademyDateTime::today();
        $acessoAte = $matricula['proxima_data_vencimento'] ?? $matricula['data_vencimento'] ?? null;
        if ($acessoAte && $acessoAte < $hoje) {
            $dataVencimento = DateBr::format(is_string($acessoAte) ? $acessoAte : null) ?? (string) $acessoAte;

            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'error' => "Acesso do aluno expirou em {$dataVencimento}",
                    'code' => 'MATRICULA_VENCIDA',
                    'data_vencimento' => $acessoAte,
                ],
            ];
        }

        $turma = $this->turmas->findById($turmaId, $tenantId);
        if (! $turma || ! ($turma['ativo'] ?? true)) {
            return $this->fail('Turma inválida ou inativa', 400);
        }

        $checkinExistente = $this->checkins->findUltimoCheckinUsuarioNaTurma($usuarioId, $turmaId);
        if ($checkinExistente) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'error' => 'Aluno já tem check-in nesta turma',
                    'code' => 'CHECKIN_DUPLICADO',
                    'detalhes' => [
                        'checkin_id' => (int) $checkinExistente['id'],
                        'aluno_id' => (int) $checkinExistente['aluno_id'],
                        'created_at' => $checkinExistente['created_at'],
                        'data_efetiva' => $checkinExistente['data_efetiva'],
                        'registrado_por_admin' => (bool) $checkinExistente['registrado_por_admin'],
                        'dica' => 'Se não aparece na lista, o check-in foi feito na véspera (janela de abertura). Use DELETE /mobile/checkin/manual/{checkin_id}/desfazer',
                    ],
                ],
            ];
        }

        $diaAula = $turma['dia_data'] ?? AcademyDateTime::today();
        $modalidadeTurma = isset($turma['modalidade_id']) ? (int) $turma['modalidade_id'] : null;

        $checkinDia = $this->checkins->usuarioTemCheckinNoDiaNaModalidade($usuarioId, $diaAula, $modalidadeTurma);
        if ($checkinDia['total'] > 0) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'error' => 'Aluno já realizou um check-in nesta modalidade em '.$diaAula.'. Máximo 1 check-in por modalidade por dia',
                    'detalhes' => [
                        'limite_diario_modalidade' => 1,
                        'data' => $diaAula,
                        'modalidade_id' => $modalidadeTurma,
                        'checkins_no_dia_nesta_modalidade' => $checkinDia['total'],
                        'ultimo_checkin_id' => $checkinDia['ultimo_checkin_id'],
                    ],
                ],
            ];
        }

        $planoInfo = $this->checkins->obterLimiteCheckinsPlano($usuarioId, $tenantId, $modalidadeTurma);
        if ($planoInfo['tem_plano'] && $planoInfo['limite'] > 0 && $planoInfo['permite_reposicao']) {
            $detalhesLimite = $this->matriculas->avaliarLimiteMensalPorMatricula(
                (int) $matricula['id'],
                false,
            );
            if ($detalhesLimite !== null) {
                return [
                    'status' => 400,
                    'body' => [
                        'success' => false,
                        'error' => $detalhesLimite['mensagem'] ?? 'Aluno atingiu o limite de check-ins do ciclo do plano',
                        'detalhes' => $detalhesLimite,
                    ],
                ];
            }
        }

        $limite = (int) ($turma['limite_alunos'] ?? 0);
        if ($limite > 0) {
            $ocupados = $this->checkins->contarCheckinsTurmaNoDia($turmaId, $tenantId, $diaAula);
            if ($ocupados >= $limite) {
                return [
                    'status' => 400,
                    'body' => [
                        'success' => false,
                        'error' => 'Sem vagas disponíveis nesta turma',
                        'code' => 'TURMA_LOTADA',
                        'detalhes' => [
                            'limite_alunos' => $limite,
                            'checkins_no_dia' => $ocupados,
                            'data_aula' => $diaAula,
                        ],
                    ],
                ];
            }
        }

        try {
            $checkinId = DB::transaction(function () use (
                $alunoId,
                $turmaId,
                $tenantId,
                $professorId,
                $diaAula,
                $limite,
            ): int {
                if ($limite > 0) {
                    $ocupados = $this->checkins->contarCheckinsTurmaNoDiaComLock($turmaId, $tenantId, $diaAula);
                    if ($ocupados >= $limite) {
                        throw new \RuntimeException('TURMA_LOTADA');
                    }
                }

                return $this->checkins->createManualEmTurma(
                    $alunoId,
                    $turmaId,
                    $tenantId,
                    $professorId,
                    $diaAula,
                );
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'TURMA_LOTADA') {
                return [
                    'status' => 400,
                    'body' => [
                        'success' => false,
                        'error' => 'Sem vagas disponíveis nesta turma',
                        'code' => 'TURMA_LOTADA',
                        'detalhes' => [
                            'limite_alunos' => $limite,
                            'data_aula' => $diaAula,
                        ],
                    ],
                ];
            }

            throw $e;
        }

        return [
            'status' => 201,
            'body' => [
                'success' => true,
                'message' => 'Check-in manual registrado com sucesso',
                'data' => [
                    'checkin_id' => $checkinId,
                    'aluno' => [
                        'aluno_id' => $alunoId,
                        'usuario_id' => $usuarioId,
                        'nome' => $aluno['nome'] ?? '',
                        'email' => $aluno['email'] ?? null,
                    ],
                    'turma_id' => $turmaId,
                    'data_hora' => AcademyDateTime::nowFormatted(),
                ],
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function desfazerCheckinManual(int $userId, ?int $tenantId, int $checkinId): array
    {
        if ($checkinId <= 0) {
            return $this->fail('checkinId é obrigatório', 400);
        }

        if (! $tenantId) {
            return $this->fail('Nenhum tenant selecionado', 400);
        }

        if ($denied = $this->exigirStaff($userId, $tenantId)) {
            return $denied;
        }

        $checkin = $this->turmas->findCheckinComTurma($checkinId, $tenantId);
        if (! $checkin) {
            return $this->fail('Check-in não encontrado', 404);
        }

        $turma = $this->turmas->findById((int) $checkin['turma_id'], $tenantId);
        if (! $turma) {
            return $this->fail('Turma não encontrada', 404);
        }

        $this->turmas->deleteCheckin($checkinId);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => 'Check-in cancelado. Aluno removido da aula.',
                'data' => [
                    'checkin_id' => $checkinId,
                    'turma' => [
                        'id' => (int) $turma['id'],
                        'nome' => $turma['nome'],
                        'horario_inicio' => $turma['horario_inicio'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function resumoFinanceiroAluno(int $userId, ?int $tenantId, int $alunoId): array
    {
        if (! $tenantId) {
            return $this->fail('Nenhum tenant selecionado', 400);
        }

        if ($alunoId <= 0) {
            return $this->fail('ID do aluno não informado', 400);
        }

        if ($denied = $this->exigirStaff($userId, $tenantId)) {
            return $denied;
        }

        $aluno = $this->alunos->findAlunoNoTenantPorId($alunoId, $tenantId);
        if (! $aluno) {
            return $this->fail('Aluno não encontrado', 404);
        }

        $matricula = $this->matriculas->findPrincipalParaResumoFinanceiro($alunoId, $tenantId);

        $matriculaFormatada = null;
        $pagamentosFormatados = [];
        $resumoFinanceiro = [
            'total_previsto' => 0.0,
            'total_pago' => 0.0,
            'total_pendente' => 0.0,
            'quantidade_pagamentos' => 0,
            'pagamentos_realizados' => 0,
        ];

        if ($matricula) {
            $matriculaId = (int) $matricula['id'];
            $plano = $this->matriculas->findPlanoResumo((int) $matricula['plano_id']);
            $pagamentos = $this->matriculas->listarPagamentosResumoFinanceiro($matriculaId);
            $totais = $this->matriculas->totaisPagamentosResumoFinanceiro($matriculaId);

            $matriculaFormatada = [
                'id' => $matriculaId,
                'usuario' => $matricula['usuario_nome'],
                'plano' => $plano ? [
                    'nome' => $plano['nome'],
                    'valor' => (float) $plano['valor'],
                    'duracao_dias' => (int) ($plano['duracao_dias'] ?? 0),
                    'checkins_semanais' => (int) ($plano['checkins_semanais'] ?? 0),
                ] : null,
                'datas' => [
                    'matricula' => $matricula['data_matricula'],
                    'inicio' => $matricula['data_inicio'],
                    'vencimento' => $matricula['data_vencimento'],
                ],
                'valor_total' => (float) $matricula['valor'],
                'status' => $matricula['status'],
                'motivo' => $matricula['motivo'],
            ];

            $pagamentosFormatados = array_map(
                static function (array $p): array {
                    $obs = (string) ($p['observacoes'] ?? '');
                    $forma = $p['forma_pagamento_nome'] ?? null;
                    $origem = null;
                    if (! empty($p['baixado_por'])) {
                        $origem = 'manual';
                    } elseif ($forma && (stripos((string) $forma, 'Mercado') !== false || stripos($obs, 'Mercado Pago') !== false)) {
                        $origem = 'mercadopago';
                    } elseif ($forma) {
                        $origem = 'manual';
                    } elseif (stripos($obs, 'Mercado Pago') !== false) {
                        $origem = 'mercadopago';
                    }

                    return [
                        'id' => (int) $p['id'],
                        'valor' => (float) $p['valor'],
                        'data_vencimento' => $p['data_vencimento'],
                        'data_pagamento' => $p['data_pagamento'],
                        'status' => $p['status_pagamento_nome'],
                        'status_pagamento_id' => (int) ($p['status_pagamento_id'] ?? 0),
                        'forma_pagamento' => $forma,
                        'baixado_por' => $p['baixado_por'] !== null ? (int) $p['baixado_por'] : null,
                        'baixado_por_nome' => $p['baixado_por_nome'] ?? null,
                        'criado_por' => $p['criado_por'] !== null ? (int) $p['criado_por'] : null,
                        'criado_por_nome' => $p['criado_por_nome'] ?? null,
                        'tipo_baixa_nome' => $p['tipo_baixa_nome'] ?? null,
                        'origem' => $origem,
                        'pendente' => $p['data_pagamento'] === null,
                    ];
                },
                $pagamentos,
            );

            $resumoFinanceiro = [
                'total_previsto' => (float) $matricula['valor'],
                'total_pago' => (float) ($totais['total_pago'] ?? 0),
                'total_pendente' => (float) ($totais['total_pendente'] ?? 0),
                'quantidade_pagamentos' => (int) ($totais['quantidade_pagamentos'] ?? 0),
                'pagamentos_realizados' => (int) ($totais['pagamentos_realizados'] ?? 0),
            ];
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'aluno' => [
                        'id' => (int) $aluno['aluno_id'],
                        'nome' => $aluno['nome'],
                    ],
                    'matricula' => $matriculaFormatada,
                    'pagamentos' => $pagamentosFormatados,
                    'resumo_financeiro' => $resumoFinanceiro,
                ],
            ],
        ];
    }

    /**
     * @return ?array{status: int, body: array<string, mixed>}
     */
    private function exigirStaff(int $userId, int $tenantId): ?array
    {
        $papelId = (int) (DB::table('tenant_usuario_papel')
            ->where('usuario_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('ativo', 1)
            ->max('papel_id') ?: 0);

        if ($papelId < 2) {
            return $this->fail('Acesso negado', 403);
        }

        return null;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function fail(string $error, int $status, ?string $code = null): array
    {
        $body = ['success' => false, 'error' => $error];
        if ($code !== null) {
            $body['code'] = $code;
        }

        return ['status' => $status, 'body' => $body];
    }
}
