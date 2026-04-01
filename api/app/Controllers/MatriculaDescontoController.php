<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\MatriculaDesconto;

class MatriculaDescontoController
{
    /**
     * Listar descontos de uma matrícula
     * GET /admin/matriculas/{id}/descontos
     */
    public function listar(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $matriculaId = (int) $args['id'];

        $db = require __DIR__ . '/../../config/database.php';
        $model = new MatriculaDesconto($db);

        try {
            $descontos = $model->listarPorMatricula($tenantId, $matriculaId);

            $response->getBody()->write(json_encode([
                'descontos' => $descontos
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error', 'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Buscar desconto por ID
     * GET /admin/matricula-descontos/{id}
     */
    public function buscar(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $id = (int) $args['id'];

        $db = require __DIR__ . '/../../config/database.php';
        $model = new MatriculaDesconto($db);

        try {
            $desconto = $model->buscarPorId($tenantId, $id);
            if (!$desconto) {
                $response->getBody()->write(json_encode([
                    'type' => 'error', 'message' => 'Desconto não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $response->getBody()->write(json_encode([
                'desconto' => $desconto
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error', 'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Criar desconto para uma matrícula
     * POST /admin/matriculas/{id}/descontos
     */
    public function criar(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $adminId = $request->getAttribute('userId') ?? $request->getAttribute('usuario_id');
        $matriculaId = (int) $args['id'];
        $data = $request->getParsedBody();

        // Validações
        $errors = [];
        if (empty($data['tipo']) || !in_array($data['tipo'], ['primeira_mensalidade', 'recorrente'])) {
            $errors[] = 'Tipo deve ser "primeira_mensalidade" ou "recorrente"';
        }
        if (empty($data['valor']) && empty($data['percentual'])) {
            $errors[] = 'Informe valor (R$) ou percentual (%)';
        }
        if (!empty($data['valor']) && !empty($data['percentual'])) {
            $errors[] = 'Informe apenas valor OU percentual, não ambos';
        }
        if (!empty($data['valor']) && (!is_numeric($data['valor']) || (float) $data['valor'] <= 0)) {
            $errors[] = 'Valor deve ser numérico e positivo';
        }
        if (!empty($data['percentual']) && (!is_numeric($data['percentual']) || (float) $data['percentual'] <= 0 || (float) $data['percentual'] > 100)) {
            $errors[] = 'Percentual deve estar entre 0.01 e 100';
        }
        if (empty($data['motivo'])) {
            $errors[] = 'Motivo é obrigatório';
        }
        if (!empty($data['vigencia_fim']) && !empty($data['vigencia_inicio']) && $data['vigencia_fim'] < $data['vigencia_inicio']) {
            $errors[] = 'Vigência fim não pode ser anterior à vigência início';
        }

        if (!empty($errors)) {
            $response->getBody()->write(json_encode([
                'type' => 'error', 'message' => implode(', ', $errors)
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $db = require __DIR__ . '/../../config/database.php';
        $model = new MatriculaDesconto($db);

        try {
            // Verificar se matrícula existe
            $stmtMat = $db->prepare("SELECT id FROM matriculas WHERE id = ? AND tenant_id = ?");
            $stmtMat->execute([$matriculaId, $tenantId]);
            if (!$stmtMat->fetchColumn()) {
                $response->getBody()->write(json_encode([
                    'type' => 'error', 'message' => 'Matrícula não encontrada'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $id = $model->criar([
                'tenant_id' => $tenantId,
                'matricula_id' => $matriculaId,
                'tipo' => $data['tipo'],
                'valor' => $data['valor'] ?? null,
                'percentual' => $data['percentual'] ?? null,
                'vigencia_inicio' => $data['vigencia_inicio'] ?? date('Y-m-d'),
                'vigencia_fim' => $data['vigencia_fim'] ?? null,
                'parcelas_restantes' => $data['parcelas_restantes'] ?? null,
                'motivo' => $data['motivo'],
                'criado_por' => $adminId,
                'autorizado_por' => $data['autorizado_por'] ?? null,
            ]);

            $desconto = $model->buscarPorId($tenantId, $id);

            // Recalcular descontos nos pagamentos pendentes da matrícula
            $pagamentosAtualizados = $model->recalcularDescontosPendentes($tenantId, $matriculaId);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Desconto criado com sucesso',
                'desconto' => $desconto,
                'pagamentos_atualizados' => $pagamentosAtualizados
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error', 'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Atualizar desconto
     * PUT /admin/matricula-descontos/{id}
     */
    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $id = (int) $args['id'];
        $data = $request->getParsedBody();

        $db = require __DIR__ . '/../../config/database.php';
        $model = new MatriculaDesconto($db);

        try {
            $desconto = $model->buscarPorId($tenantId, $id);
            if (!$desconto) {
                $response->getBody()->write(json_encode([
                    'type' => 'error', 'message' => 'Desconto não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Validações opcionais
            if (isset($data['valor']) && isset($data['percentual']) && $data['valor'] && $data['percentual']) {
                $response->getBody()->write(json_encode([
                    'type' => 'error', 'message' => 'Informe apenas valor OU percentual, não ambos'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
            }

            // Se informar valor, limpar percentual e vice-versa
            $updateData = $data;
            if (isset($data['valor']) && $data['valor']) {
                $updateData['percentual'] = null;
            } elseif (isset($data['percentual']) && $data['percentual']) {
                $updateData['valor'] = null;
            }

            $ok = $model->atualizar($tenantId, $id, $updateData);
            if (!$ok) {
                $response->getBody()->write(json_encode([
                    'type' => 'error', 'message' => 'Nenhum campo para atualizar'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
            }

            $descontoAtualizado = $model->buscarPorId($tenantId, $id);

            // Recalcular descontos nos pagamentos pendentes da matrícula
            $pagamentosAtualizados = $model->recalcularDescontosPendentes($tenantId, (int) $desconto['matricula_id']);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Desconto atualizado',
                'desconto' => $descontoAtualizado,
                'pagamentos_atualizados' => $pagamentosAtualizados
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error', 'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Desativar desconto (soft delete)
     * DELETE /admin/matricula-descontos/{id}
     */
    public function desativar(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $id = (int) $args['id'];

        $db = require __DIR__ . '/../../config/database.php';
        $model = new MatriculaDesconto($db);

        try {
            $desconto = $model->buscarPorId($tenantId, $id);
            if (!$desconto) {
                $response->getBody()->write(json_encode([
                    'type' => 'error', 'message' => 'Desconto não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $model->desativar($tenantId, $id);

            // Recalcular descontos nos pagamentos pendentes (remover desconto desativado)
            $pagamentosAtualizados = $model->recalcularDescontosPendentes($tenantId, (int) $desconto['matricula_id']);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Desconto desativado com sucesso',
                'pagamentos_atualizados' => $pagamentosAtualizados
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error', 'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
