<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

/**
 * Controller para geração de relatórios
 * 
 * @package App\Controllers
 */
#[OA\Tag(name: "Relatórios", description: "Geração de relatórios do sistema")]
class RelatorioController
{
    private $db;
    
    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
    }
    
    /**
     * Relatório de Planos e Ciclos
     * GET /admin/relatorios/planos-ciclos
     */
    #[OA\Get(
        path: "/admin/relatorios/planos-ciclos",
        summary: "Relatório de planos e ciclos",
        description: "Retorna todos os planos do tenant com seus respectivos ciclos de pagamento. Permite filtrar por status ativo/inativo.",
        tags: ["Relatórios"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "ativo",
                in: "query",
                required: false,
                description: "Filtrar por status: 1=ativos, 0=inativos, vazio=todos",
                schema: new OA\Schema(type: "string", enum: ["0", "1"])
            ),
            new OA\Parameter(
                name: "modalidade_id",
                in: "query",
                required: false,
                description: "Filtrar por modalidade específica",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Relatório de planos e ciclos",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "tenant", type: "object"),
                        new OA\Property(
                            property: "planos",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer"),
                                    new OA\Property(property: "nome", type: "string"),
                                    new OA\Property(property: "valor", type: "number"),
                                    new OA\Property(property: "ativo", type: "boolean"),
                                    new OA\Property(property: "modalidade_nome", type: "string"),
                                    new OA\Property(
                                        property: "ciclos",
                                        type: "array",
                                        items: new OA\Items(type: "object")
                                    )
                                ]
                            )
                        ),
                        new OA\Property(property: "resumo", type: "object")
                    ]
                )
            )
        ]
    )]
    public function planosCiclos(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $queryParams = $request->getQueryParams();
            
            // Filtros
            $filtroAtivo = null;
            if (isset($queryParams['ativo']) && $queryParams['ativo'] !== '') {
                $filtroAtivo = (int) $queryParams['ativo'];
            }
            
            $filtroModalidade = null;
            if (isset($queryParams['modalidade_id']) && $queryParams['modalidade_id'] !== '') {
                $filtroModalidade = (int) $queryParams['modalidade_id'];
            }
            
            // Buscar dados do tenant
            $stmtTenant = $this->db->prepare("SELECT id, nome FROM tenants WHERE id = ?");
            $stmtTenant->execute([$tenantId]);
            $tenant = $stmtTenant->fetch(\PDO::FETCH_ASSOC);
            
            // Buscar planos com filtros
            $sqlPlanos = "
                SELECT 
                    p.id,
                    p.nome,
                    p.valor,
                    p.checkins_semanais,
                    p.duracao_dias,
                    p.ativo,
                    p.atual,
                    p.created_at,
                    p.updated_at,
                    m.id as modalidade_id,
                    m.nome as modalidade_nome,
                    m.cor as modalidade_cor,
                    m.icone as modalidade_icone
                FROM planos p
                LEFT JOIN modalidades m ON m.id = p.modalidade_id
                WHERE p.tenant_id = ?
            ";
            $params = [$tenantId];
            
            if ($filtroAtivo !== null) {
                $sqlPlanos .= " AND p.ativo = ?";
                $params[] = $filtroAtivo;
            }
            
            if ($filtroModalidade !== null) {
                $sqlPlanos .= " AND p.modalidade_id = ?";
                $params[] = $filtroModalidade;
            }
            
            $sqlPlanos .= " ORDER BY m.nome ASC, p.nome ASC, p.valor ASC";
            
            $stmtPlanos = $this->db->prepare($sqlPlanos);
            $stmtPlanos->execute($params);
            $planos = $stmtPlanos->fetchAll(\PDO::FETCH_ASSOC);
            
            // Buscar todos os ciclos do tenant
            $sqlCiclos = "
                SELECT 
                    pc.id,
                    pc.plano_id,
                    pc.meses,
                    pc.valor,
                    pc.valor_mensal_equivalente,
                    pc.desconto_percentual,
                    pc.permite_recorrencia,
                    pc.ativo,
                    af.nome as frequencia_nome,
                    af.codigo as frequencia_codigo,
                    af.ordem
                FROM plano_ciclos pc
                INNER JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
                WHERE pc.tenant_id = ?
                ORDER BY pc.plano_id, af.ordem ASC
            ";
            $stmtCiclos = $this->db->prepare($sqlCiclos);
            $stmtCiclos->execute([$tenantId]);
            $todosCiclos = $stmtCiclos->fetchAll(\PDO::FETCH_ASSOC);
            
            // Organizar ciclos por plano
            $ciclosPorPlano = [];
            foreach ($todosCiclos as $ciclo) {
                $planoId = $ciclo['plano_id'];
                if (!isset($ciclosPorPlano[$planoId])) {
                    $ciclosPorPlano[$planoId] = [];
                }
                
                // Formatar valores do ciclo
                $ciclo['id'] = (int) $ciclo['id'];
                $ciclo['meses'] = (int) $ciclo['meses'];
                $ciclo['valor'] = (float) $ciclo['valor'];
                $ciclo['valor_mensal_equivalente'] = (float) $ciclo['valor_mensal_equivalente'];
                $ciclo['desconto_percentual'] = (float) $ciclo['desconto_percentual'];
                $ciclo['permite_recorrencia'] = (bool) $ciclo['permite_recorrencia'];
                $ciclo['ativo'] = (bool) $ciclo['ativo'];
                $ciclo['valor_formatado'] = 'R$ ' . number_format($ciclo['valor'], 2, ',', '.');
                $ciclo['valor_mensal_formatado'] = 'R$ ' . number_format($ciclo['valor_mensal_equivalente'], 2, ',', '.');
                
                unset($ciclo['plano_id']);
                $ciclosPorPlano[$planoId][] = $ciclo;
            }
            
            // Montar resultado final
            $resultado = [];
            $resumo = [
                'total_planos' => 0,
                'planos_ativos' => 0,
                'planos_inativos' => 0,
                'total_ciclos' => 0,
                'ciclos_ativos' => 0,
                'ciclos_inativos' => 0,
                'planos_sem_ciclos' => 0,
                'modalidades' => []
            ];
            
            foreach ($planos as $plano) {
                $planoId = (int) $plano['id'];
                $ciclos = $ciclosPorPlano[$planoId] ?? [];
                
                // Formatar valores do plano
                $planoFormatado = [
                    'id' => $planoId,
                    'nome' => $plano['nome'],
                    'valor' => (float) $plano['valor'],
                    'valor_formatado' => 'R$ ' . number_format($plano['valor'], 2, ',', '.'),
                    'checkins_semanais' => (int) $plano['checkins_semanais'],
                    'duracao_dias' => (int) $plano['duracao_dias'],
                    'ativo' => (bool) $plano['ativo'],
                    'atual' => (bool) $plano['atual'],
                    'modalidade' => [
                        'id' => $plano['modalidade_id'] ? (int) $plano['modalidade_id'] : null,
                        'nome' => $plano['modalidade_nome'],
                        'cor' => $plano['modalidade_cor'],
                        'icone' => $plano['modalidade_icone']
                    ],
                    'ciclos' => $ciclos,
                    'total_ciclos' => count($ciclos),
                    'created_at' => $plano['created_at'],
                    'updated_at' => $plano['updated_at']
                ];
                
                $resultado[] = $planoFormatado;
                
                // Atualizar resumo
                $resumo['total_planos']++;
                if ($plano['ativo']) {
                    $resumo['planos_ativos']++;
                } else {
                    $resumo['planos_inativos']++;
                }
                
                $resumo['total_ciclos'] += count($ciclos);
                foreach ($ciclos as $c) {
                    if ($c['ativo']) {
                        $resumo['ciclos_ativos']++;
                    } else {
                        $resumo['ciclos_inativos']++;
                    }
                }
                
                if (empty($ciclos)) {
                    $resumo['planos_sem_ciclos']++;
                }
                
                // Contagem por modalidade
                $modalidadeNome = $plano['modalidade_nome'] ?? 'Sem modalidade';
                if (!isset($resumo['modalidades'][$modalidadeNome])) {
                    $resumo['modalidades'][$modalidadeNome] = 0;
                }
                $resumo['modalidades'][$modalidadeNome]++;
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'tenant' => $tenant,
                'planos' => $resultado,
                'resumo' => $resumo,
                'filtros_aplicados' => [
                    'ativo' => $filtroAtivo,
                    'modalidade_id' => $filtroModalidade
                ],
                'gerado_em' => date('Y-m-d H:i:s')
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("Erro ao gerar relatório de planos e ciclos: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao gerar relatório: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
