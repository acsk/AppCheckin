<?php

namespace App\Services\Admin;

use App\Repositories\DiaRepository;
use App\Repositories\TurmaRepository;
use DateInterval;
use DateTime;

class AdminDiaService
{
    public function __construct(
        private readonly DiaRepository $dias,
        private readonly TurmaRepository $turmas,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(): array
    {
        return [
            'status' => 200,
            'body' => ['dias' => $this->dias->getAtivos()],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function horarios(int $diaId, int $tenantId): array
    {
        $dia = $this->dias->findById($diaId, $tenantId);
        if (! $dia) {
            return [
                'status' => 404,
                'body' => ['error' => 'Dia não encontrado'],
            ];
        }

        $turmas = $this->turmas->listarPorDia($tenantId, $diaId);
        $turmasComDisponibilidade = array_map(
            static function (array $turma) {
                $alunosRegistrados = (int) ($turma['alunos_count'] ?? 0);
                $vagasDisponiveis = (int) $turma['limite_alunos'] - $alunosRegistrados;

                return [
                    'id' => $turma['id'],
                    'nome' => $turma['nome'],
                    'professor_nome' => $turma['professor_nome'],
                    'modalidade_nome' => $turma['modalidade_nome'],
                    'horario_inicio' => $turma['horario_inicio'],
                    'horario_fim' => $turma['horario_fim'],
                    'limite_alunos' => (int) $turma['limite_alunos'],
                    'alunos_registrados' => $alunosRegistrados,
                    'vagas_disponiveis' => $vagasDisponiveis,
                    'tolerancia_minutos' => (int) $turma['tolerancia_minutos'],
                    'tolerancia_antes_minutos' => (int) $turma['tolerancia_antes_minutos'],
                    'ativo' => (bool) $turma['ativo'],
                ];
            },
            $turmas,
        );

        return [
            'status' => 200,
            'body' => [
                'dia' => $dia,
                'turmas' => $turmasComDisponibilidade,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function horariosPorData(int $tenantId, ?string $data): array
    {
        if (! $data) {
            return [
                'status' => 400,
                'body' => ['error' => 'Parâmetro data é obrigatório'],
            ];
        }

        $dia = $this->dias->findByData($data, $tenantId);
        if (! $dia) {
            return [
                'status' => 404,
                'body' => ['error' => 'Dia não encontrado'],
            ];
        }

        $turmas = $this->turmas->listarPorDia($tenantId, (int) $dia['id']);
        $turmasCompletas = array_map(
            static function (array $turma) {
                $alunosRegistrados = (int) ($turma['alunos_count'] ?? 0);
                $vagasDisponiveis = (int) $turma['limite_alunos'] - $alunosRegistrados;

                return [
                    'id' => $turma['id'],
                    'nome' => $turma['nome'],
                    'professor_nome' => $turma['professor_nome'],
                    'professor_id' => (int) $turma['professor_id'],
                    'modalidade_nome' => $turma['modalidade_nome'],
                    'modalidade_icone' => $turma['modalidade_icone'],
                    'modalidade_cor' => $turma['modalidade_cor'],
                    'horario_inicio' => $turma['horario_inicio'],
                    'horario_fim' => $turma['horario_fim'],
                    'limite_alunos' => (int) $turma['limite_alunos'],
                    'alunos_registrados' => $alunosRegistrados,
                    'vagas_disponiveis' => $vagasDisponiveis,
                    'percentual_ocupacao' => (int) $turma['limite_alunos'] > 0
                        ? ($alunosRegistrados / (int) $turma['limite_alunos']) * 100
                        : 0,
                    'tolerancia_minutos' => (int) $turma['tolerancia_minutos'],
                    'tolerancia_antes_minutos' => (int) $turma['tolerancia_antes_minutos'],
                    'ativo' => (bool) $turma['ativo'],
                ];
            },
            $turmas,
        );

        return [
            'status' => 200,
            'body' => [
                'dia' => $dia,
                'turmas' => $turmasCompletas,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function desativarDias(int $tenantId, array $data): array
    {
        if (! isset($data['dia_id'])) {
            return $this->error('dia_id é obrigatório', 400);
        }

        $diaId = (int) $data['dia_id'];
        $periodo = $data['periodo'] ?? 'apenas_este';
        $mes = $data['mes'] ?? date('Y-m');
        $diasSemana = [];

        try {
            $diaOrigem = $this->dias->buscarPorId($diaId, $tenantId);
            if (! $diaOrigem) {
                return $this->error('Dia não encontrado', 404);
            }

            $detalhes = [];
            $diasParaDesativar = [];

            if ($periodo === 'apenas_este') {
                $diasParaDesativar = [$diaId];
            } elseif ($periodo === 'proxima_semana') {
                $diaSemanaNum = (int) date('N', strtotime($diaOrigem['data']));
                $diaSemanaNum = $diaSemanaNum === 7 ? 1 : $diaSemanaNum + 1;
                $diasSemana = [$diaSemanaNum];

                $dataProxSemana = new DateTime($diaOrigem['data']);
                $dataProxSemana->add(new DateInterval('P7D'));
                $mes = $dataProxSemana->format('Y-m');
            } elseif ($periodo === 'mes_todo') {
                $diasSemana = [1, 2, 3, 4, 5, 6, 7];
            } elseif ($periodo === 'custom') {
                if (! isset($data['dias_semana'])) {
                    return $this->error('Para periodo=custom, dias_semana é obrigatório', 400);
                }
                $diasSemana = (array) $data['dias_semana'];
            } else {
                return $this->error('periodo deve ser: apenas_este, proxima_semana, mes_todo ou custom', 400);
            }

            if ($diasSemana !== []) {
                foreach ($diasSemana as $dia) {
                    if (! is_numeric($dia) || (int) $dia < 1 || (int) $dia > 7) {
                        return $this->error('dias_semana deve conter valores entre 1 e 7', 400);
                    }
                }

                $diasEncontrados = $this->dias->buscarPorMesEDiasSemana($tenantId, $mes, array_map('intval', $diasSemana));
                $diasParaDesativar = array_column($diasEncontrados, 'id');

                foreach ($diasEncontrados as $dia) {
                    $detalhes[] = [
                        'dia_id' => $dia['id'],
                        'data' => $dia['data'],
                    ];
                }
            }

            $totalDesativados = 0;
            if ($diasParaDesativar !== []) {
                $totalDesativados = $this->dias->desativarVarios(array_map('intval', $diasParaDesativar), $tenantId);
            }

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => "Total de $totalDesativados dia(s) desativado(s) com sucesso",
                    'summary' => ['total_desativados' => $totalDesativados],
                    'detalhes' => $detalhes,
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao desativar dias: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function deletarHorariosDoDia(int $diaId, int $tenantId): array
    {
        try {
            $dia = $this->dias->findById($diaId, $tenantId);
            if (! $dia) {
                return $this->error('Dia não encontrado', 404);
            }

            $turmasParaDeletar = $this->turmas->listarResumoPorDia($diaId, $tenantId);
            $totalTurmas = count($turmasParaDeletar);

            if ($totalTurmas === 0) {
                return [
                    'status' => 200,
                    'body' => [
                        'type' => 'info',
                        'message' => 'Nenhuma turma encontrada para este dia',
                    ],
                ];
            }

            $turmasIds = array_map(static fn (array $t) => (int) $t['id'], $turmasParaDeletar);
            $checkinsRemovidos = $this->turmas->deletarCheckinsDasTurmas($turmasIds);
            $this->turmas->deletarTurmasDoDia($diaId, $tenantId);

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => "$totalTurmas turma(s) removida(s) com sucesso do dia {$dia['data']}",
                    'data' => [
                        'dia_id' => $diaId,
                        'data' => $dia['data'],
                        'turmas_removidas' => $totalTurmas,
                        'checkins_removidos' => $checkinsRemovidos,
                        'detalhes' => $turmasParaDeletar,
                    ],
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao deletar turmas: '.$e->getMessage(), 500);
        }
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
