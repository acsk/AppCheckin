<?php

namespace App\Services\Mobile;

use App\Repositories\CheckinRepository;
use App\Repositories\TurmaRepository;
use App\Services\TurmaCheckinBloqueioService;
use App\Support\AcademyDateTime;
use App\Support\AniversarioUtil;
use Illuminate\Support\Facades\DB;

class MobileTurmaService
{
    public function __construct(
        private readonly TurmaRepository $turmas,
        private readonly CheckinRepository $checkins,
        private readonly TurmaCheckinBloqueioService $bloqueios,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarTurmas(?int $tenantId): array
    {
        if (! $tenantId) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'Nenhum tenant selecionado'],
            ];
        }

        $turmasRaw = $this->turmas->listarTurmasMobileAtivas($tenantId);

        $turmasFormatadas = array_map(static function (array $turma): array {
            return [
                'id' => (int) $turma['id'],
                'nome' => $turma['nome'],
                'professor' => [
                    'id' => (int) $turma['professor_id'],
                    'nome' => $turma['professor'],
                ],
                'modalidade' => [
                    'id' => (int) $turma['modalidade_id'],
                    'nome' => $turma['modalidade'],
                    'icone' => $turma['icone'],
                    'cor' => $turma['cor'],
                ],
                'horario' => [
                    'inicio' => $turma['horario_inicio'],
                    'fim' => $turma['horario_fim'],
                ],
                'dia_aula' => $turma['dia_aula'],
                'limite_alunos' => (int) $turma['limite_alunos'],
                'alunos_inscritos' => (int) $turma['alunos_inscritos'],
                'vagas_disponiveis' => (int) $turma['vagas_disponiveis'],
                'total_checkins' => (int) $turma['total_checkins'],
                'ativo' => (bool) $turma['ativo'],
                'created_at' => $turma['created_at'],
                'updated_at' => $turma['updated_at'],
            ];
        }, $turmasRaw);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'turmas' => $turmasFormatadas,
                    'total' => count($turmasFormatadas),
                ],
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function participantes(?int $tenantId, int $turmaId): array
    {
        if (! $tenantId) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'Nenhum tenant selecionado'],
            ];
        }

        if ($turmaId <= 0) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'turma_id é obrigatório'],
            ];
        }

        $turma = $this->turmas->findById($turmaId, $tenantId);
        if (! $turma) {
            return [
                'status' => 404,
                'body' => ['success' => false, 'error' => 'Turma não encontrada'],
            ];
        }

        $participantesRaw = $this->turmas->listarParticipantesMobile($turmaId);
        $participantesFormatados = array_map(function (array $p): array {
            $aniversario = AniversarioUtil::payload($p['data_nascimento'] ?? null);

            return [
                'checkin_id' => (int) $p['checkin_id'],
                'aluno_id' => (int) $p['aluno_id'],
                'nome' => $p['usuario_nome'],
                'email' => $p['email'],
                'data_checkin' => $p['data_checkin'],
                'hora_checkin' => $p['hora_checkin'],
                'data_checkin_formatada' => $p['data_checkin_formatada'],
                'aniversario_hoje' => $aniversario['aniversario_hoje'],
                'idade' => $aniversario['idade'],
            ];
        }, $participantesRaw);

        $vagasOcupadas = count($participantesFormatados);
        $limite = (int) $turma['limite_alunos'];

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'turma' => [
                        'id' => (int) $turma['id'],
                        'nome' => $turma['nome'],
                        'professor' => $turma['professor_nome'],
                        'modalidade' => $turma['modalidade_nome'],
                        'limite_alunos' => $limite,
                        'vagas_ocupadas' => $vagasOcupadas,
                        'vagas_disponiveis' => $limite - $vagasOcupadas,
                    ],
                    'participantes' => $participantesFormatados,
                    'resumo' => [
                        'total_participantes' => $vagasOcupadas,
                        'percentual_ocupacao' => $vagasOcupadas > 0
                            ? round(($vagasOcupadas / $limite) * 100, 1)
                            : 0,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function detalhe(?int $tenantId, int $turmaId, ?string $dataAulaParam): array
    {
        if (! $tenantId) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'Nenhum tenant selecionado'],
            ];
        }

        if ($turmaId <= 0) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'turma_id é obrigatório'],
            ];
        }

        $turma = $this->turmas->findDetalheMobile($turmaId, $tenantId);
        if (! $turma) {
            return [
                'status' => 404,
                'body' => ['success' => false, 'error' => 'Turma não encontrada'],
            ];
        }

        $dataAula = ($dataAulaParam !== null && trim($dataAulaParam) !== '')
            ? trim($dataAulaParam)
            : ($turma['dia_data'] ?? AcademyDateTime::today());

        $totalAlunos = $this->turmas->contarCheckinsPorTurmaId($turmaId);
        $limite = (int) $turma['limite_alunos'];
        $vagasDisponiveis = max(0, $limite - $totalAlunos);
        $percentualOcupacao = $limite > 0 ? round(($totalAlunos / $limite) * 100, 1) : 0;
        $checkinBloqueado = $this->bloqueios->isBloqueada($turmaId, $tenantId);

        $toleranciaAntes = (int) ($turma['tolerancia_antes_minutos'] ?? 480);
        $toleranciaDepois = (int) ($turma['tolerancia_minutos'] ?? 10);
        $dataHoraTurma = AcademyDateTime::fromDateAndTime($dataAula, (string) $turma['horario_inicio']);
        $agora = AcademyDateTime::now();

        $checkinDisponivel = false;
        $checkinJaAbriu = false;
        $checkinJaFechou = false;
        $horarioAberturaFmt = null;
        $horarioFechamentoFmt = null;

        if ($dataHoraTurma) {
            $horarioAbertura = clone $dataHoraTurma;
            $horarioAbertura->modify("-{$toleranciaAntes} minutes");
            $horarioFechamento = clone $dataHoraTurma;
            $horarioFechamento->modify("+{$toleranciaDepois} minutes");

            $checkinDisponivel = $agora >= $horarioAbertura && $agora <= $horarioFechamento;
            $checkinJaAbriu = $agora >= $horarioAbertura;
            $checkinJaFechou = $agora > $horarioFechamento;
            $horarioAberturaFmt = $horarioAbertura->format('Y-m-d H:i:s');
            $horarioFechamentoFmt = $horarioFechamento->format('Y-m-d H:i:s');
        }

        $alunosRaw = $this->turmas->listarAlunosAgregadosCheckinTurma($turmaId);
        $alunosFormatados = array_map(function (array $a): array {
            $aniversario = AniversarioUtil::payload($a['data_nascimento'] ?? null);

            return [
                'aluno_id' => (int) $a['id'],
                'nome' => $a['nome'],
                'email' => $a['email'],
                'foto_caminho' => $a['foto_caminho'] ?? null,
                'checkins' => (int) $a['checkins_do_aluno'],
                'aniversario_hoje' => $aniversario['aniversario_hoje'],
                'idade' => $aniversario['idade'],
            ];
        }, $alunosRaw);

        $checkinsRaw = $this->turmas->listarCheckinsRecentesMobile($turmaId);
        $checkinsFormatados = array_map(function (array $c): array {
            $aniversario = AniversarioUtil::payload($c['data_nascimento'] ?? null);

            return [
                'checkin_id' => (int) $c['checkin_id'],
                'aluno_id' => (int) $c['aluno_id'],
                'usuario_nome' => $c['usuario_nome'],
                'data_checkin' => $c['data_checkin'],
                'hora_checkin' => $c['hora_checkin'],
                'data_checkin_formatada' => $c['data_checkin_formatada'],
                'presente' => $c['presente'] === null ? null : (bool) $c['presente'],
                'presenca_confirmada_em' => $c['presenca_confirmada_em'],
                'presenca_confirmada_por' => $c['presenca_confirmada_por']
                    ? (int) $c['presenca_confirmada_por']
                    : null,
                'aniversario_hoje' => $aniversario['aniversario_hoje'],
                'idade' => $aniversario['idade'],
            ];
        }, $checkinsRaw);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'turma' => [
                        'id' => (int) $turma['id'],
                        'nome' => $turma['nome'],
                        'professor' => [
                            'id' => (int) $turma['professor_id'],
                            'nome' => $turma['professor_nome'],
                            'email' => $turma['professor_email'] ?? null,
                        ],
                        'modalidade' => [
                            'id' => (int) ($turma['modalidade_id'] ?? 0),
                            'nome' => $turma['modalidade_nome'],
                            'icone' => $turma['modalidade_icone'] ?? null,
                            'cor' => $turma['modalidade_cor'] ?? null,
                        ],
                        'horario' => [
                            'inicio' => $turma['horario_inicio'],
                            'fim' => $turma['horario_fim'],
                        ],
                        'hora_inicio' => $turma['horario_inicio'],
                        'hora_fim' => $turma['horario_fim'],
                        'horario_inicio' => $turma['horario_inicio'],
                        'horario_fim' => $turma['horario_fim'],
                        'dia_aula' => $dataAula,
                        'checkin' => [
                            'disponivel' => $checkinDisponivel,
                            'ja_abriu' => $checkinJaAbriu,
                            'ja_fechou' => $checkinJaFechou,
                            'abertura' => $horarioAberturaFmt,
                            'fechamento' => $horarioFechamentoFmt,
                            'tolerancia_antes_minutos' => $toleranciaAntes,
                            'tolerancia_depois_minutos' => $toleranciaDepois,
                        ],
                        'ativo' => (bool) $turma['ativo'],
                        'checkin_bloqueado' => $checkinBloqueado,
                        'limite_alunos' => $limite,
                        'alunos_inscritos' => $totalAlunos,
                        'total_alunos_matriculados' => $totalAlunos,
                        'vagas_disponiveis' => $vagasDisponiveis,
                        'percentual_ocupacao' => $percentualOcupacao,
                        'total_checkins' => $totalAlunos,
                    ],
                    'alunos' => [
                        'total' => count($alunosFormatados),
                        'lista' => $alunosFormatados,
                    ],
                    'checkins_recentes' => [
                        'total' => count($checkinsFormatados),
                        'lista' => $checkinsFormatados,
                    ],
                    'resumo' => [
                        'alunos_ativos' => count($alunosFormatados),
                        'presentes_hoje' => count($checkinsFormatados),
                        'percentual_presenca' => count($alunosFormatados) > 0
                            ? round((count($checkinsFormatados) / count($alunosFormatados)) * 100, 1)
                            : 0,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: int, body: array<string, mixed>}
     */
    public function confirmarPresenca(?int $tenantId, int $userId, int $turmaId, array $body): array
    {
        if (! $tenantId) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'Nenhum tenant selecionado'],
            ];
        }

        if ($turmaId <= 0) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'turma_id é obrigatório'],
            ];
        }

        $turma = $this->turmas->findById($turmaId, $tenantId);
        if (! $turma) {
            return [
                'status' => 404,
                'body' => ['success' => false, 'error' => 'Turma não encontrada'],
            ];
        }

        $presencas = $this->normalizarPresencas($body['presencas'] ?? []);
        if ($presencas === []) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'Nenhuma presença informada'],
            ];
        }

        $removerFaltantes = (bool) ($body['remover_faltantes'] ?? false);

        return DB::transaction(function () use (
            $tenantId,
            $userId,
            $turmaId,
            $turma,
            $presencas,
            $removerFaltantes,
        ): array {
            $confirmados = 0;
            $presentes = 0;
            $faltas = 0;

            foreach ($presencas as $checkinId => $presente) {
                $presenteBool = filter_var($presente, FILTER_VALIDATE_BOOLEAN);
                $updated = $this->checkins->atualizarPresenca(
                    (int) $checkinId,
                    $turmaId,
                    $tenantId,
                    $presenteBool,
                    $userId,
                );

                if ($updated > 0) {
                    $confirmados++;
                    if ($presenteBool) {
                        $presentes++;
                    } else {
                        $faltas++;
                    }
                }
            }

            $checkinsRemovidos = 0;
            $alunosLiberados = [];

            if ($removerFaltantes && $faltas > 0) {
                $faltantes = $this->checkins->listarFaltantesComDados($turmaId, $tenantId);

                if ($faltantes !== []) {
                    $ids = array_map(static fn (array $f): int => (int) $f['id'], $faltantes);
                    $checkinsRemovidos = $this->checkins->deleteByIds($ids);
                    $alunosLiberados = array_map(static fn (array $f): array => [
                        'nome' => $f['aluno_nome'],
                        'email' => $f['aluno_email'],
                    ], $faltantes);
                }
            }

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => "Presença confirmada: {$presentes} presentes, {$faltas} faltas",
                    'data' => [
                        'turma_id' => $turmaId,
                        'turma_nome' => $turma['nome'],
                        'confirmados' => $confirmados,
                        'presentes' => $presentes,
                        'faltas' => $faltas,
                        'checkins_removidos' => $checkinsRemovidos,
                        'alunos_liberados' => $alunosLiberados,
                        'confirmado_por' => $userId,
                        'confirmado_em' => AcademyDateTime::nowFormatted(),
                    ],
                ],
            ];
        });
    }

    /**
     * @param  mixed  $presencasRaw
     * @return array<int|string, mixed>
     */
    private function normalizarPresencas(mixed $presencasRaw): array
    {
        if (! is_array($presencasRaw) || $presencasRaw === []) {
            return [];
        }

        if (isset($presencasRaw[0]) && is_array($presencasRaw[0])) {
            $presencas = [];
            foreach ($presencasRaw as $item) {
                if (isset($item['checkin_id'])) {
                    $presencas[$item['checkin_id']] = $item['presente'] ?? false;
                }
            }

            return $presencas;
        }

        return $presencasRaw;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: int, body: array<string, mixed>}
     */
    public function alterarBloqueioCheckin(
        ?int $tenantId,
        int $userId,
        int $turmaId,
        bool $bloquear,
        array $body,
        ?array $usuario,
    ): array {
        if (! $tenantId) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'Nenhum tenant selecionado'],
            ];
        }

        if ($turmaId <= 0) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'turma_id é obrigatório'],
            ];
        }

        $turma = $this->turmas->findById($turmaId, $tenantId);
        if (! $turma) {
            return [
                'status' => 404,
                'body' => ['success' => false, 'error' => 'Turma não encontrada'],
            ];
        }

        $papel = null;
        if (is_array($usuario) && isset($usuario['papel_id'])) {
            $papel = ['id' => (int) $usuario['papel_id']];
        }

        if (! $this->bloqueios->usuarioPodeGerenciarTurma($userId, $tenantId, $turmaId, $papel)) {
            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'error' => 'Sem permissão para gerenciar check-in desta turma',
                    'code' => 'ACCESS_DENIED',
                ],
            ];
        }

        $checkinsRemovidos = 0;
        if ($bloquear) {
            $motivo = isset($body['motivo']) ? (string) $body['motivo'] : null;
            $checkinsRemovidos = $this->bloqueios->bloquear(
                $turmaId,
                $tenantId,
                $userId > 0 ? $userId : null,
                $motivo,
            );
            $message = 'Check-in bloqueado para alunos nesta aula';
            if ($checkinsRemovidos > 0) {
                $message .= sprintf(
                    ' (%d check-in%s removido%s)',
                    $checkinsRemovidos,
                    $checkinsRemovidos === 1 ? '' : 's',
                    $checkinsRemovidos === 1 ? '' : 's',
                );
            }
        } else {
            $this->bloqueios->desbloquear($turmaId, $tenantId);
            $message = 'Check-in liberado para alunos nesta aula';
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => $message,
                'checkin_bloqueado' => $bloquear,
                'turma_id' => $turmaId,
                'checkins_removidos' => $checkinsRemovidos,
            ],
        ];
    }
}
