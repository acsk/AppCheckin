<?php

namespace App\Controllers;

use App\Models\TenantFormaPagamento;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TenantFormaPagamentoController
{
    private TenantFormaPagamento $model;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->model = new TenantFormaPagamento($db);
    }

    /**
     * Listar formas de pagamento configuradas do tenant
     */
    public function listar(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $apenasAtivas = $request->getQueryParams()['apenas_ativas'] ?? false;

        $formas = $this->model->listar($tenantId, (bool) $apenasAtivas);

        $response->getBody()->write(json_encode([
            'formas_pagamento' => $formas
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Buscar configuração específica
     */
    public function buscar(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $id = (int) $args['id'];

        $forma = $this->model->buscar($id, $tenantId);

        if (!$forma) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Configuração não encontrada'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $response->getBody()->write(json_encode($forma, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Atualizar configuração
     */
    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $id = (int) $args['id'];
        $data = $request->getParsedBody();

        // Validações
        $errors = [];

        if (!isset($data['taxa_percentual']) || $data['taxa_percentual'] < 0) {
            $errors[] = 'Taxa percentual deve ser maior ou igual a zero';
        }

        if (!isset($data['taxa_fixa']) || $data['taxa_fixa'] < 0) {
            $errors[] = 'Taxa fixa deve ser maior ou igual a zero';
        }

        if ($data['aceita_parcelamento'] ?? false) {
            if (empty($data['parcelas_maximas']) || $data['parcelas_maximas'] < 1) {
                $errors[] = 'Parcelas máximas deve ser maior que zero';
            }
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'errors' => $errors
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $sucesso = $this->model->atualizar($id, $tenantId, [
            'ativo' => $data['ativo'] ?? 1,
            'taxa_percentual' => $data['taxa_percentual'],
            'taxa_fixa' => $data['taxa_fixa'],
            'aceita_parcelamento' => $data['aceita_parcelamento'] ?? 0,
            'parcelas_minimas' => $data['parcelas_minimas'] ?? 1,
            'parcelas_maximas' => $data['parcelas_maximas'] ?? 1,
            'juros_parcelamento' => $data['juros_parcelamento'] ?? 0.00,
            'parcelas_sem_juros' => $data['parcelas_sem_juros'] ?? 1,
            'dias_compensacao' => $data['dias_compensacao'] ?? 0,
            'valor_minimo' => $data['valor_minimo'] ?? 0.00,
            'observacoes' => $data['observacoes'] ?? null
        ]);

        if ($sucesso) {
            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Configuração atualizada com sucesso'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $response->getBody()->write(json_encode([
            'type' => 'error',
            'message' => 'Erro ao atualizar configuração'
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Calcular taxas (sem parcelamento)
     */
    public function calcularTaxas(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();

        $formaPagamentoId = $data['forma_pagamento_id'] ?? null;
        $valorBruto = $data['valor'] ?? 0;

        if (!$formaPagamentoId || !$valorBruto) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Forma de pagamento e valor são obrigatórios'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $calculo = $this->model->calcularValorLiquido($tenantId, $formaPagamentoId, $valorBruto);

        $response->getBody()->write(json_encode($calculo, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Calcular parcelas com juros
     */
    public function calcularParcelas(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $data = $request->getParsedBody();

        $formaPagamentoId = $data['forma_pagamento_id'] ?? null;
        $valorTotal = $data['valor'] ?? 0;
        $numeroParcelas = $data['parcelas'] ?? 1;

        if (!$formaPagamentoId || !$valorTotal) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Forma de pagamento e valor são obrigatórios'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $calculo = $this->model->calcularParcelas($tenantId, $formaPagamentoId, $valorTotal, $numeroParcelas);

        if (isset($calculo['erro'])) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => $calculo['erro']
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $response->getBody()->write(json_encode($calculo, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Listar apenas formas de pagamento ativas (para uso público no tenant)
     */
    public function listarAtivas(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        
        $formas = $this->model->listar($tenantId, true); // apenas ativas

        $response->getBody()->write(json_encode([
            'formas' => $formas
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
