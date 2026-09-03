<?php

namespace App\Services\Admin;

use App\Repositories\DiaRepository;
use App\Repositories\TurmaRepository;
use App\Services\TurmaCheckinBloqueioService;
use DateInterval;
use DateTime;

class AdminTurmaService
{
    public function __construct(
        private readonly TurmaRepository $turmas,
        private readonly DiaRepository $dias,
        private readonly TurmaCheckinBloqueioService $checkinBloqueio,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(int $tenantId, ?string $data, ?int $diaId, bool $apenasAtivas): array
    {
        $dia = null;
        $turmasLista = [];

        if ($data !== null) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
                return $this->error('Formato de data inválido. Use YYYY-MM-DD', 400);
            }

            $dia = $this->dias->findByData($data);
            if ($dia) {
                $turmasLista = $this->turmas->listarPorDia($tenantId, (int) $dia['id'], $apenasAtivas);
            }
        } elseif ($diaId !== null) {
            $dia = $this->dias->findById($diaId);
            if ($dia) {
                $turmasLista = $this->turmas->listarPorDia($tenantId, $diaId, $apenasAtivas);
            }
        } else {
            $hoje = date('Y-m-d');
            $dia = $this->dias->findByData($hoje);
            if (! $dia) {
                return $this->error('Dia atual não encontrado no sistema', 404);
            }
            $turmasLista = $this->turmas->listarPorDia($tenantId, (int) $dia['id'], $apenasAtivas);
        }

        $turmasLista = $this->checkinBloqueio->anexarFlagNasTurmas($turmasLista, $tenantId);

