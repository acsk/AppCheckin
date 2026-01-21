<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class DashboardController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
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
