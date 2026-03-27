<?php

namespace App\Controllers;

use App\Models\CreditoAluno;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CreditoAlunoController
{
    /**
     * Listar créditos de um aluno
     */
    public function listarPorAluno(Request $request, Response $response, array $args): Response
    {
        $alunoId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);
        $db = require __DIR__ . '/../../config/database.php';

        $model = new CreditoAluno($db);
        $creditos = $model->listarPorAluno($tenantId, $alunoId);

        $response->getBody()->write(json_encode($creditos, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Saldo de créditos ativos de um aluno
     */
    public function saldo(Request $request, Response $response, array $args): Response
    {
        $alunoId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);
        $db = require __DIR__ . '/../../config/database.php';

        $model = new CreditoAluno($db);
        $saldo = $model->saldoTotal($tenantId, $alunoId);
        $ativos = $model->listarAtivos($tenantId, $alunoId);

        $response->getBody()->write(json_encode([
            'saldo_total' => $saldo,
            'creditos_ativos' => $ativos
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Criar crédito manual para um aluno
     */
    public function criar(Request $request, Response $response, array $args): Response
    {
        $alunoId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);
        $adminId = $request->getAttribute('userId', null);
        $data = $request->getParsedBody();

        if (empty($data['valor']) || (float) $data['valor'] <= 0) {
            $response->getBody()->write(json_encode(['error' => 'valor é obrigatório e deve ser maior que zero']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $db = require __DIR__ . '/../../config/database.php';
        $model = new CreditoAluno($db);

        $id = $model->criar([
            'tenant_id' => $tenantId,
            'aluno_id' => $alunoId,
            'matricula_origem_id' => $data['matricula_origem_id'] ?? null,
            'pagamento_origem_id' => $data['pagamento_origem_id'] ?? null,
            'valor' => (float) $data['valor'],
            'motivo' => $data['motivo'] ?? 'Crédito manual',
            'criado_por' => $adminId
        ]);

        $credito = $model->buscarPorId($tenantId, $id);

        $response->getBody()->write(json_encode([
            'message' => 'Crédito criado com sucesso',
            'credito' => $credito
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    /**
     * Cancelar crédito
     */
    public function cancelar(Request $request, Response $response, array $args): Response
    {
        $creditoId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);
        $db = require __DIR__ . '/../../config/database.php';

        $model = new CreditoAluno($db);
        $ok = $model->cancelar($tenantId, $creditoId);

        if (!$ok) {
            $response->getBody()->write(json_encode(['error' => 'Crédito não encontrado ou já utilizado/cancelado']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode(['message' => 'Crédito cancelado com sucesso']));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
