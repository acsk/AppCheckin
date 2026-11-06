<?php

namespace App\Services\Mobile;

use App\Repositories\TurmaRepository;
use App\Services\AcessoHibridoService;
use App\Services\TurmaCheckinBloqueioService;
use App\Support\AcademyDateTime;
use App\Support\CheckinJanela;
use App\Support\CheckinToleranciaAntes;
use Illuminate\Support\Facades\DB;

class MobileHorariosService
{
    public function proximaAulaFixa(int $tenantId, int $userId): ?array
    {
        if (! app(AcessoHibridoService::class)->atualizado()) {
            return null;
        }
        $agora = AcademyDateTime::now();
        $acesso = app(AcessoHibridoService::class);
        if ($acesso->gradeAtualizada()) {
            $grade = $this->gradeAluno($tenantId, $userId);
            $aulas = DB::table('turmas as t')->join('dias as d', 'd.id', '=', 't.dia_id')
                ->where('t.tenant_id', $tenantId)->whereIn('t.turma_fixa_id', array_column($grade, 'turma_fixa_id'))->where('t.ativo', 1)->where('d.ativo', 1)
                ->where(fn ($q) => $q->where('d.data', '>', $agora->format('Y-m-d'))->orWhere(fn ($q) => $q
                    ->where('d.data', $agora->format('Y-m-d'))->where('t.horario_fim', '>', $agora->format('H:i:s'))))
                ->orderBy('d.data')->orderBy('t.horario_inicio')->get(['t.*', 'd.data as dia_data']);
            $aluno = (int) DB::table('alunos')->where('usuario_id', $userId)->value('id');
            foreach ($aulas as $t) {
                $dia = (int) (new \DateTimeImmutable($t->dia_data))->format('N');
                if (! array_filter($grade, fn ($g) => (int) $g['turma_fixa_id'] === (int) $t->turma_fixa_id && (int) $g['dia_semana'] === $dia)) {
                    continue;
                }
                $m = $acesso->matriculaParaAula($aluno, $tenantId, (array) $t);
                if ($m && $m['tipo_acesso'] === 'turma_fixa' && empty($m['reposicao'])) {
                    return ['id' => $t->id, 'nome' => $t->nome, 'data' => $t->dia_data, 'horario_inicio' => $t->horario_inicio, 'horario_fim' => $t->horario_fim];
                }
            }

            return null;
        }
        $aula = DB::table('turmas as t')->join('dias as d', 'd.id', '=', 't.dia_id')
            ->join('matriculas as m', 'm.turma_fixa_id', '=', 't.turma_fixa_id')
            ->join('status_matricula as s', 's.id', '=', 'm.status_id')->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->where('t.tenant_id', $tenantId)->where('m.tenant_id', $tenantId)->where('a.usuario_id', $userId)
            ->where('m.tipo_acesso', 'turma_fixa')->where('s.permite_checkin', 1)->where('s.ativo', 1)
            ->where('t.ativo', 1)->where('d.ativo', 1)->whereColumn('d.data', '>=', 'm.data_inicio')
            ->whereRaw('d.data <= COALESCE(m.proxima_data_vencimento, m.data_vencimento)')
            ->where(fn ($q) => $q->where('d.data', '>', $agora->format('Y-m-d'))->orWhere(fn ($q) => $q
                ->where('d.data', $agora->format('Y-m-d'))->where('t.horario_fim', '>', $agora->format('H:i:s'))))
            ->orderBy('d.data')->orderBy('t.horario_inicio')->first(['t.id', 't.nome', 'd.data', 't.horario_inicio', 't.horario_fim']);

        return $aula ? (array) $aula : null;
    }

    public function gradeAluno(int $tenantId, int $userId): array
    {
        $acesso = app(AcessoHibridoService::class);
        if (! $acesso->atualizado()) {
            return [];
        }
        $ids = DB::table('matriculas as m')->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->join('status_matricula as s', 's.id', '=', 'm.status_id')->where('m.tenant_id', $tenantId)->where('a.usuario_id', $userId)
            ->where('m.tipo_acesso', 'turma_fixa')->whereIn('s.codigo', ['ativa', 'pendente', 'aguardando_vaga'])
            ->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= ?', [AcademyDateTime::today()])->pluck('m.id');
        $grade = [];
        foreach ($ids as $id) {
            foreach ($acesso->grade($tenantId, (int) $id) as $slot) {
                $grade[] = $slot + ['matricula_id' => (int) $id];
            }
        }

        return $grade;
    }

    public function aguardandoVaga(int $tenantId, int $userId): bool
    {
        if (! app(AcessoHibridoService::class)->atualizado()) {
            return false;
        }

        return DB::table('matriculas as m')->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->join('status_matricula as s', 's.id', '=', 'm.status_id')->where('m.tenant_id', $tenantId)
            ->where('a.usuario_id', $userId)->where('s.codigo', 'aguardando_vaga')->exists();
    }

