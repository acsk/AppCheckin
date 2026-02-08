<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

/**
 * Controller para gerenciar ciclos de planos
 * 
 * @package App\Controllers
 */
#[OA\Tag(name: "Ciclos de Planos", description: "Gerenciamento de ciclos de pagamento dos planos")]
class PlanoCicloController
{
    private $db;
    
    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
    }
    
    /**
     * Listar frequências de assinatura disponíveis
     * GET /admin/assinatura-frequencias
     */
    #[OA\Get(
        path: "/admin/assinatura-frequencias",
        summary: "Listar frequências de assinatura",
        description: "Retorna todas as frequências de assinatura disponíveis (mensal, bimestral, trimestral, etc)",
        tags: ["Ciclos de Planos"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de tipos de ciclo",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "nome", type: "string", example: "Mensal"),
                                    new OA\Property(property: "codigo", type: "string", example: "mensal"),
                                    new OA\Property(property: "meses", type: "integer", example: 1),
                                    new OA\Property(property: "ordem", type: "integer", example: 1)
                                ]
                            )
                        )
                    ]
                )
            )
        ]
    )]
    public function listarFrequencias(Request $request, Response $response): Response
    {
        try {
            $stmt = $this->db->query("
                SELECT id, nome, codigo, meses, ordem 
                FROM assinatura_frequencias 
                WHERE ativo = 1 
                ORDER BY ordem ASC
            ");
            $tipos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($tipos as &$tipo) {
                $tipo['id'] = (int) $tipo['id'];
                $tipo['meses'] = (int) $tipo['meses'];
                $tipo['ordem'] = (int) $tipo['ordem'];
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $tipos
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Erro ao listar frequências de assinatura: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Erro interno']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Listar ciclos de um plano
     * GET /admin/planos/{plano_id}/ciclos
     */
    #[OA\Get(
        path: "/admin/planos/{plano_id}/ciclos",
        summary: "Listar ciclos de um plano",
        description: "Retorna todos os ciclos cadastrados para um plano específico",
        tags: ["Ciclos de Planos"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "plano_id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de ciclos do plano",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "plano", type: "object"),
                        new OA\Property(property: "ciclos", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "total", type: "integer")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Plano não encontrado")
        ]
    )]
    public function listar(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $planoId = (int) $args['plano_id'];
            
            // Verificar se plano pertence ao tenant
            $stmtPlano = $this->db->prepare("SELECT id, nome, valor FROM planos WHERE id = ? AND tenant_id = ?");
            $stmtPlano->execute([$planoId, $tenantId]);
            $plano = $stmtPlano->fetch(\PDO::FETCH_ASSOC);
            
            if (!$plano) {
                $response->getBody()->write(json_encode(['error' => 'Plano não encontrado']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    pc.id, af.nome, af.codigo, pc.meses, pc.valor, pc.valor_mensal_equivalente,
                    pc.desconto_percentual, pc.permite_recorrencia, pc.ativo, af.ordem,
                    pc.assinatura_frequencia_id
                FROM plano_ciclos pc
                INNER JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
                WHERE pc.plano_id = ? AND pc.tenant_id = ?
                ORDER BY af.ordem ASC
            ");
            $stmt->execute([$planoId, $tenantId]);
            $ciclos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Encontrar o valor mensal equivalente do ciclo mensal (meses=1) como referência
            $valorMensalReferencia = 0;
            foreach ($ciclos as $c) {
                if ((int) $c['meses'] === 1) {
                    $valorMensalReferencia = (float) $c['valor_mensal_equivalente'];
                    break;
                }
            }
            // Se não houver ciclo mensal, usar o ciclo com menor qtd de meses
            if ($valorMensalReferencia <= 0 && !empty($ciclos)) {
                $menorMeses = PHP_INT_MAX;
                foreach ($ciclos as $c) {
                    if ((int) $c['meses'] < $menorMeses) {
                        $menorMeses = (int) $c['meses'];
                        $valorMensalReferencia = (float) $c['valor_mensal_equivalente'];
                    }
                }
            }
            
            // Formatar valores e calcular economia real
            foreach ($ciclos as &$ciclo) {
                $ciclo['id'] = (int) $ciclo['id'];
                $ciclo['assinatura_frequencia_id'] = (int) $ciclo['assinatura_frequencia_id'];
                $ciclo['meses'] = (int) $ciclo['meses'];
                $ciclo['valor'] = (float) $ciclo['valor'];
                $ciclo['valor_mensal_equivalente'] = (float) $ciclo['valor_mensal_equivalente'];
                $ciclo['permite_recorrencia'] = (bool) $ciclo['permite_recorrencia'];
                $ciclo['ativo'] = (bool) $ciclo['ativo'];
                $ciclo['valor_formatado'] = 'R$ ' . number_format($ciclo['valor'], 2, ',', '.');
                $ciclo['valor_mensal_formatado'] = 'R$ ' . number_format($ciclo['valor_mensal_equivalente'], 2, ',', '.');
                
                // Calcular economia real: quanto economiza por mês em relação ao ciclo mensal
                $economiaPercentual = 0;
                $economiaValor = 0;
                if ($valorMensalReferencia > 0 && $ciclo['valor_mensal_equivalente'] < $valorMensalReferencia) {
                    $economiaPercentual = round((($valorMensalReferencia - $ciclo['valor_mensal_equivalente']) / $valorMensalReferencia) * 100, 1);
                    $economiaValor = round(($valorMensalReferencia - $ciclo['valor_mensal_equivalente']) * $ciclo['meses'], 2);
                }
                $ciclo['desconto_percentual'] = $economiaPercentual;
                $ciclo['economia_valor'] = $economiaValor;
                $ciclo['economia_formatada'] = $economiaValor > 0 
                    ? 'R$ ' . number_format($economiaValor, 2, ',', '.') . ' de economia'
                    : null;
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'plano' => $plano,
                'ciclos' => $ciclos,
                'total' => count($ciclos)
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Erro ao listar ciclos: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Erro interno']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Criar ciclo para um plano
     * POST /admin/planos/{plano_id}/ciclos
     */
    #[OA\Post(
        path: "/admin/planos/{plano_id}/ciclos",
        summary: "Criar ciclo para um plano",
        description: "Cria um novo ciclo de pagamento para o plano. Permite até 2 ciclos por frequência: um com recorrência (assinatura) e outro sem (pagamento avulso). Aceita assinatura_frequencia_id ou tipo_ciclo_id (legado).",
        tags: ["Ciclos de Planos"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "plano_id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["valor"],
                properties: [
                    new OA\Property(property: "assinatura_frequencia_id", type: "integer", example: 2, description: "ID da frequência de assinatura"),
                    new OA\Property(property: "tipo_ciclo_id", type: "integer", example: 2, description: "ID da frequência (legado, use assinatura_frequencia_id)"),
                    new OA\Property(property: "valor", type: "number", format: "float", example: 240.00, description: "Valor total do ciclo"),
                    new OA\Property(property: "permite_recorrencia", type: "boolean", example: true, description: "true = assinatura recorrente, false = pagamento avulso. Permite 1 de cada por frequência."),
                    new OA\Property(property: "ativo", type: "boolean", example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Ciclo criado com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Ciclo criado com sucesso"),
                        new OA\Property(property: "id", type: "integer", example: 5),
                        new OA\Property(property: "permite_recorrencia", type: "boolean", example: true)
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Ciclo já existe para este plano com mesmo tipo de recorrência"),
            new OA\Response(response: 404, description: "Plano ou tipo de ciclo não encontrado"),
            new OA\Response(response: 422, description: "Dados inválidos")
        ]
    )]
    public function criar(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $planoId = (int) $args['plano_id'];
            $data = $request->getParsedBody();
            
            // Compatibilidade: aceitar tanto assinatura_frequencia_id quanto tipo_ciclo_id (legado)
            $frequenciaId = $data['assinatura_frequencia_id'] ?? $data['tipo_ciclo_id'] ?? null;
            
            // Validações
            $errors = [];
            if (empty($frequenciaId)) $errors[] = 'Frequência de assinatura é obrigatória';
            if (!isset($data['valor']) || $data['valor'] < 0) $errors[] = 'Valor é obrigatório';
            
            if (!empty($errors)) {
                $response->getBody()->write(json_encode(['errors' => $errors]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
            }
            
            // Verificar se assinatura_frequencia existe e buscar meses
            $stmtTipo = $this->db->prepare("SELECT id, meses, nome FROM assinatura_frequencias WHERE id = ? AND ativo = 1");
            $stmtTipo->execute([$frequenciaId]);
            $tipoCiclo = $stmtTipo->fetch(\PDO::FETCH_ASSOC);
            
            if (!$tipoCiclo) {
                $response->getBody()->write(json_encode(['error' => 'Frequência de assinatura não encontrada']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Verificar se plano pertence ao tenant
            $stmtPlano = $this->db->prepare("SELECT id, valor FROM planos WHERE id = ? AND tenant_id = ?");
            $stmtPlano->execute([$planoId, $tenantId]);
            $plano = $stmtPlano->fetch(\PDO::FETCH_ASSOC);
            
            if (!$plano) {
                $response->getBody()->write(json_encode(['error' => 'Plano não encontrado']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Verificar se já existe ciclo com mesma frequência E mesmo tipo de recorrência
            // Permite: 1 ciclo com recorrência + 1 ciclo sem recorrência para mesma frequência
            $permiteRecorrencia = isset($data['permite_recorrencia']) ? (int) $data['permite_recorrencia'] : 1;
            $stmtCheck = $this->db->prepare("
                SELECT id FROM plano_ciclos 
                WHERE plano_id = ? AND assinatura_frequencia_id = ? AND permite_recorrencia = ?
            ");
            $stmtCheck->execute([$planoId, $frequenciaId, $permiteRecorrencia]);
            if ($stmtCheck->fetch()) {
                $tipoCobranca = $permiteRecorrencia ? 'com recorrência' : 'sem recorrência (avulso)';
                $response->getBody()->write(json_encode([
                    'error' => "Já existe um ciclo {$tipoCiclo['nome']} {$tipoCobranca} para este plano"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Calcular desconto em relação ao valor mensal do plano
            $meses = (int) $tipoCiclo['meses'];
            $valorMensalBase = $plano['valor'];
            $valorTotalSemDesconto = $valorMensalBase * $meses;
            $descontoPercentual = $valorTotalSemDesconto > 0 
                ? round((($valorTotalSemDesconto - $data['valor']) / $valorTotalSemDesconto) * 100, 2)
                : 0;
            
            // Inserir
            $stmt = $this->db->prepare("
                INSERT INTO plano_ciclos 
                (tenant_id, plano_id, assinatura_frequencia_id, meses, valor, desconto_percentual, permite_recorrencia, ativo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $tenantId,
                $planoId,
                (int) $frequenciaId,
                $meses,
                (float) $data['valor'],
                $descontoPercentual,
                $permiteRecorrencia,
                isset($data['ativo']) ? (int) $data['ativo'] : 1
            ]);
            
            $cicloId = (int) $this->db->lastInsertId();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Ciclo criado com sucesso',
                'id' => $cicloId,
                'permite_recorrencia' => (bool) $permiteRecorrencia
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
            
        } catch (\Exception $e) {
            error_log("Erro ao criar ciclo: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Erro interno: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Atualizar ciclo
     * PUT /admin/planos/{plano_id}/ciclos/{id}
     */
    #[OA\Put(
        path: "/admin/planos/{plano_id}/ciclos/{id}",
        summary: "Atualizar ciclo",
        description: "Atualiza o valor e configurações de um ciclo existente. Não permite alterar o tipo do ciclo.",
        tags: ["Ciclos de Planos"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "plano_id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "valor", type: "number", format: "float", example: 230.00),
                    new OA\Property(property: "permite_recorrencia", type: "boolean", example: true),
                    new OA\Property(property: "ativo", type: "boolean", example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Ciclo atualizado com sucesso"),
            new OA\Response(response: 404, description: "Ciclo não encontrado")
        ]
    )]
    public function atualizar(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $planoId = (int) $args['plano_id'];
            $cicloId = (int) $args['id'];
            $data = $request->getParsedBody();
            
            error_log("[PlanoCicloController::atualizar] tenantId={$tenantId}, planoId={$planoId}, cicloId={$cicloId}");
            error_log("[PlanoCicloController::atualizar] data=" . json_encode($data));
            
            // Verificar se ciclo existe e pertence ao tenant
            $stmt = $this->db->prepare("
                SELECT pc.*, p.valor as plano_valor, af.meses as tipo_meses
                FROM plano_ciclos pc
                INNER JOIN planos p ON p.id = pc.plano_id
                INNER JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
                WHERE pc.id = ? AND pc.plano_id = ? AND pc.tenant_id = ?
            ");
            $stmt->execute([$cicloId, $planoId, $tenantId]);
            $ciclo = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            error_log("[PlanoCicloController::atualizar] ciclo encontrado=" . ($ciclo ? 'SIM' : 'NAO'));
            
            if (!$ciclo) {
                $response->getBody()->write(json_encode(['error' => 'Ciclo não encontrado']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Calcular desconto se valor foi alterado
            $valor = isset($data['valor']) ? (float) $data['valor'] : (float) $ciclo['valor'];
            $meses = (int) ($ciclo['tipo_meses'] ?? 1); // Meses vem da assinatura_frequencia, default 1
            $valorMensalBase = (float) ($ciclo['plano_valor'] ?? 0);
            $valorTotalSemDesconto = $valorMensalBase * $meses;
            $descontoPercentual = $valorTotalSemDesconto > 0 
                ? round((($valorTotalSemDesconto - $valor) / $valorTotalSemDesconto) * 100, 2)
                : 0;
            
            // Atualizar (não permite mudar assinatura_frequencia, apenas valor e flags)
            $stmt = $this->db->prepare("
                UPDATE plano_ciclos
                SET valor = COALESCE(?, valor),
                    desconto_percentual = ?,
                    permite_recorrencia = COALESCE(?, permite_recorrencia),
                    ativo = COALESCE(?, ativo),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                isset($data['valor']) ? (float) $data['valor'] : null,
                $descontoPercentual,
                isset($data['permite_recorrencia']) ? (int) $data['permite_recorrencia'] : null,
                isset($data['ativo']) ? (int) $data['ativo'] : null,
                $cicloId
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Ciclo atualizado com sucesso'
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Erro ao atualizar ciclo: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Erro interno']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Excluir ciclo
     * DELETE /admin/planos/{plano_id}/ciclos/{id}
     */
    #[OA\Delete(
        path: "/admin/planos/{plano_id}/ciclos/{id}",
        summary: "Excluir ciclo",
        description: "Exclui um ciclo do plano. Não permite excluir se houver matrículas vinculadas.",
        tags: ["Ciclos de Planos"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "plano_id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Ciclo excluído com sucesso"),
            new OA\Response(response: 400, description: "Existem matrículas vinculadas"),
            new OA\Response(response: 404, description: "Ciclo não encontrado")
        ]
    )]
    public function excluir(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $planoId = (int) $args['plano_id'];
            $cicloId = (int) $args['id'];
            
            // Verificar se ciclo existe e pertence ao tenant
            $stmt = $this->db->prepare("
                SELECT id FROM plano_ciclos WHERE id = ? AND plano_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$cicloId, $planoId, $tenantId]);
            
            if (!$stmt->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Ciclo não encontrado']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Verificar se há matrículas vinculadas
            $stmtCheck = $this->db->prepare("SELECT COUNT(*) as total FROM matriculas WHERE plano_ciclo_id = ?");
            $stmtCheck->execute([$cicloId]);
            $result = $stmtCheck->fetch(\PDO::FETCH_ASSOC);
            
            if ($result['total'] > 0) {
                $response->getBody()->write(json_encode([
                    'error' => "Não é possível excluir. Existem {$result['total']} matrícula(s) vinculada(s) a este ciclo."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Excluir
            $stmt = $this->db->prepare("DELETE FROM plano_ciclos WHERE id = ?");
            $stmt->execute([$cicloId]);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Ciclo excluído com sucesso'
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Erro ao excluir ciclo: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Erro interno']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Gerar ciclos automáticos para um plano
     * POST /admin/planos/{plano_id}/ciclos/gerar
     */
    #[OA\Post(
        path: "/admin/planos/{plano_id}/ciclos/gerar",
        summary: "Gerar ciclos automáticos",
        description: "Gera automaticamente todos os ciclos para um plano baseado nas frequências cadastradas em assinatura_frequencias. Descontos são aplicados via parâmetro desconto_{codigo}, ex: desconto_mensal, desconto_trimestral. Descontos default são calculados automaticamente baseado na quantidade de meses.",
        tags: ["Ciclos de Planos"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "plano_id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            description: "Descontos opcionais por código de frequência (ex: desconto_diario, desconto_semanal, desconto_quinzenal, desconto_mensal, desconto_bimestral, desconto_trimestral, desconto_semestral, desconto_anual). Os códigos disponíveis dependem da tabela assinatura_frequencias.",
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "desconto_diario", type: "number", example: 0, description: "Desconto % para ciclo diário"),
                    new OA\Property(property: "desconto_semanal", type: "number", example: 0, description: "Desconto % para ciclo semanal"),
                    new OA\Property(property: "desconto_quinzenal", type: "number", example: 0, description: "Desconto % para ciclo quinzenal"),
                    new OA\Property(property: "desconto_mensal", type: "number", example: 0, description: "Desconto % para ciclo mensal"),
                    new OA\Property(property: "desconto_bimestral", type: "number", example: 10, description: "Desconto % para ciclo bimestral"),
                    new OA\Property(property: "desconto_trimestral", type: "number", example: 15, description: "Desconto % para ciclo trimestral"),
                    new OA\Property(property: "desconto_quadrimestral", type: "number", example: 20, description: "Desconto % para ciclo quadrimestral"),
                    new OA\Property(property: "desconto_semestral", type: "number", example: 25, description: "Desconto % para ciclo semestral"),
                    new OA\Property(property: "desconto_anual", type: "number", example: 30, description: "Desconto % para ciclo anual")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Ciclos gerados com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "ciclos", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Plano não encontrado")
        ]
    )]
    public function gerarCiclosAutomaticos(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $planoId = (int) $args['plano_id'];
            $data = $request->getParsedBody() ?? [];
            
            // Verificar se plano existe
            $stmtPlano = $this->db->prepare("SELECT id, nome, valor FROM planos WHERE id = ? AND tenant_id = ?");
            $stmtPlano->execute([$planoId, $tenantId]);
            $plano = $stmtPlano->fetch(\PDO::FETCH_ASSOC);
            
            if (!$plano) {
                $response->getBody()->write(json_encode(['error' => 'Plano não encontrado']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Buscar ciclos existentes do plano com contagem de matrículas
            $stmtCiclosExistentes = $this->db->prepare("
                SELECT pc.id, pc.assinatura_frequencia_id, pc.valor,
                       (SELECT COUNT(*) FROM matriculas m WHERE m.plano_ciclo_id = pc.id) as total_matriculas
                FROM plano_ciclos pc
                WHERE pc.plano_id = ? AND pc.tenant_id = ?
            ");
            $stmtCiclosExistentes->execute([$planoId, $tenantId]);
            $ciclosExistentes = $stmtCiclosExistentes->fetchAll(\PDO::FETCH_ASSOC);
            
            // Mapear ciclos existentes por frequencia_id e identificar os que têm matrículas
            $ciclosPorFrequencia = [];
            $ciclosComMatriculas = [];
            foreach ($ciclosExistentes as $ciclo) {
                $ciclosPorFrequencia[$ciclo['assinatura_frequencia_id']] = $ciclo;
                if ((int) $ciclo['total_matriculas'] > 0) {
                    $ciclosComMatriculas[$ciclo['assinatura_frequencia_id']] = (int) $ciclo['total_matriculas'];
                }
            }
            
            // Buscar tipos de ciclo disponíveis (usar dados da tabela, não hardcoded)
            $stmtTipos = $this->db->prepare("SELECT id, nome, codigo, meses, ordem FROM assinatura_frequencias WHERE ativo = 1 ORDER BY ordem ASC");
            $stmtTipos->execute();
            $tiposCiclo = $stmtTipos->fetchAll(\PDO::FETCH_ASSOC);
            
            if (empty($tiposCiclo)) {
                $response->getBody()->write(json_encode(['error' => 'Nenhuma frequência de assinatura cadastrada']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Mapear descontos do request (usa código da tabela como chave)
            // Descontos default progressivos baseados em meses
            $descontosDefault = [];
            foreach ($tiposCiclo as $tipo) {
                $meses = (int) $tipo['meses'];
                // Desconto progressivo: 0% para 1 mês, aumentando conforme meses
                $descontosDefault[$tipo['codigo']] = match(true) {
                    $meses <= 1 => 0,
                    $meses == 2 => 10,
                    $meses == 3 => 15,
                    $meses == 4 => 20,
                    $meses >= 6 && $meses < 12 => 25,
                    $meses >= 12 => 30,
                    default => 0
                };
            }
            
            // Sobrescrever com valores enviados na request
            $descontos = $descontosDefault;
            foreach ($tiposCiclo as $tipo) {
                $chaveRequest = 'desconto_' . $tipo['codigo'];
                if (isset($data[$chaveRequest])) {
                    $descontos[$tipo['codigo']] = (float) $data[$chaveRequest];
                }
            }
            
            $ciclosCriados = [];
            $ciclosIgnorados = [];
            
            // Statement para INSERT (novos ciclos)
            $stmtInsert = $this->db->prepare("
                INSERT INTO plano_ciclos 
                (tenant_id, plano_id, assinatura_frequencia_id, meses, valor, desconto_percentual, permite_recorrencia, ativo)
                VALUES (?, ?, ?, ?, ?, ?, 1, 1)
            ");
            
            // Statement para UPDATE (ciclos existentes sem matrículas)
            $stmtUpdate = $this->db->prepare("
                UPDATE plano_ciclos 
                SET valor = ?, desconto_percentual = ?, meses = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            foreach ($tiposCiclo as $tipo) {
                $meses = (int) $tipo['meses'];
                if ($meses < 1) {
                    error_log("Frequência {$tipo['codigo']} com meses inválido ({$tipo['meses']}), ignorando");
                    continue;
                }
                
                $frequenciaId = (int) $tipo['id'];
                $desconto = $descontos[$tipo['codigo']] ?? 0;
                $valorBase = $plano['valor'] * $meses;
                $valorComDesconto = round($valorBase * (1 - ($desconto / 100)), 2);
                
                // Verificar se ciclo já existe
                if (isset($ciclosPorFrequencia[$frequenciaId])) {
                    $cicloExistente = $ciclosPorFrequencia[$frequenciaId];
                    
                    // Se tem matrículas vinculadas, NÃO atualizar
                    if (isset($ciclosComMatriculas[$frequenciaId])) {
                        $ciclosIgnorados[] = [
                            'assinatura_frequencia_id' => $frequenciaId,
                            'nome' => $tipo['nome'],
                            'motivo' => "Possui {$ciclosComMatriculas[$frequenciaId]} matrícula(s) vinculada(s)",
                            'valor_atual' => (float) $cicloExistente['valor']
                        ];
                        continue;
                    }
                    
                    // Sem matrículas, pode atualizar
                    $stmtUpdate->execute([
                        $valorComDesconto,
                        $desconto,
                        $meses,
                        $cicloExistente['id']
                    ]);
                    
                    $ciclosCriados[] = [
                        'assinatura_frequencia_id' => $frequenciaId,
                        'nome' => $tipo['nome'],
                        'meses' => $meses,
                        'valor' => $valorComDesconto,
                        'desconto' => $desconto,
                        'economia' => round($valorBase - $valorComDesconto, 2),
                        'acao' => 'atualizado'
                    ];
                } else {
                    // Ciclo não existe, inserir novo
                    $stmtInsert->execute([
                        $tenantId,
                        $planoId,
                        $frequenciaId,
                        $meses,
                        $valorComDesconto,
                        $desconto
                    ]);
                    
                    $ciclosCriados[] = [
                        'assinatura_frequencia_id' => $frequenciaId,
                        'nome' => $tipo['nome'],
                        'meses' => $meses,
                        'valor' => $valorComDesconto,
                        'desconto' => $desconto,
                        'economia' => round($valorBase - $valorComDesconto, 2),
                        'acao' => 'criado'
                    ];
                }
            }
            
            $responseData = [
                'success' => true,
                'message' => 'Ciclos gerados com sucesso',
                'ciclos' => $ciclosCriados
            ];
            
            if (!empty($ciclosIgnorados)) {
                $responseData['ciclos_ignorados'] = $ciclosIgnorados;
                $responseData['aviso'] = 'Alguns ciclos não foram atualizados pois possuem matrículas vinculadas';
            }
            
            $response->getBody()->write(json_encode($responseData));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Erro ao gerar ciclos: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Erro interno: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
