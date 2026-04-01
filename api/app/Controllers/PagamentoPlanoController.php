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
        if (isset($data['desconto']) && !is_numeric($data['desconto'])) {
            $errors[] = 'Desconto inválido';
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
                'desconto' => $data['desconto'] ?? 0.00,
                'motivo_desconto' => $data['motivo_desconto'] ?? null,
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
     * Atualizar pagamento
     * PUT /admin/pagamentos-plano/{id}
     */
    public function atualizar(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $pagamentoId = (int) $args['id'];
        $data = $request->getParsedBody();
        
        // Admin performing the action (fallbacks to different attribute names)
        $adminId = $request->getAttribute('userId') ?? $request->getAttribute('usuario_id');
        $db = require __DIR__ . '/../../config/database.php';
        $pagamentoModel = new PagamentoPlano($db);

        try {
            $pagamento = $pagamentoModel->buscarPorId($tenantId, $pagamentoId);
            if (!$pagamento) {
                $response->getBody()->write(json_encode(['type' => 'error', 'message' => 'Pagamento não encontrado'], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Campos permitidos
            $allowed = ['valor','desconto','motivo_desconto','data_vencimento','data_pagamento','status_pagamento_id','forma_pagamento_id','comprovante','observacoes'];
            $updateData = [];
            foreach ($allowed as $f) {
                if (array_key_exists($f, $data)) $updateData[$f] = $data[$f];
            }

            if (empty($updateData)) {
                $response->getBody()->write(json_encode(['type' => 'error', 'message' => 'Nenhum campo para atualizar'], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
            }

            // Verificar sequência: se status final é PAGO, checar se há parcela anterior pendente
            $statusFinal = isset($updateData['status_pagamento_id']) ? (int)$updateData['status_pagamento_id'] : (int)$pagamento['status_pagamento_id'];
            if ($statusFinal === 2 && empty($data['forcar'])) {
                // Check 1: parcela anterior não paga
                $stmtAntEditar = $db->prepare("
                    SELECT pp.id, pp.valor, pp.data_vencimento,
                           CASE pp.status_pagamento_id
                               WHEN 1 THEN 'Aguardando'
                               WHEN 3 THEN 'Atrasado'
                               ELSE CONCAT('Status_', pp.status_pagamento_id)
                           END AS status
                    FROM pagamentos_plano pp
                    WHERE pp.tenant_id = :tenant_id
                      AND pp.matricula_id = :matricula_id
                      AND pp.status_pagamento_id NOT IN (2, 4)
                      AND pp.id != :pagamento_id
                      AND pp.data_vencimento < :data_vencimento
                    ORDER BY pp.data_vencimento ASC
                    LIMIT 1
                ");
                $stmtAntEditar->execute([
                    'tenant_id' => $tenantId,
                    'matricula_id' => $pagamento['matricula_id'],
                    'pagamento_id' => $pagamentoId,
                    'data_vencimento' => $updateData['data_vencimento'] ?? $pagamento['data_vencimento']
                ]);
                $antEditar = $stmtAntEditar->fetch(\PDO::FETCH_ASSOC);

                if ($antEditar) {
                    $antEditarDataFmt = date('d/m/Y', strtotime($antEditar['data_vencimento']));
                    $response->getBody()->write(json_encode([
                        'type' => 'warning',
                        'message' => 'Existe uma parcela anterior (vencimento ' . $antEditarDataFmt . ') ainda não paga. Confirme as parcelas na sequência.',
                        'parcela_pendente' => [
                            'id' => (int) $antEditar['id'],
                            'valor' => $antEditar['valor'],
                            'data_vencimento' => $antEditar['data_vencimento'],
                            'status' => $antEditar['status']
                        ],
                        'confirmar_mesmo_assim' => 'Envie o parâmetro "forcar": true para confirmar fora da sequência'
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
                }

                // Check 2: já existe pagamento PAGO no mesmo mês
                $dataVencRef = $updateData['data_vencimento'] ?? $pagamento['data_vencimento'];
                $stmtDupMes = $db->prepare("
                    SELECT pp.id, pp.valor, pp.data_vencimento, pp.data_pagamento
                    FROM pagamentos_plano pp
                    WHERE pp.tenant_id = :tenant_id
                      AND pp.matricula_id = :matricula_id
                      AND pp.status_pagamento_id = 2
                      AND pp.id != :pagamento_id
                      AND YEAR(pp.data_vencimento) = YEAR(:data_vencimento)
                      AND MONTH(pp.data_vencimento) = MONTH(:data_vencimento2)
                    LIMIT 1
                ");
                $stmtDupMes->execute([
                    'tenant_id' => $tenantId,
                    'matricula_id' => $pagamento['matricula_id'],
                    'pagamento_id' => $pagamentoId,
                    'data_vencimento' => $dataVencRef,
                    'data_vencimento2' => $dataVencRef
                ]);
                $dupMes = $stmtDupMes->fetch(\PDO::FETCH_ASSOC);

                if ($dupMes) {
                    $dupMesDataFmt = date('d/m/Y', strtotime($dupMes['data_vencimento']));
                    $response->getBody()->write(json_encode([
                        'type' => 'warning',
                        'message' => 'Já existe um pagamento confirmado neste mês (vencimento ' . $dupMesDataFmt . '). Não é permitido dois pagamentos pagos no mesmo mês.',
                        'pagamento_existente' => [
                            'id' => (int) $dupMes['id'],
                            'valor' => $dupMes['valor'],
                            'data_pagamento' => $dupMes['data_pagamento'],
                            'data_vencimento' => $dupMes['data_vencimento']
                        ],
                        'confirmar_mesmo_assim' => 'Envie o parâmetro "forcar": true para confirmar mesmo assim'
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
                }
            }

            // Se estiver marcando como PAGO (2) e antes não estava pago, executar fluxo de confirmação (baixa)
            if (isset($updateData['status_pagamento_id']) && (int)$updateData['status_pagamento_id'] === 2 && (int)$pagamento['status_pagamento_id'] !== 2) {

                $db = require __DIR__ . '/../../config/database.php';
                $planoModel = new Plano($db, $tenantId);

                $db->beginTransaction();
                try {
                    // Aplicar descontos ao pagamento ATUAL antes de confirmar (caso não tenha sido aplicado na criação)
                    $descontoAtualEdit = (float) ($pagamento['desconto'] ?? 0);
                    if ($descontoAtualEdit == 0) {
                        $descontoModelAtualEdit = new \App\Models\MatriculaDesconto($db);

                        $stmtPrimeiraEdit = $db->prepare("
                            SELECT COUNT(*) FROM pagamentos_plano
                            WHERE tenant_id = ? AND matricula_id = ? AND id != ? AND data_vencimento < ?
                        ");
                        $stmtPrimeiraEdit->execute([$tenantId, $pagamento['matricula_id'], $pagamentoId, $pagamento['data_vencimento']]);
                        $isPrimeiraParcelaEdit = ((int) $stmtPrimeiraEdit->fetchColumn()) === 0;

                        $descontosAplicaveisEdit = $descontoModelAtualEdit->buscarAplicaveis(
                            $tenantId, (int) $pagamento['matricula_id'],
                            $pagamento['data_vencimento'], $isPrimeiraParcelaEdit
                        );
                        $infoDescontoAtualEdit = $descontoModelAtualEdit->calcularDesconto(
                            (float) $pagamento['valor'], $descontosAplicaveisEdit
                        );

                        if ($infoDescontoAtualEdit['desconto_total'] > 0) {
                            $valorComDescontoEdit = max(0, (float) $pagamento['valor'] - $infoDescontoAtualEdit['desconto_total']);
                            $stmtUpdDescEdit = $db->prepare("
                                UPDATE pagamentos_plano
                                SET valor = ?, valor_original = ?, desconto = ?, motivo_desconto = ?, updated_at = NOW()
                                WHERE id = ? AND tenant_id = ?
                            ");
                            $stmtUpdDescEdit->execute([
                                $valorComDescontoEdit,
                                (float) $pagamento['valor'],
                                $infoDescontoAtualEdit['desconto_total'],
                                $infoDescontoAtualEdit['motivos'],
                                $pagamentoId,
                                $tenantId
                            ]);

                            // Salvar descontos aplicados na tabela pivot
                            if (!empty($infoDescontoAtualEdit['detalhes'])) {
                                $descontoModelAtualEdit->salvarDescontosAplicados($pagamentoId, $infoDescontoAtualEdit['detalhes']);
                            }

                            if (!empty($infoDescontoAtualEdit['ids'])) {
                                $descontoModelAtualEdit->decrementarParcelas($infoDescontoAtualEdit['ids']);
                            }

                            $pagamento['valor'] = $valorComDescontoEdit;
                            error_log("[atualizar] Desconto R$" . $infoDescontoAtualEdit['desconto_total'] . " aplicado ao pagamento #{$pagamentoId}");
                        }
                    }

                    // Usar campos fornecidos quando existirem
                    $dataPagamento = $updateData['data_pagamento'] ?? null;
                    $formaPagamentoId = $updateData['forma_pagamento_id'] ?? null;
                    $comprovante = $updateData['comprovante'] ?? null;
                    $observacoes = $updateData['observacoes'] ?? null;

                    $pagamentoModel->confirmarPagamento(
                        $tenantId,
                        $pagamentoId,
                        (int)$adminId,
                        $dataPagamento,
                        $formaPagamentoId,
                        $comprovante,
                        $observacoes,
                        1
                    );

                    // Gerar próxima parcela seguindo a mesma regra de confirmar()
                    $plano = $planoModel->findById($pagamento['plano_id']);

                    // Buscar ciclo da matrícula e aluno_id (fallback se parcela não tiver)
                    $stmtMatCiclo = $db->prepare("
                        SELECT m.plano_ciclo_id, m.aluno_id, m.valor as matricula_valor,
                               pc.meses as ciclo_meses, af.meses as frequencia_meses
                        FROM matriculas m
                        LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
                        LEFT JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
                        WHERE m.id = ?
                    ");
                    $stmtMatCiclo->execute([$pagamento['matricula_id']]);
                    $matCicloInfo = $stmtMatCiclo->fetch(\PDO::FETCH_ASSOC);
                    $mesesCiclo = $matCicloInfo['ciclo_meses'] ?? $matCicloInfo['frequencia_meses'] ?? null;
                    $alunoIdProxima = $pagamento['aluno_id'] ?? $matCicloInfo['aluno_id'] ?? null;
                    // Valor da próxima parcela: usar valor da matrícula (valor cheio do plano/ciclo)
                    $valorProximaParcela = $matCicloInfo['matricula_valor'] ?? $plano['valor'];

                    if ($plano && ($mesesCiclo || $plano['duracao_dias'] > 0)) {
                        $dataVencimentoAtual = new \DateTime($pagamento['data_vencimento']);
                        $proximaDataVencimento = clone $dataVencimentoAtual;
                        if ($mesesCiclo) {
                            $proximaDataVencimento->modify("+{$mesesCiclo} months");
                        } else {
                            $proximaDataVencimento->modify("+{$plano['duracao_dias']} days");
                        }

                        $jaExiste = $pagamentoModel->existePagamentoParaData(
                            $tenantId,
                            $pagamento['matricula_id'],
                            $proximaDataVencimento->format('Y-m-d')
                        );

                        if (!$jaExiste) {
                            // Aplicar descontos recorrentes à próxima parcela
                            $descontoModel = new \App\Models\MatriculaDesconto($db);
                            $descontosAplicaveis = $descontoModel->buscarAplicaveis(
                                $tenantId, $pagamento['matricula_id'],
                                $proximaDataVencimento->format('Y-m-d'), false
                            );
                            $infoDesconto = $descontoModel->calcularDesconto((float) $valorProximaParcela, $descontosAplicaveis);

                            $proximoPagamento = [
                                'tenant_id' => $tenantId,
                                'aluno_id' => $alunoIdProxima,
                                'matricula_id' => $pagamento['matricula_id'],
                                'plano_id' => $pagamento['plano_id'],
                                'valor' => $valorProximaParcela,
                                'desconto' => $infoDesconto['desconto_total'],
                                'motivo_desconto' => $infoDesconto['motivos'] ?: null,
                                'data_vencimento' => $proximaDataVencimento->format('Y-m-d'),
                                'status_pagamento_id' => 1,
                                'observacoes' => 'Pagamento gerado automaticamente após confirmação',
                                'criado_por' => $adminId
                            ];
                            $novoPagId = $pagamentoModel->criar($proximoPagamento);

                            // Salvar descontos aplicados na tabela pivot
                            if (!empty($infoDesconto['detalhes'])) {
                                $descontoModel->salvarDescontosAplicados($novoPagId, $infoDesconto['detalhes']);
                            }

                            // Decrementar parcelas_restantes dos descontos usados
                            if (!empty($infoDesconto['ids'])) {
                                $descontoModel->decrementarParcelas($infoDesconto['ids']);
                            }
                        }

                        // Atualizar matrícula com a próxima data de vencimento
                        $stmtUpdMat = $db->prepare("UPDATE matriculas SET proxima_data_vencimento = ?, updated_at = NOW() WHERE id = ?");
                        $stmtUpdMat->execute([$proximaDataVencimento->format('Y-m-d'), $pagamento['matricula_id']]);
                    }

                    $db->commit();
                } catch (\Exception $e) {
                    $db->rollBack();
                    throw $e;
                }

                $pagamentoAtualizado = $pagamentoModel->buscarPorId($tenantId, $pagamentoId);
            } else {
                $ok = $pagamentoModel->atualizar($tenantId, $pagamentoId, $updateData);
                if (!$ok) throw new \Exception('Falha ao atualizar pagamento');

                $pagamentoAtualizado = $pagamentoModel->buscarPorId($tenantId, $pagamentoId);
            }

            // Sincronizar datas da matrícula com base nas parcelas reais
            $matriculaId = (int) $pagamento['matricula_id'];

            // data_inicio = vencimento da 1ª parcela não-cancelada
            $stmtMinVenc = $db->prepare("
                SELECT MIN(data_vencimento) as min_venc
                FROM pagamentos_plano
                WHERE tenant_id = ? AND matricula_id = ? AND status_pagamento_id != 4
            ");
            $stmtMinVenc->execute([$tenantId, $matriculaId]);
            $minVenc = $stmtMinVenc->fetchColumn();

            // proxima_data_vencimento = vencimento da próxima parcela pendente/atrasada
            $stmtProxVenc = $db->prepare("
                SELECT MIN(data_vencimento) as prox_venc
                FROM pagamentos_plano
                WHERE tenant_id = ? AND matricula_id = ? AND status_pagamento_id IN (1, 3)
            ");
            $stmtProxVenc->execute([$tenantId, $matriculaId]);
            $proxVenc = $stmtProxVenc->fetchColumn();

            // data_vencimento (acesso até) = proxima_data_vencimento quando há pendente,
            // senão = MAX(data_vencimento) dos pagos (último ciclo pago)
            $dataAcessoAte = $proxVenc;
            if (!$dataAcessoAte) {
                $stmtMaxPago = $db->prepare("
                    SELECT MAX(data_vencimento) as max_venc
                    FROM pagamentos_plano
                    WHERE tenant_id = ? AND matricula_id = ? AND status_pagamento_id = 2
                ");
                $stmtMaxPago->execute([$tenantId, $matriculaId]);
                $dataAcessoAte = $stmtMaxPago->fetchColumn();
            }

            if ($minVenc || $proxVenc || $dataAcessoAte) {
                $sqlSync = "UPDATE matriculas SET ";
                $paramsSync = [];
                $sets = [];

                if ($minVenc) {
                    $sets[] = "data_inicio = ?";
                    $paramsSync[] = $minVenc;
                }
                if ($dataAcessoAte) {
                    $sets[] = "data_vencimento = ?";
                    $paramsSync[] = $dataAcessoAte;
                }
                if ($proxVenc) {
                    $sets[] = "proxima_data_vencimento = ?";
                    $paramsSync[] = $proxVenc;
                }
                $sets[] = "updated_at = NOW()";

                $sqlSync .= implode(', ', $sets) . " WHERE id = ? AND tenant_id = ?";
                $paramsSync[] = $matriculaId;
                $paramsSync[] = $tenantId;

                $db->prepare($sqlSync)->execute($paramsSync);
            }

            // Recalcular status da matrícula baseado nos pagamentos
            $pagamentoModel->atualizarStatusMatricula($tenantId, $matriculaId);

            $response->getBody()->write(json_encode(['type' => 'success','message' => 'Pagamento atualizado','pagamento' => $pagamentoAtualizado], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['type' => 'error','message' => $e->getMessage()], JSON_UNESCAPED_UNICODE));
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
        $adminId = $request->getAttribute('userId'); // Corrigido: usar 'userId' conforme definido no AuthMiddleware
        $pagamentoId = (int) $args['id'];
        $data = $request->getParsedBody();
        
        // Validar que temos um adminId
        if (!$adminId) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Usuário não autenticado'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        
        $db = require __DIR__ . '/../../config/database.php';
        $pagamentoModel = new PagamentoPlano($db);
        $planoModel = new Plano($db, $tenantId);
        
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
            
            // Verificar se existem parcelas anteriores (por data_vencimento) ainda não pagas
            if (empty($data['forcar'])) {
                // Check 1: parcela anterior não paga
                $stmtAnterior = $db->prepare("
                    SELECT pp.id, pp.valor, pp.data_vencimento,
                           CASE pp.status_pagamento_id
                               WHEN 1 THEN 'Aguardando'
                               WHEN 3 THEN 'Atrasado'
                               ELSE CONCAT('Status_', pp.status_pagamento_id)
                           END AS status
                    FROM pagamentos_plano pp
                    WHERE pp.tenant_id = :tenant_id
                      AND pp.matricula_id = :matricula_id
                      AND pp.status_pagamento_id NOT IN (2, 4)
                      AND pp.id != :pagamento_id
                      AND pp.data_vencimento < :data_vencimento
                    ORDER BY pp.data_vencimento ASC
                    LIMIT 1
                ");
                $stmtAnterior->execute([
                    'tenant_id' => $tenantId,
                    'matricula_id' => $pagamento['matricula_id'],
                    'pagamento_id' => $pagamentoId,
                    'data_vencimento' => $pagamento['data_vencimento']
                ]);
                $parcelaAnterior = $stmtAnterior->fetch(\PDO::FETCH_ASSOC);

                if ($parcelaAnterior) {
                    $parcelaAntDataFmt = date('d/m/Y', strtotime($parcelaAnterior['data_vencimento']));
                    $db->rollBack();
                    $response->getBody()->write(json_encode([
                        'type' => 'warning',
                        'message' => 'Existe uma parcela anterior (vencimento ' . $parcelaAntDataFmt . ') ainda não paga. Confirme as parcelas na sequência.',
                        'parcela_pendente' => [
                            'id' => (int) $parcelaAnterior['id'],
                            'valor' => $parcelaAnterior['valor'],
                            'data_vencimento' => $parcelaAnterior['data_vencimento'],
                            'status' => $parcelaAnterior['status']
                        ],
                        'confirmar_mesmo_assim' => 'Envie o parâmetro "forcar": true para confirmar fora da sequência'
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
                }

                // Check 2: já existe pagamento PAGO no mesmo mês
                $stmtDupMesConf = $db->prepare("
                    SELECT pp.id, pp.valor, pp.data_vencimento, pp.data_pagamento
                    FROM pagamentos_plano pp
                    WHERE pp.tenant_id = :tenant_id
                      AND pp.matricula_id = :matricula_id
                      AND pp.status_pagamento_id = 2
                      AND pp.id != :pagamento_id
                      AND YEAR(pp.data_vencimento) = YEAR(:data_vencimento)
                      AND MONTH(pp.data_vencimento) = MONTH(:data_vencimento2)
                    LIMIT 1
                ");
                $stmtDupMesConf->execute([
                    'tenant_id' => $tenantId,
                    'matricula_id' => $pagamento['matricula_id'],
                    'pagamento_id' => $pagamentoId,
                    'data_vencimento' => $pagamento['data_vencimento'],
                    'data_vencimento2' => $pagamento['data_vencimento']
                ]);
                $dupMesConf = $stmtDupMesConf->fetch(\PDO::FETCH_ASSOC);

                if ($dupMesConf) {
                    $dupMesConfDataFmt = date('d/m/Y', strtotime($dupMesConf['data_vencimento']));
                    $db->rollBack();
                    $response->getBody()->write(json_encode([
                        'type' => 'warning',
                        'message' => 'Já existe um pagamento confirmado neste mês (vencimento ' . $dupMesConfDataFmt . '). Não é permitido dois pagamentos pagos no mesmo mês.',
                        'pagamento_existente' => [
                            'id' => (int) $dupMesConf['id'],
                            'valor' => $dupMesConf['valor'],
                            'data_pagamento' => $dupMesConf['data_pagamento'],
                            'data_vencimento' => $dupMesConf['data_vencimento']
                        ],
                        'confirmar_mesmo_assim' => 'Envie o parâmetro "forcar": true para confirmar mesmo assim'
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
                }
            }
            
            // Aplicar descontos ao pagamento ATUAL antes de confirmar (caso não tenha sido aplicado na criação)
            $descontoAtual = (float) ($pagamento['desconto'] ?? 0);
            if ($descontoAtual == 0) {
                $descontoModelAtual = new \App\Models\MatriculaDesconto($db);

                // Verificar se é a 1ª parcela (não existe parcela anterior)
                $stmtPrimeira = $db->prepare("
                    SELECT COUNT(*) FROM pagamentos_plano
                    WHERE tenant_id = ? AND matricula_id = ? AND id != ? AND data_vencimento < ?
                ");
                $stmtPrimeira->execute([$tenantId, $pagamento['matricula_id'], $pagamentoId, $pagamento['data_vencimento']]);
                $isPrimeiraParcela = ((int) $stmtPrimeira->fetchColumn()) === 0;

                $descontosAplicaveisAtual = $descontoModelAtual->buscarAplicaveis(
                    $tenantId, (int) $pagamento['matricula_id'],
                    $pagamento['data_vencimento'], $isPrimeiraParcela
                );
                $infoDescontoAtual = $descontoModelAtual->calcularDesconto(
                    (float) $pagamento['valor'], $descontosAplicaveisAtual
                );

                if ($infoDescontoAtual['desconto_total'] > 0) {
                    $valorAtualComDesconto = max(0, (float) $pagamento['valor'] - $infoDescontoAtual['desconto_total']);
                    $stmtUpdDesc = $db->prepare("
                        UPDATE pagamentos_plano
                        SET valor = ?, valor_original = ?, desconto = ?, motivo_desconto = ?, updated_at = NOW()
                        WHERE id = ? AND tenant_id = ?
                    ");
                    $stmtUpdDesc->execute([
                        $valorAtualComDesconto,
                        (float) $pagamento['valor'],
                        $infoDescontoAtual['desconto_total'],
                        $infoDescontoAtual['motivos'],
                        $pagamentoId,
                        $tenantId
                    ]);

                    // Salvar descontos aplicados na tabela pivot
                    if (!empty($infoDescontoAtual['detalhes'])) {
                        $descontoModelAtual->salvarDescontosAplicados($pagamentoId, $infoDescontoAtual['detalhes']);
                    }

                    // Decrementar parcelas_restantes dos descontos usados
                    if (!empty($infoDescontoAtual['ids'])) {
                        $descontoModelAtual->decrementarParcelas($infoDescontoAtual['ids']);
                    }

                    // Atualizar referência local para resposta
                    $pagamento['valor'] = $valorAtualComDesconto;
                    $pagamento['desconto'] = $infoDescontoAtual['desconto_total'];
                    $pagamento['motivo_desconto'] = $infoDescontoAtual['motivos'];

                    error_log("[confirmar] Desconto R$" . $infoDescontoAtual['desconto_total'] . " aplicado ao pagamento #{$pagamentoId}");
                }
            }

            // Confirmar o pagamento
            $pagamentoModel->confirmarPagamento(
                $tenantId,
                $pagamentoId,
                (int) $adminId,
                $data['data_pagamento'] ?? null,
                $data['forma_pagamento_id'] ?? null,
                $data['comprovante'] ?? null,
                $data['observacoes'] ?? null,
                1 // tipo_baixa_id = 1 (Manual)
            );
            
            // Garantir data_inicio na matrícula (primeira baixa)
            $stmtDataInicio = $db->prepare("
                UPDATE matriculas
                SET data_inicio = COALESCE(data_inicio, CURDATE()),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtDataInicio->execute([(int)$pagamento['matricula_id']]);

            // Buscar informações do plano para calcular próximo vencimento
            $plano = $planoModel->findById($pagamento['plano_id']);

            // Buscar ciclo da matrícula e aluno_id (fallback se parcela não tiver)
            $stmtMatCiclo = $db->prepare("
                SELECT m.plano_ciclo_id, m.aluno_id, m.valor as matricula_valor,
                       pc.meses as ciclo_meses, af.meses as frequencia_meses
                FROM matriculas m
                LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
                LEFT JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
                WHERE m.id = ?
            ");
            $stmtMatCiclo->execute([$pagamento['matricula_id']]);
            $matCicloInfo = $stmtMatCiclo->fetch(\PDO::FETCH_ASSOC);
            $mesesCiclo = $matCicloInfo['ciclo_meses'] ?? $matCicloInfo['frequencia_meses'] ?? null;
            $alunoIdProxima = $pagamento['aluno_id'] ?? $matCicloInfo['aluno_id'] ?? null;
            // Valor da próxima parcela: usar valor da matrícula (valor cheio do plano/ciclo)
            $valorProximaParcela = $matCicloInfo['matricula_valor'] ?? $plano['valor'];

            if ($plano && ($mesesCiclo || $plano['duracao_dias'] > 0)) {
                // Calcular próxima data sempre a partir do vencimento original da parcela
                $dataVencimentoAtual = new \DateTime($pagamento['data_vencimento']);
                $proximaDataVencimento = clone $dataVencimentoAtual;
                if ($mesesCiclo) {
                    $proximaDataVencimento->modify("+{$mesesCiclo} months");
                } else {
                    $proximaDataVencimento->modify("+{$plano['duracao_dias']} days");
                }
                
                // Verificar se já existe pagamento para esta data
                $jaExiste = $pagamentoModel->existePagamentoParaData(
                    $tenantId,
                    $pagamento['matricula_id'],
                    $proximaDataVencimento->format('Y-m-d')
                );
                
                // Se não existe, criar o próximo pagamento automaticamente
                if (!$jaExiste) {
                    // Aplicar descontos recorrentes à próxima parcela
                    $descontoModel = new \App\Models\MatriculaDesconto($db);
                    $descontosAplicaveis = $descontoModel->buscarAplicaveis(
                        $tenantId, $pagamento['matricula_id'],
                        $proximaDataVencimento->format('Y-m-d'), false
                    );
                    $infoDesconto = $descontoModel->calcularDesconto((float) $valorProximaParcela, $descontosAplicaveis);

                    $proximoPagamento = [
                        'tenant_id' => $tenantId,
                        'aluno_id' => $alunoIdProxima,
                        'matricula_id' => $pagamento['matricula_id'],
                        'plano_id' => $pagamento['plano_id'],
                        'valor' => $valorProximaParcela,
                        'desconto' => $infoDesconto['desconto_total'],
                        'motivo_desconto' => $infoDesconto['motivos'] ?: null,
                        'data_vencimento' => $proximaDataVencimento->format('Y-m-d'),
                        'status_pagamento_id' => 1, // Aguardando
                        'observacoes' => 'Pagamento gerado automaticamente após confirmação',
                        'criado_por' => $adminId
                    ];
                    $novoPagamentoId = $pagamentoModel->criar($proximoPagamento);

                    // Salvar descontos aplicados na tabela pivot
                    if (!empty($infoDesconto['detalhes'])) {
                        $descontoModel->salvarDescontosAplicados($novoPagamentoId, $infoDesconto['detalhes']);
                    }

                    // Decrementar parcelas_restantes dos descontos usados
                    if (!empty($infoDesconto['ids'])) {
                        $descontoModel->decrementarParcelas($infoDesconto['ids']);
                    }
                }

                // Atualizar matrícula com a próxima data de vencimento
                $stmtUpdMat = $db->prepare("UPDATE matriculas SET proxima_data_vencimento = ?, updated_at = NOW() WHERE id = ?");
                $stmtUpdMat->execute([$proximaDataVencimento->format('Y-m-d'), $pagamento['matricula_id']]);
            }
            
            $db->commit();
            
            // Recalcular status da matrícula baseado nos pagamentos
            $pagamentoModel->atualizarStatusMatricula($tenantId, (int)$pagamento['matricula_id']);

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
     * Excluir pagamento fisicamente
     * DELETE /admin/pagamentos-plano/{id}/excluir
     */
    public function excluir(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenant_id');
        $pagamentoId = (int) $args['id'];

        $db = require __DIR__ . '/../../config/database.php';
        $pagamentoModel = new PagamentoPlano($db);

        try {
            $pagamento = $pagamentoModel->buscarPorId($tenantId, $pagamentoId);
            if (!$pagamento) {
                $response->getBody()->write(json_encode(['type' => 'error','message' => 'Pagamento não encontrado'], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $ok = $pagamentoModel->excluir($tenantId, $pagamentoId);
            if (!$ok) throw new \Exception('Falha ao excluir pagamento');

            // Recalcular status da matrícula baseado nos pagamentos restantes
            $pagamentoModel->atualizarStatusMatricula($tenantId, (int)$pagamento['matricula_id']);

            $response->getBody()->write(json_encode(['type' => 'success','message' => 'Pagamento removido com sucesso'], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['type' => 'error','message' => $e->getMessage()], JSON_UNESCAPED_UNICODE));
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
