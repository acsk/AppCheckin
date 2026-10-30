<?php

namespace App\Services;

use App\Repositories\MatriculaRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Recalcula o status das matrículas em lote (porta do job Slim atualizar_status_matriculas.php).
 *
 * Usa a mesma regra que roda quando o aluno abre o app (PagamentoPlanoService::atualizarStatusMatricula),
 * para o painel não mostrar status parado até o aluno aparecer. Só mexe em ativa/pendente/vencida:
 * bloqueada, cancelada e finalizada são decisões do admin ou estados finais.
 *
 * Cada academia roda numa transação; em simulação a transação é desfeita e só a lista de mudanças volta.
 */
class MatriculaStatusAtualizacaoService
{
    public const STATUS_ALVO = ['ativa', 'pendente', 'vencida'];

    public function __construct(
        private readonly PagamentoPlanoService $pagamentos,
        private readonly MatriculaRepository $matriculas,
    ) {}

    /**
     * @return array{mudancas: list<array{matricula_id: int, aluno: ?string, de: string, para: string}>, conferidas: int, parcelas_atrasadas: int, erro: ?string}
     */
    public function processarTenant(int $tenantId, bool $simular = false): array
    {
        $antes = $this->statusAtuais($tenantId);
        $resultado = ['mudancas' => [], 'conferidas' => count($antes), 'parcelas_atrasadas' => 0, 'erro' => null];
        if ($antes === []) {
            return $resultado;
        }

        DB::beginTransaction();
        try {
            // Parcelas vencidas viram "Atrasado" antes do recálculo (é o que define vencida/cancelada)
            $resultado['parcelas_atrasadas'] = $this->pagamentos->marcarAtrasados($tenantId);

            foreach (array_keys($antes) as $matriculaId) {
                $this->pagamentos->atualizarStatusMatricula($tenantId, $matriculaId);
                // O recálculo reativa pendentes pagas; quem empatou o limite de check-ins do ciclo volta a pendente
                $this->matriculas->marcarPendenteSeLimiteCicloEsgotado($matriculaId);
            }

            $depois = $this->statusPorId(array_keys($antes));
            foreach ($antes as $id => $a) {
                $para = $depois[$id] ?? null;
                if ($para !== null && $para !== $a['status']) {
                    $resultado['mudancas'][] = ['matricula_id' => $id, 'aluno' => $a['aluno'], 'de' => $a['status'], 'para' => $para];
                }
            }

            $simular ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::warning('[MatriculaStatusAtualizacao] falha na academia', ['tenant_id' => $tenantId, 'message' => $e->getMessage()]);
            $resultado['mudancas'] = [];
            $resultado['erro'] = $e->getMessage();
        }

        return $resultado;
    }

    /** @return list<int> */
    public function tenantsAtivos(?int $somente = null): array
    {
        $q = DB::table('tenants')->where('ativo', 1)->orderBy('id');
        if ($somente !== null) {
            $q->where('id', $somente);
        }

        return $q->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return array<int, array{status: string, aluno: ?string}> */
    private function statusAtuais(int $tenantId): array
    {
        return DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->leftJoin('alunos as a', 'a.id', '=', 'm.aluno_id')
            ->where('m.tenant_id', $tenantId)
            ->whereIn('sm.codigo', self::STATUS_ALVO)
            ->orderBy('m.id')
            ->get(['m.id', 'sm.codigo', 'a.nome'])
            ->mapWithKeys(fn ($r) => [(int) $r->id => ['status' => (string) $r->codigo, 'aluno' => $r->nome]])
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function statusPorId(array $ids): array
    {
        $out = [];
        foreach (array_chunk($ids, 500) as $lote) {
            $rows = DB::table('matriculas as m')->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
                ->whereIn('m.id', $lote)->get(['m.id', 'sm.codigo']);
            foreach ($rows as $r) {
                $out[(int) $r->id] = (string) $r->codigo;
            }
        }

        return $out;
    }
}
