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
            $usuarioId = $request->getAttribute('usuarioId');
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
                
                // Criar primeiro pagamento como PAGO
                $stmtPagamento = $this->db->prepare("
                    INSERT INTO pagamentos_plano
                    (tenant_id, aluno_id, matricula_id, plano_id, valor, data_vencimento,
                     data_pagamento, status_pagamento_id, forma_pagamento_id, tipo_baixa_id,
                     observacoes, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), 2, 9, 4, 'Assinatura recorrente - Primeiro pagamento', NOW(), NOW())
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
            $usuarioId = $request->getAttribute('usuarioId');
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
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Assinatura cancelada com sucesso'
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
            $db = require __DIR__ . '/../../config/database.php';
            
            // Extrair JWT
            $tenantId = $request->getAttribute('tenantId');
            $usuarioId = $request->getAttribute('usuarioId');
            $alunoId = $request->getAttribute('alunoId');
            
            // DEBUG: Retornar todos os atributos do request
            $allAttributes = [];
            foreach (['tenantId', 'usuarioId', 'alunoId', 'isAdmin', 'papel_id'] as $attr) {
                $allAttributes[$attr] = $request->getAttribute($attr);
            }
            
            error_log("[AssinaturaController::minhasAssinaturas] Iniciando busca de assinaturas - tenant_id=$tenantId, usuario_id=$usuarioId, aluno_id=$alunoId");
            error_log("[AssinaturaController::minhasAssinaturas] Atributos do request: " . json_encode($allAttributes));
            
            // Se não tem aluno_id no JWT, buscar
            if (!$alunoId) {
                $stmtAluno = $db->prepare('SELECT id FROM alunos WHERE usuario_id = ? AND ativo = 1 LIMIT 1');
                $stmtAluno->execute([$usuarioId]);
                $aluno = $stmtAluno->fetch(\PDO::FETCH_ASSOC);
                $alunoId = $aluno['id'] ?? null;
                
                if (!$aluno) {
                    error_log("[AssinaturaController::minhasAssinaturas] Aluno não encontrado para usuario_id=$usuarioId");
                    $response->getBody()->write(json_encode([
                        'success' => true,
                        'assinaturas' => [],
                        'total' => 0
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
                }
            }
            
            error_log("[AssinaturaController::minhasAssinaturas] Aluno encontrado: " . json_encode($aluno ?? ['id' => $alunoId]));
            
            // Query para buscar assinaturas com todas as informações relacionadas
            $sql = "
                SELECT a.id, a.status_id, a.valor, a.data_inicio, a.proxima_cobranca, 
                       a.ultima_cobranca, a.gateway_assinatura_id as mp_preapproval_id,
                       s.codigo as status_codigo, s.nome as status_nome, s.cor as status_cor,
                       f.nome as ciclo_nome, f.meses as ciclo_meses, g.nome as gateway_nome,
                       p.nome as plano_nome, mo.nome as modalidade_nome
                FROM assinaturas a
                LEFT JOIN assinatura_status s ON s.id = a.status_id
                LEFT JOIN assinatura_frequencias f ON f.id = a.frequencia_id
                LEFT JOIN assinatura_gateways g ON g.id = a.gateway_id
                LEFT JOIN planos p ON p.id = a.plano_id
                LEFT JOIN modalidades mo ON mo.id = p.modalidade_id
                WHERE a.aluno_id = ? AND a.tenant_id = ?
                ORDER BY a.data_inicio DESC
            ";
            
            error_log("[AssinaturaController::minhasAssinaturas] Executando query com aluno_id=$alunoId, tenant_id=$tenantId");
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$alunoId, $tenantId]);
            $assinaturasRaw = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            error_log("[AssinaturaController::minhasAssinaturas] Assinaturas encontradas: " . count($assinaturasRaw ?? []) . " - Raw data: " . json_encode($assinaturasRaw));
            
            // Formatar resposta
            $assinaturas = [];
            foreach ($assinaturasRaw as $row) {
                $assinaturas[] = [
                    'id' => (int)$row['id'],
                    'status' => [
                        'id' => $row['status_id'],
                        'codigo' => $row['status_codigo'],
                        'nome' => $row['status_nome'],
                        'cor' => $row['status_cor']
                    ],
                    'valor' => (float)$row['valor'],
                    'data_inicio' => $row['data_inicio'],
                    'proxima_cobranca' => $row['proxima_cobranca'],
                    'ultima_cobranca' => $row['ultima_cobranca'],
                    'mp_preapproval_id' => $row['mp_preapproval_id'],
                    'ciclo' => [
                        'nome' => $row['ciclo_nome'],
                        'meses' => (int)($row['ciclo_meses'] ?? 0)
                    ],
                    'gateway' => [
                        'nome' => $row['gateway_nome']
                    ],
                    'plano' => [
                        'nome' => $row['plano_nome'],
                        'modalidade' => $row['modalidade_nome']
                    ]
                ];
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'assinaturas' => $assinaturas,
                'total' => count($assinaturas),
                '_debug' => [
                    'request_attributes' => $allAttributes,
                    'aluno_id_usado' => $alunoId,
                    'tenant_id_usado' => $tenantId,
                    'sql_query' => str_replace('?', '%s', $sql),
                    'sql_params' => [$alunoId, $tenantId]
                ]
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            
        } catch (\Exception $e) {
            error_log("[AssinaturaController::minhasAssinaturas] Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Erro ao listar assinaturas']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    private function getStatusLabel(string $status): string
    {
        return match($status) {
            'pendente', 'pending' => 'Pendente',
            'ativa', 'authorized' => 'Ativa',
            'pausada', 'paused' => 'Pausada',
            'cancelada', 'cancelled' => 'Cancelada',
            'expirada', 'finished' => 'Expirada',
            default => ucfirst($status)
        };
    }
}
