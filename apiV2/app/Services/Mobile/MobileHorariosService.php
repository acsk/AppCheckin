<?php

namespace App\Services\Mobile;

use App\Repositories\TurmaRepository;
use App\Services\TurmaCheckinBloqueioService;
use App\Support\AcademyDateTime;

class MobileHorariosService
{
    public function __construct(
        private readonly TurmaRepository $turmas,
        private readonly TurmaCheckinBloqueioService $bloqueios,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function horariosDisponiveis(?int $tenantId, int $userId, string $data): array
    {
        if (! $tenantId) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'Nenhum tenant selecionado'],
            ];
        }

        if (! \DateTime::createFromFormat('Y-m-d', $data)) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'Formato de data inválido. Use YYYY-MM-DD'],
            ];
        }

        $dia = $this->turmas->findDiaByData($data);

        if (! $dia) {
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'data' => [
                        'dia' => null,
                        'turmas' => [],
                        'total' => 0,
                        'mensagem' => 'Nenhuma turma disponível para esta data',
                    ],
                ],
            ];
        }

        $turmasRaw = $this->turmas->listarTurmasAtivasPorDia((int) $dia['id'], $tenantId);
        $agora = AcademyDateTime::now();

        $turmasFormatadas = [];
        foreach ($turmasRaw as $turma) {
            $turma = (array) $turma;
            $checkinsCount = $this->turmas->contarCheckinsNaTurma((int) $turma['id'], $tenantId);

            $horarioInicio = $turma['horario_inicio'];
            $toleranciaAntes = (int) ($turma['tolerancia_antes_minutos'] ?? 480);
            $toleranciaDepois = (int) ($turma['tolerancia_minutos'] ?? 10);

            $dataHoraTurma = AcademyDateTime::fromDateAndTime($data, $horarioInicio);
            if (! $dataHoraTurma) {
                continue;
            }

            $horarioAbertura = clone $dataHoraTurma;
            $horarioAbertura->modify("-{$toleranciaAntes} minutes");
            $horarioFechamento = clone $dataHoraTurma;
            $horarioFechamento->modify("+{$toleranciaDepois} minutes");

            $checkinDisponivel = $agora >= $horarioAbertura && $agora <= $horarioFechamento;

            $turmasFormatadas[] = [
                'id' => (int) $turma['id'],
                'nome' => $turma['nome'],
                'professor' => [
                    'id' => (int) $turma['professor_id'],
                    'nome' => $turma['professor_nome'],
                ],
                'modalidade' => [
                    'id' => (int) $turma['modalidade_id'],
                    'nome' => $turma['modalidade_nome'],
                    'icone' => $turma['modalidade_icone'],
                    'cor' => $turma['modalidade_cor'],
                ],
                'horario' => [
                    'inicio' => $turma['horario_inicio'],
                    'fim' => $turma['horario_fim'],
                ],
                'checkin' => [
                    'disponivel' => $checkinDisponivel,
                    'ja_abriu' => $agora >= $horarioAbertura,
                    'ja_fechou' => $agora > $horarioFechamento,
                    'abertura' => $horarioAbertura->format('Y-m-d H:i:s'),
                    'fechamento' => $horarioFechamento->format('Y-m-d H:i:s'),
                    'tolerancia_antes_minutos' => $toleranciaAntes,
                    'tolerancia_depois_minutos' => $toleranciaDepois,
                ],
                'limite_alunos' => (int) $turma['limite_alunos'],
                'alunos_inscritos' => $checkinsCount,
                'vagas_disponiveis' => (int) $turma['limite_alunos'] - $checkinsCount,
                'ativo' => (bool) $turma['ativo'],
                'created_at' => $turma['created_at'],
                'updated_at' => $turma['updated_at'],
            ];
        }

        $bloqueadas = $this->bloqueios->listarTurmaIdsBloqueadas(
            $tenantId,
            array_map(static fn (array $t) => $t['id'], $turmasFormatadas),
        );
        $ehStaff = $this->bloqueios->usuarioEhStaffNoTenant($userId, $tenantId);

        $turmasFormatadas = array_values(array_filter(
            array_map(static function (array $turma) use ($bloqueadas) {
                $id = (int) $turma['id'];
                $turma['checkin_bloqueado'] = isset($bloqueadas[$id]);

                return $turma;
            }, $turmasFormatadas),
            static fn (array $turma) => $ehStaff || empty($turma['checkin_bloqueado']),
        ));

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'dia' => [
                        'id' => (int) $dia['id'],
                        'data' => $dia['data'],
                        'ativo' => (bool) $dia['ativo'],
                    ],
                    'turmas' => $turmasFormatadas,
                    'total' => count($turmasFormatadas),
                    'tenant_id_resolvido' => $tenantId,
                ],
            ],
        ];
    }
}
