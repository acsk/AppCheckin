<?php

namespace App\Repositories;

use App\Support\AcademyDateTime;
use Illuminate\Support\Facades\DB;

class MobilePerfilRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listarTenantsAtivosDoUsuario(int $userId): array
    {
        return DB::table('tenants as t')
            ->join('tenant_usuario_papel as tup', 't.id', '=', 'tup.tenant_id')
            ->where('tup.usuario_id', $userId)
            ->where('tup.ativo', 1)
            ->where('t.ativo', 1)
            ->distinct()
            ->orderBy('t.nome')
            ->get([
                't.id',
                't.nome',
                't.slug',
                't.email',
                't.telefone',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getEstatisticasCheckin(int $userId): array
    {
        try {
            $presenteFilter = function ($q) {
                $q->whereNull('c.presente')->orWhere('c.presente', 1);
            };

            $totalCheckins = (int) DB::table('checkins as c')
                ->join('alunos as a', 'a.id', '=', 'c.aluno_id')
                ->where('a.usuario_id', $userId)
                ->where($presenteFilter)
                ->count();

            $checkinsMes = (int) DB::table('checkins as c')
                ->join('alunos as a', 'a.id', '=', 'c.aluno_id')
                ->where('a.usuario_id', $userId)
                ->whereRaw('MONTH(COALESCE(c.data_checkin_date, DATE(c.created_at))) = MONTH(CURRENT_DATE())')
                ->whereRaw('YEAR(COALESCE(c.data_checkin_date, DATE(c.created_at))) = YEAR(CURRENT_DATE())')
                ->where($presenteFilter)
                ->count();

            $ultimo = DB::table('checkins as c')
                ->join('alunos as a', 'a.id', '=', 'c.aluno_id')
                ->join('turmas as t', 'c.turma_id', '=', 't.id')
                ->join('dias as d', 't.dia_id', '=', 'd.id')
                ->where('a.usuario_id', $userId)
                ->where($presenteFilter)
                ->orderByDesc('d.data')
                ->orderByDesc('t.horario_inicio')
                ->select(['c.data_checkin_date', 't.horario_inicio as hora', 'd.data'])
                ->first();

            return [
                'total_checkins' => $totalCheckins,
                'checkins_mes' => $checkinsMes,
                'sequencia_dias' => $this->calcularSequencia($userId),
                'ultimo_checkin' => $ultimo ? [
                    'data' => $ultimo->data ?? ($ultimo->data_checkin_date ?? AcademyDateTime::today()),
                    'hora' => $ultimo->hora ?? '00:00:00',
                ] : null,
            ];
        } catch (\Throwable) {
            return [
                'total_checkins' => 0,
                'checkins_mes' => 0,
                'sequencia_dias' => 0,
                'ultimo_checkin' => null,
            ];
        }
    }

    public function calcularSequencia(int $userId): int
    {
        $datas = DB::table('checkins as c')
            ->join('alunos as a', 'a.id', '=', 'c.aluno_id')
            ->join('turmas as t', 'c.turma_id', '=', 't.id')
            ->join('dias as d', 't.dia_id', '=', 'd.id')
            ->where('a.usuario_id', $userId)
            ->where(function ($q) {
                $q->whereNull('c.presente')->orWhere('c.presente', 1);
            })
            ->distinct()
            ->orderByDesc('d.data')
            ->limit(30)
            ->pluck('d.data');

        if ($datas->isEmpty()) {
            return 0;
        }

        $sequencia = 0;
        $dataEsperada = AcademyDateTime::now();

        foreach ($datas as $data) {
            $dataCheckin = AcademyDateTime::fromDateAndTime((string) $data, '12:00:00');
            if (! $dataCheckin) {
                continue;
            }

            $diff = (int) $dataEsperada->diff($dataCheckin)->days;

            if ($sequencia === 0 && $diff <= 1) {
                $sequencia = 1;
                $dataEsperada = $dataCheckin;
            } elseif ($dataEsperada->modify('-1 day')->format('Y-m-d') === $dataCheckin->format('Y-m-d')) {
                $sequencia++;
                $dataEsperada = $dataCheckin;
            } else {
                break;
            }
        }

        return $sequencia;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function getPlanoUsuario(int $userId, int $tenantId): ?array
    {
        try {
            $plano = DB::table('matriculas as m')
                ->join('planos as p', 'm.plano_id', '=', 'p.id')
                ->join('alunos as a', 'a.id', '=', 'm.aluno_id')
                ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
                ->leftJoin('plano_ciclos as pc', 'pc.id', '=', 'm.plano_ciclo_id')
                ->leftJoin('assinatura_frequencias as af', 'af.id', '=', 'pc.assinatura_frequencia_id')
                ->leftJoin('modalidades as moda', 'moda.id', '=', 'p.modalidade_id')
                ->where('a.usuario_id', $userId)
                ->where('m.tenant_id', $tenantId)
                ->whereIn('sm.codigo', ['ativa', 'pendente', 'vencida'])
                ->where('sm.permite_checkin', 1)
                ->orderByRaw("FIELD(sm.codigo, 'ativa', 'pendente', 'vencida')")
                ->orderByDesc('m.data_vencimento')
                ->select([
                    'p.id',
                    'p.nome',
                    'p.valor',
                    'p.duracao_dias',
                    'p.descricao',
                    'm.id as matricula_id',
                    'm.data_inicio',
                    'm.data_vencimento as data_fim',
                    'm.proxima_data_vencimento',
                    'm.plano_ciclo_id',
                    'm.pacote_contrato_id',
                    'sm.id as status_id',
                    'sm.codigo as vinculo_status',
                    'sm.nome as status_nome',
                    'pc.meses as ciclo_meses',
                    'pc.valor as ciclo_valor',
                    'af.nome as ciclo_frequencia_nome',
                    'af.codigo as ciclo_frequencia_codigo',
                    'moda.nome as modalidade_nome',
                    'moda.icone as modalidade_icone',
                    'moda.cor as modalidade_cor',
                ])
                ->first();

            if (! $plano) {
                return null;
            }

            $plano = (array) $plano;
            $coresStatus = [
                'ativa' => '#28A745',
                'pendente' => '#FFA500',
                'vencida' => '#DC3545',
                'cancelada' => '#6C757D',
                'suspensa' => '#6C757D',
                'bloqueado' => '#8B5CF6',
            ];

            $plano['matricula_status'] = [
                'codigo' => $plano['vinculo_status'],
                'nome' => $plano['status_nome'],
                'cor' => $coresStatus[$plano['vinculo_status']] ?? '#6C757D',
            ];

            $dataFim = $plano['proxima_data_vencimento'] ?? $plano['data_fim'] ?? null;
            $diasRestantes = null;
            $vencimentoTexto = null;

            if ($dataFim) {
                $hoje = AcademyDateTime::fromDateAndTime(AcademyDateTime::today(), '00:00:00');
                $vencimento = AcademyDateTime::fromDateAndTime((string) $dataFim, '00:00:00');
                if ($hoje && $vencimento) {
                    $diff = $hoje->diff($vencimento);
                    $diasRestantes = $diff->invert ? -$diff->days : $diff->days;

                    if ($diasRestantes < 0) {
                        $vencimentoTexto = 'Vencido há '.abs($diasRestantes).' dia(s)';
                    } elseif ($diasRestantes === 0) {
                        $vencimentoTexto = 'Vence hoje';
                    } elseif ($diasRestantes < 30) {
                        $vencimentoTexto = "Vence em {$diasRestantes} dia(s)";
                    } else {
                        $meses = (int) floor($diasRestantes / 30);
                        $diasExtras = $diasRestantes % 30;
                        $vencimentoTexto = $diasExtras > 0
                            ? "{$meses} mês(es) e {$diasExtras} dia(s)"
                            : "{$meses} mês(es)";
                    }
                }
            }

            $plano['ciclo'] = null;
            if (! empty($plano['plano_ciclo_id'])) {
                $plano['ciclo'] = [
                    'meses' => $plano['ciclo_meses'] ? (int) $plano['ciclo_meses'] : null,
                    'valor' => $plano['ciclo_valor'] ? (float) $plano['ciclo_valor'] : null,
                    'frequencia' => $plano['ciclo_frequencia_nome'],
                    'frequencia_codigo' => $plano['ciclo_frequencia_codigo'],
                ];
            }

            $plano['modalidade'] = null;
            if (! empty($plano['modalidade_nome'])) {
                $plano['modalidade'] = [
                    'nome' => $plano['modalidade_nome'],
                    'icone' => $plano['modalidade_icone'],
                    'cor' => $plano['modalidade_cor'],
                ];
            }

            $plano['vencimento'] = [
                'data' => $dataFim,
                'dias_restantes' => $diasRestantes,
                'texto' => $vencimentoTexto,
            ];

            unset(
                $plano['status_id'],
                $plano['status_nome'],
                $plano['ciclo_meses'],
                $plano['ciclo_valor'],
                $plano['ciclo_frequencia_nome'],
                $plano['ciclo_frequencia_codigo'],
                $plano['modalidade_nome'],
                $plano['modalidade_icone'],
                $plano['modalidade_cor'],
            );

            return $plano;
        } catch (\Throwable) {
            return null;
        }
    }
}
