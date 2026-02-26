<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\MercadoPagoService;
use OpenApi\Attributes as OA;

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
                    SELECT ?, ?, ?, ?, ?, ?, NULL,
                           1,
                           NULL, NULL,
                           'Aguardando pagamento da assinatura', NOW(), NOW()
                    WHERE NOT EXISTS (
                        SELECT 1 FROM pagamentos_plano
                        WHERE tenant_id = ?
                          AND matricula_id = ?
                          AND status_pagamento_id = 1
                          AND data_pagamento IS NULL
                    )
                ");
                
                $stmtPagamento->execute([
                    $tenantId,
                    $aluno['aluno_id'],
                    $matriculaId,
                    $ciclo['plano_id'],
                    $ciclo['valor'],
                    $dataInicio,
                    $tenantId,
                    $matriculaId
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
     * 
     * Fluxo:
     * 1. Verifica localmente se assinatura já está aprovada
     * 2. Se não, verifica se matrícula já está ativa (webhook processou)
     * 3. Se não, consulta a API do Mercado Pago pelo external_reference
     *    e processa o pagamento se estiver aprovado (fallback para quando webhook falha)
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

            error_log("[aprovadasHoje] Verificando matrícula #{$matriculaId} para usuário #{$usuarioId}, tenant #{$tenantId}");

            // PASSO 1: Verificar se assinatura já está aprovada localmente
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
                ORDER BY a.atualizado_em DESC
                LIMIT 1
            ");
            $stmt->execute([$tenantId, $usuarioId, $matriculaId]);
            $assinatura = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($assinatura) {
                error_log("[aprovadasHoje] ✅ Assinatura #{$assinatura['id']} já aprovada localmente");
                return $this->responderAprovada($response, $assinatura);
            }

            // PASSO 2: Verificar se a matrícula já está ativa (webhook pode ter processado via pagamento direto)
            $stmtMat = $this->db->prepare("
                SELECT m.id, sm.codigo as status_codigo
                FROM matriculas m
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                INNER JOIN alunos al ON al.id = m.aluno_id
                WHERE m.id = ? AND al.usuario_id = ? AND m.tenant_id = ?
            ");
            $stmtMat->execute([$matriculaId, $usuarioId, $tenantId]);
            $matricula = $stmtMat->fetch(\PDO::FETCH_ASSOC);

            if ($matricula && $matricula['status_codigo'] === 'ativa') {
                error_log("[aprovadasHoje] ✅ Matrícula #{$matriculaId} já está ativa");
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'approved' => true,
                    'data' => [
                        'matricula_id' => $matriculaId,
                        'status_gateway' => 'approved',
                        'status_codigo' => 'ativa',
                        'status_nome' => 'Ativa',
                        'fonte' => 'matricula_ativa'
                    ]
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // PASSO 3: Buscar assinatura pendente para obter external_reference
            $stmtPendente = $this->db->prepare("
                SELECT a.id, a.external_reference, a.tipo_cobranca, a.status_gateway,
                       a.ultima_cobranca, a.atualizado_em, a.payment_url,
                       s.codigo as status_codigo, s.nome as status_nome
                FROM assinaturas a
                INNER JOIN alunos al ON al.id = a.aluno_id
                LEFT JOIN assinatura_status s ON s.id = a.status_id
                WHERE a.tenant_id = ?
                  AND al.usuario_id = ?
                  AND a.matricula_id = ?
                ORDER BY a.criado_em DESC
                LIMIT 1
            ");
            $stmtPendente->execute([$tenantId, $usuarioId, $matriculaId]);
            $assinaturaPendente = $stmtPendente->fetch(\PDO::FETCH_ASSOC);

            $externalReference = $assinaturaPendente['external_reference'] ?? null;

            // Se não tem assinatura, tentar construir external_reference pelo padrão MAT-{id}
            if (!$externalReference) {
                // Buscar pela tabela pagamentos_plano que pode ter o external_reference
                $stmtRef = $this->db->prepare("
                    SELECT pm.external_reference
                    FROM pagamentos_mercadopago pm
                    WHERE pm.matricula_id = ?
                    ORDER BY pm.created_at DESC
                    LIMIT 1
                ");
                $stmtRef->execute([$matriculaId]);
                $externalReference = $stmtRef->fetchColumn() ?: null;
            }

            if (!$externalReference) {
                error_log("[aprovadasHoje] ⚠️ Sem external_reference para matrícula #{$matriculaId}, não é possível consultar MP");
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'approved' => false,
                    'data' => $assinaturaPendente ? [
                        'assinatura_id' => (int)$assinaturaPendente['id'],
                        'matricula_id' => $matriculaId,
                        'status_gateway' => $assinaturaPendente['status_gateway'] ?? 'pending',
                        'status_codigo' => $assinaturaPendente['status_codigo'] ?? 'pendente',
                        'status_nome' => $assinaturaPendente['status_nome'] ?? 'Pendente',
                    ] : null
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // PASSO 4: Consultar API do Mercado Pago pelo external_reference
            error_log("[aprovadasHoje] 🔍 Consultando MP por external_reference: {$externalReference}");

            try {
                $mercadoPagoService = new MercadoPagoService($tenantId);
                $resultado = $mercadoPagoService->buscarPagamentosPorExternalReference($externalReference);
                $pagamentos = $resultado['pagamentos'] ?? [];

                // Procurar pagamento aprovado
                $pagamentoAprovado = null;
                foreach ($pagamentos as $p) {
                    if (($p['status'] ?? '') === 'approved') {
                        $pagamentoAprovado = $p;
                        break;
                    }
                }

                if ($pagamentoAprovado) {
                    error_log("[aprovadasHoje] ✅ Pagamento aprovado encontrado no MP: #{$pagamentoAprovado['id']}. Processando...");

                    // Processar o pagamento como se fosse um webhook (ativar matrícula, baixar pagamento, etc.)
                    $this->processarPagamentoAprovadoMP($matriculaId, $pagamentoAprovado, $tenantId, $externalReference);

                    // Re-buscar assinatura atualizada
                    $stmtAtualizada = $this->db->prepare("
                        SELECT a.id, a.matricula_id, a.tipo_cobranca, a.status_gateway,
                               a.ultima_cobranca, a.atualizado_em, a.external_reference, a.payment_url,
                               s.codigo as status_codigo, s.nome as status_nome
                        FROM assinaturas a
                        INNER JOIN alunos al ON al.id = a.aluno_id
                        LEFT JOIN assinatura_status s ON s.id = a.status_id
                        WHERE a.tenant_id = ?
                          AND al.usuario_id = ?
                          AND a.matricula_id = ?
                        ORDER BY a.atualizado_em DESC
                        LIMIT 1
                    ");
                    $stmtAtualizada->execute([$tenantId, $usuarioId, $matriculaId]);
                    $assinaturaAtualizada = $stmtAtualizada->fetch(\PDO::FETCH_ASSOC);

                    if ($assinaturaAtualizada) {
                        return $this->responderAprovada($response, $assinaturaAtualizada);
                    }

                    // Mesmo se assinatura não atualizou, a matrícula foi ativada
                    $response->getBody()->write(json_encode([
                        'success' => true,
                        'approved' => true,
                        'data' => [
                            'matricula_id' => $matriculaId,
                            'status_gateway' => 'approved',
                            'status_codigo' => 'paga',
                            'status_nome' => 'Paga',
                            'fonte' => 'mercadopago_api'
                        ]
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json');
                }

                // Pagamento no MP mas não aprovado ainda
                $statusMP = !empty($pagamentos) ? ($pagamentos[0]['status'] ?? 'pending') : 'not_found';
                error_log("[aprovadasHoje] ⏳ Pagamento no MP com status: {$statusMP}");

                $response->getBody()->write(json_encode([
                    'success' => true,
                    'approved' => false,
                    'data' => $assinaturaPendente ? [
                        'assinatura_id' => (int)$assinaturaPendente['id'],
                        'matricula_id' => $matriculaId,
                        'status_gateway' => $statusMP,
                        'status_codigo' => $assinaturaPendente['status_codigo'] ?? 'pendente',
                        'status_nome' => $assinaturaPendente['status_nome'] ?? 'Pendente',
                        'external_reference' => $externalReference
                    ] : null
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json');

            } catch (\Exception $eMp) {
                error_log("[aprovadasHoje] ⚠️ Erro ao consultar MP: " . $eMp->getMessage());
                // Não falhar o endpoint — retornar dados locais que temos
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'approved' => false,
                    'data' => $assinaturaPendente ? [
                        'assinatura_id' => (int)$assinaturaPendente['id'],
                        'matricula_id' => $matriculaId,
                        'status_gateway' => $assinaturaPendente['status_gateway'] ?? 'pending',
                        'status_codigo' => $assinaturaPendente['status_codigo'] ?? 'pendente',
                        'status_nome' => $assinaturaPendente['status_nome'] ?? 'Pendente',
                    ] : null
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json');
            }

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

    /**
     * Responder com dados de assinatura aprovada
     */
    private function responderAprovada(Response $response, array $assinatura): Response
    {
        $response->getBody()->write(json_encode([
            'success' => true,
            'approved' => true,
            'data' => [
                'assinatura_id' => (int)$assinatura['id'],
                'matricula_id' => (int)($assinatura['matricula_id'] ?? 0),
                'status_gateway' => $assinatura['status_gateway'] ?? 'approved',
                'status_codigo' => $assinatura['status_codigo'] ?? 'paga',
                'status_nome' => $assinatura['status_nome'] ?? 'Paga',
                'tipo_cobranca' => $assinatura['tipo_cobranca'] ?? null,
                'ultima_cobranca' => $assinatura['ultima_cobranca'] ?? null,
                'atualizado_em' => $assinatura['atualizado_em'] ?? null,
                'external_reference' => $assinatura['external_reference'] ?? null,
                'payment_url' => $assinatura['payment_url'] ?? null
            ]
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Processar pagamento aprovado detectado via consulta ao MP (fallback quando webhook não chegou)
     * Replica a lógica essencial do MercadoPagoWebhookController::atualizarPagamento
     */
    private function processarPagamentoAprovadoMP(int $matriculaId, array $pagamento, int $tenantId, string $externalReference): void
    {
        try {
            error_log("[aprovadasHoje] 🔄 Processando pagamento #{$pagamento['id']} para matrícula #{$matriculaId}");

            // Verificar se já foi processado (evitar duplicidade)
            $stmtJa = $this->db->prepare("
                SELECT id FROM pagamentos_mercadopago WHERE payment_id = ? LIMIT 1
            ");
            $stmtJa->execute([$pagamento['id']]);
            if ($stmtJa->fetch()) {
                error_log("[aprovadasHoje] ℹ️ Pagamento #{$pagamento['id']} já registrado em pagamentos_mercadopago");
                // Verificar se matrícula está ativa, se não, ativar
                $this->ativarMatriculaSeNecessario($matriculaId);
                $this->baixarPagamentoPlanoSeNecessario($matriculaId, $pagamento);
                return;
            }

            // Buscar dados da matrícula
            $stmtMat = $this->db->prepare("
                SELECT m.tenant_id, m.aluno_id, m.plano_id
                FROM matriculas m WHERE m.id = ?
            ");
            $stmtMat->execute([$matriculaId]);
            $mat = $stmtMat->fetch(\PDO::FETCH_ASSOC);

            if (!$mat) {
                error_log("[aprovadasHoje] ❌ Matrícula #{$matriculaId} não encontrada");
                return;
            }

            // Inserir em pagamentos_mercadopago (registro espelho do MP)
            $stmtInsert = $this->db->prepare("
                INSERT INTO pagamentos_mercadopago (
                    tenant_id, matricula_id, aluno_id, usuario_id,
                    payment_id, external_reference, status, status_detail,
                    transaction_amount, payment_method_id, payment_type_id,
                    installments, date_approved, date_created,
                    payer_email, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmtInsert->execute([
                $mat['tenant_id'],
                $matriculaId,
                $mat['aluno_id'],
                $pagamento['metadata']['usuario_id'] ?? null,
                $pagamento['id'],
                $externalReference,
                $pagamento['status'],
                $pagamento['status_detail'] ?? null,
                $pagamento['transaction_amount'],
                $pagamento['payment_method_id'] ?? null,
                $pagamento['payment_type_id'] ?? null,
                $pagamento['installments'] ?? 1,
                $pagamento['date_approved'] ?? null,
                $pagamento['date_created'] ?? null,
                $pagamento['payer']['email'] ?? null
            ]);

            error_log("[aprovadasHoje] ✅ Registro criado em pagamentos_mercadopago");

            // Ativar matrícula
            $this->ativarMatriculaSeNecessario($matriculaId);

            // Baixar pagamento_plano
            $this->baixarPagamentoPlanoSeNecessario($matriculaId, $pagamento);

            // Atualizar assinatura para aprovada
            $this->atualizarAssinaturaParaAprovada($matriculaId, $pagamento);

            error_log("[aprovadasHoje] ✅ Processamento completo para matrícula #{$matriculaId}");

        } catch (\Exception $e) {
            error_log("[aprovadasHoje] ❌ Erro ao processar pagamento: " . $e->getMessage());
        }
    }

    /**
     * Ativar matrícula se ainda não está ativa
     */
    private function ativarMatriculaSeNecessario(int $matriculaId): void
    {
        $stmt = $this->db->prepare("
            UPDATE matriculas
            SET status_id = (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1),
                updated_at = NOW()
            WHERE id = ?
              AND status_id != (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1)
        ");
        $stmt->execute([$matriculaId]);

        if ($stmt->rowCount() > 0) {
            error_log("[aprovadasHoje] ✅ Matrícula #{$matriculaId} ativada");
        }
    }

    /**
     * Baixar pagamento_plano pendente se existir
     */
    private function baixarPagamentoPlanoSeNecessario(int $matriculaId, array $pagamento): void
    {
        $dataPagamento = !empty($pagamento['date_approved'])
            ? date('Y-m-d H:i:s', strtotime((string)$pagamento['date_approved']))
            : date('Y-m-d H:i:s');

        // Determinar forma_pagamento_id baseado no payment_type_id do MP
        $paymentType = $pagamento['payment_type_id'] ?? $pagamento['payment_method_id'] ?? '';
        $formaPagamentoId = match(true) {
            str_contains($paymentType, 'credit') => 9,   // Cartão de crédito
            str_contains($paymentType, 'debit') => 10,   // Cartão de débito
            $paymentType === 'bank_transfer' || str_contains($paymentType, 'pix') => 8, // PIX
            default => 8 // Default PIX
        };

        // Buscar pagamento pendente
        $stmtBuscar = $this->db->prepare("
            SELECT id FROM pagamentos_plano
            WHERE matricula_id = ?
              AND status_pagamento_id IN (1, 3)
              AND data_pagamento IS NULL
            ORDER BY data_vencimento ASC
            LIMIT 1
        ");
        $stmtBuscar->execute([$matriculaId]);
        $pendente = $stmtBuscar->fetch(\PDO::FETCH_ASSOC);

        if ($pendente) {
            $paymentId = $pagamento['id'] ?? 'N/A';
            $stmtUpdate = $this->db->prepare("
                UPDATE pagamentos_plano
                SET status_pagamento_id = 2,
                    data_pagamento = ?,
                    forma_pagamento_id = ?,
                    tipo_baixa_id = 4,
                    observacoes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtUpdate->execute([
                $dataPagamento,
                $formaPagamentoId,
                "Pago via Mercado Pago - Payment #{$paymentId} (detectado via polling)",
                $pendente['id']
            ]);

            if ($stmtUpdate->rowCount() > 0) {
                error_log("[aprovadasHoje] ✅ Pagamento #{$pendente['id']} baixado como PAGO");
            }
        } else {
            error_log("[aprovadasHoje] ℹ️ Nenhum pagamento pendente para matrícula #{$matriculaId}");
        }
    }

    /**
     * Atualizar assinatura para status aprovado
     */
    private function atualizarAssinaturaParaAprovada(int $matriculaId, array $pagamento): void
    {
        // Buscar status 'paga' ou 'ativa'
        $stmtStatus = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = 'paga' LIMIT 1");
        $stmtStatus->execute();
        $statusId = $stmtStatus->fetchColumn();

        if (!$statusId) {
            $stmtStatus = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = 'ativa' LIMIT 1");
            $stmtStatus->execute();
            $statusId = $stmtStatus->fetchColumn() ?: 2;
        }

        $stmt = $this->db->prepare("
            UPDATE assinaturas
            SET status_id = ?,
                status_gateway = 'approved',
                ultima_cobranca = CURDATE(),
                atualizado_em = NOW()
            WHERE matricula_id = ?
              AND status_gateway != 'approved'
        ");
        $stmt->execute([$statusId, $matriculaId]);

        if ($stmt->rowCount() > 0) {
            error_log("[aprovadasHoje] ✅ Assinatura da matrícula #{$matriculaId} atualizada para aprovada");
        }
    }
    
    /**
     * Listar todas as assinaturas do tenant (visão Admin)
     * GET /admin/assinaturas
     */
    #[OA\Get(
        path: "/admin/assinaturas",
        summary: "Listar assinaturas do tenant (Admin)",
        description: "Retorna todas as assinaturas do tenant com dados do aluno, plano, status e gateway. Suporta filtros por status, tipo de cobrança e busca por nome do aluno. Paginado.",
        tags: ["Admin - Assinaturas"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", description: "Filtrar por código de status (ativa, pendente, cancelada, pausada, expirada)", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "tipo_cobranca", in: "query", description: "Filtrar por tipo de cobrança (recorrente, avulso)", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "busca", in: "query", description: "Buscar por nome do aluno", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "page", in: "query", description: "Página (default: 1)", required: false, schema: new OA\Schema(type: "integer", default: 1)),
            new OA\Parameter(name: "per_page", in: "query", description: "Itens por página (default: 20, max: 100)", required: false, schema: new OA\Schema(type: "integer", default: 20))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de assinaturas",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "total", type: "integer"),
                        new OA\Property(property: "page", type: "integer"),
                        new OA\Property(property: "per_page", type: "integer"),
                        new OA\Property(property: "total_pages", type: "integer"),
                        new OA\Property(
                            property: "assinaturas",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer"),
                                    new OA\Property(property: "aluno_id", type: "integer"),
                                    new OA\Property(property: "aluno_nome", type: "string"),
                                    new OA\Property(property: "valor", type: "number", format: "float"),
                                    new OA\Property(property: "tipo_cobranca", type: "string"),
                                    new OA\Property(property: "status_codigo", type: "string"),
                                    new OA\Property(property: "status_nome", type: "string"),
                                    new OA\Property(property: "status_gateway", type: "string"),
                                    new OA\Property(property: "plano_nome", type: "string"),
                                    new OA\Property(property: "modalidade_nome", type: "string"),
                                    new OA\Property(property: "data_inicio", type: "string", format: "date"),
                                    new OA\Property(property: "proxima_cobranca", type: "string", format: "date", nullable: true),
                                    new OA\Property(property: "external_reference", type: "string", nullable: true),
                                    new OA\Property(property: "mp_preapproval_id", type: "string", nullable: true),
                                    new OA\Property(property: "criado_em", type: "string", format: "date-time")
                                ]
                            )
                        )
                    ]
                )
            )
        ]
    )]
    public function listarAssinaturasAdmin(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $params = $request->getQueryParams();

            // Paginação
            $page = max(1, (int)($params['page'] ?? 1));
            $perPage = min(100, max(1, (int)($params['per_page'] ?? 20)));
            $offset = ($page - 1) * $perPage;

            // Filtros
            $statusFiltro = trim($params['status'] ?? '');
            $tipoCobranca = trim($params['tipo_cobranca'] ?? '');
            $busca = trim($params['busca'] ?? '');

            // Construir WHERE
            $where = "WHERE a.tenant_id = ?";
            $binds = [$tenantId];

            if ($statusFiltro !== '') {
                $where .= " AND s.codigo = ?";
                $binds[] = $statusFiltro;
            }

            if ($tipoCobranca !== '') {
                $where .= " AND a.tipo_cobranca = ?";
                $binds[] = $tipoCobranca;
            }

            if ($busca !== '') {
                $where .= " AND (al.nome LIKE ? OR u.nome LIKE ?)";
                $binds[] = "%{$busca}%";
                $binds[] = "%{$busca}%";
            }

            // Total
            $stmtCount = $this->db->prepare("
                SELECT COUNT(*) FROM assinaturas a
                LEFT JOIN assinatura_status s ON s.id = a.status_id
                LEFT JOIN alunos al ON al.id = a.aluno_id
                LEFT JOIN usuarios u ON u.id = al.usuario_id
                {$where}
            ");
            $stmtCount->execute($binds);
            $total = (int)$stmtCount->fetchColumn();

            // Dados
            $stmt = $this->db->prepare("
                SELECT a.id, a.aluno_id, a.matricula_id, a.valor, a.tipo_cobranca,
                       a.data_inicio, a.data_fim, a.proxima_cobranca, a.ultima_cobranca,
                       a.gateway_assinatura_id as mp_preapproval_id,
                       a.external_reference, a.payment_url, a.status_gateway,
                       a.criado_em,
                       s.codigo as status_codigo, s.nome as status_nome, s.cor as status_cor,
                       f.nome as ciclo_nome, f.meses as ciclo_meses,
                       g.nome as gateway_nome,
                       p.nome as plano_nome,
                       mo.nome as modalidade_nome,
                       COALESCE(al.nome, u.nome) as aluno_nome,
                       u.email as aluno_email
                FROM assinaturas a
                LEFT JOIN assinatura_status s ON s.id = a.status_id
                LEFT JOIN assinatura_frequencias f ON f.id = a.frequencia_id
                LEFT JOIN assinatura_gateways g ON g.id = a.gateway_id
                LEFT JOIN planos p ON p.id = a.plano_id
                LEFT JOIN modalidades mo ON mo.id = p.modalidade_id
                LEFT JOIN alunos al ON al.id = a.aluno_id
                LEFT JOIN usuarios u ON u.id = al.usuario_id
                {$where}
                ORDER BY a.criado_em DESC
                LIMIT {$perPage} OFFSET {$offset}
            ");
            $stmt->execute($binds);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $assinaturas = array_map(function ($row) {
                return [
                    'id' => (int)$row['id'],
                    'aluno_id' => (int)$row['aluno_id'],
                    'aluno_nome' => $row['aluno_nome'] ?? '',
                    'aluno_email' => $row['aluno_email'] ?? '',
                    'matricula_id' => $row['matricula_id'] ? (int)$row['matricula_id'] : null,
                    'valor' => (float)$row['valor'],
                    'tipo_cobranca' => $row['tipo_cobranca'] ?? 'recorrente',
                    'status' => [
                        'codigo' => $row['status_codigo'] ?? $row['status_gateway'] ?? 'pendente',
                        'nome' => $row['status_nome'] ?? $this->getStatusLabel($row['status_gateway'] ?? 'pendente'),
                        'cor' => $row['status_cor'] ?? '#FFA500'
                    ],
                    'status_gateway' => $row['status_gateway'],
                    'plano_nome' => $row['plano_nome'] ?? '',
                    'modalidade_nome' => $row['modalidade_nome'] ?? '',
                    'ciclo' => [
                        'nome' => $row['ciclo_nome'] ?? 'Mensal',
                        'meses' => (int)($row['ciclo_meses'] ?? 1)
                    ],
                    'gateway' => $row['gateway_nome'] ?? 'Mercado Pago',
                    'data_inicio' => $row['data_inicio'],
                    'data_fim' => $row['data_fim'],
                    'proxima_cobranca' => $row['proxima_cobranca'],
                    'ultima_cobranca' => $row['ultima_cobranca'],
                    'external_reference' => $row['external_reference'],
                    'mp_preapproval_id' => $row['mp_preapproval_id'],
                    'payment_url' => $row['payment_url'],
                    'criado_em' => $row['criado_em']
                ];
            }, $rows);

            $totalPages = (int)ceil($total / $perPage);

            $response->getBody()->write(json_encode([
                'success' => true,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
                'assinaturas' => $assinaturas
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("[AssinaturaController::listarAssinaturasAdmin] Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao listar assinaturas',
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
            'vencida' => 'Vencida',
            default => ucfirst($status)
        };
    }
}
