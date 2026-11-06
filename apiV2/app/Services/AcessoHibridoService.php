<?php

namespace App\Services;

use App\Support\AcademyDateTime;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AcessoHibridoService
{
    public const LIVRE = 'horario_livre';

    public const FIXA = 'turma_fixa';

    public function aplicarStatus(int $tenantId, int $matriculaId, int $statusId, array $campos = []): int
    {
        return DB::transaction(function () use ($tenantId, $matriculaId, $statusId, $campos) {
            $m = DB::table('matriculas')->where('tenant_id', $tenantId)->where('id', $matriculaId)->first();
            abort_unless($m, 404, 'Matrícula não encontrada.');
            if (($m->tipo_acesso ?? self::LIVRE) === self::FIXA) {
                DB::table('turmas_fixas')->where('tenant_id', $tenantId)->where('id', $m->turma_fixa_id)->lockForUpdate()->first();
            }
            $m = DB::table('matriculas')->where('tenant_id', $tenantId)->where('id', $matriculaId)->lockForUpdate()->first();
            $codigo = DB::table('status_matricula')->where('id', $statusId)->value('codigo');
            if ($m && $codigo === 'ativa' && ! $this->podeAtivar($tenantId, array_replace((array) $m, $campos))) {
                $statusId = (int) DB::table('status_matricula')->where('codigo', 'aguardando_vaga')->value('id');
                if (! $statusId) {
                    throw new \RuntimeException('Status aguardando_vaga não configurado. Execute a migração de acesso híbrido.');
                }
                Log::warning('Matrícula paga sem vaga para ativação', ['tenant_id' => $tenantId, 'matricula_id' => $matriculaId]);
            }
            DB::table('matriculas')->where('tenant_id', $tenantId)->where('id', $matriculaId)
                ->update(array_replace($campos, ['status_id' => $statusId, 'updated_at' => now()]));

            return $statusId;
        }, 3);
    }

    public function atualizado(): bool
    {
        return Schema::hasColumn('matriculas', 'tipo_acesso');
    }

    public function validarMigracaoAutomatica(int $tenantId, int $matriculaId): void
    {
        if (! $this->atualizado()) {
            return;
        }
        $m = DB::table('matriculas')->where('tenant_id', $tenantId)->where('id', $matriculaId)->lockForUpdate()->first();
        abort_unless($m, 404, 'Matrícula não encontrada.');
        if (($m->tipo_acesso ?? self::LIVRE) === self::FIXA) {
            throw ValidationException::withMessages(['plano_id' => 'A matrícula agora é de turma fixa. A secretaria deve conferir o plano e qualquer pagamento de migração já recebido.']);
        }
    }

    public function validarEscolha(int $tenantId, array $dados, ?array $atual = null): array
    {
        $tipo = $dados['tipo_acesso'] ?? $atual['tipo_acesso'] ?? self::LIVRE;
        if (! in_array($tipo, [self::LIVRE, self::FIXA], true)) {
            throw ValidationException::withMessages(['tipo_acesso' => 'Tipo de acesso inválido.']);
        }
        $id = $tipo === self::FIXA ? (int) ($dados['turma_fixa_id'] ?? $atual['turma_fixa_id'] ?? 0) : null;
        if ($tipo === self::FIXA) {
            $turma = DB::table('turmas_fixas')->where('id', $id)->where('tenant_id', $tenantId)->where('ativo', 1)->first();
            $planoId = (int) ($dados['plano_id'] ?? $atual['plano_id'] ?? 0);
            $oferecido = DB::table('turma_fixa_planos as tp')->join('planos as p', 'p.id', '=', 'tp.plano_id')
                ->where('tp.turma_fixa_id', $id)->where('p.id', $planoId)->where('p.tenant_id', $tenantId)
                ->where('p.ativo', 1)->exists();
            if (! $turma || ! $oferecido) {
                throw ValidationException::withMessages(['turma_fixa_id' => 'Selecione uma turma deste tenant e um plano oferecido por ela.']);
            }
            if (! empty($dados['pacote_id'])) {
                throw ValidationException::withMessages(['pacote_id' => 'Turma fixa exige um plano individual.']);
            }
        }

        return ['tipo_acesso' => $tipo, 'turma_fixa_id' => $id];
    }

    /** O mesmo mutex é usado na ativação, na edição da turma e em todos os check-ins. */
    public function bloquearAula(array $turma, int $tenantId): void
    {
        if (! empty($turma['turma_fixa_id'])) {
            DB::table('turmas_fixas')->where('id', $turma['turma_fixa_id'])->where('tenant_id', $tenantId)->lockForUpdate()->first();
        }
        DB::table('turmas')->where('id', $turma['id'])->where('tenant_id', $tenantId)->lockForUpdate()->first();
    }

    private function fixas(int $tenantId, int $grupoId, string $data): Builder
    {
        return DB::table('matriculas as m')->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->where('m.tenant_id', $tenantId)->where('m.turma_fixa_id', $grupoId)->where('m.tipo_acesso', self::FIXA)
            ->where('s.permite_checkin', 1)->where('s.ativo', 1)
            ->whereDate('m.data_inicio', '<=', $data)
            ->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= ?', [$data]);
    }

    public function vagas(array $turma, int $tenantId, ?int $excluirMatricula = null): array
    {
        $data = substr((string) ($turma['dia_data'] ?? AcademyDateTime::today()), 0, 10);
        $fixos = empty($turma['turma_fixa_id']) ? [] : $this->fixas($tenantId, (int) $turma['turma_fixa_id'], $data)
            ->when($excluirMatricula, fn ($q) => $q->where('m.id', '<>', $excluirMatricula))
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())
            ->distinct()->pluck('m.aluno_id')->map(fn ($id) => (int) $id)->all();
        $checkins = DB::table('checkins')->where('tenant_id', $tenantId)->where('turma_id', $turma['id'])
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())
            ->distinct()->pluck('aluno_id')->map(fn ($id) => (int) $id)->all();
        $livres = count(array_diff($checkins, $fixos));
        $saldo = max(0, (int) $turma['limite_alunos'] - count($fixos) - $livres);

        return ['vagas_fixas' => count($fixos), 'vagas_livres_ocupadas' => $livres, 'vagas_livres' => $saldo, 'alunos_fixos' => $fixos];
    }

    public function matriculaParaAula(int $alunoId, int $tenantId, array $turma): ?array
    {
        $data = substr((string) $turma['dia_data'], 0, 10);
        $row = DB::table('matriculas as m')->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->where('m.aluno_id', $alunoId)->where('m.tenant_id', $tenantId)->where('p.tenant_id', $tenantId)
            ->where('p.modalidade_id', $turma['modalidade_id'])->where('s.permite_checkin', 1)->where('s.ativo', 1)
            ->whereDate('m.data_inicio', '<=', $data)
            ->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= ?', [$data])
            ->where(fn ($q) => $q->where('m.tipo_acesso', self::LIVRE)->orWhere(fn ($f) => $f
                ->where('m.tipo_acesso', self::FIXA)->where('m.turma_fixa_id', $turma['turma_fixa_id'] ?? 0)))
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())
            ->orderByDesc('m.id')->first(['m.*']);

        return $row ? (array) $row : null;
    }

    public function erroCheckin(array $turma, int $tenantId, ?array $matricula): ?string
    {
        if (! $matricula || empty($turma['ativo'])) {
            return 'Você não possui matrícula ativa com acesso a esta turma e data.';
        }
        if ($matricula['tipo_acesso'] === self::FIXA) {
            return null; // Presença na vaga já reservada; não consome saldo livre.
        }
        if (! ($turma['checkin_livre'] ?? true)) {
            return 'Esta aula não permite check-in de horário livre.';
        }

        return $this->vagas($turma, $tenantId)['vagas_livres'] > 0 ? null : 'Sem vagas livres disponíveis nesta aula.';
    }

    /** Deve executar na transação que ativa a matrícula. Inclui reservas futuras e check-ins livres já feitos. */
    public function podeAtivar(int $tenantId, array $matricula): bool
    {
        if (($matricula['tipo_acesso'] ?? self::LIVRE) !== self::FIXA) {
            return true;
        }
        $grupo = DB::table('turmas_fixas')->where('id', $matricula['turma_fixa_id'])->where('tenant_id', $tenantId)->lockForUpdate()->first();
        if (! $grupo || ! $grupo->ativo) {
            return false;
        }
        $inicio = max(AcademyDateTime::today(), substr((string) $matricula['data_inicio'], 0, 10));
        $fim = substr((string) ($matricula['proxima_data_vencimento'] ?? $matricula['data_vencimento']), 0, 10);
        $sobrepostas = DB::table('matriculas as m')->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->where('m.tenant_id', $tenantId)->where('m.turma_fixa_id', $grupo->id)->where('m.tipo_acesso', self::FIXA)
            ->where('s.permite_checkin', 1)->where('s.ativo', 1)->where('m.id', '<>', $matricula['id'] ?? 0)
            ->whereDate('m.data_inicio', '<=', $fim)->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= ?', [$inicio])
            ->lockForUpdate()
            ->get(['m.aluno_id', 'm.data_inicio', 'm.data_vencimento', 'm.proxima_data_vencimento']);
        $datas = [$inicio];
        foreach ($sobrepostas as $m) {
            $datas[] = max($inicio, substr((string) $m->data_inicio, 0, 10));
        }
        foreach (array_unique($datas) as $data) {
            $ids = $sobrepostas->filter(fn ($m) => $m->data_inicio <= $data && ($m->proxima_data_vencimento ?? $m->data_vencimento) >= $data)
                ->pluck('aluno_id')->unique()->all();
            if (! in_array($matricula['aluno_id'], $ids) && count($ids) >= $grupo->capacidade) {
                return false;
            }
        }
        $aulas = DB::table('turmas as t')->join('dias as d', 'd.id', '=', 't.dia_id')->where('t.tenant_id', $tenantId)
            ->where('t.turma_fixa_id', $grupo->id)->where('t.ativo', 1)->whereBetween('d.data', [$inicio, $fim])
            ->lockForUpdate()
            ->get(['t.*', 'd.data as dia_data']);
        foreach ($aulas as $aula) {
            $vagas = $this->vagas((array) $aula, $tenantId, $matricula['id'] ?? null);
            $jaPresente = DB::table('checkins')->where('tenant_id', $tenantId)->where('turma_id', $aula->id)
                ->where('aluno_id', $matricula['aluno_id'])->lockForUpdate()->exists();
            if (! in_array((int) $matricula['aluno_id'], $vagas['alunos_fixos']) && ! $jaPresente && $vagas['vagas_livres'] < 1) {
                return false;
            }
        }

        return true;
    }

    public function exigirContratacaoSecretaria(int $userId, int $tenantId, int $planoId): void
    {
        if (! $this->atualizado()) {
            return;
        }
        $modalidadeId = DB::table('planos')->where('tenant_id', $tenantId)->where('id', $planoId)->value('modalidade_id');
        $fixa = DB::table('matriculas as m')->join('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')->join('status_matricula as s', 's.id', '=', 'm.status_id')
            ->where('a.usuario_id', $userId)->where('m.tenant_id', $tenantId)->where('p.modalidade_id', $modalidadeId)
            ->where('m.tipo_acesso', self::FIXA)->exists();
        if ($fixa) {
            throw ValidationException::withMessages(['plano_id' => 'Para contratar ou trocar o plano de turma fixa, procure a secretaria. O pagamento da mensalidade permanece disponível.']);
        }
    }
}
