<?php

namespace App\Services\Mobile;

use App\Repositories\AlunoRepository;
use App\Repositories\CheckinRepository;
use App\Repositories\MatriculaRepository;
use App\Repositories\TurmaRepository;
use App\Repositories\UsuarioRepository;
use App\Services\TurmaCheckinBloqueioService;
use App\Support\AcademyDateTime;
use Illuminate\Support\Facades\DB;

class MobileCheckinService
{
    public function __construct(
        private readonly UsuarioRepository $usuarios,
        private readonly AlunoRepository $alunos,
        private readonly MatriculaRepository $matriculas,
        private readonly TurmaRepository $turmas,
        private readonly CheckinRepository $checkins,
        private readonly TurmaCheckinBloqueioService $bloqueios,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: int, body: array<string, mixed>}
     */
    public function registrar(int $userId, ?int $tenantId, array $body, ?int $alunoIdJwt = null): array
    {
        if (! $tenantId) {
            return $this->fail('Nenhum tenant selecionado', 400);
        }

        $usuario = $this->usuarios->findById($userId, $tenantId);
        if (! $usuario) {
            return $this->fail('Usuário não encontrado', 404);
        }

        $aluno = $this->alunos->findForTenant($userId, $tenantId);
        $alunoId = (int) ($aluno['id'] ?? $alunoIdJwt ?? 0);

        if (! $this->alunos->usuarioTemAcessoTenant($userId, $tenantId)) {
            return $this->fail(
                'Acesso negado: você não tem permissão neste tenant',
                403,
                'INVALID_TENANT_ACCESS',
            );
        }

        $this->matriculas->atualizarStatusMatriculasVencidas($userId, $tenantId);

        $matricula = $alunoId > 0
            ? $this->matriculas->findElegivelParaCheckin($alunoId, $tenantId)
            : null;

        if (! $matricula) {
            $erro = $this->matriculas->montarErroMatriculaIndisponivelCheckin($alunoId, $tenantId);

            return [
                'status' => 403,
                'body' => array_merge(
                    ['success' => false],
                    $erro,
                    [
                        'debug' => $this->matriculas->montarDebugSemMatricula(
                            $tenantId,
                            $alunoId ?: null,
                            $userId,
                            'checkin',
                        ),
                    ],
                ),
            ];
        }

        $hoje = AcademyDateTime::today();
        $acessoAte = $matricula['proxima_data_vencimento'] ?? $matricula['data_vencimento'] ?? null;
        if ($acessoAte && $acessoAte < $hoje) {
            $dataVencimento = date('d/m/Y', strtotime($acessoAte));

            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'error' => "Seu acesso expirou em {$dataVencimento}. Por favor, renove sua matrícula.",
                    'code' => 'MATRICULA_VENCIDA',
                    'data_vencimento' => $acessoAte,
                ],
            ];
        }

        $turmaId = isset($body['turma_id']) ? (int) $body['turma_id'] : 0;
        if ($turmaId <= 0) {
            return $this->fail('turma_id é obrigatório', 400);
        }

        $turma = $this->turmas->findById($turmaId, $tenantId);
        if (! $turma) {
            return $this->fail('Turma não encontrada', 404);
        }

        if ($this->bloqueios->isBloqueada($turmaId, $tenantId)) {
            return $this->fail(
                'O check-in desta aula está bloqueado pelo professor ou administrador.',
                403,
                'CHECKIN_TURMA_BLOQUEADO',
            );
        }

        if ($this->checkins->usuarioTemCheckinNaTurma($userId, $turmaId)) {
            return $this->fail('Você já realizou check-in nesta turma', 400);
        }

        $diaAula = $turma['dia_data'] ?? AcademyDateTime::today();
        $modalidadeTurma = isset($turma['modalidade_id']) ? (int) $turma['modalidade_id'] : null;

        $checkinDia = $this->checkins->usuarioTemCheckinNoDiaNaModalidade($userId, $diaAula, $modalidadeTurma);
        if ($checkinDia['total'] > 0) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'error' => 'Você já realizou um check-in nesta modalidade em '.$diaAula.'. Máximo 1 check-in por modalidade por dia',
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

        $planoInfo = $this->checkins->obterLimiteCheckinsPlano($userId, $tenantId, $modalidadeTurma);
        $limiteErro = $this->validarLimitePlano($planoInfo, $userId, $modalidadeTurma, $turma);
        if ($limiteErro !== null) {
            return $limiteErro;
        }

        $alunosCount = $this->turmas->contarAlunosInscritos($turmaId);
        if ($alunosCount >= (int) $turma['limite_alunos']) {
            return $this->fail('Sem vagas disponíveis nesta turma', 400);
        }

        $toleranciaErro = $this->validarToleranciaAntes($turma);
        if ($toleranciaErro !== null) {
            return $toleranciaErro;
        }

        if ($alunoId <= 0) {
            return $this->fail('Aluno não encontrado para este tenant', 404);
        }

        $checkinId = $this->checkins->createEmTurma($alunoId, $turmaId, $tenantId);
        if (! $checkinId) {
            return $this->fail(
                'Erro ao registrar check-in (Talvez já exista um check-in registrado)',
                500,
            );
        }

        return [
            'status' => 201,
            'body' => [
                'success' => true,
                'message' => 'Check-in realizado com sucesso!',
                'data' => [
                    'checkin_id' => $checkinId,
                    'usuario' => [
                        'id' => $userId,
                        'nome' => $usuario['nome'],
                        'email' => $usuario['email'],
                        'foto_caminho' => $aluno['foto_caminho'] ?? null,
                    ],
                    'turma' => [
                        'id' => (int) $turma['id'],
                        'nome' => $turma['nome'],
                        'professor' => $turma['professor_nome'],
                        'modalidade' => $turma['modalidade_nome'],
                    ],
                    'data_checkin' => AcademyDateTime::nowFormatted(),
                    'vagas_atualizadas' => (int) $turma['limite_alunos'] - ($alunosCount + 1),
                ],
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function desfazer(int $userId, ?int $tenantId, int $checkinId): array
    {
        if ($checkinId <= 0) {
            return $this->fail('checkinId é obrigatório', 400);
        }

        if (! $tenantId) {
            return $this->fail('Nenhum tenant selecionado', 400);
        }

        $checkin = $this->turmas->findCheckinComTurma($checkinId, $tenantId);
        if (! $checkin) {
            return $this->fail('Check-in não encontrado', 404);
        }

        if ((int) $checkin['usuario_id'] !== $userId) {
            return $this->fail('Você não tem permissão para desfazer este check-in', 403);
        }

        $turma = $this->turmas->findById((int) $checkin['turma_id'], $tenantId);
        if (! $turma) {
            return $this->fail('Turma não encontrada', 404);
        }

        $agora = AcademyDateTime::now();
        $dataHorarioInicio = AcademyDateTime::fromDateAndTime(
            (string) $checkin['dia_data'],
            (string) $turma['horario_inicio'],
        );

        if (! $dataHorarioInicio) {
            return $this->fail('Dados de horário da aula inválidos', 500);
        }

        if ($agora >= $dataHorarioInicio) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'error' => 'Não é possível desfazer o check-in. A aula já começou ou passou',
                    'detalhes' => [
                        'aula_inicio' => $dataHorarioInicio->format('Y-m-d H:i:s'),
                        'agora' => $agora->format('Y-m-d H:i:s'),
                        'mensagem' => 'O desfazimento só é permitido ANTES do horário de início da aula',
                    ],
                ],
            ];
        }

        $this->turmas->deleteCheckin($checkinId);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => 'Check-in desfeito com sucesso',
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
     * @param  array<string, mixed>  $planoInfo
     * @param  array<string, mixed>  $turma
     * @return ?array{status: int, body: array<string, mixed>}
     */
    private function validarLimitePlano(array $planoInfo, int $userId, ?int $modalidadeTurma, array $turma): ?array
    {
        // Diária: sem teto semanal/mensal — acesso controlado pela vigência.
        if (! empty($planoInfo['eh_diaria']) || (int) ($planoInfo['duracao_dias'] ?? 0) === 1) {
            return null;
        }

        if ($planoInfo['tem_plano'] && $planoInfo['limite'] > 0) {
            $primeiroDiaMes = AcademyDateTime::fromDateAndTime(
                AcademyDateTime::now()->format('Y-m-01'),
                '00:00:00',
            ) ?? AcademyDateTime::now();
            $diaSemanaInicio = (int) $primeiroDiaMes->format('w');
            $diasNoMes = (int) $primeiroDiaMes->format('t');
            $semanasNoMes = (int) ceil(($diasNoMes + $diaSemanaInicio) / 7);
            $bonusCincoSemanas = $semanasNoMes >= 5 ? 1 : 0;

            if ($planoInfo['permite_reposicao']) {
                $limiteMensal = (int) ($planoInfo['limite_mensal'] ?? ($planoInfo['limite'] * 4));
                $limiteMensal += $bonusCincoSemanas;
                $checkinsNoMes = $this->checkins->contarCheckinsNoMes($userId, $modalidadeTurma);

                if ($checkinsNoMes >= $limiteMensal) {
                    return [
                        'status' => 400,
                        'body' => [
                            'success' => false,
                            'error' => 'Você atingiu o limite de check-ins deste mês',
                            'detalhes' => [
                                'plano' => $planoInfo['plano_nome'],
                                'limite_mensal' => $limiteMensal,
                                'checkins_mes' => $checkinsNoMes,
                                'permite_reposicao' => true,
                                'mensagem' => 'Seu plano ('.$planoInfo['plano_nome'].') permite até '.$limiteMensal.' check-in(s) por mês. Você já realizou '.$checkinsNoMes.'.',
                            ],
                        ],
                    ];
                }
            } else {
                $checkinsNaSemana = $this->checkins->contarCheckinsNaSemana($userId, $modalidadeTurma);
                $limiteSemanal = $planoInfo['limite'] + $bonusCincoSemanas;

                if ($checkinsNaSemana >= $limiteSemanal) {
                    $bonusMsg = $bonusCincoSemanas > 0 ? ' (bônus mês com 5 semanas)' : '';

                    return [
                        'status' => 400,
                        'body' => [
                            'success' => false,
                            'error' => 'Você atingiu o limite de check-ins desta semana',
                            'detalhes' => [
                                'plano' => $planoInfo['plano_nome'],
                                'limite_semana' => $limiteSemanal,
                                'checkins_semana' => $checkinsNaSemana,
                                'bonus_cinco_semanas' => $bonusCincoSemanas > 0,
                                'mensagem' => 'Seu plano ('.$planoInfo['plano_nome'].') permite '.$limiteSemanal.' check-in(s) por semana'.$bonusMsg.'. Você já realizou '.$checkinsNaSemana.'.',
                            ],
                        ],
                    ];
                }
            }

            return null;
        }

        if (! $planoInfo['tem_plano']) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'error' => 'Você não possui plano ativo para esta modalidade',
                    'detalhes' => [
                        'modalidade_id' => $modalidadeTurma,
                        'modalidade' => $turma['modalidade_nome'] ?? 'Não informada',
                    ],
                ],
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $turma
     * @return ?array{status: int, body: array<string, mixed>}
     */
    private function validarToleranciaAntes(array $turma): ?array
    {
        if (empty($turma['dia_id']) || empty($turma['horario_inicio'])) {
            return null;
        }

        $dia = DB::table('dias')->where('id', $turma['dia_id'])->first();
        if (! $dia) {
            return null;
        }

        $agora = AcademyDateTime::now();
        $dataHorarioInicio = AcademyDateTime::fromDateAndTime(
            (string) $dia->data,
            (string) $turma['horario_inicio'],
        );

        if (! $dataHorarioInicio) {
            return null;
        }

        $toleranciaAntes = (int) ($turma['tolerancia_antes_minutos'] ?? 480);
        $dataMaisCedo = clone $dataHorarioInicio;
        $dataMaisCedo->modify("-{$toleranciaAntes} minutes");

        if ($agora < $dataMaisCedo) {
            $minutosAinda = (int) ceil(($dataMaisCedo->getTimestamp() - $agora->getTimestamp()) / 60);

            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'error' => 'Check-in aberto muito cedo. Aguarde o horário de abertura',
                    'detalhes' => [
                        'turma_id' => (int) $turma['id'],
                        'data_aula' => $dia->data,
                        'horario_inicio' => $turma['horario_inicio'],
                        'tolerancia_minutos' => $toleranciaAntes,
                        'abertura_checkin' => $dataMaisCedo->format('Y-m-d H:i:s'),
                        'tempo_esperar_minutos' => $minutosAinda,
                    ],
                ],
            ];
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