        return [
            'status' => 200,
            'body' => [
                'dia' => $dia,
                'turmas' => $turmasLista,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function show(int $id, int $tenantId): array
    {
        $turma = $this->turmas->findById($id, $tenantId);
        if (! $turma) {
            return $this->error('Turma não encontrada', 404);
        }

        return [
            'status' => 200,
            'body' => ['turma' => $turma],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function create(int $tenantId, array $data): array
    {
        if (empty($data['nome'])) {
            return $this->error('Nome da turma é obrigatório', 400);
        }
        if (empty($data['professor_id'])) {
            return $this->error('Professor é obrigatório', 400);
        }
        if (empty($data['modalidade_id'])) {
            return $this->error('Modalidade é obrigatória', 400);
        }
        if (empty($data['dia_id'])) {
            return $this->error('Dia é obrigatório', 400);
        }
        if (empty($data['horario_inicio']) || empty($data['horario_fim'])) {
            return $this->error('Horário de início e fim são obrigatórios', 400);
        }
        if ($data['horario_inicio'] >= $data['horario_fim']) {
            return $this->error('Horário de fim deve ser maior que horário de início', 400);
        }
        if (! $this->turmas->professorPertenceAoTenant((int) $data['professor_id'], $tenantId)) {
            return $this->error('Professor não encontrado', 400);
        }
        if (! $this->dias->findById((int) $data['dia_id'])) {
            return $this->error('Dia não encontrado', 400);
        }
        if (! empty($data['limite_alunos']) && (int) $data['limite_alunos'] < 1) {
            return $this->error('Limite de alunos deve ser maior que 0', 400);
        }

        $conflitos = $this->turmas->verificarHorarioOcupado(
            $tenantId,
            (int) $data['dia_id'],
            (string) $data['horario_inicio'],
            (string) $data['horario_fim'],
            null,
            (int) $data['professor_id'],
        );
        if ($conflitos !== []) {
            return $this->error('O professor já possui uma turma agendada neste horário neste dia', 400);
        }

        try {
            $data['tenant_id'] = $tenantId;
            $id = $this->turmas->criar($data);
            $turma = $this->turmas->findById($id);

            return [
                'status' => 201,
                'body' => [
                    'type' => 'success',
                    'message' => 'Turma criada com sucesso',
                    'turma' => $turma,
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao criar turma: '.$e->getMessage(), 500);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function update(int $id, int $tenantId, array $data): array
    {
        $turma = $this->turmas->findById($id, $tenantId);
        if (! $turma) {
            return $this->error('Turma não encontrada', 404);
        }

        if (! empty($data['professor_id'])
            && ! $this->turmas->professorPertenceAoTenant((int) $data['professor_id'], $tenantId)
        ) {
            return $this->error('Professor não encontrado', 400);
        }
        if (! empty($data['dia_id']) && ! $this->dias->findById((int) $data['dia_id'])) {
            return $this->error('Dia não encontrado', 400);
        }
        if (! empty($data['horario_inicio']) && ! empty($data['horario_fim'])
            && $data['horario_inicio'] >= $data['horario_fim']
        ) {
            return $this->error('Horário de fim deve ser maior que horário de início', 400);
        }
        if (! empty($data['limite_alunos']) && (int) $data['limite_alunos'] < 1) {
            return $this->error('Limite de alunos deve ser maior que 0', 400);
        }

        if ((! empty($data['horario_inicio']) && ! empty($data['horario_fim'])) || ! empty($data['dia_id'])) {
            $horarioInicio = ! empty($data['horario_inicio']) ? $data['horario_inicio'] : $turma['horario_inicio'];
            $horarioFim = ! empty($data['horario_fim']) ? $data['horario_fim'] : $turma['horario_fim'];
            $diaId = ! empty($data['dia_id']) ? (int) $data['dia_id'] : (int) $turma['dia_id'];
            $professorId = ! empty($data['professor_id']) ? (int) $data['professor_id'] : (int) $turma['professor_id'];

            $conflitos = $this->turmas->verificarHorarioOcupado(
                $tenantId,
                $diaId,
                (string) $horarioInicio,
                (string) $horarioFim,
                $id,
                $professorId,
            );
            if ($conflitos !== []) {
                return $this->error('O professor já possui outra turma agendada neste horário neste dia', 400);
            }
        }

        try {
            $this->turmas->atualizar($id, $data);

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Turma atualizada com sucesso',
                    'turma' => $this->turmas->findById($id),
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao atualizar turma: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function delete(int $id, int $tenantId): array
    {
        if (! $this->turmas->findById($id, $tenantId)) {
            return $this->error('Turma não encontrada', 404);
        }

        try {
            $this->turmas->deletar($id);

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Turma deletada com sucesso',
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao deletar turma: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function deletePermanente(int $id, int $tenantId): array
    {
        if (! $this->turmas->findById($id, $tenantId)) {
            return $this->error('Turma não encontrada', 404);
        }

        try {
            $this->turmas->deletarPermanente($id);

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Turma deletada permanentemente',
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao deletar turma permanentemente: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function verificarVagas(int $id, int $tenantId): array
    {
        $turma = $this->turmas->findById($id, $tenantId);
        if (! $turma) {
            return $this->error('Turma não encontrada', 404);
        }

        $alunos = $this->turmas->contarAlunos($id);

        return [
            'status' => 200,
            'body' => [
                'turma_id' => $id,
                'limite_alunos' => $turma['limite_alunos'],
                'alunos_inscritos' => $alunos,
                'vagas_disponiveis' => (int) $turma['limite_alunos'] - $alunos,
                'tem_vagas' => $this->turmas->temVagas($id),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function replicarPorDiasSemana(int $tenantId, array $data): array
    {
        if (! isset($data['dia_id'])) {
            return $this->error('dia_id é obrigatório', 400);
        }

        $diaOrigemId = (int) $data['dia_id'];
        $periodo = $data['periodo'] ?? 'custom';
        $mes = $data['mes'] ?? date('Y-m');
        $diasSemana = [];

        if ($periodo === 'proxima_semana') {
            $diaOrigem = $this->dias->buscarPorId($diaOrigemId, $tenantId);
            if (! $diaOrigem) {
                return $this->error('Dia de origem não encontrado', 404);
            }

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
            return $this->error('periodo deve ser: proxima_semana, mes_todo ou custom', 400);
        }

        foreach ($diasSemana as $dia) {
            if (! is_numeric($dia) || (int) $dia < 1 || (int) $dia > 7) {
                return $this->error('dias_semana deve conter valores entre 1 e 7', 400);
            }
        }

        try {
            $turmasOrigem = $this->turmas->listarPorDia($tenantId, $diaOrigemId);

            if (! empty($data['modalidade_id'])) {
                $modalidadeId = (int) $data['modalidade_id'];
                $turmasOrigem = array_values(array_filter(
                    $turmasOrigem,
                    static fn (array $t) => (int) $t['modalidade_id'] === $modalidadeId,
                ));
            }

            if ($turmasOrigem === []) {
                return [
                    'status' => 200,
                    'body' => [
                        'type' => 'success',
                        'message' => 'Nenhuma turma encontrada no dia de origem',
                        'summary' => [
                            'total_solicitadas' => 0,
                            'total_criadas' => 0,
                            'total_puladas' => 0,
                        ],
                    ],
                ];
            }

            $diasDestino = $this->dias->buscarDiasDoMes($mes, $diasSemana, $diaOrigemId);

            if ($diasDestino === []) {
                return [
                    'status' => 200,
                    'body' => [
                        'type' => 'success',
                        'message' => 'Nenhum dia encontrado para replicação no período',
                        'summary' => [
                            'total_solicitadas' => count($turmasOrigem),
                            'total_criadas' => 0,
                            'total_puladas' => 0,
                        ],
                    ],
                ];
            }

            $totalCriadas = 0;
            $totalPuladas = 0;
            $detalhes = [];
            $turmasCriadas = [];

            foreach ($turmasOrigem as $turmaOrigem) {
                $detalheTurma = [
                    'turma_original_id' => $turmaOrigem['id'],
                    'professor_id' => $turmaOrigem['professor_id'],
                    'modalidade_id' => $turmaOrigem['modalidade_id'],
                    'horario_inicio' => $turmaOrigem['horario_inicio'],
                    'horario_fim' => $turmaOrigem['horario_fim'],
                    'criadas' => 0,
                    'puladas' => 0,
                    'detalhes_puladas' => [],
                ];

                foreach ($diasDestino as $diaDestino) {
                    $temConflito = $this->turmas->verificarHorarioOcupado(
                        $tenantId,
                        (int) $diaDestino['id'],
                        (string) $turmaOrigem['horario_inicio'],
                        (string) $turmaOrigem['horario_fim'],
                        null,
                        (int) $turmaOrigem['professor_id'],
                    );

                    if ($temConflito !== []) {
                        $detalheTurma['puladas']++;
                        $detalheTurma['detalhes_puladas'][] = [
                            'dia_id' => $diaDestino['id'],
                            'data' => $diaDestino['data'],
                            'razao' => 'Horário já ocupado',
                        ];
                        $totalPuladas++;
                        continue;
                    }

                    $idNovo = $this->turmas->criar([
                        'tenant_id' => $tenantId,
                        'professor_id' => (int) $turmaOrigem['professor_id'],
                        'modalidade_id' => (int) $turmaOrigem['modalidade_id'],
                        'dia_id' => (int) $diaDestino['id'],
                        'horario_inicio' => $turmaOrigem['horario_inicio'],
                        'horario_fim' => $turmaOrigem['horario_fim'],
                        'nome' => $turmaOrigem['nome'] ?? '',
                        'limite_alunos' => (int) $turmaOrigem['limite_alunos'],
                        'ativo' => 1,
                    ]);

                    $detalheTurma['criadas']++;
                    $totalCriadas++;

                    $turmaCompleta = $this->turmas->findById($idNovo, $tenantId);
                    if ($turmaCompleta) {
                        $turmasCriadas[] = $turmaCompleta;
                    }
                }

                $detalhes[] = $detalheTurma;
            }

            return [
                'status' => 201,
                'body' => [
                    'type' => 'success',
                    'message' => 'Replicação concluída com sucesso',
                    'summary' => [
                        'total_solicitadas' => count($turmasOrigem),
                        'total_criadas' => $totalCriadas,
                        'total_puladas' => $totalPuladas,
                        'dias_destino' => count($diasDestino),
                    ],
                    'detalhes' => $detalhes,
                    'turmas_criadas' => $turmasCriadas,
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao replicar turmas: '.$e->getMessage(), 500);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function replicarSemana(int $tenantId, array $data): array
    {
        if (! isset($data['semana_data'])) {
            return $this->error('semana_data é obrigatório (formato: YYYY-MM-DD)', 400);
        }
        if (! isset($data['meses_destino']) || ! is_array($data['meses_destino']) || $data['meses_destino'] === []) {
            return $this->error('meses_destino é obrigatório (array de meses no formato YYYY-MM)', 400);
        }

        $semanaData = (string) $data['semana_data'];
        $mesesDestino = $data['meses_destino'];
        $modalidadeId = isset($data['modalidade_id']) ? (int) $data['modalidade_id'] : null;

        try {
            $dataRef = new DateTime($semanaData);
            $diaSemana = (int) $dataRef->format('N');

            $segundaOrigem = clone $dataRef;
            $segundaOrigem->sub(new DateInterval('P'.($diaSemana - 1).'D'));

            $domingoOrigem = clone $segundaOrigem;
            $domingoOrigem->add(new DateInterval('P6D'));

            $diasOrigem = $this->dias->buscarDiasEntreDatas(
                $segundaOrigem->format('Y-m-d'),
                $domingoOrigem->format('Y-m-d'),
            );

            if ($diasOrigem === []) {
                return $this->error('Nenhum dia encontrado na semana de origem', 404);
            }

            $diasIds = array_column($diasOrigem, 'id');
            $turmasOrigem = $this->turmas->listarAtivasPorDiaIds($tenantId, array_map('intval', $diasIds));

            if ($modalidadeId !== null) {
                $turmasOrigem = array_values(array_filter(
                    $turmasOrigem,
                    static fn (array $t) => (int) $t['modalidade_id'] === $modalidadeId,
                ));
            }

            if ($turmasOrigem === []) {
                return [
                    'status' => 200,
                    'body' => [
                        'type' => 'success',
                        'message' => 'Nenhuma turma encontrada na semana de origem',
                        'semana_origem' => [
                            'inicio' => $segundaOrigem->format('Y-m-d'),
                            'fim' => $domingoOrigem->format('Y-m-d'),
                        ],
                        'summary' => [
                            'total_turmas_origem' => 0,
                            'total_criadas' => 0,
                            'total_puladas' => 0,
                        ],
                    ],
                ];
            }

            $turmasPorDiaSemana = [];
            foreach ($turmasOrigem as $turma) {
                $ds = (int) $turma['dia_semana'];
                $turmasPorDiaSemana[$ds][] = $turma;
            }

            $totalCriadas = 0;
            $totalPuladas = 0;
            $detalhes = [];
            $turmasCriadas = [];

            foreach ($mesesDestino as $mesDestino) {
                $detalheMes = [
                    'mes' => $mesDestino,
                    'criadas' => 0,
                    'puladas' => 0,
                    'dias_processados' => [],
                ];

                foreach ($turmasPorDiaSemana as $diaSemanaNum => $turmasDoDia) {
                    $diasDestino = $this->dias->buscarDiasDoMesPorDiaSemana((string) $mesDestino, (int) $diaSemanaNum);

                    foreach ($diasDestino as $diaDestino) {
                        $detalheDia = [
                            'dia_id' => $diaDestino['id'],
                            'data' => $diaDestino['data'],
                            'dia_semana' => $diaSemanaNum,
                            'turmas_criadas' => 0,
                            'turmas_puladas' => 0,
                        ];

                        foreach ($turmasDoDia as $turmaOrigem) {
                            $temConflito = $this->turmas->verificarHorarioOcupado(
                                $tenantId,
                                (int) $diaDestino['id'],
                                (string) $turmaOrigem['horario_inicio'],
                                (string) $turmaOrigem['horario_fim'],
                                null,
                                (int) $turmaOrigem['professor_id'],
                            );

                            if ($temConflito !== []) {
                                $detalheDia['turmas_puladas']++;
                                $detalheMes['puladas']++;
                                $totalPuladas++;
                                continue;
                            }

                            $idNova = $this->turmas->criar([
                                'tenant_id' => $tenantId,
                                'professor_id' => (int) $turmaOrigem['professor_id'],
                                'modalidade_id' => (int) $turmaOrigem['modalidade_id'],
                                'dia_id' => (int) $diaDestino['id'],
                                'horario_inicio' => $turmaOrigem['horario_inicio'],
                                'horario_fim' => $turmaOrigem['horario_fim'],
                                'nome' => $turmaOrigem['nome'] ?? '',
                                'limite_alunos' => (int) $turmaOrigem['limite_alunos'],
                                'ativo' => 1,
                            ]);

                            $detalheDia['turmas_criadas']++;
                            $detalheMes['criadas']++;
                            $totalCriadas++;

                            $turmaCompleta = $this->turmas->findById($idNova, $tenantId);
                            if ($turmaCompleta) {
                                $turmasCriadas[] = $turmaCompleta;
                            }
                        }

                        $detalheMes['dias_processados'][] = $detalheDia;
                    }
                }

                $detalhes[] = $detalheMes;
            }

            return [
                'status' => 201,
                'body' => [
                    'type' => 'success',
                    'message' => 'Replicação de semana concluída com sucesso',
                    'semana_origem' => [
                        'inicio' => $segundaOrigem->format('Y-m-d'),
                        'fim' => $domingoOrigem->format('Y-m-d'),
                        'total_turmas' => count($turmasOrigem),
                    ],
                    'summary' => [
                        'meses_destino' => count($mesesDestino),
                        'total_turmas_origem' => count($turmasOrigem),
                        'total_criadas' => $totalCriadas,
                        'total_puladas' => $totalPuladas,
                    ],
                    'detalhes_por_mes' => $detalhes,
                    'turmas_criadas' => $turmasCriadas,
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao replicar semana: '.$e->getMessage(), 500);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function desativarTurma(int $tenantId, array $data): array
    {
        if (! isset($data['turma_id'])) {
            return $this->error('turma_id é obrigatório', 400);
        }

        $turmaId = (int) $data['turma_id'];
        $periodo = $data['periodo'] ?? 'apenas_esta';
        $mes = $data['mes'] ?? date('Y-m');
        $diasSemana = [];

        try {
            $turmaOrigem = $this->turmas->findById($turmaId, $tenantId);
            if (! $turmaOrigem) {
                return $this->error('Turma não encontrada', 404);
            }

            if ($periodo === 'apenas_esta') {
                $this->turmas->desativar($turmaId);

                return [
                    'status' => 200,
                    'body' => [
                        'type' => 'success',
                        'message' => 'Turma desativada com sucesso',
                        'summary' => ['total_desativadas' => 1],
                    ],
                ];
            }

            if ($periodo === 'proxima_semana') {
                $diaOrigem = $this->dias->buscarPorId((int) $turmaOrigem['dia_id'], $tenantId);
                if (! $diaOrigem) {
                    throw new \RuntimeException('Dia de origem não encontrado');
                }

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
                return $this->error('periodo deve ser: apenas_esta, proxima_semana, mes_todo ou custom', 400);
            }

            foreach ($diasSemana as $dia) {
                if (! is_numeric($dia) || (int) $dia < 1 || (int) $dia > 7) {
                    return $this->error('dias_semana deve conter valores entre 1 e 7', 400);
                }
            }

            $this->turmas->desativar($turmaId);
            $totalDesativadas = 1;

            $diasDestino = $this->dias->buscarDiasDoMes($mes, $diasSemana, (int) $turmaOrigem['dia_id']);
            $detalhes = [];

            foreach ($diasDestino as $diaDestino) {
                $turmaEncontrada = $this->turmas->buscarSimilarEmOutroDia(
                    $tenantId,
                    (int) $diaDestino['id'],
                    (int) $turmaOrigem['professor_id'],
                    (int) $turmaOrigem['modalidade_id'],
                    (string) $turmaOrigem['horario_inicio'],
                    (string) $turmaOrigem['horario_fim'],
                );

                if ($turmaEncontrada) {
                    $this->turmas->desativar((int) $turmaEncontrada['id']);
                    $totalDesativadas++;
                    $detalhes[] = [
                        'turma_id' => $turmaEncontrada['id'],
                        'dia_id' => $diaDestino['id'],
                        'data' => $diaDestino['data'],
                        'status' => 'desativada',
                    ];
                } else {
                    $detalhes[] = [
                        'dia_id' => $diaDestino['id'],
                        'data' => $diaDestino['data'],
                        'status' => 'nao_encontrada',
                        'motivo' => 'Nenhuma turma com mesmo horário neste dia',
                    ];
                }
            }

            return [
                'status' => 200,
                'body' => [
                    'type' => 'success',
                    'message' => 'Turmas desativadas com sucesso',
                    'summary' => ['total_desativadas' => $totalDesativadas],
                    'detalhes' => $detalhes,
                ],
            ];
        } catch (\Throwable $e) {
            return $this->error('Erro ao desativar turmas: '.$e->getMessage(), 500);
        }
    }

    /**
     * @param  array<string, mixed>|null  $papel
     * @return array{status: int, body: array<string, mixed>}
     */
    public function alterarBloqueioCheckin(
        int $turmaId,
        int $tenantId,
        int $userId,
        ?array $papel,
        bool $bloquear,
        ?string $motivo = null,
    ): array {
        if ($turmaId <= 0) {
            return $this->error('ID da turma inválido', 400);
        }

        if (! $this->turmas->findById($turmaId, $tenantId)) {
            return $this->error('Turma não encontrada', 404);
        }

        if (! $this->checkinBloqueio->usuarioPodeGerenciarTurma($userId, $tenantId, $turmaId, $papel)) {
            return $this->error('Sem permissão para gerenciar check-in desta turma', 403);
        }

        $checkinsRemovidos = 0;
        if ($bloquear) {
            $checkinsRemovidos = $this->checkinBloqueio->bloquear(
                $turmaId,
                $tenantId,
                $userId ?: null,
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
            $this->checkinBloqueio->desbloquear($turmaId, $tenantId);
            $message = 'Check-in liberado para alunos nesta aula';
        }

        $turmaAtualizada = $this->turmas->findById($turmaId, $tenantId);
        $turmasComFlag = $this->checkinBloqueio->anexarFlagNasTurmas(
            $turmaAtualizada ? [$turmaAtualizada] : [],
            $tenantId,
        );

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => $message,
                'turma' => $turmasComFlag[0] ?? null,
                'checkin_bloqueado' => $bloquear,
                'checkins_removidos' => $checkinsRemovidos,
            ],
        ];
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
