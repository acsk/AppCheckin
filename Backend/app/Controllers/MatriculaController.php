<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Usuario;
use App\Models\Plano;

class MatriculaController
{
    /**
     * Criar nova matrícula
     */
    public function criar(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId', 1);
        $adminId = $request->getAttribute('userId', null);
        $data = $request->getParsedBody();
        $db = require __DIR__ . '/../../config/database.php';

        // Validações
        $errors = [];
        
        if (empty($data['usuario_id'])) {
            $errors[] = 'Aluno é obrigatório';
        }
        
        if (empty($data['plano_id'])) {
            $errors[] = 'Plano é obrigatório';
        }
        
        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $usuarioId = $data['usuario_id'];
        $planoId = $data['plano_id'];
        
        // Buscar aluno
        $stmtUsuario = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND tenant_id = ? AND role_id = 1");
        $stmtUsuario->execute([$usuarioId, $tenantId]);
        $usuario = $stmtUsuario->fetch();
        
        if (!$usuario) {
            $response->getBody()->write(json_encode(['error' => 'Aluno não encontrado']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Buscar plano
        $stmtPlano = $db->prepare("SELECT * FROM planos WHERE id = ? AND tenant_id = ?");
        $stmtPlano->execute([$planoId, $tenantId]);
        $plano = $stmtPlano->fetch();
        
        if (!$plano) {
            $response->getBody()->write(json_encode(['error' => 'Plano não encontrado']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Verificar se já existe matrícula ativa
        $stmtAtiva = $db->prepare("
            SELECT * FROM matriculas 
            WHERE usuario_id = ? AND tenant_id = ? AND status = 'ativa'
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmtAtiva->execute([$usuarioId, $tenantId]);
        $matriculaAtiva = $stmtAtiva->fetch();

        // VALIDAÇÃO: Impedir alteração de plano se estiver ativo e dentro do período
        if ($matriculaAtiva && $matriculaAtiva['plano_id'] != $planoId) {
            // Verificar se a matrícula está dentro do período de validade
            $dataVencimentoMatricula = $matriculaAtiva['data_vencimento'];
            $hoje = date('Y-m-d');
            
            if ($dataVencimentoMatricula >= $hoje) {
                // Verificar se aluno tem pagamento ativo
                $stmtPagamentoAtivo = $db->prepare("
                    SELECT COUNT(*) as tem_pagamento FROM contas_receber 
                    WHERE usuario_id = ? 
                    AND tenant_id = ?
                    AND status = 'pago'
                    AND data_vencimento <= CURDATE()
                    AND DATE_ADD(data_vencimento, INTERVAL intervalo_dias DAY) >= CURDATE()
                ");
                $stmtPagamentoAtivo->execute([$usuarioId, $tenantId]);
                $pagamentoAtivo = $stmtPagamentoAtivo->fetch();
                
                if ($pagamentoAtivo && $pagamentoAtivo['tem_pagamento'] > 0) {
                    $dataVencimentoFormatada = date('d/m/Y', strtotime($dataVencimentoMatricula));
                    $response->getBody()->write(json_encode([
                        'error' => "Não é possível alterar o plano enquanto o aluno estiver ativo. O plano atual vence em {$dataVencimentoFormatada}. Aguarde o vencimento ou cancele a matrícula atual."
                    ]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
            }
        }

        // Determinar datas e motivo
        $dataMatricula = date('Y-m-d');
        $dataInicio = $data['data_inicio'] ?? $dataMatricula;
        $dataVencimento = date('Y-m-d', strtotime($dataInicio . " +{$plano['duracao_dias']} days"));
        $valor = $data['valor'] ?? $plano['valor'];
        $motivo = $data['motivo'] ?? 'nova';
        $matriculaAnteriorId = null;
        $planoAnteriorId = null;

        // Se já tem matrícula ativa, determinar motivo
        if ($matriculaAtiva) {
            $planoAnteriorId = $matriculaAtiva['plano_id'];
            $matriculaAnteriorId = $matriculaAtiva['id'];
            
            if ($planoId == $planoAnteriorId) {
                $motivo = 'renovacao';
            } else {
                $planoAnterior = $db->prepare("SELECT * FROM planos WHERE id = ?");
                $planoAnterior->execute([$planoAnteriorId]);
                $planoAnt = $planoAnterior->fetch();
                
                if ($planoAnt) {
                    $motivo = $plano['valor'] > $planoAnt['valor'] ? 'upgrade' : 'downgrade';
                }
            }
            
            // Finalizar matrícula anterior
            $stmtFinalizar = $db->prepare("
                UPDATE matriculas 
                SET status = 'finalizada', updated_at = NOW()
                WHERE id = ?
            ");
            $stmtFinalizar->execute([$matriculaAtiva['id']]);
        }

        // Criar matrícula
        $stmtInsert = $db->prepare("
            INSERT INTO matriculas 
            (tenant_id, usuario_id, plano_id, data_matricula, data_inicio, data_vencimento, 
             valor, status, motivo, matricula_anterior_id, plano_anterior_id, observacoes, criado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'ativa', ?, ?, ?, ?, ?)
        ");
        
        $stmtInsert->execute([
            $tenantId,
            $usuarioId,
            $planoId,
            $dataMatricula,
            $dataInicio,
            $dataVencimento,
            $valor,
            $motivo,
            $matriculaAnteriorId,
            $planoAnteriorId,
            $data['observacoes'] ?? null,
            $adminId
        ]);
        
        $matriculaId = (int) $db->lastInsertId();

        // Atualizar dados do aluno
        $stmtUpdateUsuario = $db->prepare("
            UPDATE usuarios 
            SET plano_id = ?, data_vencimento_plano = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdateUsuario->execute([$planoId, $dataVencimento, $usuarioId]);

        // Registrar no histórico de planos
        $stmtHistorico = $db->prepare("
            INSERT INTO historico_planos 
            (usuario_id, plano_anterior_id, plano_novo_id, data_inicio, 
             data_vencimento, valor_pago, motivo, observacoes, criado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmtHistorico->execute([
            $usuarioId,
            $planoAnteriorId,
            $planoId,
            $dataInicio,
            $dataVencimento,
            $valor,
            $motivo,
            $data['observacoes'] ?? null,
            $adminId
        ]);
        
        $historicoId = (int) $db->lastInsertId();

        // Criar conta a receber
        $referenciaMes = date('Y-m', strtotime($dataInicio));
        $stmtConta = $db->prepare("
            INSERT INTO contas_receber 
            (tenant_id, usuario_id, plano_id, historico_plano_id, valor, data_vencimento, 
             status, referencia_mes, recorrente, intervalo_dias, criado_por)
            VALUES (?, ?, ?, ?, ?, ?, 'pendente', ?, true, ?, ?)
        ");
        
        $stmtConta->execute([
            $tenantId,
            $usuarioId,
            $planoId,
            $historicoId,
            $valor,
            $dataInicio, // primeira conta vence na data de início
            $referenciaMes,
            $plano['duracao_dias'],
            $adminId
        ]);
        
        $contaId = (int) $db->lastInsertId();

        // Buscar matrícula criada
        $stmtMatricula = $db->prepare("
            SELECT 
                m.*,
                u.nome as aluno_nome,
                u.email as aluno_email,
                p.nome as plano_nome,
                p.duracao_dias,
                admin.nome as criado_por_nome
            FROM matriculas m
            INNER JOIN usuarios u ON m.usuario_id = u.id
            INNER JOIN planos p ON m.plano_id = p.id
            LEFT JOIN usuarios admin ON m.criado_por = admin.id
            WHERE m.id = ?
        ");
        $stmtMatricula->execute([$matriculaId]);
        $matricula = $stmtMatricula->fetch();
        
        // Buscar conta criada
        $stmtConta = $db->prepare("
            SELECT * FROM contas_receber WHERE id = ?
        ");
        $stmtConta->execute([$contaId]);
        $conta = $stmtConta->fetch();

        $response->getBody()->write(json_encode([
            'message' => 'Matrícula realizada com sucesso',
            'matricula' => $matricula,
            'conta_criada' => $conta
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    /**
     * Listar matrículas
     */
    public function listar(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId', 1);
        $params = $request->getQueryParams();
        $db = require __DIR__ . '/../../config/database.php';
        
        $sql = "
            SELECT 
                m.*,
                u.nome as aluno_nome,
                u.email as aluno_email,
                p.nome as plano_nome,
                p.valor as plano_valor,
                p.duracao_dias,
                admin_criou.nome as criado_por_nome
            FROM matriculas m
            INNER JOIN usuarios u ON m.usuario_id = u.id
            INNER JOIN planos p ON m.plano_id = p.id
            LEFT JOIN usuarios admin_criou ON m.criado_por = admin_criou.id
            WHERE m.tenant_id = ?
        ";
        
        $executeParams = [$tenantId];
        
        if (isset($params['usuario_id'])) {
            $sql .= " AND m.usuario_id = ?";
            $executeParams[] = $params['usuario_id'];
        }
        
        if (isset($params['status'])) {
            $sql .= " AND m.status = ?";
            $executeParams[] = $params['status'];
        }
        
        $sql .= " ORDER BY m.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($executeParams);
        $matriculas = $stmt->fetchAll();
        
        $response->getBody()->write(json_encode([
            'matriculas' => $matriculas,
            'total' => count($matriculas)
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Cancelar matrícula
     */
    public function cancelar(Request $request, Response $response, array $args): Response
    {
        $matriculaId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);
        $adminId = $request->getAttribute('userId', null);
        $data = $request->getParsedBody();
        $db = require __DIR__ . '/../../config/database.php';
        
        $stmt = $db->prepare("SELECT * FROM matriculas WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$matriculaId, $tenantId]);
        $matricula = $stmt->fetch();
        
        if (!$matricula) {
            $response->getBody()->write(json_encode(['error' => 'Matrícula não encontrada']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        if ($matricula['status'] === 'cancelada') {
            $response->getBody()->write(json_encode(['error' => 'Matrícula já está cancelada']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $motivoCancelamento = $data['motivo_cancelamento'] ?? 'Cancelado pelo admin';
        
        $stmtUpdate = $db->prepare("
            UPDATE matriculas 
            SET status = 'cancelada',
                cancelado_por = ?,
                data_cancelamento = CURDATE(),
                motivo_cancelamento = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmtUpdate->execute([$adminId, $motivoCancelamento, $matriculaId]);
        
        // Remover plano do aluno
        $stmtUpdateUsuario = $db->prepare("
            UPDATE usuarios 
            SET plano_id = NULL, data_vencimento_plano = NULL
            WHERE id = ?
        ");
        $stmtUpdateUsuario->execute([$matricula['usuario_id']]);
        
        $response->getBody()->write(json_encode([
            'message' => 'Matrícula cancelada com sucesso'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Dar baixa em conta a receber
     */
    public function darBaixaConta(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId', 1);
        $adminId = $request->getAttribute('userId', null);
        $contaId = (int) $args['id'];
        $data = $request->getParsedBody();
        $db = require __DIR__ . '/../../config/database.php';
        
        // Buscar conta
        $stmt = $db->prepare("SELECT * FROM contas_receber WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$contaId, $tenantId]);
        $conta = $stmt->fetch();
        
        if (!$conta) {
            $response->getBody()->write(json_encode(['error' => 'Conta não encontrada']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        if ($conta['status'] === 'pago') {
            $response->getBody()->write(json_encode(['error' => 'Conta já está paga']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $dataPagamento = $data['data_pagamento'] ?? date('Y-m-d');
        $formaPagamentoId = $data['forma_pagamento_id'] ?? null;
        $observacoes = $data['observacoes'] ?? null;
        
        // Calcular desconto se houver forma de pagamento
        $valorLiquido = $conta['valor'];
        $valorDesconto = 0;
        
        if ($formaPagamentoId) {
            $stmtForma = $db->prepare("SELECT percentual_desconto FROM formas_pagamento WHERE id = ? AND ativo = 1");
            $stmtForma->execute([$formaPagamentoId]);
            $formaPagamento = $stmtForma->fetch();
            
            if ($formaPagamento && $formaPagamento['percentual_desconto'] > 0) {
                $valorDesconto = ($conta['valor'] * $formaPagamento['percentual_desconto']) / 100;
                $valorLiquido = $conta['valor'] - $valorDesconto;
            }
        }
        
        // Atualizar conta para paga
        $stmtUpdate = $db->prepare("
            UPDATE contas_receber 
            SET status = 'pago',
                data_pagamento = ?,
                forma_pagamento_id = ?,
                valor_liquido = ?,
                valor_desconto = ?,
                observacoes = ?,
                baixa_por = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmtUpdate->execute([
            $dataPagamento,
            $formaPagamentoId,
            $valorLiquido,
            $valorDesconto,
            $observacoes,
            $adminId,
            $contaId
        ]);
        
        // Se é recorrente, criar próxima conta
        if ($conta['recorrente']) {
            $proximaDataVencimento = date('Y-m-d', strtotime($conta['data_vencimento'] . ' + ' . $conta['intervalo_dias'] . ' days'));
            $proximaReferencia = date('Y-m', strtotime($proximaDataVencimento));
            
            $stmtProxima = $db->prepare("
                INSERT INTO contas_receber 
                (tenant_id, usuario_id, plano_id, historico_plano_id, valor, data_vencimento, 
                 status, referencia_mes, recorrente, intervalo_dias, conta_origem_id, criado_por)
                VALUES (?, ?, ?, ?, ?, ?, 'pendente', ?, true, ?, ?, ?)
            ");
            
            $stmtProxima->execute([
                $tenantId,
                $conta['usuario_id'],
                $conta['plano_id'],
                $conta['historico_plano_id'],
                $conta['valor'],
                $proximaDataVencimento,
                $proximaReferencia,
                $conta['intervalo_dias'],
                $contaId,
                $adminId
            ]);
            
            $proximaContaId = (int) $db->lastInsertId();
            
            // Atualizar referência na conta atual
            $stmtUpdateRef = $db->prepare("UPDATE contas_receber SET proxima_conta_id = ? WHERE id = ?");
            $stmtUpdateRef->execute([$proximaContaId, $contaId]);
        }
        
        // Buscar conta atualizada
        $stmt->execute([$contaId, $tenantId]);
        $contaAtualizada = $stmt->fetch();
        
        $response->getBody()->write(json_encode([
            'message' => 'Baixa realizada com sucesso',
            'conta' => $contaAtualizada
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
