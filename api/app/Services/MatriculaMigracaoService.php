<?php

namespace App\Services;

use App\Models\CreditoAluno;
use App\Support\MobilePagamentoMetodos;
use PDO;

/**
 * Migração de plano pelo app mobile com crédito automático:
 * - Upgrade: valor cheio do plano atual (paga só a diferença)
 * - Ciclo vigente: proporcional aos dias restantes
 * - Ciclo encerrado: crédito zero (período já consumido)
 */
class MatriculaMigracaoService
{
    public function __construct(private readonly PDO $db) {}

    /**
     * Cálculo proporcional puro (sem banco) — usado em testes e simulação.
     *
     * @return array{
     *   tipo_credito: string,
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
        date_default_timezone_set('America/Sao_Paulo');
        $inicio = new \DateTime($dataInicio);
        $venc = new \DateTime($dataVencimento);
        $ref = new \DateTime($hoje ?? date('Y-m-d'));

        $diasTotais = max(1, (int) $inicio->diff($venc)->days);
        $diasRestantes = ($ref <= $venc) ? max(0, (int) $ref->diff($venc)->days) : 0;
        $diasUsados = max(0, $diasTotais - $diasRestantes);

        if ($diasRestantes > 0 && $ref <= $venc) {
            $credito = round(($valorCiclo / $diasTotais) * $diasRestantes, 2);
            $tipo = 'proporcional';
        } else {
            $credito = 0.0;
            $tipo = 'proporcional';
        }

        $valorConsumido = round(max(0, $valorCiclo - $credito), 2);

        return [
            'tipo_credito' => $tipo,
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

        $pagamentoFlags = MobilePagamentoMetodos::flags($this->db, $tenantId);
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

        $ctx = $this->resolverContexto($userId, $tenantId, $planoId, $planoCicloId);
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
        $valorParcela = max(0, round($valorNovo - min($creditoValor, $valorNovo), 2));
        $motivo = $ctx['motivo'];
        $isRecorrente = (bool) $ctx['is_recorrente'];

        if ($valorParcela > 0 && $valorParcela < 0.50) {
            return $this->erro(400, 'VALOR_MINIMO', 'O valor mínimo para pagamento é R$ 0,50', [
                'valor_atual' => $valorParcela,
                'valor_minimo' => 0.50,
            ]);
        }

        date_default_timezone_set('America/Sao_Paulo');
        $dataInicio = date('Y-m-d');
        $dataInicioObj = new \DateTime($dataInicio);
        $proximaDataVencimento = clone $dataInicioObj;
        $duracaoMeses = (int) $ctx['duracao_meses'];
        $duracaoDias = (int) $ctx['duracao_dias'];

        if ($duracaoMeses > 1) {
            $proximaDataVencimento->modify("+{$duracaoMeses} months");
        } else {
            $proximaDataVencimento->modify("+{$duracaoDias} days");
        }
        $dataVencimento = $proximaDataVencimento->format('Y-m-d');
        $diaVencimento = (int) ($matricula['dia_vencimento'] ?? date('d'));
        $planoAnteriorId = (int) $matricula['plano_id'];
        $novoPlanoId = (int) $novoPlano['id'];
        $novoCicloId = $novoCiclo ? (int) $novoCiclo['id'] : null;
        $ativarDireto = $valorParcela <= 0.001;

        $stmtMotivo = $this->db->prepare('SELECT id FROM motivo_matricula WHERE codigo = ? LIMIT 1');
        $stmtMotivo->execute([$motivo]);
        $motivoId = (int) ($stmtMotivo->fetchColumn() ?: 1);

        if ($ativarDireto) {
            $stmtStatus = $this->db->prepare("SELECT id FROM status_matricula WHERE codigo IN ('ativa', 'ativo') LIMIT 1");
            $stmtStatus->execute();
            $statusNovoId = (int) $stmtStatus->fetchColumn();
        } else {
            $stmtStatus = $this->db->prepare("SELECT id FROM status_matricula WHERE codigo = 'pendente' LIMIT 1");
            $stmtStatus->execute();
            $statusNovoId = (int) $stmtStatus->fetchColumn();
        }

        $creditoModel = new CreditoAluno($this->db);
        $creditoId = null;
        $pagamentoOrigemId = $creditoInfo['pagamento_origem_id'] ?? null;
        $novoPagamentoId = null;

        try {
            $this->db->beginTransaction();

            $pagamentoPlanoModel = new \App\Models\PagamentoPlano($this->db);
            $pagamentoPlanoModel->cancelarParcelasAbertas(
                $tenantId,
                $matriculaId,
                'Cancelado por migração de plano via app'
            );

            $stmtUpdate = $this->db->prepare('
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
                $dataInicioPersistir = (string) ($matricula['data_inicio'] ?? $dataInicio);
                $dataVencimentoPersistir = (string) (
                    $matricula['data_vencimento']
                    ?? $matricula['proxima_data_vencimento']
                    ?? $dataVencimento
                );
                $proximaPersistir = (string) (
                    $matricula['proxima_data_vencimento']
                    ?? $dataVencimentoPersistir
                );
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

            $stmtHistorico = $this->db->prepare('
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
                $valorParcela > 0 ? $valorParcela : $valorNovo,
                $motivo,
                'Migração de plano via app mobile',
                $userId,
            ]);

            if ($creditoValor > 0) {
                $stmtCredito = $this->db->prepare('
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
                $creditoId = (int) $this->db->lastInsertId();

                $creditoAplicado = min($creditoValor, $valorNovo);
                if ($creditoAplicado > 0) {
                    $novoCredUsado = round($creditoAplicado, 2);
                    $saldoCredito = $creditoValor - $novoCredUsado;
                    $statusCreditoId = $saldoCredito <= 0.001 ? CreditoAluno::STATUS_UTILIZADO : CreditoAluno::STATUS_ATIVO;
                    $stmtUtilizar = $this->db->prepare('
                        UPDATE creditos_aluno SET valor_utilizado = ?, status_credito_id = ?, updated_at = NOW() WHERE id = ?
                    ');
                    $stmtUtilizar->execute([$novoCredUsado, $statusCreditoId, $creditoId]);
                }

                if ($pagamentoOrigemId && in_array($creditoInfo['tipo_credito'] ?? '', ['valor_cheio', 'valor_cheio_plano'], true)) {
                    $stmtCancelarPago = $this->db->prepare("
                        UPDATE pagamentos_plano
                        SET status_pagamento_id = 4,
                            observacoes = CONCAT(COALESCE(observacoes, ''), ' [Convertido em crédito na migração de plano]'),
                            updated_at = NOW()
                        WHERE id = ? AND tenant_id = ? AND status_pagamento_id = 2
                    ");
                    $stmtCancelarPago->execute([$pagamentoOrigemId, $tenantId]);
                }
            }

            if ($ativarDireto) {
                $statusPagoId = 2;

                $stmtPagamento = $this->db->prepare('
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
                    $creditoValor > 0 ? $creditoValor : null,
                    $dataInicio,
                    $statusPagoId,
                    'Migração de plano — crédito cobriu valor integral',
                    $userId,
                ]);
                $novoPagamentoId = (int) $this->db->lastInsertId();
            } else {
                $stmtPagamento = $this->db->prepare('
                    INSERT INTO pagamentos_plano
                    (tenant_id, aluno_id, matricula_id, plano_id, valor, credito_id, credito_aplicado, data_vencimento,
                     status_pagamento_id, observacoes, criado_por, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, NOW(), NOW())
                ');
                $stmtPagamento->execute([
                    $tenantId,
                    $alunoId,
                    $matriculaId,
                    $novoPlanoId,
                    $valorParcela,
                    $creditoId,
                    $creditoValor > 0 ? min($creditoValor, $valorNovo) : null,
                    $dataInicio,
                    'Primeiro pagamento — migração de plano via app',
                    $userId,
                ]);
                $novoPagamentoId = (int) $this->db->lastInsertId();
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[MatriculaMigracaoService::migrar] '.$e->getMessage());

            return $this->erro(500, 'ERRO_INTERNO', 'Não foi possível migrar o plano. Tente novamente.', [
                'error' => $e->getMessage(),
            ]);
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

        $cicloAtualNome = $this->resolverNomeCicloMatricula($ctx['matricula'], (int) ($ctx['matricula']['tenant_id'] ?? 0));
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
        $stmt = $this->db->prepare('
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
     * @return array<string, mixed>
     */
    private function resolverContexto(int $userId, int $tenantId, int $planoId, ?int $planoCicloId): array
    {
        if ($planoId <= 0) {
            return ['erro' => $this->erro(400, 'PLANO_OBRIGATORIO', 'Plano é obrigatório')];
        }

        $stmtAluno = $this->db->prepare('SELECT id FROM alunos WHERE usuario_id = ? LIMIT 1');
        $stmtAluno->execute([$userId]);
        $alunoId = (int) $stmtAluno->fetchColumn();
        if (! $alunoId) {
            return ['erro' => $this->erro(404, 'ALUNO_NAO_ENCONTRADO', 'Perfil de aluno não encontrado')];
        }

        $usuarioModel = new \App\Models\Usuario($this->db);
        $usuario = $usuarioModel->findById($userId, $tenantId);
        if (! $usuario) {
            return ['erro' => $this->erro(404, 'USUARIO_NAO_ENCONTRADO', 'Usuário não encontrado')];
        }

        $stmtPlano = $this->db->prepare('
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
        $permiteRecorrenciaCiclo = false;

        if ($planoCicloId) {
            $stmtCiclo = $this->db->prepare('
                SELECT pc.*, af.nome as ciclo_nome, af.meses as frequencia_meses
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
            $cicloNome = $novoCiclo['ciclo_nome'];
            $permiteRecorrenciaCiclo = (bool) $novoCiclo['permite_recorrencia'];
        }

        $pagamentoFlags = MobilePagamentoMetodos::flags($this->db, $tenantId);
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
            'is_recorrente' => $isRecorrente,
            'credito' => $credito,
            'motivo' => $motivo,
            'aptidao' => $aptidao,
        ];
    }

    /**
     * Matrícula apta a migrar:
     * - status ativa e acesso vigente
     * - sem parcela aberta atrasada
     * Cancelada/vencida/finalizada: não apta (e sem crédito).
     * Limite de check-ins do ciclo esgotado: apta, mas sem crédito.
     *
     * @param  array<string, mixed>  $matricula
     * @return array{apto: bool, gera_credito: bool, code: string, message: string, motivo: string|null}
     */
    public function avaliarAptidaoMigracao(array $matricula, int $tenantId): array
    {
        $status = strtolower(trim((string) ($matricula['status_codigo'] ?? '')));
        $matriculaId = (int) ($matricula['id'] ?? 0);
        // Acesso operacional (pode ser parcela futura).
        $acessoAte = (string) ($matricula['proxima_data_vencimento'] ?? $matricula['data_vencimento'] ?? '');
        // Ciclo vigente pago — base do crédito (não usar proxima_data_vencimento).
        $cicloVigenteAte = (string) ($matricula['data_vencimento'] ?? $matricula['proxima_data_vencimento'] ?? '');
        $hoje = date('Y-m-d');

        if (in_array($status, ['cancelada', 'vencida', 'finalizada'], true)) {
            return [
                'apto' => false,
                'gera_credito' => false,
                'code' => 'MATRICULA_NAO_ATIVA',
                'message' => 'Matrícula cancelada, vencida ou finalizada não pode migrar com crédito. Regularize com uma nova contratação.',
                'motivo' => $status,
            ];
        }

        if ($status !== 'ativa') {
            return [
                'apto' => false,
                'gera_credito' => false,
                'code' => 'MATRICULA_NAO_ATIVA',
                'message' => 'Só é possível migrar com matrícula ativa e em dia.',
                'motivo' => $status !== '' ? $status : 'status_invalido',
            ];
        }

        if ($acessoAte !== '' && $acessoAte !== '0000-00-00' && $acessoAte < $hoje) {
            return [
                'apto' => false,
                'gera_credito' => false,
                'code' => 'MATRICULA_VENCIDA',
                'message' => 'Matrícula vencida não gera crédito de migração. Regularize o pagamento primeiro.',
                'motivo' => 'acesso_vencido',
            ];
        }

        if ($matriculaId > 0 && $this->temParcelaAtrasada($matriculaId, $tenantId)) {
            return [
                'apto' => false,
                'gera_credito' => false,
                'code' => 'MATRICULA_EM_ATRASO',
                'message' => 'Há parcela em atraso. Quite o débito antes de migrar de plano.',
                'motivo' => 'parcela_atrasada',
            ];
        }

        // Ciclo vigente encerrado (data_vencimento): pode migrar, sem crédito.
        if ($cicloVigenteAte !== '' && $cicloVigenteAte !== '0000-00-00' && $cicloVigenteAte <= $hoje) {
            return [
                'apto' => true,
                'gera_credito' => false,
                'code' => 'CICLO_ENCERRADO',
                'message' => 'Ciclo vigente encerrado: migração sem crédito.',
                'motivo' => 'ciclo_encerrado',
            ];
        }

        // Limite de check-ins do ciclo esgotado: pode migrar, mas sem crédito
        // (o benefício do ciclo já foi consumido).
        if ($this->limiteCheckinsCicloEsgotado($matricula, $tenantId)) {
            return [
                'apto' => true,
                'gera_credito' => false,
                'code' => 'LIMITE_CHECKINS_ESGOTADO',
                'message' => 'Limite de check-ins do ciclo esgotado: migração sem crédito.',
                'motivo' => 'limite_checkins_ciclo',
            ];
        }

        // Sem pagamento pago no ciclo: nada a abater.
        if ($matriculaId > 0 && $this->buscarUltimoPagamentoPagoId($matriculaId, $tenantId) === null) {
            return [
                'apto' => true,
                'gera_credito' => false,
                'code' => 'SEM_PAGAMENTO_PAGO',
                'message' => 'Sem pagamento quitado neste ciclo: migração sem crédito.',
                'motivo' => 'sem_pagamento_pago',
            ];
        }

        return [
            'apto' => true,
            'gera_credito' => true,
            'code' => 'OK',
            'message' => 'Matrícula apta para migração',
            'motivo' => null,
        ];
    }

    /**
     * Verifica o teto de check-ins do ciclo vigente (não usa liberação de renovação,
     * que prioriza acesso_vencido e mascara o limite esgotado).
     *
     * @param  array<string, mixed>  $matricula
     */
    public function limiteCheckinsCicloEsgotado(array $matricula, int $tenantId): bool
    {
        $alunoId = (int) ($matricula['aluno_id'] ?? 0);
        if ($alunoId <= 0) {
            return false;
        }

        $stmtUser = $this->db->prepare('SELECT usuario_id FROM alunos WHERE id = ? LIMIT 1');
        $stmtUser->execute([$alunoId]);
        $usuarioId = (int) $stmtUser->fetchColumn();
        if ($usuarioId <= 0) {
            return false;
        }

        $modalidadeId = isset($matricula['modalidade_id']) ? (int) $matricula['modalidade_id'] : null;

        $checkin = new \App\Models\Checkin($this->db);
        $planoInfo = $checkin->obterLimiteCheckinsPlano($usuarioId, $tenantId, $modalidadeId);
        if (
            empty($planoInfo['tem_plano'])
            || empty($planoInfo['permite_reposicao'])
            || (int) ($planoInfo['limite'] ?? 0) <= 0
        ) {
            return false;
        }

        $detalhes = $checkin->avaliarLimiteMensalReposicao(
            $usuarioId,
            $tenantId,
            $modalidadeId,
            $planoInfo
        );

        return $detalhes !== null;
    }

    public function temParcelaAtrasada(int $matriculaId, int $tenantId): bool
    {
        $stmt = $this->db->prepare('
            SELECT 1
            FROM pagamentos_plano
            WHERE tenant_id = ?
              AND matricula_id = ?
              AND status_pagamento_id IN (1, 3)
              AND data_pagamento IS NULL
              AND data_vencimento < CURDATE()
            LIMIT 1
        ');
        $stmt->execute([$tenantId, $matriculaId]);

        return (bool) $stmt->fetchColumn();
    }

    public function buscarMatriculaAtivaModalidade(int $alunoId, int $tenantId, int $modalidadeId): ?array
    {
        $stmt = $this->db->prepare("
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
        date_default_timezone_set('America/Sao_Paulo');
        $valorCicloAtual = (float) $matricula['valor'];
        $planoNome = (string) ($matricula['plano_nome'] ?? 'plano atual');

        $aptidao = $this->avaliarAptidaoMigracao($matricula, $tenantId);
        if (! $aptidao['gera_credito']) {
            return [
                'tipo_credito' => 'sem_credito',
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

        $dataInicio = (string) $matricula['data_inicio'];
        // Ciclo vigente = data_vencimento (alinhado ao painel). proxima_data_vencimento é parcela futura.
        $dataVencimentoStr = (string) ($matricula['data_vencimento'] ?? $matricula['proxima_data_vencimento']);
        $hoje = date('Y-m-d');
        $base = self::calcularCreditoProporcional($valorCicloAtual, $dataInicio, $dataVencimentoStr, $hoje);

        // Ciclo sem dias restantes: sem crédito (upgrade inclusive).
        if ($base['dias_restantes'] <= 0 || $dataVencimentoStr <= $hoje) {
            return array_merge($base, [
                'tipo_credito' => 'sem_credito',
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
        $stmt = $this->db->prepare('
            SELECT id
            FROM pagamentos_plano
            WHERE matricula_id = ? AND tenant_id = ? AND status_pagamento_id = 2
            ORDER BY data_vencimento DESC
            LIMIT 1
        ');
        $stmt->execute([$matriculaId, $tenantId]);
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

        $stmtTenant = $this->db->prepare('SELECT nome FROM tenants WHERE id = ?');
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
        $externalReference = "MAT-{$matriculaId}-".time();

        try {
            $mp = new MercadoPagoService($tenantId);

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
                $this->salvarPixRegistro($tenantId, $matriculaId, $pixData);
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
                $dataVencimento,
            );
        } catch (\Throwable $e) {
            error_log('[MatriculaMigracaoService::gerarPagamentoMercadoPago] '.$e->getMessage());

            return ['erro' => $this->erro(500, 'ERRO_PAGAMENTO', 'Plano alterado, mas falha ao gerar pagamento. Tente reabrir o pagamento pendente.', [
                'mp_error' => $e->getMessage(),
                'matricula_id' => $matriculaId,
            ])];
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
        string $dataVencimento,
    ): void {
        $gatewayId = $this->lookupId('assinatura_gateways', 'mercadopago', 1);
        $statusPendenteId = $this->lookupId('assinatura_status', 'pendente', 1);
        $statusPagaId = $this->lookupId(
            'assinatura_status',
            'paga',
            $this->lookupId('assinatura_status', 'ativa', $statusPendenteId)
        );
        $frequenciaId = $this->lookupId('assinatura_frequencias', strtolower($cicloNome), 4);

        $metodoPagamentoId = null;
        if ($isRecorrente) {
            $metodoPagamentoId = $this->lookupId('metodos_pagamento', 'credit_card', 1);
        } elseif ($metodoPagamento === 'pix') {
            $metodoPagamentoId = $this->lookupId('metodos_pagamento', 'pix', null);
        }

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
            'dia_cobranca' => (int) date('d'),
            'data_inicio' => date('Y-m-d'),
            'proxima_cobranca' => $isRecorrente ? date('Y-m-d', strtotime('+1 month')) : null,
            'data_fim' => ! $isRecorrente ? $dataVencimento : null,
            'tipo_cobranca' => $isRecorrente ? 'recorrente' : 'avulso',
            'atualizado_em' => date('Y-m-d H:i:s'),
        ];

        if ($metodoPagamentoId !== null) {
            $payload['metodo_pagamento_id'] = $metodoPagamentoId;
        }

        // Reutilizar só assinatura PENDENTE da matrícula.
        // Assinatura já paga/ativa NÃO deve ser sobrescrita — senão some da tela.
        $stmtPend = $this->db->prepare("
            SELECT a.id
            FROM assinaturas a
            INNER JOIN assinatura_status s ON s.id = a.status_id
            WHERE a.matricula_id = ? AND a.tenant_id = ?
              AND s.codigo IN ('pendente', 'pending')
            ORDER BY a.id DESC
            LIMIT 1
        ");
        $stmtPend->execute([$matriculaId, $tenantId]);
        $pendenteId = $stmtPend->fetchColumn();

        if ($pendenteId) {
            $sets = [];
            $params = [];
            foreach ($payload as $col => $val) {
                $sets[] = "{$col} = ?";
                $params[] = $val;
            }
            $params[] = (int) $pendenteId;
            $this->db->prepare('UPDATE assinaturas SET '.implode(', ', $sets).' WHERE id = ?')
                ->execute($params);

            return;
        }

        // Encerrar a assinatura vigente (paga/ativa) preservando histórico na listagem.
        $stmtVigente = $this->db->prepare("
            SELECT a.id
            FROM assinaturas a
            INNER JOIN assinatura_status s ON s.id = a.status_id
            WHERE a.matricula_id = ? AND a.tenant_id = ?
              AND s.codigo NOT IN ('pendente', 'pending', 'cancelada', 'cancelled')
            ORDER BY a.id DESC
            LIMIT 1
        ");
        $stmtVigente->execute([$matriculaId, $tenantId]);
        $vigenteId = $stmtVigente->fetchColumn();
        if ($vigenteId && $statusPagaId > 0) {
            $this->db->prepare("
                UPDATE assinaturas
                SET status_id = ?,
                    status_gateway = CASE
                        WHEN status_gateway IS NULL OR status_gateway = '' OR status_gateway = 'pending'
                        THEN 'approved'
                        ELSE status_gateway
                    END,
                    data_fim = COALESCE(data_fim, ?),
                    atualizado_em = NOW()
                WHERE id = ?
            ")->execute([$statusPagaId, date('Y-m-d'), (int) $vigenteId]);
        }

        $cols = array_merge(['tenant_id', 'matricula_id', 'criado_em'], array_keys($payload));
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $values = array_merge([$tenantId, $matriculaId, date('Y-m-d H:i:s')], array_values($payload));
        $this->db->prepare('INSERT INTO assinaturas ('.implode(', ', $cols).") VALUES ({$placeholders})")
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

        $this->db->prepare('
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
            isset($pixData['date_of_expiration'])
                ? date('Y-m-d H:i:s', strtotime($pixData['date_of_expiration']))
                : null,
            $pixData['status'] ?? 'pending',
        ]);
    }

    private function lookupId(string $table, string $codigo, ?int $fallback): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM {$table} WHERE codigo = ? LIMIT 1");
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
