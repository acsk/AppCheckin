<?php

namespace App\Services\Admin\Prospeccao;

use App\Repositories\ProspeccaoRepository;
use App\Support\AcademyDateTime;
use App\Support\MotivoCancelamento;
use App\Support\ProspeccaoStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mantém prospeccao_oportunidades em dia com as matrículas. Idempotente: pode rodar a qualquer momento.
 *
 * Elegível: aluno com matrícula `cancelada` dentro da janela da academia (parâmetros PARAM_JANELA e
 * PARAM_CARENCIA: cancelou nos últimos N meses e há pelo menos M meses), sem matrícula em andamento
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
    /** Padrão de PARAM_JANELA (era fixo no código) */
    public const JANELA_MESES = 24;

    /** Parâmetro: cancelados nos últimos N meses (1–120) */
    public const PARAM_JANELA = 'prospeccao_janela_meses';

    /** Parâmetro: cancelados há pelo menos M meses (0 = imediatamente; sempre menor que a janela) */
    public const PARAM_CARENCIA = 'prospeccao_carencia_meses';

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
     * Janela da academia em meses e em datas de cancelamento (de = mais antiga aceita, ate = mais recente).
     *
     * @return array{meses: int, carencia_meses: int, de: string, ate: string}
     */
    public function janela(int $tenantId): array
    {
        $meses = (int) ($this->repo->valorParametro($tenantId, self::PARAM_JANELA) ?? self::JANELA_MESES);
        $meses = max(1, min(120, $meses ?: self::JANELA_MESES));
        $carencia = max(0, min($meses - 1, (int) ($this->repo->valorParametro($tenantId, self::PARAM_CARENCIA) ?? 0)));
        $hoje = AcademyDateTime::todayStart();

        return [
            'meses' => $meses,
            'carencia_meses' => $carencia,
            'de' => (clone $hoje)->modify("-{$meses} months")->format('Y-m-d'),
            'ate' => (clone $hoje)->modify("-{$carencia} months")->format('Y-m-d'),
        ];
    }

    /**
     * @return array{criadas: int, atualizadas: int, recuperadas: int, scores: int}
     */
    public function sincronizar(int $tenantId): array
    {
        $hoje = AcademyDateTime::todayStart();
        $janela = $this->janela($tenantId);
        $dataMinima = $janela['de'];
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

            // Carência: o cancelamento mais recente ainda é novo demais. Aplicada aqui (e não na consulta)
            // para não usar uma cancelada mais antiga como referência. Oportunidade já aberta segue normal.
            if ($data > $janela['ate'] && ! isset($abertaPorAluno[$alunoId])) {
                continue;
            }

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
                $motivo = MotivoCancelamento::escolhivel($m->motivo_cancelamento_codigo ?? null) ? $m->motivo_cancelamento_codigo : null;
                $id = DB::transaction(function () use ($tenantId, $alunoId, $matriculaId, $data, $estimada, $status, $agora, $motivo) {
                    $id = $this->repo->inserirOportunidade([
                        'tenant_id' => $tenantId,
                        'aluno_id' => $alunoId,
                        'matricula_referencia_id' => $matriculaId,
                        'data_cancelamento_referencia' => $data,
                        'data_cancelamento_estimada' => $estimada,
                        'motivo_categoria' => $motivo,
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

    /**
     * Por que cada aluno com matrícula cancelada está (ou não) na prospecção. Conta alunos, exceto
     * `sem_aluno` (matrículas canceladas sem aluno vinculado, comuns em registros antigos).
     *
     * @return array<string, int|string>
     */
    public function diagnostico(int $tenantId): array
    {
        $janela = $this->janela($tenantId);
        $dataMinima = $janela['de'];
        $porAluno = [];
        $semAluno = 0;
        $total = 0;
        foreach ($this->repo->matriculasCanceladasParaDiagnostico($tenantId) as $m) {
            $total++;
            if (! $m->aluno_id) {
                $semAluno++;

                continue;
            }
            $a = &$porAluno[(int) $m->aluno_id];
            $a ??= ['em_andamento' => (bool) $m->em_andamento, 'vinculo' => (bool) $m->vinculo_aluno, 'validas' => 0, 'na_janela' => 0, 'ultima' => ''];
            if (! $m->diaria) {
                $a['validas']++;
                $a['ultima'] = max($a['ultima'], (string) $m->data_efetiva);
                if ($m->data_efetiva >= $dataMinima) {
                    $a['na_janela']++;
                }
            }
            unset($a);
        }

        $motivos = ['em_andamento' => 0, 'somente_diarias' => 0, 'fora_da_janela' => 0, 'aguardando_carencia' => 0, 'sem_vinculo_aluno' => 0, 'elegiveis' => 0];
        foreach ($porAluno as $a) {
            $motivo = match (true) {
                $a['em_andamento'] => 'em_andamento',
                $a['validas'] === 0 => 'somente_diarias',
                $a['na_janela'] === 0 => 'fora_da_janela',
                $a['ultima'] > $janela['ate'] => 'aguardando_carencia',
                ! $a['vinculo'] => 'sem_vinculo_aluno',
                default => 'elegiveis',
            };
            $motivos[$motivo]++;
        }

        return [
            'matriculas_canceladas' => $total,
            'matriculas_sem_aluno' => $semAluno,
            'alunos_com_cancelamento' => count($porAluno),
        ] + $motivos + [
            'oportunidades_abertas' => $this->repo->contarOportunidades($tenantId, true),
            'oportunidades_total' => $this->repo->contarOportunidades($tenantId, false),
            'janela_meses' => $janela['meses'],
            'carencia_meses' => $janela['carencia_meses'],
        ];
    }

    /**
     * Inicia a prospecção de um aluno escolhido pela equipe (inclusive fora da janela automática ou sem
     * vínculo ativo). Continua valendo: precisa de matrícula cancelada no tenant, nenhuma em andamento e
     * nenhuma oportunidade aberta.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function iniciarManual(int $tenantId, int $alunoId, int $usuarioId): array
    {
        $ref = $alunoId > 0 ? $this->repo->ultimaCanceladaDoAluno($tenantId, $alunoId) : null;
        if (! $ref) {
            return ['status' => 404, 'body' => ['error' => 'Este aluno não tem matrícula cancelada nesta academia']];
        }
        if ($this->repo->alunoTemMatriculaEmAndamento($tenantId, $alunoId)) {
            return ['status' => 409, 'body' => ['error' => 'O aluno tem matrícula em andamento (ativa, pendente, vencida ou bloqueada); não há o que recuperar']];
        }
        if ($this->repo->existeOportunidadeAbertaDoAluno($tenantId, $alunoId, 0)) {
            return ['status' => 409, 'body' => ['error' => 'O aluno já está em prospecção']];
        }
        $existente = $this->repo->oportunidadePorReferencia($tenantId, (int) $ref->id);
        if ($existente) {
            return ['status' => 409, 'body' => ['error' => 'Este cancelamento já foi prospectado. Abra a ficha para reabrir a oportunidade.', 'oportunidade_id' => (int) $existente->id]];
        }

        $agora = AcademyDateTime::nowFormatted();
        $restrito = (bool) $this->repo->restricaoAtiva($tenantId, $alunoId);
        $status = $restrito ? ProspeccaoStatus::NAO_CONTATAR : ProspeccaoStatus::NAO_CONTATADO;
        $id = DB::transaction(function () use ($tenantId, $alunoId, $ref, $status, $restrito, $agora, $usuarioId) {
            $id = $this->repo->inserirOportunidade([
                'tenant_id' => $tenantId,
                'aluno_id' => $alunoId,
                'matricula_referencia_id' => (int) $ref->id,
                'data_cancelamento_referencia' => substr((string) ($ref->data_cancelamento ?? $ref->data_atualizacao), 0, 10),
                'data_cancelamento_estimada' => $ref->data_cancelamento === null,
                'motivo_categoria' => MotivoCancelamento::escolhivel($ref->motivo_cancelamento_codigo ?? null) ? $ref->motivo_cancelamento_codigo : null,
                'status' => $status,
                'responsavel_id' => $usuarioId,
                'encerrada_em' => $restrito ? $agora : null,
            ]);
            $this->repo->registrarHistorico($tenantId, $id, null, $status, $usuarioId, 'Prospecção iniciada manualmente (matrícula cancelada #'.$ref->id.')');

            return $id;
        });
        $this->score->recalcular($tenantId, [$id]);
        Log::info('prospeccao.iniciar_manual', ['tenant_id' => $tenantId, 'oportunidade_id' => $id, 'usuario_id' => $usuarioId]);

        return ['status' => 201, 'body' => ['oportunidade_id' => $id, 'nao_contatar' => $restrito]];
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
