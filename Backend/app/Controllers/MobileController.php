<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Usuario;

/**
 * MobileController
 * 
 * Controller específico para as necessidades do App Mobile.
 * Contém endpoints otimizados para consumo mobile com dados agregados.
 * 
 * @package App\Controllers
 * @author App Checkin Team
 * @version 1.0.0
 */
class MobileController
{
    private Usuario $usuarioModel;
    private \PDO $db;

    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
        $this->usuarioModel = new Usuario($this->db);
    }

    /**
     * Retorna o perfil completo do usuário logado com estatísticas
     * Endpoint otimizado para a tela de perfil do App Mobile
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @return Response JSON com perfil completo
     * 
     * @api GET /mobile/perfil
     */
    public function perfil(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $tenantId = $request->getAttribute('tenantId');
        
        // Buscar dados do usuário
        $usuario = $this->usuarioModel->findById($userId, $tenantId);

        if (!$usuario) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Usuário não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Buscar estatísticas de check-ins
        $estatisticas = $this->getEstatisticasCheckin($userId, $tenantId);

        // Buscar informações do tenant atual
        $tenant = $this->getTenantInfo($tenantId);

        // Buscar plano do usuário
        $plano = $this->getPlanoUsuario($userId, $tenantId);

        // Montar resposta
        $perfil = [
            'id' => $usuario['id'],
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'email_global' => $usuario['email_global'] ?? $usuario['email'],
            'cpf' => $usuario['cpf'] ?? null,
            'telefone' => $usuario['telefone'] ?? null,
            'foto_base64' => $usuario['foto_base64'] ?? null,
            'data_nascimento' => $usuario['data_nascimento'] ?? null,
            'role_id' => $usuario['role_id'],
            'role_nome' => $this->getRoleName($usuario['role_id']),
            'membro_desde' => $usuario['created_at'],
            'tenant' => $tenant,
            'plano' => $plano,
            'estatisticas' => $estatisticas,
        ];

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $perfil
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Retorna estatísticas de check-in do usuário
     */
    private function getEstatisticasCheckin(int $userId, ?int $tenantId): array
    {
        // Total de check-ins do usuário
        $sqlTotal = "SELECT COUNT(*) as total FROM checkins WHERE usuario_id = :user_id";
        $stmt = $this->db->prepare($sqlTotal);
        $stmt->execute(['user_id' => $userId]);
        $totalCheckins = (int) ($stmt->fetch()['total'] ?? 0);

        // Check-ins do mês atual
        $sqlMes = "SELECT COUNT(*) as total FROM checkins 
                   WHERE usuario_id = :user_id 
                   AND MONTH(data_checkin) = MONTH(CURRENT_DATE())
                   AND YEAR(data_checkin) = YEAR(CURRENT_DATE())";
        $stmtMes = $this->db->prepare($sqlMes);
        $stmtMes->execute(['user_id' => $userId]);
        $checkinsMes = (int) ($stmtMes->fetch()['total'] ?? 0);

        // Sequência atual (dias consecutivos)
        $sequencia = $this->calcularSequencia($userId);

        // Último check-in
        $sqlUltimo = "SELECT c.data_checkin, h.hora, d.data
                      FROM checkins c
                      INNER JOIN horarios h ON c.horario_id = h.id
                      INNER JOIN dias d ON h.dia_id = d.id
                      WHERE c.usuario_id = :user_id
                      ORDER BY d.data DESC, h.hora DESC LIMIT 1";
        
        $stmtUltimo = $this->db->prepare($sqlUltimo);
        $stmtUltimo->execute(['user_id' => $userId]);
        $ultimoCheckin = $stmtUltimo->fetch();

        return [
            'total_checkins' => $totalCheckins,
            'checkins_mes' => $checkinsMes,
            'sequencia_dias' => $sequencia,
            'ultimo_checkin' => $ultimoCheckin ? [
                'data' => $ultimoCheckin['data'] ?? date('Y-m-d', strtotime($ultimoCheckin['data_checkin'])),
                'hora' => $ultimoCheckin['hora'] ?? date('H:i:s', strtotime($ultimoCheckin['data_checkin']))
            ] : null,
        ];
    }

    /**
     * Calcula a sequência de dias consecutivos de check-in
     */
    private function calcularSequencia(int $userId): int
    {
        // Busca datas únicas de check-in usando a tabela dias (relacionada aos horarios)
        $sql = "SELECT DISTINCT d.data 
                FROM checkins c
                INNER JOIN horarios h ON c.horario_id = h.id
                INNER JOIN dias d ON h.dia_id = d.id
                WHERE c.usuario_id = :user_id
                ORDER BY d.data DESC LIMIT 30";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $datas = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($datas)) {
            return 0;
        }

        $sequencia = 0;
        $dataEsperada = new \DateTime();
        
        foreach ($datas as $data) {
            $dataCheckin = new \DateTime($data);
            $diff = $dataEsperada->diff($dataCheckin)->days;
            
            // Se o check-in é de hoje ou ontem (início da sequência)
            if ($sequencia === 0 && $diff <= 1) {
                $sequencia = 1;
                $dataEsperada = $dataCheckin;
            } 
            // Se é o dia anterior ao esperado (continua sequência)
            elseif ($dataEsperada->modify('-1 day')->format('Y-m-d') === $dataCheckin->format('Y-m-d')) {
                $sequencia++;
                $dataEsperada = $dataCheckin;
            } 
            // Quebra na sequência
            else {
                break;
            }
        }

        return $sequencia;
    }

    /**
     * Busca informações do tenant
     */
    private function getTenantInfo(?int $tenantId): ?array
    {
        if (!$tenantId) {
            return null;
        }

        $sql = "SELECT id, nome, slug, email, telefone 
                FROM tenants WHERE id = :id AND ativo = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $tenantId]);
        $tenant = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $tenant ?: null;
    }

    /**
     * Busca plano do usuário no tenant
     */
    private function getPlanoUsuario(int $userId, ?int $tenantId): ?array
    {
        if (!$tenantId) {
            return null;
        }

        $sql = "SELECT p.id, p.nome, p.valor, p.duracao_dias, p.descricao,
                       ut.data_inicio, ut.data_fim, ut.status as vinculo_status
                FROM usuario_tenant ut
                INNER JOIN planos p ON ut.plano_id = p.id
                WHERE ut.usuario_id = :user_id 
                AND ut.tenant_id = :tenant_id
                AND ut.status = 'ativo'
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);
        $plano = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $plano ?: null;
    }

    /**
     * Retorna o nome da role
     */
    private function getRoleName(?int $roleId): string
    {
        $roles = [
            1 => 'Aluno',
            2 => 'Admin',
            3 => 'Super Admin'
        ];
        return $roles[$roleId] ?? 'Usuário';
    }

    /**
     * Lista os tenants disponíveis para o usuário logado
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @return Response JSON com lista de tenants
     * 
     * @api GET /mobile/tenants
     */
    public function tenants(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        
        $tenants = $this->usuarioModel->getTenantsByUsuario($userId);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'tenants' => $tenants,
                'total' => count($tenants)
            ]
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Registra um check-in do usuário
     * Nota: Requer que exista um horário disponível para hoje
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @return Response JSON com confirmação
     * 
     * @api POST /mobile/checkin
     */
    public function registrarCheckin(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $tenantId = $request->getAttribute('tenantId');
        $body = $request->getParsedBody() ?? [];
        
        // O app pode enviar o horario_id específico ou buscar o horário atual do dia
        $horarioId = $body['horario_id'] ?? null;
        
        if (!$horarioId) {
            // Buscar horário disponível para hoje no momento atual
            $hoje = date('Y-m-d');
            $horaAtual = date('H:i:s');
            
            $sqlHorario = "SELECT h.id 
                           FROM horarios h
                           INNER JOIN dias d ON h.dia_id = d.id
                           WHERE d.data = :data 
                           AND h.ativo = 1
                           ORDER BY ABS(TIMEDIFF(h.hora, :hora)) ASC
                           LIMIT 1";
            $stmtHorario = $this->db->prepare($sqlHorario);
            $stmtHorario->execute(['data' => $hoje, 'hora' => $horaAtual]);
            $horario = $stmtHorario->fetch();
            
            if (!$horario) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Não há horário disponível para check-in hoje'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            $horarioId = $horario['id'];
        }

        // Verificar se já fez check-in neste horário
        $sqlVerifica = "SELECT id FROM checkins 
                        WHERE usuario_id = :user_id 
                        AND horario_id = :horario_id";
        $stmtVerifica = $this->db->prepare($sqlVerifica);
        $stmtVerifica->execute([
            'user_id' => $userId,
            'horario_id' => $horarioId
        ]);

        if ($stmtVerifica->fetch()) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Você já realizou check-in neste horário!',
                'ja_fez_checkin' => true
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Registrar check-in
        $sqlInsert = "INSERT INTO checkins (usuario_id, horario_id) 
                      VALUES (:user_id, :horario_id)";
        $stmtInsert = $this->db->prepare($sqlInsert);
        $result = $stmtInsert->execute([
            'user_id' => $userId,
            'horario_id' => $horarioId
        ]);

        if ($result) {
            // Buscar estatísticas atualizadas
            $estatisticas = $this->getEstatisticasCheckin($userId, $tenantId);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Check-in realizado com sucesso!',
                'data' => [
                    'checkin' => [
                        'data' => date('Y-m-d'),
                        'hora' => date('H:i:s')
                    ],
                    'estatisticas' => $estatisticas
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        }

        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => 'Erro ao registrar check-in'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    /**
     * Lista o histórico de check-ins do usuário
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @return Response JSON com histórico
     * 
     * @api GET /mobile/checkins
     */
    public function historicoCheckins(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $queryParams = $request->getQueryParams();
        
        $limit = min((int) ($queryParams['limit'] ?? 30), 100);
        $offset = (int) ($queryParams['offset'] ?? 0);

        $sql = "SELECT c.id, c.data_checkin, c.created_at,
                       d.data, h.hora
                FROM checkins c
                INNER JOIN horarios h ON c.horario_id = h.id
                INNER JOIN dias d ON h.dia_id = d.id
                WHERE c.usuario_id = :user_id
                ORDER BY d.data DESC, h.hora DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        $checkins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Contar total
        $sqlCount = "SELECT COUNT(*) as total FROM checkins WHERE usuario_id = :user_id";
        $stmtCount = $this->db->prepare($sqlCount);
        $stmtCount->execute(['user_id' => $userId]);
        $total = (int) $stmtCount->fetch()['total'];

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'checkins' => $checkins,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