    public function __construct(
        private readonly TurmaRepository $turmas,
        private readonly TurmaCheckinBloqueioService $bloqueios,
        private readonly CheckinToleranciaAntes $toleranciaAntes,
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
            $acesso = app(AcessoHibridoService::class);
            $matriculaAula = null;
            $saldo = null;
            if ($acesso->atualizado()) {
                $completa = $this->turmas->findById((int) $turma['id'], $tenantId);
                $turma = array_replace($turma, $completa ?? []);
                $alunoId = (int) DB::table('alunos')->where('usuario_id', $userId)->value('id');
                $matriculaAula = $acesso->matriculaParaAula($alunoId, $tenantId, $turma);
                if (! $this->bloqueios->usuarioEhStaffNoTenant($userId, $tenantId)
                    && (! $matriculaAula || (empty($matriculaAula['reposicao']) && $matriculaAula['tipo_acesso'] === 'horario_livre' && ! $turma['checkin_livre']))) {
                    continue;
                }
                $saldo = $acesso->vagas($turma, $tenantId);
            }
            $checkinsCount = $this->turmas->contarCheckinsNaTurma((int) $turma['id'], $tenantId);

            $horarioInicio = $turma['horario_inicio'];
            $turmaAntes = (int) ($turma['tolerancia_antes_minutos'] ?? 480);
            $toleranciaAntes = $this->toleranciaAntes->effectiveAntesMinutos($tenantId, $turmaAntes);
            $maxAcademia = $this->toleranciaAntes->maxMinutosTenant($tenantId);
            $toleranciaDepois = (int) ($turma['tolerancia_minutos'] ?? 10);

            $dataHoraTurma = AcademyDateTime::fromDateAndTime($data, $horarioInicio);
            if (! $dataHoraTurma) {
                continue;
            }

            $horarioAbertura = clone $dataHoraTurma;
            $horarioAbertura->modify("-{$toleranciaAntes} minutes");
            $horarioFechamento = CheckinJanela::fechamento($dataHoraTurma, $turma);
            $limiteCancelamento = CheckinJanela::limiteCancelamento($dataHoraTurma, $turma);

            $fixa = ($matriculaAula['tipo_acesso'] ?? '') === 'turma_fixa' || ! empty($matriculaAula['reposicao']);
            $checkinDisponivel = $agora >= $horarioAbertura && $agora <= $horarioFechamento
                && ($fixa || ($saldo['vagas_livres'] ?? 1) > 0);

            $turmasFormatadas[] = [
                'id' => (int) $turma['id'],
                'nome' => $turma['nome'],
                'tipo_acesso' => $matriculaAula['tipo_acesso'] ?? 'horario_livre',
                'turma_fixa_id' => $turma['turma_fixa_id'] ?? null,
                'vaga_reservada' => $fixa,
                'reposicao' => (bool) ($matriculaAula['reposicao'] ?? false),
                'reposicoes_confirmadas' => $saldo['reposicoes_confirmadas'] ?? 0,
                'vagas_fixas' => $saldo['vagas_fixas'] ?? 0,
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
                    'tolerancia_antes_turma_minutos' => $turmaAntes,
                    'max_tolerancia_academia_minutos' => $maxAcademia > 0 ? $maxAcademia : null,
                    'tolerancia_depois_minutos' => $toleranciaDepois,
                    'tolerancia_antes_checkin_minutos' => CheckinJanela::fechamentoAntesMinutos($turma),
                    'tolerancia_cancelamento_minutos' => CheckinJanela::cancelamentoAntesMinutos($turma),
                    'cancelamento_ate' => $limiteCancelamento->format('Y-m-d H:i:s'),
                ],
                'limite_alunos' => (int) $turma['limite_alunos'],
                'alunos_inscritos' => $checkinsCount,
                'vagas_disponiveis' => $fixa ? 1 : ($saldo['vagas_livres'] ?? max(0, (int) $turma['limite_alunos'] - $checkinsCount)),
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
        $resumoCheckins = $this->turmas->resumoCheckinsPorTurmas(
            array_map(static fn (array $t) => $t['id'], $turmasFormatadas),
            $tenantId,
            $userId,
        );

        $turmasFormatadas = array_values(array_filter(
            array_map(static function (array $turma) use ($bloqueadas, $resumoCheckins) {
                $id = (int) $turma['id'];
                $turma['checkin_bloqueado'] = isset($bloqueadas[$id]);
                $turma['meu_checkin_id'] = $resumoCheckins[$id]['meu_checkin_id'] ?? null;
                $turma['participantes_preview'] = $resumoCheckins[$id]['nomes'] ?? [];

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
