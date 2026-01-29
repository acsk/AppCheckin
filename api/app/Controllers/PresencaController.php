<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Checkin;
use App\Models\Turma;
use App\Models\Professor;
use App\Models\Usuario;
use PDO;

/**
 * Controller para gerenciamento de presenças pelo professor
 * Permite que professores confirmem a presença dos alunos que fizeram check-in
 * 
 * Papéis permitidos (via tenant_usuario_papel):
 * - 2: professor
 * - 3: admin
 * - 4: super_admin (acesso total)
 * 
 * O papel é definido POR TENANT via tabela tenant_usuario_papel
 * Um usuário pode ter múltiplos papéis no mesmo tenant
 */
class PresencaController
{
    private PDO $db;
    private Checkin $checkinModel;
    private Turma $turmaModel;
    private Professor $professorModel;

    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
        $this->checkinModel = new Checkin($this->db);
        $this->turmaModel = new Turma($this->db);
        $this->professorModel = new Professor($this->db);
    }

    /**
     * Obter ID do professor vinculado ao usuário no tenant
     * Agora usa a coluna usuario_id diretamente
     */
    private function getProfessorIdDoUsuario(int $userId, int $tenantId): ?int
    {
        // Primeiro verifica se o usuário tem papel de professor no tenant
        $stmtPapel = $this->db->prepare(
            "SELECT 1 FROM tenant_usuario_papel 
             WHERE usuario_id = :user_id AND tenant_id = :tenant_id AND papel_id = 2 AND ativo = 1"
        );
        $stmtPapel->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);
        
        if (!$stmtPapel->fetch()) {
            return null; // Não tem papel de professor neste tenant
        }
        
        // Busca o professor vinculado ao usuário
        $stmt = $this->db->prepare(
            "SELECT p.id 
             FROM professores p
             WHERE p.usuario_id = :user_id AND p.ativo = 1
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int) $result['id'] : null;
    }
    
    /**
     * Verifica se o usuário tem papel de professor ou admin no tenant atual
     * Usa o atributo 'papel' adicionado pelo ProfessorMiddleware
     */
    private function isProfessorOuAdmin(Request $request): bool
    {
        $papel = $request->getAttribute('papel');
        return $papel && (int) $papel['id'] >= 2;
    }
    
    /**
     * Verifica se o usuário é apenas professor (não admin)
     */
    private function isApenasProf(Request $request): bool
    {
        $papel = $request->getAttribute('papel');
        return $papel && (int) $papel['id'] === 2;
    }

    /**
     * Listar alunos com check-in em uma turma para controle de presença
     * GET /admin/turmas/{turmaId}/presencas
     */
    public function listarPresencas(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $turmaId = (int) $args['turmaId'];

        try {
            // Verificar se turma existe e pertence ao tenant
            $turma = $this->turmaModel->findById($turmaId, $tenantId);
            
            if (!$turma) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Turma não encontrada'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }

            // Listar check-ins da turma
            $checkins = $this->checkinModel->listarCheckinsTurma($turmaId, $tenantId);

            // Obter estatísticas
            $estatisticas = $this->checkinModel->estatisticasPresencaTurma($turmaId);

            // Formatar resposta
            $checkinsFormatados = array_map(function($c) {
                return [
                    'checkin_id' => (int) $c['id'],
                    'aluno' => [
                        'id' => (int) $c['usuario_id'],
                        'nome' => $c['aluno_nome'],
                        'email' => $c['aluno_email']
                    ],
                    'data_checkin' => $c['data_checkin'],
                    'presenca' => [
                        'status' => $c['presente'] === null ? 'nao_verificado' : ($c['presente'] ? 'presente' : 'falta'),
                        'confirmada_em' => $c['presenca_confirmada_em'],
                        'confirmada_por' => $c['confirmado_por_nome']
                    ]
                ];
            }, $checkins);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'data' => [
                    'turma' => [
                        'id' => (int) $turma['id'],
                        'nome' => $turma['nome'],
                        'professor' => $turma['professor_nome'],
                        'modalidade' => $turma['modalidade_nome'],
                        'horario_inicio' => $turma['horario_inicio'],
                        'horario_fim' => $turma['horario_fim'],
                        'dia_data' => $turma['dia_data'] ?? null
                    ],
                    'checkins' => $checkinsFormatados,
                    'estatisticas' => [
                        'total_checkins' => (int) $estatisticas['total_checkins'],
                        'presentes' => (int) $estatisticas['presentes'],
                        'faltas' => (int) $estatisticas['faltas'],
                        'nao_verificados' => (int) $estatisticas['nao_verificados'],
                        'percentual_presenca' => $estatisticas['total_checkins'] > 0 
                            ? round(($estatisticas['presentes'] / $estatisticas['total_checkins']) * 100, 1) 
                            : 0
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao listar presenças: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Marcar presença de um aluno específico
     * PATCH /admin/checkins/{checkinId}/presenca
     * Body: { "presente": true/false }
     */
    public function marcarPresenca(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $userId = $request->getAttribute('userId');
        $checkinId = (int) $args['checkinId'];
        $body = $request->getParsedBody();

        try {
            // Validar body
            if (!isset($body['presente'])) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Campo "presente" é obrigatório (true/false)'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }

            $presente = filter_var($body['presente'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($presente === null) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Campo "presente" deve ser true ou false'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }

            // Verificar se check-in existe
            $checkin = $this->checkinModel->findById($checkinId);
            if (!$checkin) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Check-in não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }

            // Marcar presença
            $result = $this->checkinModel->marcarPresenca($checkinId, $presente, $userId);

            if (!$result) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Erro ao marcar presença'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
            }

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => $presente ? 'Presença confirmada' : 'Falta registrada',
                'data' => [
                    'checkin_id' => $checkinId,
                    'presente' => $presente,
                    'confirmado_em' => date('Y-m-d H:i:s')
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao marcar presença: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Marcar presença em lote para uma turma
     * POST /admin/turmas/{turmaId}/presencas/lote
     * Body: { "checkin_ids": [1, 2, 3], "presente": true/false }
     * ou { "marcar_todos": true, "presente": true/false }
     */
    public function marcarPresencaLote(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $userId = $request->getAttribute('userId');
        $turmaId = (int) $args['turmaId'];
        $body = $request->getParsedBody();

        try {
            // Verificar se turma existe e pertence ao tenant
            $turma = $this->turmaModel->findById($turmaId, $tenantId);
            
            if (!$turma) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Turma não encontrada'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }

            // Validar body
            if (!isset($body['presente'])) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Campo "presente" é obrigatório (true/false)'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }

            $presente = filter_var($body['presente'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($presente === null) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Campo "presente" deve ser true ou false'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }

            $checkinIds = [];

            // Se marcar_todos = true, buscar todos os check-ins da turma
            if (!empty($body['marcar_todos']) && $body['marcar_todos'] === true) {
                $checkins = $this->checkinModel->listarCheckinsTurma($turmaId, $tenantId);
                $checkinIds = array_column($checkins, 'id');
            } 
            // Senão, usar os IDs fornecidos
            elseif (!empty($body['checkin_ids']) && is_array($body['checkin_ids'])) {
                $checkinIds = array_map('intval', $body['checkin_ids']);
            } else {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Forneça "checkin_ids" (array) ou "marcar_todos": true'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }

            if (empty($checkinIds)) {
                $response->getBody()->write(json_encode([
                    'type' => 'info',
                    'message' => 'Nenhum check-in para atualizar'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
            }

            // Marcar presença em lote
            $atualizados = $this->checkinModel->marcarPresencaEmLote($checkinIds, $presente, $userId);

            // Obter estatísticas atualizadas
            $estatisticas = $this->checkinModel->estatisticasPresencaTurma($turmaId);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => "$atualizados presença(s) " . ($presente ? 'confirmada(s)' : 'marcada(s) como falta'),
                'data' => [
                    'turma_id' => $turmaId,
                    'atualizados' => $atualizados,
                    'presente' => $presente,
                    'estatisticas' => [
                        'total_checkins' => (int) $estatisticas['total_checkins'],
                        'presentes' => (int) $estatisticas['presentes'],
                        'faltas' => (int) $estatisticas['faltas'],
                        'nao_verificados' => (int) $estatisticas['nao_verificados']
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao marcar presenças em lote: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Confirmar presença de toda a turma (usado pelo professor no app mobile)
     * POST /professor/turmas/{turmaId}/confirmar-presenca
     * Body: { 
     *   "presencas": { "checkin_id": true/false, ... },
     *   "remover_faltantes": true/false (opcional, default: true)
     * }
     * 
     * IMPORTANTE: Se remover_faltantes = true, os check-ins de alunos marcados como falta
     * serão DELETADOS, liberando o crédito semanal para o aluno remarcar em outra aula.
     */
    public function confirmarPresencaTurma(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $userId = $request->getAttribute('userId');
        $turmaId = (int) $args['turmaId'];
        $body = $request->getParsedBody();

        try {
            // Verificar se turma existe e pertence ao tenant
            $turma = $this->turmaModel->findById($turmaId, $tenantId);
            
            if (!$turma) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Turma não encontrada'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }

            // Se for apenas professor (papel_id = 2), verificar se a turma é dele
            if ($this->isApenasProf($request)) {
                $professorId = $this->getProfessorIdDoUsuario($userId, $tenantId);
                
                if (!$professorId) {
                    $response->getBody()->write(json_encode([
                        'type' => 'error',
                        'code' => 'PROFESSOR_NOT_FOUND',
                        'message' => 'Cadastro de professor não encontrado para este usuário'
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
                }

                if ((int) $turma['professor_id'] !== $professorId) {
                    $response->getBody()->write(json_encode([
                        'type' => 'error',
                        'code' => 'NOT_YOUR_CLASS',
                        'message' => 'Você só pode confirmar presença das suas próprias turmas'
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(403);
                }
            }

            // Validar body
            if (!isset($body['presencas']) || !is_array($body['presencas'])) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Campo "presencas" é obrigatório e deve ser um objeto {checkin_id: true/false}'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
            }

            $presencas = $body['presencas'];
            $removerFaltantes = isset($body['remover_faltantes']) 
                ? filter_var($body['remover_faltantes'], FILTER_VALIDATE_BOOLEAN) 
                : true; // Default: remover faltantes

            if (empty($presencas)) {
                $response->getBody()->write(json_encode([
                    'type' => 'info',
                    'message' => 'Nenhuma presença para confirmar'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
            }

            // Confirmar presenças
            $resultado = $this->checkinModel->confirmarPresencaTurma(
                $turmaId,
                $tenantId,
                $presencas,
                $userId,
                $removerFaltantes
            );

            // Obter estatísticas atualizadas
            $estatisticas = $this->checkinModel->estatisticasPresencaTurma($turmaId);

            $mensagem = "Presença confirmada: {$resultado['presencas']} presente(s), {$resultado['faltas']} falta(s)";
            if ($removerFaltantes && $resultado['checkins_removidos']['removidos'] > 0) {
                $mensagem .= ". {$resultado['checkins_removidos']['removidos']} check-in(s) de faltantes removido(s) (créditos liberados)";
            }

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => $mensagem,
                'data' => [
                    'turma_id' => $turmaId,
                    'turma_nome' => $turma['nome'],
                    'confirmados' => $resultado['confirmados'],
                    'presencas' => $resultado['presencas'],
                    'faltas' => $resultado['faltas'],
                    'checkins_removidos' => $resultado['checkins_removidos'],
                    'estatisticas' => [
                        'total_checkins' => (int) $estatisticas['total_checkins'],
                        'presentes' => (int) $estatisticas['presentes'],
                        'faltas' => (int) $estatisticas['faltas'],
                        'nao_verificados' => (int) $estatisticas['nao_verificados']
                    ],
                    'confirmado_em' => date('Y-m-d H:i:s'),
                    'confirmado_por' => $userId
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao confirmar presença: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Listar turmas do professor com check-ins pendentes
     * GET /professor/turmas/pendentes
     */
    public function listarTurmasPendentes(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $userId = $request->getAttribute('userId');

        try {
            // Se for apenas professor (papel_id = 2), buscar pelo professor_id vinculado
            if ($this->isApenasProf($request)) {
                $professorId = $this->getProfessorIdDoUsuario($userId, $tenantId);
                
                if (!$professorId) {
                    $response->getBody()->write(json_encode([
                        'type' => 'error',
                        'code' => 'PROFESSOR_NOT_FOUND',
                        'message' => 'Cadastro de professor não encontrado'
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
                }

                $turmas = $this->checkinModel->listarTurmasComCheckinsPendentes($professorId, $tenantId);
            } else {
                // Admin/Super Admin vê todas as turmas com pendências
                $stmt = $this->db->prepare(
                    "SELECT DISTINCT t.id, t.nome, t.horario_inicio, t.horario_fim,
                            t.professor_id,
                            p.nome as professor_nome,
                            d.data as dia_data,
                            m.nome as modalidade_nome,
                            m.icone as modalidade_icone,
                            m.cor as modalidade_cor,
                            (SELECT COUNT(*) FROM checkins c2 WHERE c2.turma_id = t.id AND c2.presente IS NULL) as pendentes,
                            (SELECT COUNT(*) FROM checkins c3 WHERE c3.turma_id = t.id) as total_checkins
                     FROM turmas t
                     INNER JOIN dias d ON t.dia_id = d.id
                     INNER JOIN modalidades m ON t.modalidade_id = m.id
                     INNER JOIN professores p ON t.professor_id = p.id
                     INNER JOIN checkins c ON c.turma_id = t.id
                     WHERE t.tenant_id = :tenant_id
                     AND t.ativo = 1
                     AND c.presente IS NULL
                     ORDER BY d.data DESC, t.horario_inicio DESC"
                );
                $stmt->execute(['tenant_id' => $tenantId]);
                $turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'data' => [
                    'turmas' => $turmas,
                    'total' => count($turmas)
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao listar turmas pendentes: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Listar check-ins de uma turma para o professor marcar presença
     * GET /professor/turmas/{turmaId}/checkins
     */
    public function listarCheckinsParaPresenca(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $userId = $request->getAttribute('userId');
        $turmaId = (int) $args['turmaId'];

        try {
            // Verificar se turma existe
            $turma = $this->turmaModel->findById($turmaId, $tenantId);
            
            if (!$turma) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Turma não encontrada'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }

            // Se for apenas professor (papel_id = 2), verificar se a turma é dele
            if ($this->isApenasProf($request)) {
                $professorId = $this->getProfessorIdDoUsuario($userId, $tenantId);
                
                if (!$professorId || (int) $turma['professor_id'] !== $professorId) {
                    $response->getBody()->write(json_encode([
                        'type' => 'error',
                        'code' => 'NOT_YOUR_CLASS',
                        'message' => 'Você só pode ver check-ins das suas próprias turmas'
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(403);
                }
            }

            // Listar check-ins da turma
            $checkins = $this->checkinModel->listarCheckinsTurma($turmaId, $tenantId);
            $estatisticas = $this->checkinModel->estatisticasPresencaTurma($turmaId);

            // Formatar resposta
            $checkinsFormatados = array_map(function($c) {
                return [
                    'checkin_id' => (int) $c['id'],
                    'aluno' => [
                        'id' => (int) $c['usuario_id'],
                        'nome' => $c['aluno_nome'],
                        'email' => $c['aluno_email']
                    ],
                    'data_checkin' => $c['data_checkin'],
                    'presenca' => [
                        'status' => $c['presente'] === null ? 'pendente' : ($c['presente'] ? 'presente' : 'falta'),
                        'confirmada_em' => $c['presenca_confirmada_em'],
                        'confirmada_por' => $c['confirmado_por_nome']
                    ]
                ];
            }, $checkins);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'data' => [
                    'turma' => [
                        'id' => (int) $turma['id'],
                        'nome' => $turma['nome'],
                        'professor' => $turma['professor_nome'],
                        'modalidade' => $turma['modalidade_nome'],
                        'horario_inicio' => $turma['horario_inicio'],
                        'horario_fim' => $turma['horario_fim'],
                        'dia_data' => $turma['dia_data'] ?? null
                    ],
                    'checkins' => $checkinsFormatados,
                    'estatisticas' => [
                        'total_checkins' => (int) ($estatisticas['total_checkins'] ?? 0),
                        'presentes' => (int) ($estatisticas['presentes'] ?? 0),
                        'faltas' => (int) ($estatisticas['faltas'] ?? 0),
                        'pendentes' => (int) ($estatisticas['nao_verificados'] ?? 0)
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao listar check-ins: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Remover check-ins de faltantes manualmente (libera créditos)
     * DELETE /professor/turmas/{turmaId}/faltantes
     */
    public function removerFaltantes(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $userId = $request->getAttribute('userId');
        $turmaId = (int) $args['turmaId'];

        try {
            // Verificar se turma existe
            $turma = $this->turmaModel->findById($turmaId, $tenantId);
            
            if (!$turma) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => 'Turma não encontrada'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }

            // Se for apenas professor (papel_id = 2), verificar se a turma é dele
            if ($this->isApenasProf($request)) {
                $professorId = $this->getProfessorIdDoUsuario($userId, $tenantId);
                
                if (!$professorId || (int) $turma['professor_id'] !== $professorId) {
                    $response->getBody()->write(json_encode([
                        'type' => 'error',
                        'code' => 'NOT_YOUR_CLASS',
                        'message' => 'Você só pode gerenciar suas próprias turmas'
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(403);
                }
            }

            // Remover check-ins de faltantes
            $resultado = $this->checkinModel->removerCheckinsFaltantes($turmaId, $tenantId);

            if ($resultado['removidos'] === 0) {
                $response->getBody()->write(json_encode([
                    'type' => 'info',
                    'message' => 'Nenhum check-in de faltante para remover',
                    'data' => $resultado
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
            }

            // Lista de alunos que tiveram crédito liberado
            $alunosLiberados = array_map(function($c) {
                return [
                    'id' => (int) $c['usuario_id'],
                    'nome' => $c['aluno_nome'],
                    'email' => $c['aluno_email']
                ];
            }, $resultado['checkins']);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'message' => "{$resultado['removidos']} check-in(s) de faltante(s) removido(s). Créditos liberados para remarcar.",
                'data' => [
                    'turma_id' => $turmaId,
                    'turma_nome' => $turma['nome'],
                    'removidos' => $resultado['removidos'],
                    'alunos_liberados' => $alunosLiberados
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao remover faltantes: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Dashboard do professor - resumo das turmas e presenças
     * GET /professor/dashboard
     */
    public function dashboardProfessor(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $userId = $request->getAttribute('userId');
        $papel = $request->getAttribute('papel');

        try {
            // Verificar se é professor (papel_id = 2) - admin usa outro dashboard
            if (!$papel || (int) $papel['id'] !== 2) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'code' => 'ACCESS_DENIED',
                    'message' => 'Este endpoint é exclusivo para professores. Admins devem usar o dashboard administrativo.',
                    'papel_atual' => $papel['nome'] ?? 'nenhum'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(403);
            }

            $professorId = $this->getProfessorIdDoUsuario($userId, $tenantId);
            
            if (!$professorId) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'code' => 'PROFESSOR_NOT_FOUND',
                    'message' => 'Cadastro de professor não encontrado. Verifique se seu email está vinculado a um professor.'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }

            // Buscar dados do professor
            $professor = $this->professorModel->findById($professorId, $tenantId);

            // Turmas do professor
            $turmas = $this->turmaModel->listarPorProfessor($professorId, $tenantId, true);

            // Turmas com check-ins pendentes
            $turmasPendentes = $this->checkinModel->listarTurmasComCheckinsPendentes($professorId, $tenantId);

            // Estatísticas gerais
            $stmt = $this->db->prepare(
                "SELECT 
                    COUNT(DISTINCT t.id) as total_turmas,
                    (SELECT COUNT(*) FROM checkins c 
                     INNER JOIN turmas t2 ON c.turma_id = t2.id 
                     WHERE t2.professor_id = :prof_id1 AND c.presente IS NULL) as checkins_pendentes,
                    (SELECT COUNT(*) FROM checkins c 
                     INNER JOIN turmas t3 ON c.turma_id = t3.id 
                     WHERE t3.professor_id = :prof_id2 
                     AND c.presente = 1 
                     AND MONTH(c.data_checkin) = MONTH(CURRENT_DATE())
                     AND YEAR(c.data_checkin) = YEAR(CURRENT_DATE())) as presencas_mes,
                    (SELECT COUNT(*) FROM checkins c 
                     INNER JOIN turmas t4 ON c.turma_id = t4.id 
                     WHERE t4.professor_id = :prof_id3 
                     AND c.presente = 0 
                     AND MONTH(c.data_checkin) = MONTH(CURRENT_DATE())
                     AND YEAR(c.data_checkin) = YEAR(CURRENT_DATE())) as faltas_mes
                 FROM turmas t
                 WHERE t.professor_id = :prof_id4
                 AND t.tenant_id = :tenant_id
                 AND t.ativo = 1"
            );
            $stmt->execute([
                'prof_id1' => $professorId,
                'prof_id2' => $professorId,
                'prof_id3' => $professorId,
                'prof_id4' => $professorId,
                'tenant_id' => $tenantId
            ]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'type' => 'success',
                'data' => [
                    'professor' => [
                        'id' => (int) $professor['id'],
                        'nome' => $professor['nome'],
                        'email' => $professor['email']
                    ],
                    'estatisticas' => [
                        'total_turmas' => (int) ($stats['total_turmas'] ?? 0),
                        'checkins_pendentes' => (int) ($stats['checkins_pendentes'] ?? 0),
                        'presencas_mes' => (int) ($stats['presencas_mes'] ?? 0),
                        'faltas_mes' => (int) ($stats['faltas_mes'] ?? 0)
                    ],
                    'turmas_pendentes' => $turmasPendentes,
                    'total_turmas_pendentes' => count($turmasPendentes)
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'type' => 'error',
                'message' => 'Erro ao carregar dashboard: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }
}
