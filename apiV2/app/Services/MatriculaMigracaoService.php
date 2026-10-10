<?php

namespace App\Services;

use App\Repositories\UsuarioRepository;
use App\Support\AcademyDateTime;
use App\Support\MobilePagamentoMetodos;
use App\Support\ReferenciaExterna;
use App\Support\StatusPagamentoPlano;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;

/**
 * Migração de plano pelo app mobile com crédito automático (portado da API Slim):
 * - Upgrade: valor cheio do plano atual (paga só a diferença)
 * - Ciclo vigente: proporcional aos dias restantes
 * - Ciclo encerrado: crédito zero (período já consumido)
 */
class MatriculaMigracaoService
{
    /** status_creditos_aluno */
    private const CREDITO_STATUS_ATIVO = 1;

    private const CREDITO_STATUS_UTILIZADO = 2;

    private const MIN_VALOR_PAGAMENTO = 0.50;

    /** Tolerância em reais para comparar valores arredondados a centavos. */
    private const FLOAT_EPSILON = 0.001;

    public function __construct(
        private readonly UsuarioRepository $usuarios,
        private readonly MatriculaMigracaoAptidaoService $aptidao,
        private readonly PagamentoPlanoService $pagamentosPlano,
    ) {}

    /**
     * PDO da conexão atual, obtido no momento do uso (não guardado no construtor): acompanha
     * reconnect/troca de conexão e é o mesmo PDO usado por DB::beginTransaction()/commit().
     *
     * Métodos com várias consultas devem capturar uma vez (`$pdo = $this->db();`) dentro do
     * próprio escopo/transação e reutilizar, em vez de resolver a conexão a cada statement.
     */
    private function db(): PDO
    {
        return DB::connection()->getPdo();
    }

