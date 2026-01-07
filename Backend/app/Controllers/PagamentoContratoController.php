<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\PagamentoContrato;
use App\Models\TenantPlano;

class PagamentoContratoController
{
    private PagamentoContrato $pagamentoModel;
    private TenantPlano $contratoModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->pagamentoModel = new PagamentoContrato($db);
        $this->contratoModel = new TenantPlano($db);
    }

    /**
     * Listar todos os pagamentos
     * GET /superadmin/pagamentos
     */
    public function index(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        
        $filtros = [];
        if (!empty($queryParams['status_pagamento_id'])) {
            $filtros['status_pagamento_id'] = $queryParams['status_pagamento_id'];
        }
        if (!empty($queryParams['tenant_id'])) {
            $filtros['tenant_id'] = $queryParams['tenant_id'];
        }
        if (!empty($queryParams['data_inicio'])) {
            $filtros['data_inicio'] = $queryParams['data_inicio'];
        }
        if (!empty($queryParams['data_fim'])) {
            $filtros['data_fim'] = $queryParams['data_fim'];
        }
        
        $pagamentos = $this->pagamentoModel->listarTodos($filtros);
        
        $response->getBody()->write(json_encode([
            'pagamentos' => $pagamentos
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Resumo de pagamentos
     * GET /superadmin/pagamentos/resumo
     */
    public function resumo(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        
        $filtros = [];
        if (!empty($queryParams['tenant_id'])) {
            $filtros['tenant_id'] = $queryParams['tenant_id'];
        }
        if (!empty($queryParams['data_inicio'])) {
            $filtros['data_inicio'] = $queryParams['data_inicio'];
        }
        if (!empty($queryParams['data_fim'])) {
            $filtros['data_fim'] = $queryParams['data_fim'];
        }
        
        $resumo = $this->pagamentoModel->resumo($filtros);
        
        $response->getBody()->write(json_encode([
            'resumo' => $resumo
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Listar pagamentos de um contrato
     * GET /superadmin/contratos/{id}/pagamentos
     */
    public function listarPorContrato(Request $request, Response $response, array $args): Response
    {
        $contratoId = (int) $args['id'];
        
        $pagamentos = $this->pagamentoModel->listarPorContrato($contratoId);
        
        $response->getBody()->write(json_encode([
            'pagamentos' => $pagamentos
        ], JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Criar novo pagamento para um contrato
     * POST /superadmin/contratos/{id}/pagamentos
     */
    public function criar(Request $request, Response $response, array $args): Response
    {
        $contratoId = (int) $args['id'];
        $data = $request->getParsedBody();
        
        // Verificar se contrato existe
        $contrato = $this->contratoModel->buscarPorId($contratoId);
        if (!$contrato) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Contrato não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        // Validações
        $errors = [];
        if (empty($data['valor']) || !is_numeric($data['valor']) || $data['valor'] <= 0) {
            $errors[] = 'Valor inválido';
        }
        if (empty($data['data_vencimento'])) {
            $errors[] = 'Data de vencimento é obrigatória';
        }
        if (empty($data['forma_pagamento_id'])) {
            $errors[] = 'Forma de pagamento é obrigatória';
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
                'tenant_plano_id' => $contratoId,
                'valor' => $data['valor'],
                'data_vencimento' => $data['data_vencimento'],
                'data_pagamento' => $data['data_pagamento'] ?? null,
                'status_pagamento_id' => $data['status_pagamento_id'] ?? 1,
                'forma_pagamento_id' => $data['forma_pagamento_id'],
                'comprovante' => $data['comprovante'] ?? null,
                'observacoes' => $data['observacoes'] ?? null
            ];
            
            $pagamentoId = $this->pagamentoModel->criar($pagamentoData);
            $pagamento = $this->pagamentoModel->buscarPorId($pagamentoId);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Pagamento registrado com sucesso',
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
     * Confirmar pagamento
     * POST /superadmin/pagamentos/{id}/confirmar
     */
    public function confirmar(Request $request, Response $response, array $args): Response
    {
        $pagamentoId = (int) $args['id'];
        $data = $request->getParsedBody();
        
        $pagamento = $this->pagamentoModel->buscarPorId($pagamentoId);
        if (!$pagamento) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Pagamento não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        try {
            $this->pagamentoModel->confirmarPagamento(
                $pagamentoId,
                $data['data_pagamento'] ?? null,
                $data['forma_pagamento_id'] ?? null,
                $data['comprovante'] ?? null,
                $data['observacoes'] ?? null
            );
            
            // Verificar se deve desbloquear o contrato
            $temPendentes = $this->pagamentoModel->temPagamentosPendentes($pagamento['tenant_plano_id']);
            if (!$temPendentes) {
                // Desbloquear contrato (status_id = 1 - Ativo)
                $this->contratoModel->atualizarStatus($pagamento['tenant_plano_id'], 1);
            }
            
            // Gerar próximo pagamento automaticamente
            $dataVencimentoAtual = new \DateTime($pagamento['data_vencimento']);
            $proximaDataVencimento = clone $dataVencimentoAtual;
            $proximaDataVencimento->modify('+1 month');
            
            // Verificar se já existe pagamento para esta data
            $pagamentos = $this->pagamentoModel->listarPorContrato($pagamento['tenant_plano_id']);
            $jaExisteProximo = false;
            foreach ($pagamentos as $p) {
                if ($p['data_vencimento'] === $proximaDataVencimento->format('Y-m-d')) {
                    $jaExisteProximo = true;
                    break;
                }
            }
            
            // Se não existe, criar o próximo pagamento
            if (!$jaExisteProximo) {
                $proximoPagamento = [
                    'tenant_plano_id' => $pagamento['tenant_plano_id'],
                    'valor' => $pagamento['valor'],
                    'data_vencimento' => $proximaDataVencimento->format('Y-m-d'),
                    'status_pagamento_id' => 1, // Aguardando
                    'forma_pagamento' => $pagamento['forma_pagamento'],
                    'observacoes' => 'Pagamento gerado automaticamente após confirmação'
                ];
                $this->pagamentoModel->criar($proximoPagamento);
            }
            
            $pagamentoAtualizado = $this->pagamentoModel->buscarPorId($pagamentoId);
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Pagamento confirmado com sucesso',
                'pagamento' => $pagamentoAtualizado
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
     * Cancelar pagamento
     * DELETE /superadmin/pagamentos/{id}
     */
    public function cancelar(Request $request, Response $response, array $args): Response
    {
        $pagamentoId = (int) $args['id'];
        $data = $request->getParsedBody();
        
        $pagamento = $this->pagamentoModel->buscarPorId($pagamentoId);
        if (!$pagamento) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Pagamento não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        try {
            $this->pagamentoModel->cancelar($pagamentoId, $data['observacoes'] ?? null);
            
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
     * Marcar pagamentos atrasados e bloquear contratos
     * POST /superadmin/pagamentos/marcar-atrasados
     */
    public function marcarAtrasados(Request $request, Response $response): Response
    {
        try {
            $qtdAtrasados = $this->pagamentoModel->marcarAtrasados();
            
            // Buscar contratos com pagamentos atrasados e bloqueá-los
            $contratosBloqueados = $this->bloquearContratosComPagamentosAtrasados();
            
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => "Processamento concluído: {$qtdAtrasados} pagamento(s) marcado(s) como atrasado(s), {$contratosBloqueados} contrato(s) bloqueado(s)",
                'pagamentos_atrasados' => $qtdAtrasados,
                'contratos_bloqueados' => $contratosBloqueados
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
     * Bloquear contratos com pagamentos atrasados
     */
    private function bloquearContratosComPagamentosAtrasados(): int
    {
        $sql = "UPDATE tenant_planos_sistema c
                SET c.status_id = 4
                WHERE c.status_id = 1
                AND EXISTS (
                    SELECT 1 FROM pagamentos_contrato p
                    WHERE p.tenant_plano_id = c.id
                    AND p.status_pagamento_id = 3
                )";
        
        $stmt = $this->contratoModel->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->rowCount();
    }
}
