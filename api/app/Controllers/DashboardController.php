<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use OpenApi\Attributes as OA;

class DashboardController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
    }

    /**
     * GET /admin/dashboard/cards
     * Retorna os dados para os 4 cards principais do dashboard
     */
    #[OA\Get(
        path: "/admin/dashboard/cards",
        summary: "Cards do Dashboard",
        description: "Retorna os dados para os 4 cards principais do dashboard: Total de Alunos, Receita Mensal, Check-ins Hoje e Planos Vencendo",
        tags: ["Dashboard"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Dados dos cards retornados com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(
                                    property: "total_alunos",
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "total", type: "integer", example: 6),
                                        new OA\Property(property: "ativos", type: "integer", example: 6),
                                        new OA\Property(property: "inativos", type: "integer", example: 0)
                                    ]
                                ),
                                new OA\Property(
                                    property: "receita_mensal",
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "valor", type: "number", example: 1500.00),
                                        new OA\Property(property: "valor_formatado", type: "string", example: "R$ 1.500,00"),
                                        new OA\Property(property: "contas_pendentes", type: "integer", example: 3)
                                    ]
                                ),
                                new OA\Property(
                                    property: "checkins_hoje",
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "hoje", type: "integer", example: 12),
                                        new OA\Property(property: "no_mes", type: "integer", example: 145)
                                    ]
                                ),
                                new OA\Property(
                                    property: "planos_vencendo",
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "vencendo", type: "integer", example: 2),
                                        new OA\Property(property: "novos_este_mes", type: "integer", example: 5)
                                    ]
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 500, description: "Erro interno do servidor")
        ]
    )]
    public function cards(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');

        try {
            $dados = [
                'total_alunos' => $this->getTotalAlunos($tenantId),
                'receita_mensal' => $this->getReceitaMensal($tenantId),
                'checkins_hoje' => $this->getCheckinsHoje($tenantId),
                'planos_vencendo' => $this->getPlanosVencendo($tenantId),
            ];

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $dados
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            error_log("Erro no dashboard/cards: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao carregar cards do dashboard',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Total de Alunos (ativos e inativos)
     */
    private function getTotalAlunos(int $tenantId): array
    {
        // Contar alunos via tenant_usuario_papel com papel_id = 1 (Aluno)
        $stmt = $this->db->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN tup.ativo = 1 THEN 1 ELSE 0 END) as ativos,
                SUM(CASE WHEN tup.ativo = 0 THEN 1 ELSE 0 END) as inativos
             FROM tenant_usuario_papel tup
             WHERE tup.tenant_id = :tenant_id 
             AND tup.papel_id = 1"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total' => (int) ($result['total'] ?? 0),
            'ativos' => (int) ($result['ativos'] ?? 0),
            'inativos' => (int) ($result['inativos'] ?? 0),
        ];
    }

    /**
     * Receita Mensal (pago + pendentes)
     */
    private function getReceitaMensal(int $tenantId): array
    {
        $ano = (int) date('Y');
        $mes = (int) date('m');

        // Total recebido no mês (status_pagamento_id = 2 = Pago)
        $stmtPago = $this->db->prepare(
            "SELECT COALESCE(SUM(valor), 0) as total
             FROM pagamentos_plano
             WHERE tenant_id = :tenant_id 
             AND status_pagamento_id = 2
             AND YEAR(data_pagamento) = :ano 
             AND MONTH(data_pagamento) = :mes"
        );
        $stmtPago->execute(['tenant_id' => $tenantId, 'ano' => $ano, 'mes' => $mes]);
        $valorPago = (float) $stmtPago->fetchColumn();

        // Contas pendentes (status_pagamento_id = 1 = Aguardando ou 3 = Atrasado)
        $stmtPendentes = $this->db->prepare(
            "SELECT COUNT(*) as total
             FROM pagamentos_plano
             WHERE tenant_id = :tenant_id 
             AND status_pagamento_id IN (1, 3)
             AND YEAR(data_vencimento) = :ano 
             AND MONTH(data_vencimento) = :mes"
        );
        $stmtPendentes->execute(['tenant_id' => $tenantId, 'ano' => $ano, 'mes' => $mes]);
        $contasPendentes = (int) $stmtPendentes->fetchColumn();

        return [
            'valor' => $valorPago,
            'valor_formatado' => 'R$ ' . number_format($valorPago, 2, ',', '.'),
            'contas_pendentes' => $contasPendentes,
        ];
    }

    /**
     * Check-ins Hoje (hoje + total do mês)
     */
    private function getCheckinsHoje(int $tenantId): array
    {
        $hoje = date('Y-m-d');
        $ano = (int) date('Y');
        $mes = (int) date('m');

        // Check-ins de hoje
        $stmtHoje = $this->db->prepare(
            "SELECT COUNT(*) as total
             FROM checkins
             WHERE tenant_id = :tenant_id 
             AND DATE(data_checkin) = :hoje"
        );
        $stmtHoje->execute(['tenant_id' => $tenantId, 'hoje' => $hoje]);
        $checkinsHoje = (int) $stmtHoje->fetchColumn();

        // Check-ins do mês
        $stmtMes = $this->db->prepare(
            "SELECT COUNT(*) as total
             FROM checkins
             WHERE tenant_id = :tenant_id 
             AND YEAR(data_checkin) = :ano
             AND MONTH(data_checkin) = :mes"
        );
        $stmtMes->execute(['tenant_id' => $tenantId, 'ano' => $ano, 'mes' => $mes]);
        $checkinsMes = (int) $stmtMes->fetchColumn();

        return [
            'hoje' => $checkinsHoje,
            'no_mes' => $checkinsMes,
        ];
    }

    /**
     * Planos Vencendo (próximos 7 dias + novos este mês)
     */
    private function getPlanosVencendo(int $tenantId): array
    {
        $hoje = date('Y-m-d');
        $em7Dias = date('Y-m-d', strtotime('+7 days'));
        $ano = (int) date('Y');
        $mes = (int) date('m');

        // Matrículas vencendo nos próximos 7 dias (status_id = 1 = ativa)
        $stmtVencendo = $this->db->prepare(
            "SELECT COUNT(*) as total
             FROM matriculas
             WHERE tenant_id = :tenant_id 
             AND status_id = 1
             AND data_vencimento BETWEEN :hoje AND :em7dias"
        );
        $stmtVencendo->execute([
            'tenant_id' => $tenantId, 
            'hoje' => $hoje, 
            'em7dias' => $em7Dias
        ]);
        $vencendo = (int) $stmtVencendo->fetchColumn();

        // Novas matrículas este mês (motivo_id = 1 = nova)
        $stmtNovos = $this->db->prepare(
            "SELECT COUNT(*) as total
             FROM matriculas
             WHERE tenant_id = :tenant_id 
             AND YEAR(data_matricula) = :ano
             AND MONTH(data_matricula) = :mes"
        );
        $stmtNovos->execute(['tenant_id' => $tenantId, 'ano' => $ano, 'mes' => $mes]);
        $novosEsteMes = (int) $stmtNovos->fetchColumn();

        return [
            'vencendo' => $vencendo,
            'novos_este_mes' => $novosEsteMes,
        ];
    }

    /**
     * GET /admin/dashboard
     * Retorna todos os contadores do dashboard
     */
    public function index(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');

        try {
            $dados = [
                'alunos' => $this->contarAlunos($tenantId),
                'turmas' => $this->contarTurmas($tenantId),
                'professores' => $this->contarProfessores($tenantId),
                'modalidades' => $this->contarModalidades($tenantId),
                'checkins_hoje' => $this->contarCheckinsHoje($tenantId),
                'matrículas_ativas' => $this->contarMatriculasAtivas($tenantId),
                'receita_mes' => $this->calcularReceitaMes($tenantId),
                'contratos_ativos' => $this->contarContratosAtivos($tenantId)
            ];

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'data' => $dados
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao carregar dashboard: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Contar total de alunos ativos
     */
    private function contarAlunos(int $tenantId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total FROM usuarios 
             WHERE tenant_id = :tenant_id AND papel = 'aluno' AND ativo = 1"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Contar total de turmas ativas
     */
    private function contarTurmas(int $tenantId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total FROM turmas 
             WHERE tenant_id = :tenant_id AND ativo = 1"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Contar total de professores ativos
     */
    private function contarProfessores(int $tenantId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total FROM usuarios 
             WHERE tenant_id = :tenant_id AND papel = 'professor' AND ativo = 1"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Contar total de modalidades
     */
    private function contarModalidades(int $tenantId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total FROM modalidades 
             WHERE tenant_id = :tenant_id AND ativo = 1"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Contar check-ins do dia atual
     */
    private function contarCheckinsHoje(int $tenantId): int
    {
        $hoje = date('Y-m-d');
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total FROM checkins c
             INNER JOIN turmas t ON c.turma_id = t.id
             INNER JOIN dias d ON t.dia_id = d.id
             WHERE t.tenant_id = :tenant_id AND DATE(d.data) = :data"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'data' => $hoje]);
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Contar matrículas ativas
     */
    private function contarMatriculasAtivas(int $tenantId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total FROM matriculas 
             WHERE tenant_id = :tenant_id AND ativo = 1"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Calcular receita do mês
     */
    private function calcularReceitaMes(int $tenantId): array
    {
        $mesAtual = date('Y-m');
        $ano = (int)date('Y');
        $mes = (int)date('m');
        
        // Total pago
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(valor), 0) as total FROM pagamentos 
             WHERE tenant_id = :tenant_id 
             AND YEAR(data_pagamento) = :ano 
             AND MONTH(data_pagamento) = :mes
             AND status IN ('concluido', 'processando')"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'ano' => $ano, 'mes' => $mes]);
        $totalPago = (float) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Total pendente
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(valor), 0) as total FROM pagamentos 
             WHERE tenant_id = :tenant_id 
             AND YEAR(data_vencimento) = :ano 
             AND MONTH(data_vencimento) = :mes
             AND status IN ('pendente', 'atrasado')"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'ano' => $ano, 'mes' => $mes]);
        $totalPendente = (float) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        return [
            'pago' => $totalPago,
            'pendente' => $totalPendente,
            'total' => $totalPago + $totalPendente,
            'mes' => $mesAtual
        ];
    }

    /**
     * Contar contratos ativos
     */
    private function contarContratosAtivos(int $tenantId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total FROM contratos 
             WHERE tenant_id = :tenant_id AND status = 'ativo'"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * GET /admin/dashboard/turmas-por-modalidade
     * Retorna quantidade de turmas por modalidade
     */
    public function turmasPorModalidade(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');

        try {
            $stmt = $this->db->prepare(
                "SELECT m.id, m.nome, COUNT(t.id) as total
                 FROM modalidades m
                 LEFT JOIN turmas t ON m.id = t.modalidade_id AND t.ativo = 1
                 WHERE m.tenant_id = :tenant_id AND m.ativo = 1
                 GROUP BY m.id, m.nome
                 ORDER BY total DESC"
            );
            $stmt->execute(['tenant_id' => $tenantId]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'data' => $dados
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao buscar turmas por modalidade: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * GET /admin/dashboard/alunos-por-modalidade
     * Retorna quantidade de alunos por modalidade
     */
    public function alunosPorModalidade(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');

        try {
            $stmt = $this->db->prepare(
                "SELECT m.id, m.nome, COUNT(DISTINCT it.usuario_id) as total
                 FROM modalidades m
                 LEFT JOIN turmas t ON m.id = t.modalidade_id AND t.ativo = 1
                 LEFT JOIN inscricoes_turmas it ON t.id = it.turma_id AND it.ativo = 1
                 WHERE m.tenant_id = :tenant_id AND m.ativo = 1
                 GROUP BY m.id, m.nome
                 ORDER BY total DESC"
            );
            $stmt->execute(['tenant_id' => $tenantId]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'data' => $dados
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao buscar alunos por modalidade: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * GET /admin/dashboard/checkins-últimos-7-dias
     * Retorna checkins dos últimos 7 dias
     */
    public function checkinsÚltimos7Dias(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');

        try {
            $stmt = $this->db->prepare(
                "SELECT DATE(d.data) as data, COUNT(c.id) as total
                 FROM dias d
                 LEFT JOIN turmas t ON d.id = t.dia_id AND t.ativo = 1
                 LEFT JOIN checkins c ON t.id = c.turma_id
                 WHERE t.tenant_id = :tenant_id 
                 AND d.data >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                 AND d.data <= CURDATE()
                 GROUP BY DATE(d.data)
                 ORDER BY data ASC"
            );
            $stmt->execute(['tenant_id' => $tenantId]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'data' => $dados
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao buscar checkins: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }
}