    /**
     * Cálculo proporcional puro (sem banco) — usado em testes e simulação.
     *
     * Contrato:
     * - tipo_credito: 'proporcional' quando há crédito (> 0); 'sem_credito' quando é zero
     *   (ciclo encerrado, valor zero ou ciclo inválido). O app usa 'sem_credito' para exibir o aviso.
     * - tem_credito: atalho booleano para credito > 0.
     * - ciclo_invalido: true quando alguma data é inválida ou vencimento <= início (dado inconsistente).
     *   Datas aceitas: "Y-m-d" ou "Y-m-d H:i:s" (estrito). Qualquer outra (incl. "2026", "tomorrow")
     *   é inválida. Nunca lança exceção por data inválida: devolve sem_credito com ciclo_invalido = true.
     *
     * @return array{
     *   tipo_credito: 'proporcional'|'sem_credito',
     *   tem_credito: bool,
     *   ciclo_invalido: bool,
     *   valor_plano_atual: float,
     *   valor_consumido: float,
     *   credito: float,
     *   dias_totais: int,
     *   dias_restantes: int,
     *   dias_usados: int
     * }
     */
    public static function calcularCreditoProporcional(
        float $valorCiclo,
        string $dataInicio,
        string $dataVencimento,
        ?string $hoje = null,
    ): array {
        // Parse estrito (Y-m-d ou Y-m-d H:i:s): o construtor do DateTime aceitaria "2026",
        // "tomorrow" etc. como datas válidas e geraria crédito sobre valores sem sentido.
        $inicio = AcademyDateTime::fromDbDate($dataInicio);
        $venc = AcademyDateTime::fromDbDate($dataVencimento);
        $ref = AcademyDateTime::fromDbDate($hoje ?? AcademyDateTime::today());
        if ($inicio === null || $venc === null || $ref === null) {
            return self::creditoCicloInvalido($valorCiclo);
        }

        // Ciclo que ainda não começou: conta a partir do início (restantes ≤ totais, usados = 0).
        if ($ref < $inicio) {
            $ref = $inicio;
        }

        // diff()->days é absoluto: vencimento antes/igual ao início é dado inválido → sem crédito.
        if ($venc <= $inicio) {
            return self::creditoCicloInvalido($valorCiclo);
        }

        $diasTotais = max(1, (int) $inicio->diff($venc)->days);
        $diasRestantes = ($ref <= $venc) ? max(0, (int) $ref->diff($venc)->days) : 0;
        $diasUsados = max(0, $diasTotais - $diasRestantes);

        $credito = ($diasRestantes > 0 && $ref <= $venc)
            ? round(($valorCiclo / $diasTotais) * $diasRestantes, 2)
            : 0.0;
        $temCredito = $credito > 0;

        $valorConsumido = round(max(0, $valorCiclo - $credito), 2);

        return [
            'tipo_credito' => $temCredito ? 'proporcional' : 'sem_credito',
            'tem_credito' => $temCredito,
            'ciclo_invalido' => false,
            'valor_plano_atual' => $valorCiclo,
            'valor_consumido' => $valorConsumido,
            'credito' => $credito,
            'dias_totais' => $diasTotais,
            'dias_restantes' => $diasRestantes,
            'dias_usados' => $diasUsados,
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function simular(int $userId, int $tenantId, int $planoId, ?int $planoCicloId): array
    {
        $ctx = $this->resolverContexto($userId, $tenantId, $planoId, $planoCicloId);
        if (isset($ctx['erro'])) {
            return $ctx['erro'];
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => $this->montarResumoSimulacao($ctx),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function migrar(int $userId, int $tenantId, array $data): array
    {
        $planoId = (int) ($data['plano_id'] ?? 0);
        $planoCicloId = ! empty($data['plano_ciclo_id']) ? (int) $data['plano_ciclo_id'] : null;
        $metodoPagamento = strtolower(trim((string) ($data['metodo_pagamento'] ?? 'checkout')));

        if (! in_array($metodoPagamento, ['checkout', 'pix'], true)) {
            return $this->erro(400, 'METODO_PAGAMENTO_INVALIDO', 'Método de pagamento inválido. Use pix ou checkout.');
        }

        $pagamentoFlags = MobilePagamentoMetodos::flags($tenantId);
        $habilitarCartao = $pagamentoFlags['habilitar_cartao_credito'];
        $habilitarPix = $pagamentoFlags['habilitar_pix'];
        $metodoPagamento = MobilePagamentoMetodos::normalizarMetodo(
            $metodoPagamento,
            $habilitarCartao,
            $habilitarPix
        );

        if ($metodoPagamento === 'pix' && ! $habilitarPix) {
            return $this->erro(400, 'PIX_NAO_DISPONIVEL', 'Pagamento PIX não está habilitado para esta academia');
        }

        if ($metodoPagamento === 'checkout' && ! $habilitarCartao) {
            return $this->erro(400, 'CHECKOUT_NAO_DISPONIVEL', 'Pagamento por checkout não está disponível. Use PIX.');
        }

        $ctx = $this->resolverContexto($userId, $tenantId, $planoId, $planoCicloId, $pagamentoFlags);
        if (isset($ctx['erro'])) {
            return $ctx['erro'];
        }

        $matricula = $ctx['matricula'];
        $matriculaId = (int) $matricula['id'];
        $alunoId = (int) $ctx['aluno_id'];
        $novoPlano = $ctx['novo_plano'];
        $novoCiclo = $ctx['novo_ciclo'];
        $valorNovo = (float) $ctx['valor_novo'];
        $creditoInfo = $ctx['credito'];
        $creditoValor = (float) $creditoInfo['credito'];
        // Parte do crédito que de fato abate o novo plano (o restante fica como saldo do aluno).
        $creditoAplicado = $creditoValor > 0 ? round(min($creditoValor, $valorNovo), 2) : 0.0;
        $valorParcela = max(0, round($valorNovo - $creditoAplicado, 2));
        $motivo = $ctx['motivo'];
        $isRecorrente = (bool) $ctx['is_recorrente'];

        if ($valorParcela > 0 && $valorParcela < self::MIN_VALOR_PAGAMENTO) {
            return $this->erro(400, 'VALOR_MINIMO', sprintf(
                'O valor mínimo para pagamento é R$ %s',
                number_format(self::MIN_VALOR_PAGAMENTO, 2, ',', '.'),
            ), [
                'valor_atual' => $valorParcela,
                'valor_minimo' => self::MIN_VALOR_PAGAMENTO,
            ]);
        }

        $agora = AcademyDateTime::now();
        // todayStart() nunca é null (fromDateAndTime pode ser).
        $dataInicioObj = AcademyDateTime::todayStart();
        $dataInicio = $dataInicioObj->format('Y-m-d');
        $proximaDataVencimento = clone $dataInicioObj;
        $duracaoMeses = (int) $ctx['duracao_meses'];
        $duracaoDias = (int) $ctx['duracao_dias'];

        if ($duracaoMeses > 1) {
            $proximaDataVencimento->modify("+{$duracaoMeses} months");
        } else {
            $proximaDataVencimento->modify("+{$duracaoDias} days");
        }
        $dataVencimento = $proximaDataVencimento->format('Y-m-d');
        $diaVencimento = (int) ($matricula['dia_vencimento'] ?? $agora->format('d'));
        $planoAnteriorId = (int) $matricula['plano_id'];
        $novoPlanoId = (int) $novoPlano['id'];
        $novoCicloId = $novoCiclo ? (int) $novoCiclo['id'] : null;
        $ativarDireto = $valorParcela <= self::FLOAT_EPSILON;

        $motivoId = (int) $this->lookupId('motivo_matricula', $motivo, 1);

        // Sem fallback numérico: um id "chutado" poderia gravar status errado na matrícula.
        $statusNovoId = $this->resolverStatusMatriculaId($ativarDireto);
        if ($statusNovoId === null) {
            $codigoStatus = $ativarDireto ? 'ativa' : 'pendente';
            Log::error('MatriculaMigracaoService::migrar status_matricula não encontrado', [
                'tenant_id' => $tenantId,
                'matricula_id' => $matriculaId,
                'status_codigo' => $codigoStatus,
            ]);

            return $this->erro(500, 'ERRO_INTERNO', 'Não foi possível migrar o plano: status de matrícula não configurado. Contate a academia.', [
                'status_codigo' => $codigoStatus,
            ]);
        }

        $creditoId = null;
        $pagamentoOrigemId = $creditoInfo['pagamento_origem_id'] ?? null;
        $novoPagamentoId = null;

        try {
            DB::beginTransaction();

            // PDO da transação capturado uma vez e reutilizado em todos os statements abaixo.
            $pdo = $this->db();

            $this->pagamentosPlano->cancelarParcelasAbertas(
                $tenantId,
                $matriculaId,
                'Cancelado por migração de plano via app'
            );

            $stmtUpdate = $pdo->prepare('
                UPDATE matriculas
                SET plano_id = ?,
                    plano_ciclo_id = ?,
                    plano_anterior_id = ?,
                    valor = ?,
                    data_inicio = ?,
                    data_vencimento = ?,
                    proxima_data_vencimento = ?,
                    dia_vencimento = ?,
                    status_id = ?,
                    motivo_id = ?,
                    observacoes = ?,
                    cancelado_por = NULL,
                    data_cancelamento = NULL,
                    motivo_cancelamento = NULL,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ');

            // Pendente: mantém vigência atual até o pagamento confirmar o novo ciclo.
            // Ativa direto (crédito cobriu): aplica datas do novo ciclo agora.
            if ($ativarDireto) {
                $dataInicioPersistir = $dataInicio;
                $dataVencimentoPersistir = $dataVencimento;
                $proximaPersistir = $dataVencimento;
            } else {
                // Regrava a vigência atual normalizada em Y-m-d (o banco pode devolver DATETIME).
                $dataInicioPersistir = AcademyDateTime::dateOnly($matricula['data_inicio'] ?? null) ?? $dataInicio;
                $dataVencimentoPersistir = AcademyDateTime::dateOnly($matricula['data_vencimento'] ?? null)
                    ?? AcademyDateTime::dateOnly($matricula['proxima_data_vencimento'] ?? null)
                    ?? $dataVencimento;
                $proximaPersistir = AcademyDateTime::dateOnly($matricula['proxima_data_vencimento'] ?? null)
                    ?? $dataVencimentoPersistir;
            }

            $stmtUpdate->execute([
                $novoPlanoId,
                $novoCicloId,
                $planoAnteriorId,
                $valorNovo,
                $dataInicioPersistir,
                $dataVencimentoPersistir,
                $proximaPersistir,
                $diaVencimento,
                $statusNovoId,
                $motivoId,
                'Migração de plano via app mobile',
                $matriculaId,
                $tenantId,
            ]);

            // rowCount() pode ser zero quando o driver considera que nenhum valor mudou.
            // Confirma a existência da matrícula no tenant antes de classificar como erro.
            $linhasAfetadas = $stmtUpdate->rowCount();
            if ($linhasAfetadas === 0) {
                $stmtExiste = $pdo->prepare('SELECT 1 FROM matriculas WHERE id = ? AND tenant_id = ? LIMIT 1');
                $stmtExiste->execute([$matriculaId, $tenantId]);
                if ($stmtExiste->fetchColumn() === false) {
                    throw new \RuntimeException("UPDATE matriculas não encontrou matrícula {$matriculaId} no tenant {$tenantId}");
                }
            } elseif ($linhasAfetadas !== 1) {
                throw new \RuntimeException("UPDATE matriculas afetou {$linhasAfetadas} linhas para a matrícula {$matriculaId}");
            }

            $stmtHistorico = $pdo->prepare('
                INSERT INTO historico_planos
                (usuario_id, plano_anterior_id, plano_novo_id, data_inicio, data_vencimento, valor_pago, motivo, observacoes, criado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmtHistorico->execute([
                $userId,
                $planoAnteriorId,
                $novoPlanoId,
                $dataInicio,
                $dataVencimento,
                // Valor efetivamente cobrado (0 quando o crédito cobriu tudo); o abatido vai na observação.
                $valorParcela,
                $motivo,
                $creditoAplicado > 0
                    ? sprintf(
                        'Migração de plano via app mobile (plano R$ %s, crédito aplicado R$ %s)',
                        number_format($valorNovo, 2, ',', '.'),
                        number_format($creditoAplicado, 2, ',', '.'),
                    )
                    : 'Migração de plano via app mobile',
                $userId,
            ]);

            if ($creditoValor > 0) {
                $stmtCredito = $pdo->prepare('
                    INSERT INTO creditos_aluno
                    (tenant_id, aluno_id, matricula_origem_id, pagamento_origem_id, valor, motivo, criado_por)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ');
                $stmtCredito->execute([
                    $tenantId,
                    $alunoId,
                    $matriculaId,
                    $pagamentoOrigemId,
                    $creditoValor,
                    $creditoInfo['motivo'],
                    $userId,
                ]);
                $creditoId = (int) $pdo->lastInsertId();

                if ($creditoAplicado > 0) {
                    $novoCredUsado = $creditoAplicado;
                    $saldoCredito = $creditoValor - $novoCredUsado;
                    $statusCreditoId = $saldoCredito <= self::FLOAT_EPSILON ? self::CREDITO_STATUS_UTILIZADO : self::CREDITO_STATUS_ATIVO;
                    $stmtUtilizar = $pdo->prepare('
                        UPDATE creditos_aluno SET valor_utilizado = ?, status_credito_id = ?, updated_at = NOW()
                        WHERE id = ? AND tenant_id = ?
                    ');
                    $stmtUtilizar->execute([$novoCredUsado, $statusCreditoId, $creditoId, $tenantId]);
                }

                if ($pagamentoOrigemId && in_array($creditoInfo['tipo_credito'] ?? '', ['valor_cheio', 'valor_cheio_plano'], true)) {
                    $stmtCancelarPago = $pdo->prepare("
                        UPDATE pagamentos_plano
                        SET status_pagamento_id = ?,
                            observacoes = CONCAT(COALESCE(observacoes, ''), ' [Convertido em crédito na migração de plano]'),
                            updated_at = NOW()
                        WHERE id = ? AND tenant_id = ? AND status_pagamento_id = ?
                    ");
                    $stmtCancelarPago->execute([
                        StatusPagamentoPlano::CANCELADO,
                        $pagamentoOrigemId,
                        $tenantId,
                        StatusPagamentoPlano::PAGO,
                    ]);
                }
            }

            if ($ativarDireto) {
                $stmtPagamento = $pdo->prepare('
                    INSERT INTO pagamentos_plano
                    (tenant_id, aluno_id, matricula_id, plano_id, valor, credito_id, credito_aplicado, data_vencimento,
                     status_pagamento_id, data_pagamento, observacoes, criado_por, created_at, updated_at)
                    VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, NOW(), ?, ?, NOW(), NOW())
                ');
                $stmtPagamento->execute([
                    $tenantId,
                    $alunoId,
                    $matriculaId,
                    $novoPlanoId,
                    $creditoId,
                    $creditoAplicado > 0 ? $creditoAplicado : null,
                    $dataInicio,
                    StatusPagamentoPlano::PAGO,
                    'Migração de plano — crédito cobriu valor integral',
                    $userId,
                ]);
                $novoPagamentoId = (int) $pdo->lastInsertId();
            } else {
                $stmtPagamento = $pdo->prepare('
                    INSERT INTO pagamentos_plano
                    (tenant_id, aluno_id, matricula_id, plano_id, valor, credito_id, credito_aplicado, data_vencimento,
                     status_pagamento_id, observacoes, criado_por, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ');
                $stmtPagamento->execute([
                    $tenantId,
                    $alunoId,
                    $matriculaId,
                    $novoPlanoId,
                    $valorParcela,
                    $creditoId,
                    $creditoAplicado > 0 ? $creditoAplicado : null,
                    $dataInicio,
                    StatusPagamentoPlano::AGUARDANDO,
                    'Primeiro pagamento — migração de plano via app',
                    $userId,
                ]);
                $novoPagamentoId = (int) $pdo->lastInsertId();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('MatriculaMigracaoService::migrar falhou', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'matricula_id' => $matriculaId,
                'plano_novo_id' => $novoPlanoId,
                'exception' => $e,
            ]);

            return $this->erro(500, 'ERRO_INTERNO', 'Não foi possível migrar o plano. Tente novamente.');
        }

        $simulacao = $this->montarResumoSimulacao($ctx);
        $body = [
            'success' => true,
            'message' => $ativarDireto
                ? 'Plano migrado com sucesso. Nenhum pagamento adicional necessário.'
                : 'Plano migrado. Complete o pagamento para ativar.',
            'data' => array_merge($simulacao, [
                'matricula_id' => $matriculaId,
                'novo_pagamento_id' => $novoPagamentoId,
                'status' => $ativarDireto ? 'ativa' : 'pendente',
                'plano_anterior_nome' => $matricula['plano_nome'],
                'plano_novo_nome' => $novoPlano['nome'],
            ]),
        ];

        if ($ativarDireto) {
            return ['status' => 200, 'body' => $body];
        }

        $usuario = $ctx['usuario'];
        $mpResult = $this->gerarPagamentoMercadoPago(
            $tenantId,
            $userId,
            $alunoId,
            $matriculaId,
            $novoPlano,
            $novoCiclo,
            $ctx['ciclo_nome'],
            $ctx['frequencia_id'] ?? null,
            $valorParcela,
            $isRecorrente,
            $duracaoMeses,
            $metodoPagamento,
            $usuario,
            $dataVencimento,
        );

        if (isset($mpResult['erro'])) {
            $erro = $mpResult['erro'];
            if (($erro['body']['code'] ?? '') === 'ERRO_PAGAMENTO') {
                $erro['body']['matricula_id'] = $matriculaId;
                $erro['body']['data'] = [
                    'matricula_id' => $matriculaId,
                    'status' => 'pendente',
                    'valor_parcela' => $valorParcela,
                    'metodo_pagamento' => $metodoPagamento,
                ];
            }

            return $erro;
        }

        $body['data'] = array_merge($body['data'], $mpResult['pagamento']);

        return ['status' => 200, 'body' => $body];
    }

    /**
     * @return array<string, mixed>
     */
    private function montarResumoSimulacao(array $ctx): array
    {
        $credito = $ctx['credito'];
        $valorNovo = (float) $ctx['valor_novo'];
        $valorParcela = max(0, round($valorNovo - min((float) $credito['credito'], $valorNovo), 2));

        $cicloAtualNome = $ctx['ciclo_atual_nome'] ?? null;
        $cicloNovoNome = (string) ($ctx['ciclo_nome'] ?? 'Mensal');

        return [
            'matricula_origem_id' => (int) $ctx['matricula']['id'],
            'plano_atual' => [
                'id' => (int) $ctx['matricula']['plano_id'],
                'nome' => $ctx['matricula']['plano_nome'],
                'plano_ciclo_id' => ! empty($ctx['matricula']['plano_ciclo_id'])
                    ? (int) $ctx['matricula']['plano_ciclo_id']
                    : null,
                'ciclo_nome' => $cicloAtualNome,
                'valor' => (float) $ctx['matricula']['valor'],
                'valor_formatado' => 'R$ '.number_format((float) $ctx['matricula']['valor'], 2, ',', '.'),
            ],
            'plano_novo' => [
                'id' => (int) $ctx['novo_plano']['id'],
                'nome' => $ctx['novo_plano']['nome'],
                'plano_ciclo_id' => $ctx['novo_ciclo'] ? (int) $ctx['novo_ciclo']['id'] : null,
                'ciclo_nome' => $cicloNovoNome,
                'valor' => $valorNovo,
                'valor_formatado' => 'R$ '.number_format($valorNovo, 2, ',', '.'),
            ],
            'ciclo' => [
                'atual' => $cicloAtualNome,
                'novo' => $cicloNovoNome,
                'texto' => $cicloAtualNome && $cicloAtualNome !== $cicloNovoNome
                    ? "{$cicloAtualNome} → {$cicloNovoNome}"
                    : $cicloNovoNome,
            ],
            'credito' => [
                'tipo' => $credito['tipo_credito'],
                'valor' => (float) $credito['credito'],
                'valor_formatado' => 'R$ '.number_format((float) $credito['credito'], 2, ',', '.'),
                'valor_consumido' => (float) $credito['valor_consumido'],
                'valor_consumido_formatado' => 'R$ '.number_format((float) $credito['valor_consumido'], 2, ',', '.'),
                'dias_restantes' => (int) $credito['dias_restantes'],
                'dias_usados' => (int) $credito['dias_usados'],
                'dias_totais' => (int) $credito['dias_totais'],
                'motivo' => $credito['motivo'],
            ],
            'valor_parcela' => $valorParcela,
            'valor_parcela_formatado' => 'R$ '.number_format($valorParcela, 2, ',', '.'),
            'motivo_migracao' => $ctx['motivo'],
            'tipo_cobranca' => $ctx['is_recorrente'] ? 'recorrente' : 'avulso',
            'recorrente' => (bool) $ctx['is_recorrente'],
        ];
    }

    /**
     * @param  array<string, mixed>  $matricula
     */
    private function resolverNomeCicloMatricula(array $matricula, int $tenantId): ?string
    {
        $cicloId = ! empty($matricula['plano_ciclo_id']) ? (int) $matricula['plano_ciclo_id'] : 0;
        if ($cicloId <= 0) {
            return null;
        }

        $tenant = $tenantId > 0 ? $tenantId : (int) ($matricula['tenant_id'] ?? 0);
        $stmt = $this->db()->prepare('
            SELECT af.nome
            FROM plano_ciclos pc
            INNER JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
            WHERE pc.id = ? AND (? = 0 OR pc.tenant_id = ?)
            LIMIT 1
        ');
        $stmt->execute([$cicloId, $tenant, $tenant]);
        $nome = $stmt->fetchColumn();

        return $nome ? (string) $nome : null;
    }

    /**
     * @param  array{habilitar_pix: bool, habilitar_cartao_credito: bool}|null  $pagamentoFlags
     *         flags já lidas pelo chamador (migrar); null = ler aqui (simular)
     * @return array<string, mixed>
     */
    private function resolverContexto(
        int $userId,
        int $tenantId,
        int $planoId,
        ?int $planoCicloId,
        ?array $pagamentoFlags = null,
    ): array
    {
        if ($planoId <= 0) {
            return ['erro' => $this->erro(400, 'PLANO_OBRIGATORIO', 'Plano é obrigatório')];
        }

        $pdo = $this->db();

        $stmtAluno = $pdo->prepare('SELECT id FROM alunos WHERE usuario_id = ? LIMIT 1');
        $stmtAluno->execute([$userId]);
        $alunoId = (int) $stmtAluno->fetchColumn();
        if (! $alunoId) {
            return ['erro' => $this->erro(404, 'ALUNO_NAO_ENCONTRADO', 'Perfil de aluno não encontrado')];
        }

        $usuario = $this->usuarios->findById($userId, $tenantId);
        if (! $usuario) {
            return ['erro' => $this->erro(404, 'USUARIO_NAO_ENCONTRADO', 'Usuário não encontrado')];
        }

        $stmtPlano = $pdo->prepare('
            SELECT p.*, m.nome as modalidade_nome
            FROM planos p
            LEFT JOIN modalidades m ON m.id = p.modalidade_id
            WHERE p.id = ? AND p.tenant_id = ? AND p.ativo = 1
        ');
        $stmtPlano->execute([$planoId, $tenantId]);
        $novoPlano = $stmtPlano->fetch(PDO::FETCH_ASSOC);
        if (! $novoPlano) {
            return ['erro' => $this->erro(404, 'PLANO_NAO_ENCONTRADO', 'Plano não encontrado ou inativo')];
        }

        $novoCiclo = null;
        $valorNovo = (float) $novoPlano['valor'];
        $duracaoMeses = 1;
        $duracaoDias = (int) $novoPlano['duracao_dias'];
        $cicloNome = 'Mensal';
        $frequenciaId = null;
        $permiteRecorrenciaCiclo = false;

        if ($planoCicloId) {
            $stmtCiclo = $pdo->prepare('
                SELECT pc.*, af.id as frequencia_vinculada_id, af.nome as ciclo_nome, af.meses as frequencia_meses
                FROM plano_ciclos pc
                LEFT JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
                WHERE pc.id = ? AND pc.plano_id = ? AND pc.tenant_id = ? AND pc.ativo = 1
            ');
            $stmtCiclo->execute([$planoCicloId, $planoId, $tenantId]);
            $novoCiclo = $stmtCiclo->fetch(PDO::FETCH_ASSOC);
            if (! $novoCiclo) {
                return ['erro' => $this->erro(404, 'CICLO_NAO_ENCONTRADO', 'Ciclo de pagamento não encontrado')];
            }
            $valorNovo = (float) $novoCiclo['valor'];
            $duracaoMeses = (int) ($novoCiclo['meses'] ?? $novoCiclo['frequencia_meses'] ?? 1);
            $duracaoDias = $duracaoMeses > 1 ? $duracaoMeses * 30 : (int) $novoPlano['duracao_dias'];
            // LEFT JOIN: ciclo sem frequência vinculada vem com ciclo_nome NULL.
            $cicloNome = self::nomeCiclo($novoCiclo['ciclo_nome'] ?? null, $duracaoMeses);
            // Frequência vinculada que existe de fato (af.id do JOIN), independente de ter nome.
            // Id órfão (frequência apagada) vem NULL aqui → fallback por nome/duração.
            $frequenciaId = (int) ($novoCiclo['frequencia_vinculada_id'] ?? 0) > 0
                ? (int) $novoCiclo['frequencia_vinculada_id']
                : null;
            $permiteRecorrenciaCiclo = (bool) ($novoCiclo['permite_recorrencia'] ?? false);
        }

        $pagamentoFlags ??= MobilePagamentoMetodos::flags($tenantId);
        $isRecorrente = MobilePagamentoMetodos::isRecorrenteEfetivo(
            $permiteRecorrenciaCiclo,
            $pagamentoFlags['habilitar_cartao_credito']
        );

        if ($valorNovo <= 0) {
            return ['erro' => $this->erro(400, 'PLANO_INVALIDO', 'Este plano não está disponível para migração')];
        }

        $modalidadeId = (int) $novoPlano['modalidade_id'];
        $matricula = $this->buscarMatriculaAtivaModalidade($alunoId, $tenantId, $modalidadeId);
        if (! $matricula) {
            return ['erro' => $this->erro(400, 'SEM_MATRICULA_ATIVA', 'Não há matrícula ativa nesta modalidade para migrar. Use a contratação normal.')];
        }

        $aptidao = $this->avaliarAptidaoMigracao($matricula, $tenantId);
        if (! $aptidao['apto']) {
            return ['erro' => $this->erro(400, $aptidao['code'], $aptidao['message'], [
                'motivo' => $aptidao['motivo'] ?? null,
                'gera_credito' => false,
            ])];
        }

        if ((int) $matricula['plano_id'] === $planoId
            && (int) ($matricula['plano_ciclo_id'] ?? 0) === (int) ($planoCicloId ?? 0)) {
            return ['erro' => $this->erro(400, 'MESMO_PLANO', 'O plano e ciclo selecionados são iguais ao atual')];
        }

        $valorAtual = (float) $matricula['valor'];
        $credito = $this->calcularCreditoMigracao($matricula, $tenantId, (int) $matricula['id'], $valorNovo);
        $motivo = $valorNovo > $valorAtual ? 'upgrade' : ($valorNovo < $valorAtual ? 'downgrade' : 'renovacao');

        return [
            'aluno_id' => $alunoId,
            'usuario' => $usuario,
            'matricula' => $matricula,
            'novo_plano' => $novoPlano,
            'novo_ciclo' => $novoCiclo,
            'valor_novo' => $valorNovo,
            'duracao_meses' => $duracaoMeses,
            'duracao_dias' => $duracaoDias,
            'ciclo_nome' => $cicloNome,
            'frequencia_id' => $frequenciaId,
            'is_recorrente' => $isRecorrente,
            'credito' => $credito,
            'motivo' => $motivo,
            'aptidao' => $aptidao,
            'ciclo_atual_nome' => $this->resolverNomeCicloMatricula($matricula, $tenantId),
        ];
    }

    /**
     * @param  array<string, mixed>  $matricula
     * @return array{apto: bool, gera_credito: bool, code: string, message: string, motivo: string|null}
     */
    public function avaliarAptidaoMigracao(array $matricula, int $tenantId): array
    {
        return $this->aptidao->avaliarAptidaoMigracao($matricula, $tenantId);
    }

    public function temParcelaAtrasada(int $matriculaId, int $tenantId): bool
    {
        return $this->aptidao->temParcelaAtrasada($matriculaId, $tenantId);
    }

    public function buscarMatriculaAtivaModalidade(int $alunoId, int $tenantId, int $modalidadeId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT m.*, sm.codigo as status_codigo, p.modalidade_id, p.nome as plano_nome
            FROM matriculas m
            INNER JOIN status_matricula sm ON sm.id = m.status_id
            INNER JOIN planos p ON p.id = m.plano_id
            WHERE m.aluno_id = ?
              AND m.tenant_id = ?
              AND p.modalidade_id = ?
              AND sm.codigo = 'ativa'
              AND COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= CURDATE()
            ORDER BY m.updated_at DESC, m.id DESC
            LIMIT 1
        ");
        $stmt->execute([$alunoId, $tenantId, $modalidadeId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Upgrade: crédito do valor cheio do plano atual (paga só a diferença — igual painel "abater_plano").
     * Downgrade: proporcional aos dias restantes do ciclo vigente (data_vencimento, não proxima_data_vencimento).
     * Cancelada/vencida/atrasada/limite de check-ins esgotado: crédito zero.
     *
     * @return array<string, mixed>
     */
    public function calcularCreditoMigracao(array $matricula, int $tenantId, int $matriculaId, ?float $valorNovo = null): array
    {
        $valorCicloAtual = (float) $matricula['valor'];
        $planoNome = (string) ($matricula['plano_nome'] ?? 'plano atual');

        $aptidao = $this->avaliarAptidaoMigracao($matricula, $tenantId);
        if (! $aptidao['gera_credito']) {
            return [
                'tipo_credito' => 'sem_credito',
                'tem_credito' => false,
                'valor_plano_atual' => $valorCicloAtual,
                'valor_consumido' => $valorCicloAtual,
                'credito' => 0.0,
                'dias_totais' => 0,
                'dias_restantes' => 0,
                'dias_usados' => 0,
                'pagamento_origem_id' => null,
                'motivo' => 'Sem crédito: '.$aptidao['message'],
                'aptidao' => $aptidao,
            ];
        }

        // Normalizadas para Y-m-d: comparar "2026-10-01 00:00:00" <= "2026-10-01" como string dá false.
        $dataInicio = AcademyDateTime::dateOnly($matricula['data_inicio'] ?? null);
        // Ciclo vigente = data_vencimento (alinhado ao painel). proxima_data_vencimento é parcela futura.
        $dataVencimentoStr = AcademyDateTime::dateOnly($matricula['data_vencimento'] ?? null)
            ?? AcademyDateTime::dateOnly($matricula['proxima_data_vencimento'] ?? null);
        $hoje = AcademyDateTime::today();

        if ($dataInicio === null || $dataVencimentoStr === null) {
            return [
                'tipo_credito' => 'sem_credito',
                'tem_credito' => false,
                'valor_plano_atual' => $valorCicloAtual,
                'valor_consumido' => $valorCicloAtual,
                'credito' => 0.0,
                'dias_totais' => 0,
                'dias_restantes' => 0,
                'dias_usados' => 0,
                'pagamento_origem_id' => null,
                'motivo' => 'Sem crédito: datas do ciclo atual inválidas',
                'aptidao' => $aptidao,
            ];
        }

        $base = self::calcularCreditoProporcional($valorCicloAtual, $dataInicio, $dataVencimentoStr, $hoje);

        // Ciclo sem dias restantes: sem crédito (upgrade inclusive).
        if ($base['dias_restantes'] <= 0 || $dataVencimentoStr <= $hoje) {
            return array_merge($base, [
                'tipo_credito' => 'sem_credito',
                'tem_credito' => false,
                'pagamento_origem_id' => null,
                'credito' => 0.0,
                'valor_consumido' => $valorCicloAtual,
                'motivo' => 'Sem crédito: ciclo já encerrado (período consumido)',
                'aptidao' => $aptidao,
            ]);
        }

        // Upgrade com ciclo ainda vigente: crédito do valor cheio do plano atual.
        if ($valorNovo !== null && $valorNovo > $valorCicloAtual) {
            $pagamentoOrigemId = $this->buscarUltimoPagamentoPagoId($matriculaId, $tenantId);

            return [
                'tipo_credito' => 'valor_cheio_plano',
                'tem_credito' => $valorCicloAtual > 0,
                'valor_plano_atual' => $valorCicloAtual,
                'valor_consumido' => 0.0,
                'credito' => round($valorCicloAtual, 2),
                'dias_totais' => (int) $base['dias_totais'],
                'dias_restantes' => (int) $base['dias_restantes'],
                'dias_usados' => (int) $base['dias_usados'],
                'pagamento_origem_id' => $pagamentoOrigemId,
                'motivo' => sprintf(
                    'Crédito do plano atual (%s — R$%s)',
                    $planoNome,
                    number_format($valorCicloAtual, 2, ',', '.')
                ),
                'aptidao' => $aptidao,
            ];
        }

        $motivo = sprintf(
            'Crédito proporcional (%d dias restantes de R$%s)',
            $base['dias_restantes'],
            number_format($valorCicloAtual, 2, ',', '.')
        );

        return array_merge($base, [
            'pagamento_origem_id' => null,
            'motivo' => $motivo,
            'aptidao' => $aptidao,
        ]);
    }

    private function buscarUltimoPagamentoPagoId(int $matriculaId, int $tenantId): ?int
    {
        $stmt = $this->db()->prepare('
            SELECT id
            FROM pagamentos_plano
            WHERE matricula_id = ? AND tenant_id = ? AND status_pagamento_id = ?
            ORDER BY data_vencimento DESC
            LIMIT 1
        ');
        $stmt->execute([$matriculaId, $tenantId, StatusPagamentoPlano::PAGO]);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }

    /**
     * @param  array<string, mixed>  $novoPlano
     * @param  array<string, mixed>|null  $novoCiclo
     * @param  array<string, mixed>  $usuario
     * @return array{pagamento: array<string, mixed>}|array{erro: array{status: int, body: array<string, mixed>}}
     */
    private function gerarPagamentoMercadoPago(
        int $tenantId,
        int $userId,
        int $alunoId,
        int $matriculaId,
        array $novoPlano,
        ?array $novoCiclo,
        string $cicloNome,
        ?int $frequenciaId,
        float $valorParcela,
        bool $isRecorrente,
        int $duracaoMeses,
        string $metodoPagamento,
        array $usuario,
        string $dataVencimento,
    ): array {
        $descricaoCompra = $novoCiclo
            ? "{$novoPlano['nome']} ({$cicloNome}) - {$novoPlano['modalidade_nome']}"
            : "{$novoPlano['nome']} - {$novoPlano['modalidade_nome']}";

        $stmtTenant = $this->db()->prepare('SELECT nome FROM tenants WHERE id = ?');
        $stmtTenant->execute([$tenantId]);
        $academiaNome = $stmtTenant->fetchColumn() ?: 'Academia';

        $dadosPagamento = [
            'tenant_id' => $tenantId,
            'matricula_id' => $matriculaId,
            'aluno_id' => $alunoId,
            'usuario_id' => $userId,
            'aluno_nome' => $usuario['nome'],
            'aluno_email' => $usuario['email'],
            'aluno_telefone' => $usuario['telefone'] ?? '',
            'aluno_cpf' => $usuario['cpf'] ?? null,
            'plano_nome' => $novoPlano['nome'],
            'descricao' => "Migração: {$descricaoCompra}",
            'valor' => $valorParcela,
            'max_parcelas' => 12,
            'academia_nome' => $academiaNome,
            'apenas_cartao' => $isRecorrente,
        ];

        $paymentUrl = null;
        $preferenceId = null;
        $tipoPagamento = 'pagamento_unico';
        $pixData = null;
        $externalReference = ReferenciaExterna::matricula($matriculaId);

        try {
            $mp = app()->makeWith(MercadoPagoService::class, ['tenantId' => $tenantId]);

            if ($metodoPagamento === 'pix') {
                $cpfPix = preg_replace('/[^0-9]/', '', $usuario['cpf'] ?? '');
                if (strlen($cpfPix) !== 11) {
                    return ['erro' => $this->erro(400, 'CPF_OBRIGATORIO_PIX', 'CPF válido é obrigatório para pagamento PIX')];
                }
                $pixData = $mp->criarPagamentoPix($dadosPagamento);
                $tipoPagamento = 'pix';
                $paymentUrl = $pixData['ticket_url'] ?? null;
                $preferenceId = $pixData['id'] ?? null;
                $externalReference = $pixData['external_reference'] ?? $externalReference;
            } elseif ($isRecorrente) {
                $preferencia = $mp->criarPreferenciaAssinatura($dadosPagamento, $duracaoMeses);
                $tipoPagamento = 'assinatura';
                $paymentUrl = $preferencia['init_point'] ?? null;
                $preferenceId = $preferencia['id'] ?? null;
                $externalReference = $preferencia['external_reference'] ?? $externalReference;
            } else {
                $preferencia = $mp->criarPreferenciaPagamento($dadosPagamento);
                $tipoPagamento = 'pagamento_unico';
                $paymentUrl = $preferencia['init_point'] ?? null;
                $preferenceId = $preferencia['id'] ?? null;
                $externalReference = $preferencia['external_reference'] ?? $externalReference;
            }
        } catch (\Throwable $e) {
            Log::error('MatriculaMigracaoService::gerarPagamentoMercadoPago falhou', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'matricula_id' => $matriculaId,
                'metodo_pagamento' => $metodoPagamento,
                'recorrente' => $isRecorrente,
                'exception' => $e,
            ]);

            return ['erro' => $this->erro(
                500,
                'ERRO_PAGAMENTO',
                'Plano alterado, mas falha ao gerar pagamento. Tente reabrir o pagamento pendente.',
                ['matricula_id' => $matriculaId],
            )];
        }

        // Daqui em diante o pagamento já existe no gateway. As escritas locais vão juntas em
        // uma transação para não sobrar registro parcial e, se falharem, os identificadores do
        // gateway ficam logados em nível crítico para permitir reconciliação/retentativa.
        try {
            DB::transaction(function () use (
                $tenantId,
                $matriculaId,
                $alunoId,
                $novoPlano,
                $valorParcela,
                $isRecorrente,
                $metodoPagamento,
                $preferenceId,
                $paymentUrl,
                $externalReference,
                $cicloNome,
                $frequenciaId,
                $dataVencimento,
                $duracaoMeses,
                $pixData,
            ): void {
                if ($pixData !== null) {
                    $this->salvarPixRegistro($tenantId, $matriculaId, $pixData);
                }

                $this->salvarAssinatura(
                    $tenantId,
                    $matriculaId,
                    $alunoId,
                    (int) $novoPlano['id'],
                    $valorParcela,
                    $isRecorrente,
                    $metodoPagamento,
                    $preferenceId,
                    $paymentUrl,
                    $externalReference,
                    $cicloNome,
                    $frequenciaId,
                    $dataVencimento,
                    $duracaoMeses,
                );
            });
        } catch (\Throwable $e) {
            Log::critical('MatriculaMigracaoService: pagamento criado no gateway sem registro local', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'matricula_id' => $matriculaId,
                'metodo_pagamento' => $metodoPagamento,
                'recorrente' => $isRecorrente,
                'external_reference' => $externalReference,
                'preference_id' => $preferenceId,
                'payment_url' => $paymentUrl,
                'pix_payment_id' => $pixData['id'] ?? null,
                'exception' => $e,
            ]);

            return ['erro' => $this->erro(
                500,
                'ERRO_PAGAMENTO',
                'Plano alterado e pagamento gerado, mas não foi possível registrá-lo. Contate a academia informando a referência.',
                [
                    'matricula_id' => $matriculaId,
                    'external_reference' => $externalReference,
                ],
            )];
        }

        return [
            'pagamento' => [
                'payment_url' => $paymentUrl,
                'preference_id' => $preferenceId,
                'tipo_pagamento' => $tipoPagamento,
                'metodo_pagamento' => $metodoPagamento,
                'pix' => $pixData ? [
                    'payment_id' => $pixData['id'] ?? null,
                    'status' => $pixData['status'] ?? null,
                    'qr_code' => $pixData['qr_code'] ?? null,
                    'qr_code_base64' => $pixData['qr_code_base64'] ?? null,
                    'ticket_url' => $pixData['ticket_url'] ?? null,
                    'expires_at' => $pixData['date_of_expiration'] ?? null,
                ] : null,
            ],
        ];
    }

    private function salvarAssinatura(
        int $tenantId,
        int $matriculaId,
        int $alunoId,
        int $planoId,
        float $valor,
        bool $isRecorrente,
        string $metodoPagamento,
        ?string $preferenceId,
        ?string $paymentUrl,
        string $externalReference,
        string $cicloNome,
        ?int $frequenciaId,
        string $dataVencimento,
        int $duracaoMeses,
    ): void {
        $gatewayId = $this->lookupId('assinatura_gateways', 'mercadopago', 1);
        $statusPendenteId = $this->lookupId('assinatura_status', 'pendente', 1);
        // Preferir a frequência vinculada ao ciclo; busca por nome só como fallback (legado).
        $frequenciaId ??= $this->lookupId('assinatura_frequencias', strtolower($cicloNome), 4);

        $metodoPagamentoId = null;
        if ($isRecorrente) {
            $metodoPagamentoId = $this->lookupId('metodos_pagamento', 'credit_card', 1);
        } elseif ($metodoPagamento === 'pix') {
            $metodoPagamentoId = $this->lookupId('metodos_pagamento', 'pix', null);
        }

        $agora = AcademyDateTime::now();
        $payload = [
            'aluno_id' => $alunoId,
            'plano_id' => $planoId,
            'gateway_id' => $gatewayId,
            'gateway_assinatura_id' => $isRecorrente ? $preferenceId : null,
            'gateway_preference_id' => ! $isRecorrente ? $preferenceId : null,
            'external_reference' => $externalReference,
            'payment_url' => $paymentUrl,
            'status_id' => $statusPendenteId,
            'status_gateway' => 'pending',
            'valor' => $valor,
            'frequencia_id' => $frequenciaId,
            'dia_cobranca' => (int) $agora->format('d'),
            'data_inicio' => $agora->format('Y-m-d'),
            // Recorrência avança pelo ciclo real (mensal, bimestral, trimestral...).
            'proxima_cobranca' => $isRecorrente
                ? (clone $agora)->modify('+'.max(1, $duracaoMeses).' months')->format('Y-m-d')
                : null,
            'data_fim' => ! $isRecorrente ? $dataVencimento : null,
            'tipo_cobranca' => $isRecorrente ? 'recorrente' : 'avulso',
            'metodo_pagamento_id' => $metodoPagamentoId,
            'cancelado_por_id' => null,
            'motivo_cancelamento' => null,
            'atualizado_em' => $agora->format('Y-m-d H:i:s'),
        ];

        // O schema permite uma assinatura por matrícula; o histórico fica em pagamentos_plano.
        // A matrícula serializa também a criação quando ainda não existe uma assinatura.
        $matricula = DB::table('matriculas')
            ->where('id', $matriculaId)
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->first(['id']);
        if (! $matricula) {
            throw new \RuntimeException("Matrícula {$matriculaId} não encontrada no tenant {$tenantId}");
        }

        $pdo = $this->db();
        $assinatura = DB::table('assinaturas')
            ->where('matricula_id', $matriculaId)
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->first(['id']);

        if ($assinatura) {
            $sets = [];
            $params = [];
            foreach ($payload as $col => $val) {
                $sets[] = "{$col} = ?";
                $params[] = $val;
            }
            $params[] = (int) $assinatura->id;
            $params[] = $tenantId;
            $pdo->prepare('UPDATE assinaturas SET '.implode(', ', $sets).' WHERE id = ? AND tenant_id = ?')
                ->execute($params);

            return;
        }

        $cols = array_merge(['tenant_id', 'matricula_id', 'criado_em'], array_keys($payload));
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $values = array_merge([$tenantId, $matriculaId, $agora->format('Y-m-d H:i:s')], array_values($payload));
        $pdo->prepare('INSERT INTO assinaturas ('.implode(', ', $cols).") VALUES ({$placeholders})")
            ->execute($values);
    }

    /**
     * @param  array<string, mixed>  $pixData
     */
    private function salvarPixRegistro(int $tenantId, int $matriculaId, array $pixData): void
    {
        if (empty($pixData['id']) || empty($pixData['ticket_url'])) {
            return;
        }

        $this->db()->prepare('
            INSERT INTO pagamentos_pix
            (tenant_id, matricula_id, payment_id, ticket_url, qr_code, qr_code_base64, expires_at, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $tenantId,
            $matriculaId,
            (string) $pixData['id'],
            $pixData['ticket_url'] ?? null,
            $pixData['qr_code'] ?? null,
            $pixData['qr_code_base64'] ?? null,
            $this->expiracaoPix($pixData['date_of_expiration'] ?? null, $tenantId, $matriculaId),
            $pixData['status'] ?? 'pending',
        ]);
    }

    /** Status da matrícula após migrar: ativa (crédito cobriu) ou pendente; null se não configurado. */
    private function resolverStatusMatriculaId(bool $ativarDireto): ?int
    {
        $id = $ativarDireto
            ? $this->lookupId('status_matricula', 'ativa', $this->lookupId('status_matricula', 'ativo', null))
            : $this->lookupId('status_matricula', 'pendente', null);

        return $id !== null && $id > 0 ? $id : null;
    }

    /**
     * Expiração do PIX no horário da academia. Formato inesperado do gateway não pode abortar
     * a migração (o PIX já foi criado no MP): grava null e registra no log.
     */
    private function expiracaoPix(mixed $valor, int $tenantId, int $matriculaId): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $texto = is_string($valor) ? trim($valor) : '';

        // Só ISO-8601 (T ou espaço, fração opcional, Z/±hh:mm opcional). O construtor do DateTime
        // aceitaria "tomorrow"/"2026" e ainda "rolaria" 2026-02-30 para 02/03 sem lançar exceção.
        $iso = '/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2}):(\d{2})(?:\.\d{1,6})?(?:Z|([+-])(\d{2}):?(\d{2}))?$/';
        if (preg_match($iso, $texto, $m) !== 1
            || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])
            || (int) $m[4] > 23 || (int) $m[5] > 59 || (int) $m[6] > 59
        ) {
            Log::warning('MatriculaMigracaoService: date_of_expiration do PIX em formato inesperado', [
                'tenant_id' => $tenantId,
                'matricula_id' => $matriculaId,
                'valor' => $valor,
            ]);

            return null;
        }

        if (isset($m[7]) && $m[7] !== '') {
            $offsetHoras = (int) $m[8];
            $offsetMinutos = (int) $m[9];
            if ($offsetHoras > 14 || $offsetMinutos > 59 || ($offsetHoras === 14 && $offsetMinutos !== 0)) {
                Log::warning('MatriculaMigracaoService: date_of_expiration do PIX em formato inesperado', [
                    'tenant_id' => $tenantId,
                    'matricula_id' => $matriculaId,
                    'valor' => $valor,
                ]);

                return null;
            }
        }

        try {
            $tz = AcademyDateTime::tz();

            // Sem offset: interpreta no fuso da academia (não no do processo).
            // Com offset (formato usual do MP): o construtor respeita o offset e setTimezone converte.
            return (new \DateTimeImmutable($texto, $tz))
                ->setTimezone($tz)
                ->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            Log::warning('MatriculaMigracaoService: date_of_expiration do PIX inválido', [
                'tenant_id' => $tenantId,
                'matricula_id' => $matriculaId,
                'valor' => $valor,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Resultado de calcularCreditoProporcional para ciclo com datas inválidas/inconsistentes.
     *
     * @return array<string, mixed>
     */
    private static function creditoCicloInvalido(float $valorCiclo): array
    {
        return [
            'tipo_credito' => 'sem_credito',
            'tem_credito' => false,
            'ciclo_invalido' => true,
            'valor_plano_atual' => $valorCiclo,
            'valor_consumido' => round(max(0, $valorCiclo), 2),
            'credito' => 0.0,
            'dias_totais' => 0,
            'dias_restantes' => 0,
            'dias_usados' => 0,
        ];
    }

    /** Nome do ciclo para exibição/descrição; fallback pela duração quando a frequência não está vinculada. */
    private static function nomeCiclo(mixed $nome, int $duracaoMeses): string
    {
        $nome = is_string($nome) ? trim($nome) : '';
        if ($nome !== '') {
            return $nome;
        }

        return match (max(1, $duracaoMeses)) {
            1 => 'Mensal',
            2 => 'Bimestral',
            3 => 'Trimestral',
            6 => 'Semestral',
            12 => 'Anual',
            default => "{$duracaoMeses} meses",
        };
    }

    /** Tabelas de domínio consultadas por código. Nome de tabela não é parametrizável no PDO. */
    private const TABELAS_LOOKUP = [
        'status_matricula',
        'motivo_matricula',
        'assinatura_gateways',
        'assinatura_status',
        'assinatura_frequencias',
        'metodos_pagamento',
    ];

    private function lookupId(string $table, string $codigo, ?int $fallback): ?int
    {
        if (! in_array($table, self::TABELAS_LOOKUP, true)) {
            throw new \InvalidArgumentException("Tabela não permitida em lookupId: {$table}");
        }

        $stmt = $this->db()->prepare("SELECT id FROM {$table} WHERE codigo = ? LIMIT 1");
        $stmt->execute([$codigo]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : $fallback;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{status: int, body: array<string, mixed>}
     */
    private function erro(int $status, string $code, string $message, array $extra = []): array
    {
        return [
            'status' => $status,
            'body' => array_merge([
                'success' => false,
                'type' => 'error',
                'code' => $code,
                'message' => $message,
            ], $extra),
        ];
    }
}
