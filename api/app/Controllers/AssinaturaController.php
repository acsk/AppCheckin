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
                SELECT id FROM assinaturas_mercadopago 
                WHERE aluno_id = ? AND tenant_id = ? AND status IN ('pending', 'authorized')
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
                
                // Salvar assinatura no banco
                $stmtAssinatura = $this->db->prepare("
                    INSERT INTO assinaturas_mercadopago
                    (tenant_id, matricula_id, aluno_id, plano_ciclo_id,
                     mp_preapproval_id, mp_payer_id, status, valor,
                     dia_cobranca, data_inicio, proxima_cobranca)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $diaCobranca = (int) date('d');
                $proximaCobranca = date('Y-m-d', strtotime("+{$ciclo['meses']} months"));
                
                $stmtAssinatura->execute([
                    $tenantId,
                    $matriculaId,
                    $aluno['aluno_id'],
                    $ciclo['id'],
                    $resultadoMP['id'] ?? null,
                    $resultadoMP['payer_id'] ?? null,
                    $resultadoMP['status'] ?? 'pending',
                    $ciclo['valor'],
                    $diaCobranca,
                    $dataInicio,
                    $proximaCobranca
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
                SELECT asm.*, a.usuario_id
                FROM assinaturas_mercadopago asm
                INNER JOIN alunos a ON a.id = asm.aluno_id
                WHERE asm.id = ? AND asm.tenant_id = ?
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
            
            if ($assinatura['status'] === 'cancelled') {
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
            $stmtUpdate = $this->db->prepare("
                UPDATE assinaturas_mercadopago
                SET status = 'cancelled',
                    motivo_cancelamento = ?,
                    cancelado_por = ?,
                    data_cancelamento = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmtUpdate->execute([
                $data['motivo'] ?? 'Cancelado pelo usuário',
                $usuarioId,
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
            
            $stmt = $this->db->prepare("
                SELECT 
                    asm.id,
                    asm.status,
                    asm.valor,
                    asm.data_inicio,
                    asm.proxima_cobranca,
                    asm.ultima_cobranca,
                    pc.nome as ciclo_nome,
                    pc.meses as ciclo_meses,
                    p.nome as plano_nome,
                    m.nome as modalidade_nome
                FROM assinaturas_mercadopago asm
                INNER JOIN alunos a ON a.id = asm.aluno_id
                INNER JOIN plano_ciclos pc ON pc.id = asm.plano_ciclo_id
                INNER JOIN planos p ON p.id = pc.plano_id
                LEFT JOIN modalidades m ON m.id = p.modalidade_id
                WHERE a.usuario_id = ? AND asm.tenant_id = ?
                ORDER BY asm.created_at DESC
            ");
            $stmt->execute([$usuarioId, $tenantId]);
            $assinaturas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Formatar
            foreach ($assinaturas as &$ass) {
                $ass['id'] = (int) $ass['id'];
                $ass['valor'] = (float) $ass['valor'];
                $ass['valor_formatado'] = 'R$ ' . number_format($ass['valor'], 2, ',', '.');
                $ass['ciclo_meses'] = (int) $ass['ciclo_meses'];
                $ass['status_label'] = $this->getStatusLabel($ass['status']);
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
            'pending' => 'Pendente',
            'authorized' => 'Ativa',
            'paused' => 'Pausada',
            'cancelled' => 'Cancelada',
            'finished' => 'Finalizada',
            default => $status
        };
    }
}
