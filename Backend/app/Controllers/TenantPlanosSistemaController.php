<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\TenantPlano;
use App\Models\Tenant;
use App\Models\PlanoSistema;

/**
 * Controller para gerenciar Contratos (TenantPlanosSistema)
 * Associação de academias (tenants) com planos do sistema
 */
class TenantPlanosSistemaController
{
    private TenantPlano $tenantPlanoModel;
    private Tenant $tenantModel;
    private PlanoSistema $planoSistemaModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->tenantPlanoModel = new TenantPlano($db);
        $this->tenantModel = new Tenant($db);
        $this->planoSistemaModel = new PlanoSistema($db);
    }

    /**
     * Listar todos os contratos
     * GET /contratos
     */
    public function index(Request $request, Response $response): Response
    {
        $contratos = $this->tenantPlanoModel->listarTodos();

        $response->getBody()->write(json_encode([
            'contratos' => $contratos,
            'total' => count($contratos)
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Buscar contratos de uma academia específica
     * GET /academias/{tenantId}/contratos
     */
    public function contratosPorAcademia(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int) $args['tenantId'];

        // Verificar se academia existe
        $academia = $this->tenantModel->findById($tenantId);
        if (!$academia) {
            $response->getBody()->write(json_encode([
                'error' => 'Academia não encontrada'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $contratos = $this->tenantPlanoModel->listarPorTenant($tenantId);

        $response->getBody()->write(json_encode([
            'academia' => [
                'id' => $academia['id'],
                'nome' => $academia['nome']
            ],
            'contratos' => $contratos,
            'total' => count($contratos)
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Buscar contrato ativo de uma academia
     * GET /academias/{tenantId}/contrato-ativo
     */
    public function contratoAtivo(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int) $args['tenantId'];

        $contrato = $this->tenantPlanoModel->buscarContratoAtivo($tenantId);

        if (!$contrato) {
            $response->getBody()->write(json_encode([
                'message' => 'Academia não possui contrato ativo',
                'type' => 'warning',
                'contrato' => null
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }

        $response->getBody()->write(json_encode($contrato, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Buscar contrato por ID
     * GET /contratos/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $contratoId = (int) $args['id'];

        $contrato = $this->tenantPlanoModel->buscarPorId($contratoId);
        if (!$contrato) {
            $response->getBody()->write(json_encode([
                'error' => 'Contrato não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'contrato' => $contrato
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Associar plano do sistema a uma academia (criar contrato)
     * POST /academias/{tenantId}/contratos
     * 
     * REGRA: Uma academia só pode ter UM contrato ativo por vez
     */
    public function associarPlano(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int) $args['tenantId'];
        $data = $request->getParsedBody();

        // Verificar se academia existe
        $academia = $this->tenantModel->findById($tenantId);
        if (!$academia) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Academia não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Verificar se já existe contrato ativo
        $contratoAtivo = $this->tenantPlanoModel->buscarContratoAtivo($tenantId);
        if ($contratoAtivo) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Esta academia já possui um contrato ativo',
                'contrato_ativo' => [
                    'id' => $contratoAtivo['id'],
                    'plano' => $contratoAtivo['plano_nome'],
                    'data_inicio' => $contratoAtivo['data_inicio'],
                    'data_vencimento' => $contratoAtivo['data_vencimento']
                ],
                'sugestao' => 'Use o endpoint POST /academias/{id}/trocar-plano para trocar de plano, ou cancele/desative o contrato atual'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }

        // Validações
        $errors = [];
        if (empty($data['plano_sistema_id'])) {
            $errors[] = 'Plano do sistema é obrigatório';
        } else {
            // Verificar se plano existe
            $plano = $this->planoSistemaModel->buscarPorId($data['plano_sistema_id']);
            if (!$plano) {
                $errors[] = 'Plano do sistema não encontrado';
            } elseif (!$plano['ativo']) {
                $errors[] = 'Este plano não está ativo';
            } elseif (!$plano['atual']) {
                // Permitir criar contrato com plano não-atual, mas avisar
                // Isso é útil para manter contratos existentes mesmo quando o plano não está mais disponível
            }
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
            // Calcular data de vencimento baseado na duração do plano
            $plano = $this->planoSistemaModel->buscarPorId($data['plano_sistema_id']);
            $dataInicio = $data['data_inicio'] ?? date('Y-m-d');

            // Criar novo contrato
            $contratoData = [
                'tenant_id' => $tenantId,
                'plano_sistema_id' => $data['plano_sistema_id'],
                'status_id' => 2, // 2 = Pendente (aguardando primeiro pagamento)
                'data_inicio' => $dataInicio,
                'observacoes' => $data['observacoes'] ?? null
            ];

            $contratoId = $this->tenantPlanoModel->criar($contratoData);
            
            // Criar primeiro pagamento com status "Aguardando"
            $db = require __DIR__ . '/../../config/database.php';
            $pagamentoModel = new \App\Models\PagamentoContrato($db);
            
            $pagamentoData = [
                'contrato_id' => $contratoId,
                'valor' => $plano['valor'],
                'data_vencimento' => $dataInicio,
                'status_pagamento_id' => 1, // 1 = Aguardando
                'forma_pagamento_id' => $data['forma_pagamento_id'],
                'observacoes' => 'Primeiro pagamento do contrato'
            ];
            
            $pagamentoModel->criar($pagamentoData);
            
            $contrato = $this->tenantPlanoModel->buscarPorId($contratoId);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Contrato criado com sucesso',
                'contrato' => $contrato
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }

    /**
     * Trocar plano de uma academia
     * POST /academias/{tenantId}/trocar-plano
     */
    public function trocarPlano(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int) $args['tenantId'];
        $data = $request->getParsedBody();

        // Verificar se academia existe
        $academia = $this->tenantModel->findById($tenantId);
        if (!$academia) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Academia não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Validações
        $errors = [];
        if (empty($data['plano_sistema_id'])) {
            $errors[] = 'Plano do sistema é obrigatório';
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
            $resultado = $this->tenantPlanoModel->trocarPlano(
                $tenantId,
                $data['plano_sistema_id'],
                $data['forma_pagamento_id'],
                $data['observacoes'] ?? 'Troca de plano'
            );

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Plano trocado com sucesso',
                'contrato' => $resultado
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao trocar plano: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Renovar contrato
     * POST /contratos/{id}/renovar
     */
    public function renovar(Request $request, Response $response, array $args): Response
    {
        $contratoId = (int) $args['id'];
        $data = $request->getParsedBody();

        $contrato = $this->tenantPlanoModel->buscarPorId($contratoId);
        if (!$contrato) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Contrato não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        try {
            $novaDataVencimento = $data['data_vencimento'] ?? date('Y-m-d', strtotime($contrato['data_vencimento'] . ' +30 days'));
            
            $this->tenantPlanoModel->renovar($contratoId, $novaDataVencimento);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Contrato renovado com sucesso',
                'nova_data_vencimento' => $novaDataVencimento
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao renovar contrato: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Cancelar contrato
     * DELETE /contratos/{id}
     */
    public function cancelar(Request $request, Response $response, array $args): Response
    {
        $contratoId = (int) $args['id'];

        $contrato = $this->tenantPlanoModel->buscarPorId($contratoId);
        if (!$contrato) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Contrato não encontrado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $this->tenantPlanoModel->cancelar($contratoId);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Contrato cancelado com sucesso'
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Listar contratos próximos do vencimento
     * GET /contratos/proximos-vencimento?dias=7
     */
    public function proximosVencimento(Request $request, Response $response): Response
    {
        $dias = (int) ($request->getQueryParams()['dias'] ?? 7);
        $contratos = $this->tenantPlanoModel->proximosVencimento($dias);

        $response->getBody()->write(json_encode([
            'contratos' => $contratos,
            'total' => count($contratos),
            'dias' => $dias
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Listar contratos vencidos
     * GET /contratos/vencidos
     */
    public function vencidos(Request $request, Response $response): Response
    {
        $contratos = $this->tenantPlanoModel->vencidos();

        $response->getBody()->write(json_encode([
            'contratos' => $contratos,
            'total' => count($contratos)
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
