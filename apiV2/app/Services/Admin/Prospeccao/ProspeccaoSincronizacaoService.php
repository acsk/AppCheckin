<?php

namespace App\Services\Admin\Prospeccao;

use App\Repositories\ProspeccaoRepository;
use App\Support\AcademyDateTime;
use App\Support\ProspeccaoStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mantém prospeccao_oportunidades em dia com as matrículas. Idempotente: pode rodar a qualquer momento.
 *
 * Elegível: aluno com matrícula `cancelada` nos últimos JANELA_MESES, sem matrícula em andamento
 * (ativa/pendente/vencida/bloqueado) no tenant e que não seja diária avulsa. A referência é a
 * cancelada mais recente (data_cancelamento; sem ela, a data da última atualização — marcada como estimada).
 *
 * Sem duplicidade: uma oportunidade por matrícula de referência e no máximo uma aberta por aluno
 * (ambos garantidos também por UNIQUE no banco). Cancelou de novo depois de encerrada → novo ciclo.
 *
 * Recuperação: só quando o aluno volta a ter matrícula ATIVA (nova após o cancelamento, ou a própria
 * referência reativada). Matrícula pendente não muda nada — a oportunidade segue no status em que está.
 */
class ProspeccaoSincronizacaoService
{
    public const JANELA_MESES = 24;

    /** Intervalo mínimo entre sincronizações disparadas pelo painel. */
    private const INTERVALO_MINUTOS = 10;

    private const SCORES_POR_EXECUCAO = 500;

    public function __construct(
        private readonly ProspeccaoRepository $repo,
        private readonly ProspeccaoScoreService $score,
    ) {}

    /**
     * Sincroniza se a última execução do tenant tiver mais de INTERVALO_MINUTOS (ou se forçado).
     *
     * @return array{executada: bool, criadas?: int, atualizadas?: int, recuperadas?: int, scores?: int}
     */
    public function sincronizarSeNecessario(int $tenantId, bool $forcar = false): array
    {
        $chave = 'prospeccao:sincronizacao:'.$tenantId;
        if (! $forcar && Cache::has($chave)) {
            return ['executada' => false];
        }
        Cache::put($chave, true, now()->addMinutes(self::INTERVALO_MINUTOS));

        return ['executada' => true] + $this->sincronizar($tenantId);
    }

    /**
     * @return array{criadas: int, atualizadas: int, recuperadas: int, scores: int}
     */
    public function sincronizar(int $tenantId): array
    {
        $hoje = AcademyDateTime::todayStart();
        $dataMinima = (clone $hoje)->modify('-'.self::JANELA_MESES.' months')->format('Y-m-d');
        $agora = AcademyDateTime::nowFormatted();

        $recuperadas = $this->registrarRecuperacoes($tenantId, $agora);

        $existentes = $this->repo->oportunidadesParaSincronizar($tenantId);
        $referencias = [];
        $abertaPorAluno = [];
        foreach ($existentes as $o) {
            $referencias[(int) $o->matricula_referencia_id] = true;
            if ($o->encerrada_em === null) {
                $abertaPorAluno[(int) $o->aluno_id] = $o;
            }
        }
        $restritos = $this->repo->alunosComRestricao($tenantId);

        $criadas = 0;
        $atualizadas = 0;
        $novas = [];
        $vistos = [];
        foreach ($this->repo->matriculasCanceladasElegiveis($tenantId, $dataMinima) as $m) {
            $alunoId = (int) $m->aluno_id;
            if (isset($vistos[$alunoId])) {
                continue; // só a cancelada mais recente de cada aluno (a consulta já vem ordenada)
            }
            $vistos[$alunoId] = true;
            $matriculaId = (int) $m->id;
            if (isset($referencias[$matriculaId])) {
                continue;
            }
            $estimada = $m->data_cancelamento === null;
            $data = substr((string) ($m->data_cancelamento ?? $m->data_atualizacao), 0, 10);

            // Aluno com oportunidade aberta e cancelamento mais novo: atualiza a referência em vez de duplicar.
            if (isset($abertaPorAluno[$alunoId])) {
                $aberta = $abertaPorAluno[$alunoId];
                DB::transaction(function () use ($tenantId, $aberta, $matriculaId, $data, $estimada) {
                    $this->repo->atualizarReferencia($tenantId, (int) $aberta->id, $matriculaId, $data, $estimada);
                    $this->repo->registrarHistorico($tenantId, (int) $aberta->id, $aberta->status, $aberta->status, null, 'Nova matrícula cancelada #'.$matriculaId.' passou a ser a referência');
                });
                $novas[] = (int) $aberta->id;
                $atualizadas++;

                continue;
            }

            $status = isset($restritos[$alunoId]) ? ProspeccaoStatus::NAO_CONTATAR : ProspeccaoStatus::NAO_CONTATADO;
            try {
                $id = DB::transaction(function () use ($tenantId, $alunoId, $matriculaId, $data, $estimada, $status, $agora) {
                    $id = $this->repo->inserirOportunidade([
                        'tenant_id' => $tenantId,
                        'aluno_id' => $alunoId,
                        'matricula_referencia_id' => $matriculaId,
                        'data_cancelamento_referencia' => $data,
                        'data_cancelamento_estimada' => $estimada,
                        'status' => $status,
                        'encerrada_em' => $status === ProspeccaoStatus::NAO_CONTATAR ? $agora : null,
                    ]);
                    $this->repo->registrarHistorico($tenantId, $id, null, $status, null, 'Oportunidade criada a partir da matrícula cancelada #'.$matriculaId);

                    return $id;
                });
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                continue; // outra sincronização concorrente criou a mesma oportunidade
            }
            $novas[] = $id;
            $criadas++;
        }

        $paraScore = array_values(array_unique(array_merge(
            $novas,
            $this->repo->oportunidadesComScoreDesatualizado($tenantId, $hoje->format('Y-m-d'), self::SCORES_POR_EXECUCAO),
        )));
        $scores = $this->score->recalcular($tenantId, $paraScore);

        if ($criadas || $atualizadas || $recuperadas) {
            Log::info('prospeccao.sincronizacao', compact('tenantId', 'criadas', 'atualizadas', 'recuperadas', 'scores'));
        }

        return compact('criadas', 'atualizadas', 'recuperadas', 'scores');
    }

    private function registrarRecuperacoes(int $tenantId, string $agora): int
    {
        $total = 0;
        foreach ($this->repo->conversoesPendentes($tenantId) as $c) {
            DB::transaction(function () use ($tenantId, $c, $agora) {
                $this->repo->atualizar($tenantId, (int) $c->oportunidade_id, [
                    'status' => ProspeccaoStatus::RECUPERADO,
                    'matricula_conversao_id' => (int) $c->matricula_id,
                    'convertida_em' => $agora,
                    'encerrada_em' => $agora,
                ]);
                $this->repo->cancelarAgendamentosPendentes($tenantId, (int) $c->oportunidade_id, null, $agora);
                $this->repo->registrarHistorico($tenantId, (int) $c->oportunidade_id, $c->status, ProspeccaoStatus::RECUPERADO, null, 'Matrícula #'.$c->matricula_id.' ativa');
            });
            $total++;
        }

        return $total;
    }
}
