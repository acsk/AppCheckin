<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MercadoPagoService;

/**
 * Controller para gerenciar assinaturas recorrentes do MercadoPago
 */
class AssinaturaController
{
    private $db;
    
    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
    }
    
    /**
     * Criar assinatura recorrente
     * POST /mobile/assinatura/criar
     */
    public function criar(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $usuarioId = $request->getAttribute('userId');
            $data = $request->getParsedBody();
            
            // Validações
            if (empty($data['plano_ciclo_id'])) {
                $response->getBody()->write(json_encode(['error' => 'Ciclo do plano é obrigatório']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            if (empty($data['card_token'])) {
                $response->getBody()->write(json_encode(['error' => 'Token do cartão é obrigatório']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Buscar ciclo do plano
            $stmtCiclo = $this->db->prepare("
                SELECT pc.*, p.nome as plano_nome, p.duracao_dias, p.checkins_semanais,
                       m.nome as modalidade_nome
                FROM plano_ciclos pc
                INNER JOIN planos p ON p.id = pc.plano_id
                LEFT JOIN modalidades m ON m.id = p.modalidade_id
                WHERE pc.id = ? AND pc.tenant_id = ? AND pc.ativo = 1 AND pc.permite_recorrencia = 1
            ");
            $stmtCiclo->execute([$data['plano_ciclo_id'], $tenantId]);
            $ciclo = $stmtCiclo->fetch(\PDO::FETCH_ASSOC);
            
            if (!$ciclo) {
                $response->getBody()->write(json_encode(['error' => 'Ciclo não encontrado ou não permite recorrência']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Buscar dados do aluno
            $stmtAluno = $this->db->prepare("
                SELECT a.id as aluno_id, u.email, u.nome, u.cpf
                FROM alunos a
                INNER JOIN usuarios u ON u.id = a.usuario_id
                WHERE a.usuario_id = ? AND a.tenant_id = ?
            ");
            $stmtAluno->execute([$usuarioId, $tenantId]);
            $aluno = $stmtAluno->fetch(\PDO::FETCH_ASSOC);
            
            if (!$aluno) {
                $response->getBody()->write(json_encode(['error' => 'Aluno não encontrado']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Verificar se já tem assinatura ativa
            $stmtCheck = $this->db->prepare("
                SELECT a.id FROM assinaturas a
                INNER JOIN assinatura_status s ON s.id = a.status_id
                WHERE a.aluno_id = ? AND a.tenant_id = ? AND s.codigo IN ('pendente', 'ativa')
            ");
            $stmtCheck->execute([$aluno['aluno_id'], $tenantId]);
            if ($stmtCheck->fetch()) {
                $response->getBody()->write(json_encode([
                    'error' => 'Você já possui uma assinatura ativa. Cancele a atual antes de criar uma nova.'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Criar matrícula primeiro
            $this->db->beginTransaction();
            
            try {
                // Calcular datas
                $dataInicio = date('Y-m-d');
                $duracaoDias = $ciclo['duracao_dias'] * $ciclo['meses'];
                $dataVencimento = date('Y-m-d', strtotime("+{$duracaoDias} days"));
                $proximaDataVencimento = date('Y-m-d', strtotime("+{$ciclo['meses']} months"));
                
                // Criar matrícula como ATIVA (já que é assinatura)
                $stmtMatricula = $this->db->prepare("
                    INSERT INTO matriculas 
                    (tenant_id, aluno_id, plano_id, plano_ciclo_id, tipo_cobranca,
                     data_matricula, data_inicio, data_vencimento, valor, status_id,
                     motivo_id, proxima_data_vencimento, periodo_teste)
                    VALUES (?, ?, ?, ?, 'recorrente', ?, ?, ?, ?, 1, 1, ?, 0)
                ");
                
                $stmtMatricula->execute([
                    $tenantId,
                    $aluno['aluno_id'],
                    $ciclo['plano_id'],
                    $ciclo['id'],
                    $dataInicio,
                    $dataInicio,
                    $dataVencimento,
                    $ciclo['valor'],
                    $proximaDataVencimento
                ]);
                
                $matriculaId = (int) $this->db->lastInsertId();
                
                // Criar assinatura no MercadoPago
                $mercadoPagoService = new MercadoPagoService($tenantId);
                
                $dadosAssinatura = [
                    'reason' => "{$ciclo['plano_nome']} - {$ciclo['nome']}",
                    'external_reference' => "MAT-{$matriculaId}-" . time(),
                    'payer_email' => $aluno['email'],
                    'card_token_id' => $data['card_token'],
                    'auto_recurring' => [
                        'frequency' => $ciclo['meses'],
                        'frequency_type' => 'months',
                        'transaction_amount' => (float) $ciclo['valor'],
                        'currency_id' => 'BRL'
                    ],
                    'back_url' => $data['back_url'] ?? 'https://app.appcheckin.com.br/assinatura/retorno',
                    'status' => 'authorized' // Iniciar já autorizada
                ];
                
                $resultadoMP = $mercadoPagoService->criarAssinatura($dadosAssinatura);
                
                if (!$resultadoMP || isset($resultadoMP['error'])) {
                    throw new \Exception($resultadoMP['message'] ?? 'Erro ao criar assinatura no MercadoPago');
                }
                
                // Salvar assinatura no banco (tabela genérica)
                // Buscar IDs das tabelas de lookup
                $stmtGateway = $this->db->prepare("SELECT id FROM assinatura_gateways WHERE codigo = 'mercadopago'");
                $stmtGateway->execute();
                $gatewayId = $stmtGateway->fetchColumn() ?: 1;
                
                $statusMP = $resultadoMP['status'] ?? 'pending';
                $statusCodigo = match($statusMP) {
                    'authorized' => 'ativa',
                    'paused' => 'pausada',
                    'cancelled' => 'cancelada',
                    default => 'pendente'
                };
                $stmtStatus = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = ?");
                $stmtStatus->execute([$statusCodigo]);
                $statusId = $stmtStatus->fetchColumn() ?: 1;
                
                $stmtFreq = $this->db->prepare("SELECT id FROM assinatura_frequencias WHERE codigo = 'mensal'");
                $stmtFreq->execute();
                $frequenciaId = $stmtFreq->fetchColumn() ?: 4;
                
                $stmtAssinatura = $this->db->prepare("
                    INSERT INTO assinaturas
                    (tenant_id, matricula_id, aluno_id, plano_id,
                     gateway_id, gateway_assinatura_id, gateway_cliente_id,
                     status_id, status_gateway, valor,
                     frequencia_id, dia_cobranca, data_inicio, proxima_cobranca,
                     metodo_pagamento_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $diaCobranca = (int) date('d');
                $proximaCobranca = date('Y-m-d', strtotime("+{$ciclo['meses']} months"));
                
                // Método de pagamento = cartão de crédito
                $stmtMetodo = $this->db->prepare("SELECT id FROM metodos_pagamento WHERE codigo = 'credit_card'");
                $stmtMetodo->execute();
                $metodoPagamentoId = $stmtMetodo->fetchColumn() ?: 1;
                
                $stmtAssinatura->execute([
                    $tenantId,
                    $matriculaId,
                    $aluno['aluno_id'],
                    $ciclo['plano_id'],
                    $gatewayId,
                    $resultadoMP['id'] ?? null,
                    $resultadoMP['payer_id'] ?? null,
                    $statusId,
                    $statusMP,
                    $ciclo['valor'],
                    $frequenciaId,
                    $diaCobranca,
                    $dataInicio,
                    $proximaCobranca,
                    $metodoPagamentoId
                ]);
                
                $assinaturaId = (int) $this->db->lastInsertId();
                
                // Criar primeiro pagamento como PENDENTE
                $stmtPagamento = $this->db->prepare("
                    INSERT INTO pagamentos_plano
                    (tenant_id, aluno_id, matricula_id, plano_id, valor, data_vencimento,
                     data_pagamento, status_pagamento_id, forma_pagamento_id, tipo_baixa_id,
                     observacoes, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NULL,
                            1,
                            NULL, NULL,
                            'Aguardando pagamento da assinatura', NOW(), NOW())
                ");
                
                $stmtPagamento->execute([
                    $tenantId,
                    $aluno['aluno_id'],
                    $matriculaId,
                    $ciclo['plano_id'],
                    $ciclo['valor'],
                    $dataInicio
                ]);
                
                $this->db->commit();
                
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Assinatura criada com sucesso',
                    'data' => [
                        'assinatura_id' => $assinaturaId,
                        'matricula_id' => $matriculaId,
                        'mp_preapproval_id' => $resultadoMP['id'] ?? null,
                        'status' => $resultadoMP['status'] ?? 'pending',
                        'valor' => (float) $ciclo['valor'],
                        'ciclo' => $ciclo['nome'],
                        'proxima_cobranca' => $proximaCobranca,
                        'init_point' => $resultadoMP['init_point'] ?? null
                    ]
                ]));
                
                return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
                
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            error_log("[AssinaturaController::criar] Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao criar assinatura: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Cancelar assinatura
     * POST /mobile/assinatura/{id}/cancelar
     */
    public function cancelar(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $usuarioId = $request->getAttribute('userId');
            $assinaturaId = (int) $args['id'];
            $data = $request->getParsedBody();
            
            // Buscar assinatura
            $stmt = $this->db->prepare("
                SELECT ass.*, al.usuario_id, s.codigo as status_codigo, ass.gateway_assinatura_id as mp_preapproval_id
                FROM assinaturas ass
                INNER JOIN alunos al ON al.id = ass.aluno_id
                INNER JOIN assinatura_status s ON s.id = ass.status_id
                WHERE ass.id = ? AND ass.tenant_id = ?
            ");
            $stmt->execute([$assinaturaId, $tenantId]);
            $assinatura = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$assinatura) {
                $response->getBody()->write(json_encode(['error' => 'Assinatura não encontrada']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Verificar se é o dono da assinatura (ou admin)
            $isAdmin = $request->getAttribute('isAdmin') ?? false;
            if ($assinatura['usuario_id'] != $usuarioId && !$isAdmin) {
                $response->getBody()->write(json_encode(['error' => 'Sem permissão para cancelar esta assinatura']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }
            
            if ($assinatura['status_codigo'] === 'cancelada') {
                $response->getBody()->write(json_encode(['error' => 'Assinatura já está cancelada']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Cancelar no MercadoPago
            if ($assinatura['mp_preapproval_id']) {
                try {
                    $mercadoPagoService = new MercadoPagoService($tenantId);
                    $mercadoPagoService->cancelarAssinatura($assinatura['mp_preapproval_id']);
                } catch (\Exception $e) {
                    error_log("Erro ao cancelar no MP: " . $e->getMessage());
                    // Continua mesmo se falhar no MP
                }
            }
            
            // Atualizar no banco
            $stmtStatusCancelada = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = 'cancelada'");
            $stmtStatusCancelada->execute();
            $statusCanceladaId = $stmtStatusCancelada->fetchColumn() ?: 4;
            
            $stmtTipoCancelamento = $this->db->prepare("SELECT id FROM assinatura_cancelamento_tipos WHERE codigo = 'usuario'");
            $stmtTipoCancelamento->execute();
            $tipoCancelamentoId = $stmtTipoCancelamento->fetchColumn() ?: 1;
            
            $stmtUpdate = $this->db->prepare("
                UPDATE assinaturas
                SET status_id = ?,
                    status_gateway = 'cancelled',
                    motivo_cancelamento = ?,
                    cancelado_por_id = ?,
                    atualizado_em = NOW()
                WHERE id = ?
            ");
            
            $stmtUpdate->execute([
                $statusCanceladaId,
                $data['motivo'] ?? 'Cancelado pelo usuário',
                $tipoCancelamentoId,
                $assinaturaId
            ]);
            
            // Cancelar matrícula associada à assinatura
            $stmtStatusMatriculaCancelada = $this->db->prepare("
                SELECT id FROM status_matricula WHERE codigo = 'cancelada' LIMIT 1
            ");
            $stmtStatusMatriculaCancelada->execute();
            $statusMatriculaCanceladaId = $stmtStatusMatriculaCancelada->fetchColumn() ?: 3;
            
            $stmtCancelarMatricula = $this->db->prepare("
                UPDATE matriculas 
                SET status_id = ?,
                    updated_at = NOW()
                WHERE tenant_id = ?
                AND aluno_id = ?
                AND status_id IN (
                    SELECT id FROM status_matricula WHERE codigo IN ('ativa', 'vencida')
                )
                AND id IN (
                    SELECT matricula_id FROM assinaturas WHERE id = ? AND matricula_id IS NOT NULL
                )
            ");
            $stmtCancelarMatricula->execute([
                $statusMatriculaCanceladaId,
                $tenantId,
                $assinatura['aluno_id'],
                $assinaturaId
            ]);
            $matriculasCanceladas = $stmtCancelarMatricula->rowCount();
            
            error_log("[AssinaturaController::cancelar] Assinatura #{$assinaturaId} cancelada. Matrículas canceladas: {$matriculasCanceladas}");
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Assinatura cancelada com sucesso',
                'matriculas_canceladas' => $matriculasCanceladas
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("[AssinaturaController::cancelar] Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Erro ao cancelar assinatura']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Listar assinaturas do usuário
     * GET /mobile/assinaturas
     */
    public function minhasAssinaturas(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $usuarioId = $request->getAttribute('userId');
            $alunoId = $request->getAttribute('aluno_id');
            
            error_log("[minhasAssinaturas] tenant=$tenantId, usuario=$usuarioId, alunoJWT=$alunoId");
            
            // Se não tem aluno_id no JWT, buscar pelo usuario_id
            if (!$alunoId) {
                $stmt = $this->db->prepare('SELECT id FROM alunos WHERE usuario_id = ? LIMIT 1');
                $stmt->execute([$usuarioId]);
                $alunoId = $stmt->fetchColumn();
                error_log("[minhasAssinaturas] alunoId buscado do banco: $alunoId");
            }
            
            if (!$alunoId) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'assinaturas' => [],
                    'total' => 0
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Reconciliar assinaturas avulsas canceladas/estornadas (casos manuais)
            try {
                $stmtStatusCancelada = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = 'cancelada' LIMIT 1");
                $stmtStatusCancelada->execute();
                $statusCanceladaId = (int) ($stmtStatusCancelada->fetchColumn() ?: 0);
                if ($statusCanceladaId > 0) {
                    $stmtReconcilia = $this->db->prepare("
                        UPDATE assinaturas a
                        INNER JOIN pagamentos_mercadopago pm
                            ON pm.matricula_id = a.matricula_id
                           AND pm.tenant_id = a.tenant_id
                        SET a.status_id = ?,
                            a.status_gateway = pm.status,
                            a.atualizado_em = NOW()
                        WHERE a.aluno_id = ?
                          AND a.tenant_id = ?
                          AND a.tipo_cobranca = 'avulso'
                          AND pm.status IN ('cancelled', 'refunded', 'charged_back')
                          AND (a.status_id != ? OR a.status_gateway NOT IN ('cancelled', 'refunded', 'charged_back'))
                    ");
                    $stmtReconcilia->execute([$statusCanceladaId, $alunoId, $tenantId, $statusCanceladaId]);
                }

            } catch (\Exception $e) {
                error_log("[minhasAssinaturas] Erro ao reconciliar assinaturas: " . $e->getMessage());
            }
            
            $stmt = $this->db->prepare("
                  SELECT a.id, a.status_id, a.valor, a.data_inicio, a.data_fim, a.proxima_cobranca,
                       a.ultima_cobranca, a.gateway_assinatura_id as mp_preapproval_id,
                       a.gateway_preference_id, a.external_reference, a.payment_url, a.tipo_cobranca,
                      a.status_gateway, a.cancelado_por_id,
                       s.codigo as status_codigo, s.nome as status_nome, s.cor as status_cor,
                      ct.codigo as cancelado_por_codigo, ct.nome as cancelado_por_nome,
                       f.nome as ciclo_nome, f.meses as ciclo_meses,
                       g.nome as gateway_nome,
                       p.nome as plano_nome,
                       mo.nome as modalidade_nome
                FROM assinaturas a
                LEFT JOIN assinatura_status s ON s.id = a.status_id
                  LEFT JOIN assinatura_cancelamento_tipos ct ON ct.id = a.cancelado_por_id
                LEFT JOIN assinatura_frequencias f ON f.id = a.frequencia_id
                LEFT JOIN assinatura_gateways g ON g.id = a.gateway_id
                LEFT JOIN planos p ON p.id = a.plano_id
                LEFT JOIN modalidades mo ON mo.id = p.modalidade_id
                WHERE a.aluno_id = ? AND a.tenant_id = ?
                ORDER BY a.data_inicio DESC
            ");
            $stmt->execute([$alunoId, $tenantId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            error_log("[minhasAssinaturas] aluno=$alunoId, tenant=$tenantId, encontradas=" . count($rows));
            
            $assinaturas = [];
            foreach ($rows as $row) {
                $statusCodigo = $row['status_codigo'] ?? $row['status_gateway'] ?? 'pendente';
                $statusNome = $row['status_nome'] ?? $this->getStatusLabel($row['status_gateway'] ?? 'pendente');
                $statusCor = $row['status_cor'] ?? '#FFA500';

                $canceladoPorId = isset($row['cancelado_por_id']) ? (int)$row['cancelado_por_id'] : 0;
                $canceladoPorCodigo = strtolower((string)($row['cancelado_por_codigo'] ?? ''));
                $foiCanceladaPeloUsuario = $canceladoPorId === 1 || $canceladoPorCodigo === 'usuario';

                // Regra de precedência: se foi cancelada pelo usuário, sempre refletir como cancelada.
                if ($foiCanceladaPeloUsuario) {
                    $statusCodigo = 'cancelada';
                    $statusNome = $row['cancelado_por_nome'] ?: 'Cancelado pelo Usuário';
                    $statusCor = '#DC2626';
                }

                $isPendente = in_array($statusCodigo, ['pendente', 'pending']);
                $tipoCobranca = $row['tipo_cobranca'] ?? 'recorrente';
                
                $assinaturaData = [
                    'id' => (int)$row['id'],
                    'status' => [
                        'id' => (int)$row['status_id'],
                        'codigo' => $statusCodigo,
                        'nome' => $statusNome,
                        'cor' => $statusCor
                    ],
                    'cancelamento' => [
                        'cancelado_por_id' => $canceladoPorId ?: null,
                        'cancelado_por_codigo' => $row['cancelado_por_codigo'] ?? null,
                        'cancelado_por_nome' => $row['cancelado_por_nome'] ?? null,
                    ],
                    'valor' => (float)$row['valor'],
                    'tipo_cobranca' => $tipoCobranca,
                    'recorrente' => $tipoCobranca === 'recorrente',
                    'data_inicio' => $row['data_inicio'],
                    'data_fim' => $row['data_fim'],
                    'proxima_cobranca' => $row['proxima_cobranca'],
                    'ultima_cobranca' => $row['ultima_cobranca'],
                    'mp_preapproval_id' => $row['mp_preapproval_id'],
                    'preference_id' => $row['gateway_preference_id'],
                    'external_reference' => $row['external_reference'],
                    'ciclo' => [
                        'nome' => $row['ciclo_nome'] ?? 'Mensal',
                        'meses' => (int)($row['ciclo_meses'] ?? 1)
                    ],
                    'gateway' => [
                        'nome' => $row['gateway_nome'] ?? 'Mercado Pago'
                    ],
                    'plano' => [
                        'nome' => $row['plano_nome'] ?? '',
                        'modalidade' => $row['modalidade_nome'] ?? ''
                    ]
                ];
                
                // Se está pendente, incluir payment_url para recuperação do pagamento
                if ($isPendente && !empty($row['payment_url'])) {
                    $assinaturaData['payment_url'] = $row['payment_url'];
                    $assinaturaData['pode_pagar'] = true;
                } else {
                    $assinaturaData['pode_pagar'] = false;
                }
                
                $assinaturas[] = $assinaturaData;
            }

            // Pacotes onde o usuário é pagante (ver beneficiários)
            $pacotes = [];
            try {
                $stmtPacotes = $this->db->prepare("
                    SELECT pc.id as contrato_id, pc.status, pc.valor_total, pc.data_inicio, pc.data_fim,
                           p.nome as pacote_nome
                    FROM pacote_contratos pc
                    INNER JOIN pacotes p ON p.id = pc.pacote_id
                    WHERE pc.tenant_id = ? AND pc.pagante_usuario_id = ?
                    ORDER BY pc.created_at DESC
                ");
                $stmtPacotes->execute([$tenantId, $usuarioId]);
                $pacotesRows = $stmtPacotes->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($pacotesRows as $pc) {
                    $stmtBen = $this->db->prepare("
                        SELECT a.id as aluno_id, a.nome as aluno_nome
                        FROM pacote_beneficiarios pb
                        INNER JOIN alunos a ON a.id = pb.aluno_id
                        WHERE pb.pacote_contrato_id = ? AND pb.tenant_id = ?
                    ");
                    $stmtBen->execute([(int)$pc['contrato_id'], $tenantId]);
                    $beneficiarios = $stmtBen->fetchAll(\PDO::FETCH_ASSOC);

                    $pacotes[] = [
                        'contrato_id' => (int) $pc['contrato_id'],
                        'status' => $pc['status'],
                        'valor_total' => (float) $pc['valor_total'],
                        'data_inicio' => $pc['data_inicio'],
                        'data_fim' => $pc['data_fim'],
                        'pacote_nome' => $pc['pacote_nome'],
                        'beneficiarios' => array_map(function ($b) {
                            return [
                                'aluno_id' => (int) $b['aluno_id'],
                                'nome' => $b['aluno_nome']
                            ];
                        }, $beneficiarios)
                    ];
                }
            } catch (\Exception $e) {
                error_log("[minhasAssinaturas] Erro ao buscar pacotes: " . $e->getMessage());
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'assinaturas' => $assinaturas,
                'total' => count($assinaturas),
                'pacotes' => $pacotes
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("[minhasAssinaturas] ERRO: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao listar assinaturas',
                'detail' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Verificar se há assinatura aprovada hoje (para PIX/redirecionamento)
     * GET /mobile/assinaturas/aprovadas-hoje?matricula_id=123
     */
    public function aprovadasHoje(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $usuarioId = $request->getAttribute('userId');
            $params = $request->getQueryParams();
            $matriculaId = isset($params['matricula_id']) ? (int)$params['matricula_id'] : 0;

            if ($matriculaId <= 0) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'matricula_id é obrigatório'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $stmt = $this->db->prepare("
                SELECT a.id, a.matricula_id, a.tipo_cobranca, a.status_gateway,
                       a.ultima_cobranca, a.atualizado_em, a.external_reference, a.payment_url,
                       s.codigo as status_codigo, s.nome as status_nome
                FROM assinaturas a
                INNER JOIN alunos al ON al.id = a.aluno_id
                LEFT JOIN assinatura_status s ON s.id = a.status_id
                WHERE a.tenant_id = ?
                  AND al.usuario_id = ?
                  AND a.matricula_id = ?
                  AND (a.status_gateway = 'approved' OR s.codigo IN ('ativa', 'paga'))
                  AND DATE(COALESCE(a.ultima_cobranca, a.atualizado_em, a.criado_em)) = CURDATE()
                ORDER BY a.atualizado_em DESC
                LIMIT 1
            ");
            $stmt->execute([$tenantId, $usuarioId, $matriculaId]);
            $assinatura = $stmt->fetch(\PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'success' => true,
                'approved' => (bool)$assinatura,
                'data' => $assinatura ? [
                    'assinatura_id' => (int)$assinatura['id'],
                    'matricula_id' => (int)$assinatura['matricula_id'],
                    'status_gateway' => $assinatura['status_gateway'] ?? null,
                    'status_codigo' => $assinatura['status_codigo'] ?? null,
                    'status_nome' => $assinatura['status_nome'] ?? null,
                    'tipo_cobranca' => $assinatura['tipo_cobranca'] ?? null,
                    'ultima_cobranca' => $assinatura['ultima_cobranca'],
                    'atualizado_em' => $assinatura['atualizado_em'],
                    'external_reference' => $assinatura['external_reference'] ?? null,
                    'payment_url' => $assinatura['payment_url'] ?? null
                ] : null
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("[AssinaturaController::aprovadasHoje] Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao consultar assinatura aprovada',
                'detail' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    private function getStatusLabel(string $status): string
    {
        return match($status) {
            'pendente', 'pending' => 'Pendente',
            'ativa', 'authorized', 'approved' => 'Ativa',
            'pausada', 'paused' => 'Pausada',
            'cancelada', 'cancelled', 'refunded', 'charged_back' => 'Cancelada',
            'expirada', 'finished' => 'Expirada',
            default => ucfirst($status)
        };
    }
}
