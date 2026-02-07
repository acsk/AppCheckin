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
            $tenantId = $request->getAttribute('tenantId');
            $usuarioId = $request->getAttribute('usuarioId');
            
            error_log("[AssinaturaController::minhasAssinaturas] tenant_id={$tenantId}, usuario_id={$usuarioId}");
            
            // Buscar aluno_id do usuário
            $stmtAluno = $this->db->prepare("SELECT id FROM alunos WHERE usuario_id = ? AND tenant_id = ?");
            $stmtAluno->execute([$usuarioId, $tenantId]);
            $aluno = $stmtAluno->fetch(\PDO::FETCH_ASSOC);
            
            error_log("[AssinaturaController::minhasAssinaturas] aluno encontrado: " . json_encode($aluno));
            
            if (!$aluno) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'assinaturas' => [],
                    'total' => 0
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }
            
            // Verificar qual tabela existe (nova ou antiga)
            $assinaturas = [];
            
            // Tentar nova tabela primeiro
            try {
                $stmtCheck = $this->db->query("SELECT 1 FROM assinaturas LIMIT 1");
                $usarNovaTabela = true;
            } catch (\Exception $e) {
                $usarNovaTabela = false;
            }
            
            if ($usarNovaTabela) {
                // Query na nova tabela assinaturas
                $stmt = $this->db->prepare("
                    SELECT 
                        a.id,
                        COALESCE(s.codigo, 'pendente') as status,
                        COALESCE(s.nome, 'Pendente') as status_nome,
                        COALESCE(s.cor, '#FFA500') as status_cor,
                        a.valor,
                        a.data_inicio,
                        a.proxima_cobranca,
                        a.ultima_cobranca,
                        a.gateway_assinatura_id as mp_preapproval_id,
                        COALESCE(f.nome, 'Mensal') as ciclo_nome,
                        COALESCE(f.meses, 1) as ciclo_meses,
                        COALESCE(g.nome, 'Mercado Pago') as gateway_nome,
                        p.nome as plano_nome,
                        mo.nome as modalidade_nome
                    FROM assinaturas a
                    LEFT JOIN assinatura_status s ON s.id = a.status_id
                    LEFT JOIN assinatura_frequencias f ON f.id = a.frequencia_id
                    LEFT JOIN assinatura_gateways g ON g.id = a.gateway_id
                    LEFT JOIN planos p ON p.id = a.plano_id
                    LEFT JOIN modalidades mo ON mo.id = p.modalidade_id
                    WHERE a.aluno_id = ? AND a.tenant_id = ?
                    ORDER BY a.criado_em DESC
                ");
                $stmt->execute([$aluno['id'], $tenantId]);
                $assinaturas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
            } else {
                // Fallback: Query na tabela antiga assinaturas_mercadopago
                $stmt = $this->db->prepare("
                    SELECT 
                        asm.id,
                        asm.status,
                        CASE asm.status
                            WHEN 'authorized' THEN 'Ativa'
                            WHEN 'pending' THEN 'Pendente'
                            WHEN 'paused' THEN 'Pausada'
                            WHEN 'cancelled' THEN 'Cancelada'
                            ELSE asm.status
                        END as status_nome,
                        CASE asm.status
                            WHEN 'authorized' THEN '#28A745'
                            WHEN 'pending' THEN '#FFA500'
                            WHEN 'paused' THEN '#6C757D'
                            WHEN 'cancelled' THEN '#DC3545'
                            ELSE '#6C757D'
                        END as status_cor,
                        asm.valor,
                        asm.data_inicio,
                        asm.proxima_cobranca,
                        asm.ultima_cobranca,
                        asm.mp_preapproval_id,
                        'Mensal' as ciclo_nome,
                        1 as ciclo_meses,
                        'Mercado Pago' as gateway_nome,
                        p.nome as plano_nome,
                        mo.nome as modalidade_nome
                    FROM assinaturas_mercadopago asm
                    LEFT JOIN matriculas mat ON mat.id = asm.matricula_id
                    LEFT JOIN planos p ON p.id = mat.plano_id
                    LEFT JOIN modalidades mo ON mo.id = p.modalidade_id
                    WHERE asm.aluno_id = ? AND asm.tenant_id = ?
                    ORDER BY asm.created_at DESC
                ");
                $stmt->execute([$aluno['id'], $tenantId]);
                $assinaturas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
            
            error_log("[AssinaturaController::minhasAssinaturas] assinaturas encontradas: " . count($assinaturas));
            
            // Formatar
            foreach ($assinaturas as &$ass) {
                $ass['id'] = (int) $ass['id'];
                $ass['valor'] = (float) $ass['valor'];
                $ass['valor_formatado'] = 'R$ ' . number_format($ass['valor'], 2, ',', '.');
                $ass['ciclo_meses'] = (int) ($ass['ciclo_meses'] ?? 1);
                $ass['status_label'] = $ass['status_nome'] ?? $this->getStatusLabel($ass['status']);
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'assinaturas' => $assinaturas,
                'total' => count($assinaturas)
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
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
