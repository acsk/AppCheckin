<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MatriculaRepository
{
    public function findElegivelParaCheckin(int $alunoId, int $tenantId): ?array
    {
        $row = DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.aluno_id', $alunoId)
            ->where('m.tenant_id', $tenantId)
            ->where('sm.permite_checkin', 1)
            ->where('sm.ativo', 1)
            ->orderByDesc('m.created_at')
            ->select([
                'm.id',
                'm.proxima_data_vencimento',
                'm.data_vencimento',
                'm.periodo_teste',
                'sm.codigo as status_codigo',
                'sm.nome as status_nome',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    public function atualizarStatusMatriculasVencidas(int $userId, int $tenantId): void
    {
        try {
            $hoje = date('Y-m-d');

            $matriculasVencidas = DB::table('matriculas as m')
                ->join('alunos as a', 'a.id', '=', 'm.aluno_id')
                ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
                ->where('a.usuario_id', $userId)
                ->where('m.tenant_id', $tenantId)
                ->where('sm.permite_checkin', 1)
                ->where('sm.ativo', 1)
                ->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) < ?', [$hoje])
                ->select([
                    'm.id',
                    DB::raw('DATEDIFF(?, COALESCE(m.proxima_data_vencimento, m.data_vencimento)) as dias_vencido'),
                ])
                ->addBinding($hoje, 'select')
                ->get();

            foreach ($matriculasVencidas as $matricula) {
                $diasVencido = (int) $matricula->dias_vencido;
                $novoStatus = $diasVencido >= 5 ? 'cancelada' : 'vencida';

                DB::table('matriculas')
                    ->where('id', $matricula->id)
                    ->update([
                        'status_id' => DB::table('status_matricula')
                            ->where('codigo', $novoStatus)
                            ->value('id'),
                        'updated_at' => now(),
                    ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[MatriculaRepository] Erro ao atualizar matrículas vencidas: '.$e->getMessage());
        }
    }

    public function buscarMatriculaMaisRecentePorAluno(int $alunoId, int $tenantId): ?array
    {
        $row = DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.aluno_id', $alunoId)
            ->where('m.tenant_id', $tenantId)
            ->orderByDesc('m.created_at')
            ->select([
                'm.id',
                'm.proxima_data_vencimento',
                'm.data_vencimento',
                'sm.codigo as status_codigo',
                'sm.nome as status_nome',
                'sm.permite_checkin',
                'sm.ativo as status_ativo',
            ])
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function montarErroMatriculaIndisponivelCheckin(int $alunoId, int $tenantId): array
    {
        $ultima = $this->buscarMatriculaMaisRecentePorAluno($alunoId, $tenantId);
        $restricao = $this->avaliarRestricaoAcessoMatricula($ultima);

        if ($restricao !== null) {
            $erro = [
                'code' => $restricao['code'],
                'error' => $restricao['mensagem'],
                'matricula_id' => $restricao['matricula_id'],
                'status_codigo' => $restricao['status_codigo'],
            ];
            if (! empty($restricao['data_vencimento'])) {
                $erro['data_vencimento'] = $restricao['data_vencimento'];
            }
            if (! empty($restricao['status'])) {
                $erro['status'] = $restricao['status'];
            }

            return $erro;
        }

        return [
            'code' => 'SEM_MATRICULA',
            'error' => 'Você não possui matrícula ativa',
            'matricula_id' => $ultima ? (int) $ultima['id'] : null,
            'status_codigo' => $ultima['status_codigo'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function montarDebugSemMatricula(int $tenantId, ?int $alunoId, ?int $userId, string $origem): array
    {
        $debug = [
            'origem' => $origem,
            'tenant_id' => $tenantId,
            'aluno_id' => $alunoId,
            'usuario_id' => $userId,
            'data_hoje' => date('Y-m-d'),
        ];

        if (empty($alunoId)) {
            $debug['warning'] = 'aluno_id não identificado para diagnóstico detalhado';

            return $debug;
        }

        $debug['total_matriculas_aluno_tenant'] = (int) DB::table('matriculas')
            ->where('tenant_id', $tenantId)
            ->where('aluno_id', $alunoId)
            ->count();

        return $debug;
    }

    /**
     * @param  ?array<string, mixed>  $matricula
     * @return ?array<string, mixed>
     */
    private function avaliarRestricaoAcessoMatricula(?array $matricula): ?array
    {
        if (! $matricula) {
            return [
                'code' => 'SEM_MATRICULA',
                'mensagem' => 'Você não possui matrícula ativa',
                'matricula_id' => null,
                'status_codigo' => null,
            ];
        }

        $statusCodigo = $matricula['status_codigo'] ?? '';
        $statusNome = $matricula['status_nome'] ?? $statusCodigo;
        $matriculaId = (int) ($matricula['id'] ?? 0);

        if ($statusCodigo === 'bloqueado') {
            return [
                'code' => 'MATRICULA_BLOQUEADA',
                'mensagem' => 'Sua matrícula está bloqueada. Entre em contato com a academia.',
                'matricula_id' => $matriculaId,
                'status_codigo' => $statusCodigo,
                'status' => $statusNome,
            ];
        }

        if ((int) ($matricula['permite_checkin'] ?? 0) !== 1 || (int) ($matricula['status_ativo'] ?? 0) !== 1) {
            return [
                'code' => $this->codigoErroPorStatusMatricula($statusCodigo),
                'mensagem' => "Sua matrícula está {$statusNome}. Entre em contato com a academia.",
                'matricula_id' => $matriculaId,
                'status_codigo' => $statusCodigo,
                'status' => $statusNome,
            ];
        }

        $hoje = date('Y-m-d');
        $acessoAte = $matricula['proxima_data_vencimento'] ?? $matricula['data_vencimento'] ?? null;
        if ($acessoAte && $acessoAte < $hoje) {
            $dataVencimento = date('d/m/Y', strtotime($acessoAte));

            return [
                'code' => 'MATRICULA_VENCIDA',
                'mensagem' => "Seu acesso expirou em {$dataVencimento}. Por favor, renove sua matrícula.",
                'matricula_id' => $matriculaId,
                'status_codigo' => $statusCodigo,
                'status' => $statusNome,
                'data_vencimento' => $acessoAte,
            ];
        }

        return null;
    }

    private function codigoErroPorStatusMatricula(string $statusCodigo): string
    {
        return match ($statusCodigo) {
            'cancelada' => 'MATRICULA_CANCELADA',
            'finalizada' => 'MATRICULA_FINALIZADA',
            'pendente' => 'MATRICULA_PENDENTE',
            'vencida' => 'MATRICULA_VENCIDA',
            default => 'MATRICULA_INATIVA',
        };
    }
}
