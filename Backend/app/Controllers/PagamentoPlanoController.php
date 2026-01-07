<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\PagamentoPlano;
use App\Models\Plano;

class PagamentoPlanoController
{
    /**
     * Listar pagamentos de uma matrícula
     * GET /admin/matriculas/{id}/pagamentos
     */
    public function listarPorMatricula(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $matriculaId = (int) $args['id'];
        
        $db = require __DIR__ . '/../../config/database.php';
        $pagamentoModel = new PagamentoPlano($db);
        
        try {
            $pagamentos = $pagamentoModel->listarPorMatricula($tenantId, $matriculaId);
            
            $response->getBody()->write(json_encode([
                'pagamentos' => $pagamentos
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Listar pagamentos de um usuário/aluno
     * GET /admin/usuarios/{id}/pagamentos
     */
    public function listarPorUsuario(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $usuarioId = (int) $args['id'];
        $queryParams = $request->getQueryParams();
        
        $db = require __DIR__ . '/../../config/database.php';
        $pagamentoModel = new PagamentoPlano($db);
        
        try {
            $filtros = [];
            if (!empty($queryParams['status_pagamento_id'])) {
                $filtros['status_pagamento_id'] = $queryParams['status_pagamento_id'];
            }
            
            $pagamentos = $pagamentoModel->listarPorUsuario($tenantId, $usuarioId, $filtros);
            
            $response->getBody()->write(json_encode([
                'pagamentos' => $pagamentos
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Listar todos os pagamentos
     * GET /admin/pagamentos-plano
     */
    public function index(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $queryParams = $request->getQueryParams();
        
        $db = require __DIR__ . '/../../config/database.php';
        $pagamentoModel = new PagamentoPlano($db);
        
        try {
            $filtros = [];
            if (!empty($queryParams['status_pagamento_id'])) {
                $filtros['status_pagamento_id'] = $queryParams['status_pagamento_id'];
            }
            if (!empty($queryParams['usuario_id'])) {
                $filtros['usuario_id'] = $queryParams['usuario_id'];
            }
            if (!empty($queryParams['data_inicio'])) {
                $filtros['data_inicio'] = $queryParams['data_inicio'];
            }
            if (!empty($queryParams['data_fim'])) {
                $filtros['data_fim'] = $queryParams['data_fim'];
            }
            
            $pagamentos = $pagamentoModel->listarTodos($tenantId, $filtros);
            
            $response->getBody()->write(json_encode([
                'pagamentos' => $pagamentos
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Resumo financeiro
     * GET /admin/pagamentos-plano/resumo
     */
    public function resumo(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $queryParams = $request->getQueryParams();
        
        $db = require __DIR__ . '/../../config/database.php';
        $pagamentoModel = new PagamentoPlano($db);
        
        try {
            $filtros = [];
            if (!empty($queryParams['data_inicio'])) {
                $filtros['data_inicio'] = $queryParams['data_inicio'];
            }
            if (!empty($queryParams['data_fim'])) {
                $filtros['data_fim'] = $queryParams['data_fim'];
            }
            
            $resumo = $pagamentoModel->resumo($tenantId, $filtros);
            
            $response->getBody()->write(json_encode([
                'resumo' => $resumo
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Buscar pagamento por ID
     * GET /admin/pagamentos-plano/{id}
     */
    public function buscar(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $pagamentoId = (int) $args['id'];
        
        $db = require __DIR__ . '/../../config/database.php';
        $pagamentoModel = new PagamentoPlano($db);
        
        try {
            $pagamento = $pagamentoModel->buscarPorId($tenantId, $pagamentoId);
            
            if (!$pagamento) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Pagamento não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            $response->getBody()->write(json_encode([
                'pagamento' => $pagamento
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Criar pagamento manualmente
     * POST /admin/matriculas/{id}/pagamentos
     */
    public function criar(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $adminId = $request->getAttribute('usuario_id');
        $matriculaId = (int) $args['id'];
        $data = $request->getParsedBody();
        
        $db = require __DIR__ . '/../../config/database.php';
        $pagamentoModel = new PagamentoPlano($db);
        
        // Validações
        $errors = [];
        if (empty($data['valor']) || !is_numeric($data['valor']) || $data['valor'] <= 0) {
            $errors[] = 'Valor inválido';
        }
        if (empty($data['data_vencimento'])) {
            $errors[] = 'Data de vencimento é obrigatória';
        }
        if (empty($data['usuario_id'])) {
            $errors[] = 'ID do aluno é obrigatório';
        }
        if (empty($data['plano_id'])) {
            $errors[] = 'ID do plano é obrigatório';
        }
        
        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => implode(', ', $errors)
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }
        
        try {
            $pagamentoData = [
                'tenant_id' => $tenantId,
                'matricula_id' => $matriculaId,
                'usuario_id' => $data['usuario_id'],
                'plano_id' => $data['plano_id'],
                'valor' => $data['valor'],
                'data_vencimento' => $data['data_vencimento'],
                'data_pagamento' => $data['data_pagamento'] ?? null,
                'status_pagamento_id' => $data['status_pagamento_id'] ?? 1,
                'forma_pagamento_id' => $data['forma_pagamento_id'] ?? null,
                'comprovante' => $data['comprovante'] ?? null,
                'observacoes' => $data['observacoes'] ?? null,
                'criado_por' => $adminId
            ];
            
            $pagamentoId = $pagamentoModel->criar($pagamentoData);
            $pagamento = $pagamentoModel->buscarPorId($tenantId, $pagamentoId);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Pagamento criado com sucesso',
                'pagamento' => $pagamento
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Confirmar pagamento (dar baixa)
     * POST /admin/pagamentos-plano/{id}/confirmar
     */
    public function confirmar(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $adminId = $request->getAttribute('usuario_id');
        $pagamentoId = (int) $args['id'];
        $data = $request->getParsedBody();
        
        $db = require __DIR__ . '/../../config/database.php';
        $pagamentoModel = new PagamentoPlano($db);
        $planoModel = new Plano($db);
        
        try {
            $db->beginTransaction();
            
            // Buscar pagamento
            $pagamento = $pagamentoModel->buscarPorId($tenantId, $pagamentoId);
            if (!$pagamento) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Pagamento não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Confirmar o pagamento
            $pagamentoModel->confirmarPagamento(
                $tenantId,
                $pagamentoId,
                $adminId,
                $data['data_pagamento'] ?? null,
                $data['forma_pagamento_id'] ?? null,
                $data['comprovante'] ?? null,
                $data['observacoes'] ?? null
            );
            
            // Buscar informações do plano para calcular próximo vencimento
            $plano = $planoModel->buscarPorId($tenantId, $pagamento['plano_id']);
            
            if ($plano && $plano['duracao_dias'] > 0) {
                // Calcular próxima data de vencimento
                $dataVencimentoAtual = new \DateTime($pagamento['data_vencimento']);
                $proximaDataVencimento = clone $dataVencimentoAtual;
                $proximaDataVencimento->modify("+{$plano['duracao_dias']} days");
                
                // Verificar se já existe pagamento para esta data
                $jaExiste = $pagamentoModel->existePagamentoParaData(
                    $tenantId,
                    $pagamento['matricula_id'],
                    $proximaDataVencimento->format('Y-m-d')
                );
                
                // Se não existe, criar o próximo pagamento automaticamente
                if (!$jaExiste) {
                    $proximoPagamento = [
                        'tenant_id' => $tenantId,
                        'matricula_id' => $pagamento['matricula_id'],
                        'usuario_id' => $pagamento['usuario_id'],
                        'plano_id' => $pagamento['plano_id'],
                        'valor' => $plano['valor'], // Usar valor atual do plano
                        'data_vencimento' => $proximaDataVencimento->format('Y-m-d'),
                        'status_pagamento_id' => 1, // Aguardando
                        'observacoes' => 'Pagamento gerado automaticamente após confirmação',
                        'criado_por' => $adminId
                    ];
                    $pagamentoModel->criar($proximoPagamento);
                }
            }
            
            $db->commit();
            
            // Buscar pagamento atualizado
            $pagamentoAtualizado = $pagamentoModel->buscarPorId($tenantId, $pagamentoId);
            
            // Buscar todos os pagamentos da matrícula
            $todosPagamentos = $pagamentoModel->listarPorMatricula($tenantId, $pagamento['matricula_id']);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Pagamento confirmado com sucesso. Próximo pagamento gerado automaticamente.',
                'pagamento' => $pagamentoAtualizado,
                'pagamentos' => $todosPagamentos
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $db->rollBack();
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Cancelar pagamento
     * DELETE /admin/pagamentos-plano/{id}
     */
    public function cancelar(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $pagamentoId = (int) $args['id'];
        $data = $request->getParsedBody();
        
        $db = require __DIR__ . '/../../config/database.php';
        $pagamentoModel = new PagamentoPlano($db);
        
        try {
            $pagamento = $pagamentoModel->buscarPorId($tenantId, $pagamentoId);
            if (!$pagamento) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Pagamento não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            $pagamentoModel->cancelar(
                $tenantId,
                $pagamentoId,
                $data['observacoes'] ?? 'Pagamento cancelado'
            );
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Pagamento cancelado com sucesso'
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Marcar pagamentos atrasados
     * POST /admin/pagamentos-plano/marcar-atrasados
     */
    public function marcarAtrasados(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        
        $db = require __DIR__ . '/../../config/database.php';
        $pagamentoModel = new PagamentoPlano($db);
        
        try {
            $total = $pagamentoModel->marcarAtrasados($tenantId);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => "Total de {$total} pagamento(s) marcado(s) como atrasado(s)",
                'total' => $total
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
