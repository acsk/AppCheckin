<?php
/**
 * AssinaturaController.php
 * Controlador para gerenciamento de assinaturas
 */

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class AssinaturaController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
    }

    /**
     * GET /admin/assinaturas
     * Lista assinaturas da academia do usuário
     */
    public function listar(Request $request, Response $response)
    {
        try {
            $params = $request->getQueryParams();
            $tenantId = $request->getAttribute('tenant_id');
            $status = $params['status'] ?? 'ativa';
            $planoId = $params['plano_id'] ?? null;
            $alunoId = $params['aluno_id'] ?? null;
            $pagina = (int)($params['pagina'] ?? 1);
            $limite = min((int)($params['limite'] ?? 20), 100);
            $offset = ($pagina - 1) * $limite;

            // Query base
            $sql = "SELECT 
                a.id,
                a.aluno_id,
                al.nome as aluno_nome,
                al.cpf as aluno_cpf,
                a.plano_id,
                p.nome as plano_nome,
                p.valor,
                m.id as modalidade_id,
                m.nome as modalidade_nome,
                a.academia_id,
                ac.nome as academia_nome,
                a.status,
                a.data_inicio,
                a.data_vencimento,
                DATEDIFF(a.data_vencimento, CURDATE()) as dias_restantes,
                a.valor_mensal,
                a.forma_pagamento,
                p.ciclo_tipo,
                a.permite_recorrencia,
                a.renovacoes_restantes,
                a.motivo_cancelamento,
                a.data_cancelamento,
                a.criado_em,
                a.atualizado_em
            FROM assinaturas a
            INNER JOIN alunos al ON a.aluno_id = al.id
            INNER JOIN planos p ON a.plano_id = p.id
            INNER JOIN modalidades m ON p.modalidade_id = m.id
            INNER JOIN academias ac ON a.academia_id = ac.id
            WHERE a.academia_id = ?";

            $bindings = [$tenantId];

            // Filtros
            if ($status !== 'todas') {
                $sql .= " AND a.status = ?";
                $bindings[] = $status;
            }

            if ($planoId) {
                $sql .= " AND a.plano_id = ?";
                $bindings[] = $planoId;
            }

            if ($alunoId) {
                $sql .= " AND a.aluno_id = ?";
                $bindings[] = $alunoId;
            }

            // Contar total
            $countStmt = $this->db->prepare(str_replace('SELECT a.id, a.aluno_id', 'SELECT COUNT(*) as total', $sql));
            $countStmt->execute($bindings);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Executar query com paginação
            $sql .= " ORDER BY a.data_vencimento ASC LIMIT ? OFFSET ?";
            $bindings[] = $limite;
            $bindings[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $assinaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Assinaturas listadas com sucesso',
                'data' => [
                    'assinaturas' => $assinaturas,
                    'paginacao' => [
                        'total' => $total,
                        'pagina' => $pagina,
                        'limite' => $limite,
                        'total_paginas' => ceil($total / $limite)
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao listar assinaturas',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * GET /superadmin/assinaturas
     * Lista assinaturas de todas as academias (SuperAdmin)
     */
    public function listarTodas(Request $request, Response $response)
    {
        try {
            $params = $request->getQueryParams();
            $tenantId = $params['tenant_id'] ?? null;
            $status = $params['status'] ?? 'ativa';
            $pagina = (int)($params['pagina'] ?? 1);
            $limite = min((int)($params['limite'] ?? 20), 100);
            $offset = ($pagina - 1) * $limite;

            $sql = "SELECT 
                a.id,
                a.aluno_id,
                al.nome as aluno_nome,
                al.cpf as aluno_cpf,
                a.plano_id,
                p.nome as plano_nome,
                p.valor,
                m.id as modalidade_id,
                m.nome as modalidade_nome,
                a.academia_id,
                ac.nome as academia_nome,
                a.status,
                a.data_inicio,
                a.data_vencimento,
                DATEDIFF(a.data_vencimento, CURDATE()) as dias_restantes,
                a.valor_mensal,
                a.forma_pagamento,
                a.criado_em,
                a.atualizado_em
            FROM assinaturas a
            INNER JOIN alunos al ON a.aluno_id = al.id
            INNER JOIN planos p ON a.plano_id = p.id
            INNER JOIN modalidades m ON p.modalidade_id = m.id
            INNER JOIN academias ac ON a.academia_id = ac.id
            WHERE 1=1";

            $bindings = [];

            if ($tenantId) {
                $sql .= " AND a.academia_id = ?";
                $bindings[] = $tenantId;
            }

            if ($status !== 'todas') {
                $sql .= " AND a.status = ?";
                $bindings[] = $status;
            }

            $countSql = str_replace('SELECT a.id', 'SELECT COUNT(*) as total', $sql);
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($bindings);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            $sql .= " ORDER BY a.data_vencimento ASC LIMIT ? OFFSET ?";
            $bindings[] = $limite;
            $bindings[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $assinaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Assinaturas listadas com sucesso',
                'data' => [
                    'assinaturas' => $assinaturas,
                    'paginacao' => [
                        'total' => $total,
                        'pagina' => $pagina,
                        'limite' => $limite,
                        'total_paginas' => ceil($total / $limite)
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao listar assinaturas',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * GET /admin/assinaturas/:id
     * Buscar detalhes de uma assinatura
     */
    public function buscar(Request $request, Response $response, array $args)
    {
        try {
            $id = $args['id'];
            $tenantId = $request->getAttribute('tenant_id');

            $sql = "SELECT 
                a.*,
                al.id as aluno_id,
                al.nome as aluno_nome,
                al.cpf as aluno_cpf,
                al.email as aluno_email,
                al.telefone,
                al.data_nascimento,
                p.id as plano_id,
                p.nome as plano_nome,
                p.valor,
                p.ciclo_tipo,
                p.checkins_semana,
                m.id as modalidade_id,
                m.nome as modalidade_nome,
                ac.id as academia_id,
                ac.nome as academia_nome,
                ac.email as academia_email
            FROM assinaturas a
            INNER JOIN alunos al ON a.aluno_id = al.id
            INNER JOIN planos p ON a.plano_id = p.id
            INNER JOIN modalidades m ON p.modalidade_id = m.id
            INNER JOIN academias ac ON a.academia_id = ac.id
            WHERE a.id = ? AND a.academia_id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $tenantId]);
            $assinatura = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$assinatura) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Assinatura não encontrada'
                ], JSON_UNESCAPED_UNICODE));

                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Buscar histórico de renovações
            $renewalSql = "SELECT * FROM assinatura_renovacoes WHERE assinatura_id = ? ORDER BY data_renovacao DESC";
            $renewalStmt = $this->db->prepare($renewalSql);
            $renewalStmt->execute([$id]);
            $renovacoes = $renewalStmt->fetchAll(PDO::FETCH_ASSOC);

            $assinatura['historico_renovacoes'] = $renovacoes;

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Assinatura encontrada',
                'data' => [
                    'assinatura' => $assinatura
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao buscar assinatura',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * POST /admin/assinaturas
     * Criar nova assinatura
     */
    public function criar(Request $request, Response $response)
    {
        try {
            $body = $request->getParsedBody();
            $tenantId = $request->getAttribute('tenant_id');

            // Validações
            $alunoId = $body['aluno_id'] ?? null;
            $planoId = $body['plano_id'] ?? null;
            $dataInicio = $body['data_inicio'] ?? null;
            $formaPagamento = $body['forma_pagamento'] ?? 'dinheiro';
            $renovacoes = $body['renovacoes'] ?? 0;

            if (!$alunoId || !$planoId || !$dataInicio) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Campos obrigatórios faltando'
                ], JSON_UNESCAPED_UNICODE));

                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Verificar se aluno existe
            $alunoStmt = $this->db->prepare("SELECT id FROM alunos WHERE id = ? AND academia_id = ?");
            $alunoStmt->execute([$alunoId, $tenantId]);
            if (!$alunoStmt->fetch()) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Aluno não encontrado'
                ], JSON_UNESCAPED_UNICODE));

                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Buscar plano
            $planoStmt = $this->db->prepare("SELECT * FROM planos WHERE id = ? AND academia_id = ?");
            $planoStmt->execute([$planoId, $tenantId]);
            $plano = $planoStmt->fetch(PDO::FETCH_ASSOC);

            if (!$plano) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Plano não encontrado'
                ], JSON_UNESCAPED_UNICODE));

                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Verificar se aluno já tem assinatura ativa para este plano
            $existsStmt = $this->db->prepare(
                "SELECT id FROM assinaturas WHERE aluno_id = ? AND plano_id = ? AND status = 'ativa'"
            );
            $existsStmt->execute([$alunoId, $planoId]);
            if ($existsStmt->fetch()) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Aluno já possui assinatura ativa para este plano'
                ], JSON_UNESCAPED_UNICODE));

                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }

            // Calcular data de vencimento
            $cicloTipo = $plano['ciclo_tipo'];
            $dataVencimento = $this->calcularDataVencimento($dataInicio, $cicloTipo);

            // Inserir assinatura
            $sql = "INSERT INTO assinaturas 
                (aluno_id, plano_id, academia_id, status, data_inicio, data_vencimento, valor_mensal, forma_pagamento, ciclo_tipo, permite_recorrencia, renovacoes_restantes, criado_em, atualizado_em)
                VALUES (?, ?, ?, 'ativa', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $alunoId,
                $planoId,
                $tenantId,
                $dataInicio,
                $dataVencimento,
                $plano['valor'],
                $formaPagamento,
                $cicloTipo,
                $plano['permite_recorrencia'] ?? true,
                $renovacoes
            ]);

            $novaAssinaturaId = $this->db->lastInsertId();

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Assinatura criada com sucesso',
                'data' => [
                    'assinatura' => [
                        'id' => $novaAssinaturaId,
                        'aluno_id' => $alunoId,
                        'plano_id' => $planoId,
                        'status' => 'ativa',
                        'data_inicio' => $dataInicio,
                        'data_vencimento' => $dataVencimento,
                        'valor_mensal' => $plano['valor'],
                        'forma_pagamento' => $formaPagamento,
                        'renovacoes_restantes' => $renovacoes,
                        'criado_em' => date('Y-m-d\TH:i:s\Z')
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao criar assinatura',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * POST /admin/assinaturas/:id/suspender
     * Suspender assinatura
     */
    public function suspender(Request $request, Response $response, array $args)
    {
        try {
            $id = $args['id'];
            $tenantId = $request->getAttribute('tenant_id');
            $body = $request->getParsedBody();
            $motivo = $body['motivo'] ?? 'Não especificado';
            $dataSuspensao = $body['data_suspensao'] ?? date('Y-m-d');

            // Verificar se assinatura existe
            $stmt = $this->db->prepare(
                "SELECT id, status FROM assinaturas WHERE id = ? AND academia_id = ?"
            );
            $stmt->execute([$id, $tenantId]);
            $assinatura = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$assinatura) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Assinatura não encontrada'
                ], JSON_UNESCAPED_UNICODE));

                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            if ($assinatura['status'] !== 'ativa') {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Apenas assinaturas ativas podem ser suspensas'
                ], JSON_UNESCAPED_UNICODE));

                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }

            // Atualizar assinatura
            $updateStmt = $this->db->prepare(
                "UPDATE assinaturas SET status = 'suspensa', data_suspensao = ?, motivo_suspensao = ?, atualizado_em = NOW() WHERE id = ?"
            );
            $updateStmt->execute([$dataSuspensao, $motivo, $id]);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Assinatura suspensa com sucesso',
                'data' => [
                    'assinatura' => [
                        'id' => $id,
                        'status' => 'suspensa',
                        'motivo_suspensao' => $motivo,
                        'data_suspensao' => $dataSuspensao,
                        'atualizado_em' => date('Y-m-d\TH:i:s\Z')
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao suspender assinatura',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * POST /admin/assinaturas/:id/cancelar
     * Cancelar assinatura
     */
    public function cancelar(Request $request, Response $response, array $args)
    {
        try {
            $id = $args['id'];
            $tenantId = $request->getAttribute('tenant_id');
            $body = $request->getParsedBody();
            $motivo = $body['motivo'] ?? 'Não especificado';
            $dataCancelamento = $body['data_cancelamento'] ?? date('Y-m-d');

            $stmt = $this->db->prepare(
                "SELECT id, status, data_inicio, valor_mensal FROM assinaturas WHERE id = ? AND academia_id = ?"
            );
            $stmt->execute([$id, $tenantId]);
            $assinatura = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$assinatura) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Assinatura não encontrada'
                ], JSON_UNESCAPED_UNICODE));

                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            if ($assinatura['status'] === 'cancelada') {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Assinatura já foi cancelada'
                ], JSON_UNESCAPED_UNICODE));

                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }

            // Calcular dias usados e reembolso
            $dataInicio = new \DateTime($assinatura['data_inicio']);
            $dataCancelStr = new \DateTime($dataCancelamento);
            $diasUsados = $dataInicio->diff($dataCancelStr)->days;

            // Atualizar assinatura
            $updateStmt = $this->db->prepare(
                "UPDATE assinaturas SET status = 'cancelada', data_cancelamento = ?, motivo_cancelamento = ?, atualizado_em = NOW() WHERE id = ?"
            );
            $updateStmt->execute([$dataCancelamento, $motivo, $id]);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => 'Assinatura cancelada com sucesso',
                'data' => [
                    'assinatura' => [
                        'id' => $id,
                        'status' => 'cancelada',
                        'motivo_cancelamento' => $motivo,
                        'data_cancelamento' => $dataCancelamento,
                        'dias_usados' => $diasUsados,
                        'atualizado_em' => date('Y-m-d\TH:i:s\Z')
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao cancelar assinatura',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Calcular data de vencimento baseado no tipo de ciclo
     */
    private function calcularDataVencimento(string $dataInicio, string $cicloTipo): string
    {
        $data = new \DateTime($dataInicio);

        switch ($cicloTipo) {
            case 'semanal':
                $data->add(new \DateInterval('P7D'));
                break;
            case 'mensal':
                $data->add(new \DateInterval('P1M'));
                break;
            case 'trimestral':
                $data->add(new \DateInterval('P3M'));
                break;
            case 'semestral':
                $data->add(new \DateInterval('P6M'));
                break;
            case 'anual':
                $data->add(new \DateInterval('P1Y'));
                break;
            default:
                $data->add(new \DateInterval('P1M'));
        }

        return $data->format('Y-m-d');
    }
}
