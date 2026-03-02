<?php

namespace App\Controllers;

use App\Models\Parametro;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

/**
 * Controller para gerenciar parâmetros do sistema
 */
class ParametroController
{
    private PDO $db;
    private Parametro $parametro;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->parametro = new Parametro($db);
    }
    
    /**
     * GET /api/parametros
     * Lista todas as categorias e parâmetros do tenant
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenant_id');
            
            if (!$tenantId) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Tenant não identificado'
                ], 401);
            }
            
            $categorias = $this->parametro->getCategorias();
            $result = [];
            
            foreach ($categorias as $categoria) {
                $parametros = $this->parametro->getByCategoria($tenantId, $categoria['codigo']);
                $result[] = [
                    'categoria' => $categoria,
                    'parametros' => $parametros
                ];
            }
            
            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            error_log("[ParametroController] Erro: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erro ao carregar parâmetros'
            ], 500);
        }
    }
    
    /**
     * GET /api/parametros/categorias
     * Lista apenas as categorias disponíveis
     */
    public function categorias(Request $request, Response $response): Response
    {
        try {
            $categorias = $this->parametro->getCategorias();
            
            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $categorias
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erro ao carregar categorias'
            ], 500);
        }
    }
    
    /**
     * GET /api/parametros/{categoria}
     * Lista parâmetros de uma categoria específica
     */
    public function byCategoria(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenant_id');
            $categoria = $args['categoria'] ?? null;
            
            if (!$tenantId) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Tenant não identificado'
                ], 401);
            }
            
            if (!$categoria) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Categoria não informada'
                ], 400);
            }
            
            $parametros = $this->parametro->getByCategoria($tenantId, $categoria);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'categoria' => $categoria,
                'data' => $parametros
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erro ao carregar parâmetros da categoria'
            ], 500);
        }
    }
    
    /**
     * GET /api/parametros/valor/{codigo}
     * Obtém valor de um parâmetro específico
     */
    public function getValue(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenant_id');
            $codigo = $args['codigo'] ?? null;
            
            if (!$tenantId || !$codigo) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Tenant ou código não informado'
                ], 400);
            }
            
            $valor = $this->parametro->get($tenantId, $codigo);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'codigo' => $codigo,
                'valor' => $valor
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erro ao obter parâmetro'
            ], 500);
        }
    }
    
    /**
     * PUT /api/parametros/{codigo}
     * Atualiza valor de um parâmetro
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenant_id');
            $usuarioId = $request->getAttribute('user_id');
            $codigo = $args['codigo'] ?? null;
            $body = $request->getParsedBody();
            
            if (!$tenantId) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Tenant não identificado'
                ], 401);
            }
            
            if (!$codigo) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Código do parâmetro não informado'
                ], 400);
            }
            
            if (!isset($body['valor'])) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Valor não informado'
                ], 400);
            }
            
            $result = $this->parametro->set($tenantId, $codigo, $body['valor'], $usuarioId);
            
            if ($result) {
                return $this->jsonResponse($response, [
                    'success' => true,
                    'message' => 'Parâmetro atualizado com sucesso',
                    'codigo' => $codigo,
                    'valor' => $body['valor']
                ]);
            }
            
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Parâmetro não encontrado'
            ], 404);
            
        } catch (\Exception $e) {
            error_log("[ParametroController] Erro ao atualizar: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erro ao atualizar parâmetro'
            ], 500);
        }
    }
    
    /**
     * PATCH /api/parametros/{codigo}/toggle
     * Inverte valor de um parâmetro booleano (true <-> false)
     */
    public function toggle(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenant_id');
            $usuarioId = $request->getAttribute('user_id');
            $codigo = $args['codigo'] ?? null;
            
            if (!$tenantId) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Tenant não identificado'
                ], 401);
            }
            
            if (!$codigo) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Código do parâmetro não informado'
                ], 400);
            }
            
            // Buscar valor atual
            $valorAtual = $this->parametro->get($tenantId, $codigo);
            
            if ($valorAtual === null) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Parâmetro não encontrado'
                ], 404);
            }
            
            // Inverter valor booleano
            $novoValor = !$valorAtual;
            
            $result = $this->parametro->set($tenantId, $codigo, $novoValor, $usuarioId);
            
            if ($result) {
                return $this->jsonResponse($response, [
                    'success' => true,
                    'message' => 'Parâmetro alternado com sucesso',
                    'codigo' => $codigo,
                    'valor_anterior' => $valorAtual,
                    'valor' => $novoValor
                ]);
            }
            
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erro ao alternar parâmetro'
            ], 500);
            
        } catch (\Exception $e) {
            error_log("[ParametroController] Erro ao toggle: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erro ao alternar parâmetro'
            ], 500);
        }
    }
    
    /**
     * PATCH /api/parametros/{codigo}
     * Atualiza valor de um parâmetro (persistência imediata)
     * Diferença do PUT: retorna o parâmetro completo após atualização
     */
    public function patch(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenant_id');
            $usuarioId = $request->getAttribute('user_id');
            $codigo = $args['codigo'] ?? null;
            $body = $request->getParsedBody();
            
            if (!$tenantId) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Tenant não identificado'
                ], 401);
            }
            
            if (!$codigo) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Código do parâmetro não informado'
                ], 400);
            }
            
            if (!isset($body['valor'])) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Valor não informado'
                ], 400);
            }
            
            $result = $this->parametro->set($tenantId, $codigo, $body['valor'], $usuarioId);
            
            if ($result) {
                // Buscar parâmetro atualizado para retornar
                $stmt = $this->db->prepare("
                    SELECT p.id, p.codigo, p.nome, p.descricao, p.tipo_valor, 
                           p.valor_padrao, p.opcoes_select,
                           COALESCE(pt.valor, p.valor_padrao) as valor,
                           tp.codigo as categoria_codigo, tp.nome as categoria_nome
                    FROM parametros p
                    LEFT JOIN tipos_parametro tp ON tp.id = p.tipo_parametro_id
                    LEFT JOIN parametros_tenant pt ON pt.parametro_id = p.id AND pt.tenant_id = ?
                    WHERE p.codigo = ?
                    LIMIT 1
                ");
                $stmt->execute([$tenantId, $codigo]);
                $parametro = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($parametro) {
                    // Converter valor conforme tipo
                    $parametro['valor'] = $this->parametro->convertValue(
                        $parametro['valor'], 
                        $parametro['tipo_valor']
                    );
                    
                    if ($parametro['opcoes_select']) {
                        $parametro['opcoes_select'] = json_decode($parametro['opcoes_select'], true);
                    }
                }
                
                return $this->jsonResponse($response, [
                    'success' => true,
                    'message' => 'Parâmetro atualizado com sucesso',
                    'data' => $parametro
                ]);
            }
            
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Parâmetro não encontrado'
            ], 404);
            
        } catch (\Exception $e) {
            error_log("[ParametroController] Erro ao patch: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erro ao atualizar parâmetro'
            ], 500);
        }
    }

    /**
     * PUT /api/parametros
     * Atualiza múltiplos parâmetros de uma vez
     */
    public function updateMultiple(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenant_id');
            $usuarioId = $request->getAttribute('user_id');
            $body = $request->getParsedBody();
            
            if (!$tenantId) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Tenant não identificado'
                ], 401);
            }
            
            if (empty($body['parametros']) || !is_array($body['parametros'])) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Parâmetros não informados. Esperado: {"parametros": {"codigo": "valor", ...}}'
                ], 400);
            }
            
            $result = $this->parametro->setMultiple($tenantId, $body['parametros'], $usuarioId);
            
            if ($result) {
                return $this->jsonResponse($response, [
                    'success' => true,
                    'message' => count($body['parametros']) . ' parâmetro(s) atualizado(s) com sucesso'
                ]);
            }
            
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erro ao atualizar parâmetros'
            ], 500);
            
        } catch (\Exception $e) {
            error_log("[ParametroController] Erro ao atualizar múltiplos: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erro ao atualizar parâmetros'
            ], 500);
        }
    }
    
    /**
     * GET /api/parametros/pagamentos/resumo
     * Retorna resumo das configurações de pagamento (para uso interno)
     */
    public function resumoPagamentos(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenant_id');
            
            if (!$tenantId) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Tenant não identificado'
                ], 401);
            }
            
            $resumo = [
                'formas_pagamento' => [
                    'pix' => $this->parametro->isEnabled($tenantId, 'habilitar_pix'),
                    'cartao_credito' => $this->parametro->isEnabled($tenantId, 'habilitar_cartao_credito'),
                    'cartao_debito' => $this->parametro->isEnabled($tenantId, 'habilitar_cartao_debito'),
                    'boleto' => $this->parametro->isEnabled($tenantId, 'habilitar_boleto'),
                ],
                'cobranca' => [
                    'modo' => $this->parametro->get($tenantId, 'modo_cobranca', 'avulso'),
                    'recorrencia_habilitada' => $this->parametro->isEnabled($tenantId, 'habilitar_cobranca_recorrente'),
                    'gerar_proxima_automatico' => $this->parametro->isEnabled($tenantId, 'gerar_proxima_cobranca'),
                    'dias_antecedencia' => $this->parametro->getInt($tenantId, 'dias_antecedencia_cobranca', 5),
                ],
                'gateway' => $this->parametro->get($tenantId, 'gateway_pagamento', 'mercadopago'),
                'tolerancia' => [
                    'dias_vencimento' => $this->parametro->getInt($tenantId, 'dias_tolerancia_vencimento', 5),
                    'pagamento_parcial' => $this->parametro->isEnabled($tenantId, 'permitir_pagamento_parcial'),
                ]
            ];
            
            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $resumo
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Erro ao carregar resumo de pagamentos'
            ], 500);
        }
    }
    
    /**
     * Helper para resposta JSON
     */
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
