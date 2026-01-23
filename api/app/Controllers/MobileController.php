<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Usuario;
use App\Models\Turma;
use App\Models\Checkin;
use App\Models\Wod;
use App\Models\WodBloco;
use App\Models\WodVariacao;

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
    private Turma $turmaModel;
    private Checkin $checkinModel;
    private Wod $wodModel;
    private WodBloco $wodBlocoModel;
    private WodVariacao $wodVariacaoModel;
    private \PDO $db;

    public function __construct()
    {
        $this->db = require __DIR__ . '/../../config/database.php';
        $this->usuarioModel = new Usuario($this->db);
        $this->turmaModel = new Turma($this->db);
        $this->checkinModel = new Checkin($this->db);
        $this->wodModel = new Wod($this->db);
        $this->wodBlocoModel = new WodBloco($this->db);
        $this->wodVariacaoModel = new WodVariacao($this->db);
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
        try {
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

            // Buscar informações de todos os tenants do usuário
            $tenants = $this->getTenantsDoUsuario($userId);

            // Buscar plano do usuário
            $plano = $this->getPlanoUsuario($userId, $tenantId);

            // Buscar ranking do usuário em cada modalidade no mês atual
            $rankingModalidades = $this->checkinModel->rankingUsuarioPorModalidade($userId, $tenantId);

            // Montar resposta
            $perfil = [
                'id' => $usuario['id'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
                'email_global' => $usuario['email'] ?? null,
                'cpf' => $usuario['cpf'] ?? null,
                'telefone' => $usuario['telefone'] ?? null,
                'foto_base64' => $usuario['foto_base64'] ?? null,
                'foto_caminho' => $usuario['foto_caminho'] ?? null,
                'data_nascimento' => $usuario['data_nascimento'] ?? null,
                'role_id' => $usuario['role_id'],
                'role_nome' => $this->getRoleName($usuario['role_id']),
                'membro_desde' => $usuario['created_at'],
                'tenants' => $tenants,
                'plano' => $plano,
                'estatisticas' => $estatisticas,
                'ranking_modalidades' => $rankingModalidades,
            ];

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $perfil
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("Erro no endpoint /mobile/perfil: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao carregar perfil',
                'message' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Retorna estatísticas de check-in do usuário
     */
    private function getEstatisticasCheckin(int $userId, ?int $tenantId): array
    {
        try {
            // Total de check-ins do usuário
            $sqlTotal = "SELECT COUNT(*) as total FROM checkins WHERE usuario_id = :user_id";
            $stmt = $this->db->prepare($sqlTotal);
            $stmt->execute(['user_id' => $userId]);
            $totalCheckins = (int) ($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);

            // Check-ins do mês atual
            $sqlMes = "SELECT COUNT(*) as total FROM checkins 
                       WHERE usuario_id = :user_id 
                       AND MONTH(data_checkin) = MONTH(CURRENT_DATE())
                       AND YEAR(data_checkin) = YEAR(CURRENT_DATE())";
            $stmtMes = $this->db->prepare($sqlMes);
            $stmtMes->execute(['user_id' => $userId]);
            $checkinsMes = (int) ($stmtMes->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);

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
            $ultimoCheckin = $stmtUltimo->fetch(\PDO::FETCH_ASSOC);

            return [
                'total_checkins' => $totalCheckins,
                'checkins_mes' => $checkinsMes,
                'sequencia_dias' => $sequencia,
                'ultimo_checkin' => $ultimoCheckin ? [
                    'data' => $ultimoCheckin['data'] ?? date('Y-m-d'),
                    'hora' => $ultimoCheckin['hora'] ?? '00:00:00'
                ] : null,
            ];
        } catch (\Exception $e) {
            // Log de erro para debug
            error_log("Erro ao buscar estatísticas de check-in do usuário {$userId}: " . $e->getMessage());
            
            // Retornar valores padrão em caso de erro
            return [
                'total_checkins' => 0,
                'checkins_mes' => 0,
                'sequencia_dias' => 0,
                'ultimo_checkin' => null,
            ];
        }
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
    /**
     * Busca todos os tenants associados ao usuário
     */
    private function getTenantsDoUsuario(int $userId): array
    {
        $sql = "SELECT DISTINCT t.id, t.nome, t.slug, t.email, t.telefone 
                FROM tenants t
                INNER JOIN usuario_tenant ut ON t.id = ut.tenant_id
                WHERE ut.usuario_id = :user_id AND t.ativo = 1
                ORDER BY t.nome ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

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
     * Busca plano do usuário no tenant através de matrículas
     * A relação de plano agora é gerenciada apenas através de matriculas, não mais via usuario_tenant
     */
    private function getPlanoUsuario(int $userId, ?int $tenantId): ?array
    {
        if (!$tenantId) {
            return null;
        }

        try {
            // Buscar plano através da matrícula mais recente ativa
            $sql = "SELECT p.id, p.nome, p.valor, p.duracao_dias, p.descricao,
                           m.data_inicio, m.data_vencimento as data_fim, m.status as vinculo_status
                    FROM matriculas m
                    INNER JOIN planos p ON m.plano_id = p.id
                    WHERE m.usuario_id = :user_id 
                    AND m.tenant_id = :tenant_id
                    AND m.status IN ('ativa', 'pendente')
                    ORDER BY m.data_vencimento DESC
                    LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);
            $plano = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $plano ?: null;
        } catch (\Exception $e) {
            // Log de erro para debug
            error_log("Erro ao buscar plano do usuário {$userId} no tenant {$tenantId}: " . $e->getMessage());
            return null;
        }
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
     * Lista os contratos/planos ativos do tenant
     * Mostra qual é o plano ativo da academia
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @return Response JSON com contrato ativo
     * 
     * @api GET /mobile/contratos
     */
    public function contratos(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        
        if (!$tenantId) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Nenhum tenant selecionado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Buscar contrato ativo
        $sqlContrato = "SELECT tp.id, tp.tenant_id, tp.plano_sistema_id, tp.status_id,
                               tp.data_inicio, tp.observacoes,
                               ps.nome as plano_nome, ps.valor, ps.descricao, ps.duracao_dias,
                               ps.max_alunos, ps.max_admins, ps.features,
                               sc.nome as status_nome, sc.codigo as status_codigo,
                               t.nome as tenant_nome, t.slug
                        FROM tenant_planos_sistema tp
                        INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
                        INNER JOIN status_contrato sc ON tp.status_id = sc.id
                        INNER JOIN tenants t ON tp.tenant_id = t.id
                        WHERE tp.tenant_id = :tenant_id
                        AND tp.status_id = 1
                        LIMIT 1";
        
        $stmtContrato = $this->db->prepare($sqlContrato);
        $stmtContrato->execute(['tenant_id' => $tenantId]);
        $contratoAtivo = $stmtContrato->fetch(\PDO::FETCH_ASSOC);

        if (!$contratoAtivo) {
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'contrato_ativo' => null,
                    'mensagem' => 'Nenhum contrato ativo no momento'
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Buscar pagamentos do contrato
        $sqlPagamentos = "SELECT id, valor, data_vencimento, data_pagamento, 
                                 forma_pagamento, sp.nome as status_pagamento
                          FROM pagamentos_contrato pc
                          INNER JOIN status_pagamento sp ON pc.status_pagamento_id = sp.id
                          WHERE pc.contrato_id = :contrato_id
                          ORDER BY data_vencimento ASC";
        
        $stmtPagamentos = $this->db->prepare($sqlPagamentos);
        $stmtPagamentos->execute(['contrato_id' => $contratoAtivo['id']]);
        $pagamentos = $stmtPagamentos->fetchAll(\PDO::FETCH_ASSOC);

        // Calcular informações do contrato
        $dataInicio = new \DateTime($contratoAtivo['data_inicio']);
        $diasTotal = (int) $contratoAtivo['duracao_dias'];
        $dataFim = (clone $dataInicio)->modify("+{$diasTotal} days");
        $hoje = new \DateTime();
        
        // Calcular dias restantes
        $diasRestantes = max(0, $dataFim->getTimestamp() - $hoje->getTimestamp()) / (24 * 3600);
        $diasRestantes = (int) floor($diasRestantes);
        
        // Calcular percentual de uso
        $diasDecorridos = $hoje->getTimestamp() - $dataInicio->getTimestamp();
        $diasDecorridos = (int) floor($diasDecorridos / (24 * 3600));
        $percentualUso = min(100, max(0, round(($diasDecorridos / $diasTotal) * 100)));

        // Processar features (se for JSON)
        $features = [];
        if (!empty($contratoAtivo['features'])) {
            $featuresData = json_decode($contratoAtivo['features'], true);
            if (is_array($featuresData)) {
                $features = $featuresData;
            }
        }

        $contratoFormatado = [
            'id' => (int) $contratoAtivo['id'],
            'plano' => [
                'id' => (int) $contratoAtivo['plano_sistema_id'],
                'nome' => $contratoAtivo['plano_nome'],
                'descricao' => $contratoAtivo['descricao'],
                'valor' => (float) $contratoAtivo['valor'],
                'max_alunos' => (int) $contratoAtivo['max_alunos'],
                'max_admins' => (int) $contratoAtivo['max_admins'],
                'features' => $features
            ],
            'status' => [
                'id' => (int) $contratoAtivo['status_id'],
                'nome' => $contratoAtivo['status_nome'],
                'codigo' => $contratoAtivo['status_codigo']
            ],
            'vigencia' => [
                'data_inicio' => $contratoAtivo['data_inicio'],
                'data_fim' => $dataFim->format('Y-m-d'),
                'dias_restantes' => $diasRestantes,
                'dias_total' => $diasTotal,
                'percentual_uso' => $percentualUso,
                'ativo' => $hoje <= $dataFim
            ],
            'pagamentos' => [
                'total' => count($pagamentos),
                'lista' => array_map(function($pag) {
                    return [
                        'id' => (int) $pag['id'],
                        'valor' => (float) $pag['valor'],
                        'data_vencimento' => $pag['data_vencimento'],
                        'data_pagamento' => $pag['data_pagamento'],
                        'status' => $pag['status_pagamento'],
                        'forma_pagamento' => $pag['forma_pagamento']
                    ];
                }, $pagamentos)
            ],
            'observacoes' => $contratoAtivo['observacoes']
        ];

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'contrato_ativo' => $contratoFormatado,
                'tenant' => [
                    'id' => (int) $contratoAtivo['tenant_id'],
                    'nome' => $contratoAtivo['tenant_nome'],
                    'slug' => $contratoAtivo['slug']
                ]
            ]
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Lista todos os contratos/planos do tenant
     * Permite visualizar múltiplos planos (ativo, vencido, pendente, etc)
     * Útil quando há múltiplos planos contratados
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @return Response JSON com lista de contratos
     * 
     * @api GET /mobile/planos
     */
    public function planos(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        
        if (!$tenantId) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Nenhum tenant selecionado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Buscar todos os contratos do tenant
        $sqlContratos = "SELECT tp.id, tp.tenant_id, tp.plano_sistema_id, tp.status_id,
                                tp.data_inicio, tp.observacoes,
                                ps.nome as plano_nome, ps.valor, ps.descricao, ps.duracao_dias,
                                ps.max_alunos, ps.max_admins, ps.features,
                                sc.nome as status_nome, sc.codigo as status_codigo,
                                t.nome as tenant_nome, t.slug
                         FROM tenant_planos_sistema tp
                         INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
                         INNER JOIN status_contrato sc ON tp.status_id = sc.id
                         INNER JOIN tenants t ON tp.tenant_id = t.id
                         WHERE tp.tenant_id = :tenant_id
                         ORDER BY tp.status_id ASC, tp.data_inicio DESC";
        
        $stmtContratos = $this->db->prepare($sqlContratos);
        $stmtContratos->execute(['tenant_id' => $tenantId]);
        $contratos = $stmtContratos->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($contratos)) {
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'planos' => [],
                    'total' => 0,
                    'mensagem' => 'Nenhum plano contratado'
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Processar cada contrato
        $planosFormatados = [];
        $hoje = new \DateTime();

        foreach ($contratos as $contrato) {
            // Buscar pagamentos do contrato
            $sqlPagamentos = "SELECT id, valor, data_vencimento, data_pagamento, 
                                     forma_pagamento, sp.nome as status_pagamento
                              FROM pagamentos_contrato pc
                              INNER JOIN status_pagamento sp ON pc.status_pagamento_id = sp.id
                              WHERE pc.contrato_id = :contrato_id
                              ORDER BY data_vencimento ASC";
            
            $stmtPagamentos = $this->db->prepare($sqlPagamentos);
            $stmtPagamentos->execute(['contrato_id' => $contrato['id']]);
            $pagamentos = $stmtPagamentos->fetchAll(\PDO::FETCH_ASSOC);

            // Calcular informações do contrato
            $dataInicio = new \DateTime($contrato['data_inicio']);
            $diasTotal = (int) $contrato['duracao_dias'];
            $dataFim = (clone $dataInicio)->modify("+{$diasTotal} days");
            
            // Calcular dias restantes
            $diasRestantes = max(0, $dataFim->getTimestamp() - $hoje->getTimestamp()) / (24 * 3600);
            $diasRestantes = (int) floor($diasRestantes);
            
            // Calcular percentual de uso
            $diasDecorridos = $hoje->getTimestamp() - $dataInicio->getTimestamp();
            $diasDecorridos = (int) floor($diasDecorridos / (24 * 3600));
            $percentualUso = min(100, max(0, round(($diasDecorridos / $diasTotal) * 100)));

            // Processar features
            $features = [];
            if (!empty($contrato['features'])) {
                $featuresData = json_decode($contrato['features'], true);
                if (is_array($featuresData)) {
                    $features = $featuresData;
                }
            }

            // Contar pagamentos por status
            $pagamentosResumido = [
                'total' => count($pagamentos),
                'pago' => count(array_filter($pagamentos, fn($p) => $p['status_pagamento'] === 'Pago')),
                'aguardando' => count(array_filter($pagamentos, fn($p) => $p['status_pagamento'] === 'Aguardando')),
                'atrasado' => count(array_filter($pagamentos, fn($p) => $p['status_pagamento'] === 'Atrasado'))
            ];

            $planosFormatados[] = [
                'id' => (int) $contrato['id'],
                'plano' => [
                    'id' => (int) $contrato['plano_sistema_id'],
                    'nome' => $contrato['plano_nome'],
                    'descricao' => $contrato['descricao'],
                    'valor' => (float) $contrato['valor'],
                    'max_alunos' => (int) $contrato['max_alunos'],
                    'max_admins' => (int) $contrato['max_admins'],
                    'features' => $features
                ],
                'status' => [
                    'id' => (int) $contrato['status_id'],
                    'nome' => $contrato['status_nome'],
                    'codigo' => $contrato['status_codigo']
                ],
                'vigencia' => [
                    'data_inicio' => $contrato['data_inicio'],
                    'data_fim' => $dataFim->format('Y-m-d'),
                    'dias_restantes' => $diasRestantes,
                    'dias_total' => $diasTotal,
                    'percentual_uso' => $percentualUso,
                    'ativo' => $hoje <= $dataFim && $contrato['status_id'] == 1
                ],
                'pagamentos' => [
                    'total' => $pagamentosResumido['total'],
                    'pago' => $pagamentosResumido['pago'],
                    'aguardando' => $pagamentosResumido['aguardando'],
                    'atrasado' => $pagamentosResumido['atrasado'],
                    'lista' => array_map(function($pag) {
                        return [
                            'id' => (int) $pag['id'],
                            'valor' => (float) $pag['valor'],
                            'data_vencimento' => $pag['data_vencimento'],
                            'data_pagamento' => $pag['data_pagamento'],
                            'status' => $pag['status_pagamento'],
                            'forma_pagamento' => $pag['forma_pagamento']
                        ];
                    }, $pagamentos)
                ],
                'observacoes' => $contrato['observacoes']
            ];
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'planos' => $planosFormatados,
                'total' => count($planosFormatados),
                'tenant' => [
                    'id' => (int) $contratos[0]['tenant_id'],
                    'nome' => $contratos[0]['tenant_nome'],
                    'slug' => $contratos[0]['slug']
                ]
            ]
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
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

    /**
     * GET /mobile/horarios
     * Retorna todos os horários disponíveis para hoje
     * Útil para o aluno selecionar uma aula para fazer check-in
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @return Response JSON com horários do dia
     */
    public function horariosHoje(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        
        // Buscar o dia de hoje
        $dataHoje = date('Y-m-d');
        
        $sql = "SELECT d.id, d.data, d.ativo,
                h.id as horario_id, h.hora, h.horario_inicio, h.horario_fim, 
                h.limite_alunos, h.tolerancia_minutos, h.ativo as horario_ativo,
                COUNT(DISTINCT t.id) as total_turmas,
                COUNT(DISTINCT c.id) as total_confirmados
                FROM dias d
                LEFT JOIN horarios h ON d.id = h.dia_id AND h.ativo = 1
                LEFT JOIN turmas t ON h.id = t.horario_id AND t.ativo = 1
                LEFT JOIN checkins c ON t.id = c.turma_id AND c.data_checkin = DATE(CURRENT_TIMESTAMP)
                WHERE d.tenant_id = :tenant_id 
                AND d.data = :data
                AND d.ativo = 1
                GROUP BY h.id
                ORDER BY h.horario_inicio ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'data' => $dataHoje
        ]);
        
        $horarios = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'data' => $dataHoje,
                'horarios' => $horarios,
                'total' => count($horarios)
            ]
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * GET /mobile/horarios/proximos
     * Retorna os próximos horários disponíveis (próximos 7 dias)
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @return Response JSON com horários dos próximos dias
     */
    public function horariosProximos(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();
        
        // Dias a retornar (padrão: 7)
        $dias = isset($queryParams['dias']) ? (int) $queryParams['dias'] : 7;
        
        $dataInicio = date('Y-m-d');
        $dataFim = date('Y-m-d', strtotime("+{$dias} days"));
        
        $sql = "SELECT d.id, d.data, DAYNAME(d.data) as dia_semana, d.ativo,
                h.id as horario_id, h.hora, h.horario_inicio, h.horario_fim, 
                h.limite_alunos, h.tolerancia_minutos, h.ativo as horario_ativo,
                COUNT(DISTINCT t.id) as total_turmas,
                COUNT(DISTINCT c.id) as total_confirmados,
                GROUP_CONCAT(DISTINCT m.nome SEPARATOR ', ') as modalidades,
                GROUP_CONCAT(DISTINCT p.nome SEPARATOR ', ') as professores
                FROM dias d
                LEFT JOIN horarios h ON d.id = h.dia_id AND h.ativo = 1
                LEFT JOIN turmas t ON h.id = t.horario_id AND t.ativo = 1
                LEFT JOIN modalidades m ON t.modalidade_id = m.id
                LEFT JOIN professores p ON t.professor_id = p.id
                LEFT JOIN checkins c ON t.id = c.turma_id AND c.data_checkin = DATE(CURRENT_TIMESTAMP)
                WHERE d.tenant_id = :tenant_id 
                AND d.data BETWEEN :data_inicio AND :data_fim
                AND d.ativo = 1
                AND h.id IS NOT NULL
                GROUP BY h.id
                ORDER BY d.data ASC, h.horario_inicio ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim
        ]);
        
        $horarios = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Agrupar por data
        $horariosPorData = [];
        foreach ($horarios as $horario) {
            $data = $horario['data'];
            if (!isset($horariosPorData[$data])) {
                $horariosPorData[$data] = [
                    'data' => $data,
                    'dia_semana' => $horario['dia_semana'],
                    'ativo' => (bool) $horario['ativo'],
                    'horarios' => []
                ];
            }
            $horariosPorData[$data]['horarios'][] = $horario;
        }
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'periodo' => [
                    'inicio' => $dataInicio,
                    'fim' => $dataFim,
                    'dias' => $dias
                ],
                'dias' => array_values($horariosPorData),
                'total_dias' => count($horariosPorData),
                'total_horarios' => count($horarios)
            ]
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * GET /mobile/horarios/{diaId}
     * Retorna todos os horários de um dia específico com detalhes das turmas
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @return Response JSON com horários do dia
     */
    public function horariosPorDia(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $diaId = (int) $args['diaId'];
        
        // Buscar informações do dia
        $sqlDia = "SELECT id, data, ativo FROM dias WHERE id = :id AND tenant_id = :tenant_id";
        $stmtDia = $this->db->prepare($sqlDia);
        $stmtDia->execute(['id' => $diaId, 'tenant_id' => $tenantId]);
        $dia = $stmtDia->fetch(\PDO::FETCH_ASSOC);
        
        if (!$dia) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Dia não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        // Buscar horários e turmas deste dia
        $sql = "SELECT h.id as horario_id, h.hora, h.horario_inicio, h.horario_fim, 
                h.limite_alunos, h.tolerancia_minutos, h.ativo as horario_ativo,
                t.id as turma_id, t.nome as turma_nome, t.ativo as turma_ativa,
                m.id as modalidade_id, m.nome as modalidade_nome, m.cor,
                p.id as professor_id, p.nome as professor_nome,
                COUNT(DISTINCT c.id) as confirmados,
                (h.limite_alunos - COUNT(DISTINCT c.id)) as vagas
                FROM horarios h
                LEFT JOIN turmas t ON h.id = t.horario_id
                LEFT JOIN modalidades m ON t.modalidade_id = m.id
                LEFT JOIN professores p ON t.professor_id = p.id
                LEFT JOIN checkins c ON t.id = c.turma_id
                WHERE h.dia_id = :dia_id AND h.ativo = 1
                GROUP BY h.id, t.id
                ORDER BY h.horario_inicio ASC, t.nome ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dia_id' => $diaId]);
        $dados = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Agrupar por horário
        $horarios = [];
        foreach ($dados as $item) {
            $horarioId = $item['horario_id'];
            
            if (!isset($horarios[$horarioId])) {
                $horarios[$horarioId] = [
                    'horario_id' => $horarioId,
                    'hora' => $item['hora'],
                    'horario_inicio' => $item['horario_inicio'],
                    'horario_fim' => $item['horario_fim'],
                    'limite_alunos' => $item['limite_alunos'],
                    'tolerancia_minutos' => $item['tolerancia_minutos'],
                    'ativo' => (bool) $item['horario_ativo'],
                    'turmas' => []
                ];
            }
            
            // Adicionar turma ao horário
            if ($item['turma_id']) {
                $horarios[$horarioId]['turmas'][] = [
                    'turma_id' => $item['turma_id'],
                    'turma_nome' => $item['turma_nome'],
                    'ativa' => (bool) $item['turma_ativa'],
                    'modalidade' => [
                        'id' => $item['modalidade_id'],
                        'nome' => $item['modalidade_nome'],
                        'cor' => $item['cor']
                    ],
                    'professor' => [
                        'id' => $item['professor_id'],
                        'nome' => $item['professor_nome']
                    ],
                    'confirmados' => (int) $item['confirmados'],
                    'vagas' => (int) $item['vagas']
                ];
            }
        }
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'dia' => $dia,
                'horarios' => array_values($horarios),
                'total_horarios' => count($horarios)
            ]
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * GET /mobile/planos
     * Retorna os planos que o usuário logado tem matrículas ativas/pendentes no tenant selecionado
     * Busca através da tabela de matrículas, não da tabela de planos
     * Útil para ver quais planos o usuário já contratou
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @return Response JSON com planos contratados pelo usuário
     */
    public function planosDoUsuario(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $userId = $request->getAttribute('userId');
            $queryParams = $request->getQueryParams();
            
            if (!$tenantId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Nenhum tenant selecionado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Por padrão retorna apenas matrículas ATIVAS
            // Se passar ?todas=true, retorna todas as matrículas (inclusive canceladas/pendentes/finalizadas)
            $retornarTodas = isset($queryParams['todas']) && $queryParams['todas'] === 'true';
            
            // Buscar matrículas do usuário com detalhes do plano
            $sql = "SELECT mat.id, mat.usuario_id, mat.plano_id, mat.data_matricula, mat.data_inicio, mat.data_vencimento, mat.valor, mat.status, mat.motivo, p.id as plano_id_ref, p.tenant_id, p.modalidade_id, p.nome as plano_nome, p.descricao, p.valor as plano_valor, p.duracao_dias, p.checkins_semanais, p.ativo, p.created_at, p.updated_at FROM matriculas mat INNER JOIN planos p ON mat.plano_id = p.id WHERE mat.usuario_id = :user_id AND mat.tenant_id = :tenant_id";
            
            // Filtra por status ativo da matrícula
            if (!$retornarTodas) {
                $sql .= " AND mat.status = 'ativa'";
            }
            
            $sql .= " ORDER BY mat.data_vencimento DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'tenant_id' => $tenantId
            ]);
            $matriculas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Buscar modalidades para enriquecer a resposta
            $modalidadesSql = "SELECT id, nome, cor FROM modalidades";
            $modalidadesStmt = $this->db->prepare($modalidadesSql);
            $modalidadesStmt->execute();
            $modalidades = [];
            foreach ($modalidadesStmt->fetchAll(\PDO::FETCH_ASSOC) as $mod) {
                $modalidades[(int)$mod['id']] = $mod;
            }

            // Processar matrículas para retornar com tipos corretos
            $matriculasFormatadas = array_map(function($mat) use ($modalidades) {
                $modalidadeId = (int) $mat['modalidade_id'];
                $modalidade = isset($modalidades[$modalidadeId]) ? $modalidades[$modalidadeId] : null;
                
                return [
                    'matricula_id' => (int) $mat['id'],
                    'plano' => [
                        'id' => (int) $mat['plano_id_ref'],
                        'tenant_id' => (int) $mat['tenant_id'],
                        'nome' => $mat['plano_nome'],
                        'descricao' => $mat['descricao'],
                        'valor' => (float) $mat['plano_valor'],
                        'duracao_dias' => (int) $mat['duracao_dias'],
                        'checkins_semanais' => (int) $mat['checkins_semanais'],
                        'ativo' => (bool) $mat['ativo'],
                        'modalidade' => $modalidade ? [
                            'id' => (int) $modalidade['id'],
                            'nome' => $modalidade['nome'],
                            'cor' => $modalidade['cor']
                        ] : [
                            'id' => null,
                            'nome' => '',
                            'cor' => ''
                        ]
                    ],
                    'datas' => [
                        'matricula' => $mat['data_matricula'],
                        'inicio' => $mat['data_inicio'],
                        'vencimento' => $mat['data_vencimento']
                    ],
                    'valor' => (float) $mat['valor'],
                    'status' => $mat['status'],
                    'motivo' => $mat['motivo'],
                    'created_at' => $mat['created_at'],
                    'updated_at' => $mat['updated_at']
                ];
            }, $matriculas);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'matriculas' => $matriculasFormatadas,
                    'total' => count($matriculasFormatadas),
                    'apenas_ativos' => !$retornarTodas
                ]
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            error_log("Erro em planosDoUsuario: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao carregar matrículas',
                'message' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * POST /mobile/checkin
     * Registra check-in do usuário em uma turma selecionada
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @return Response JSON com confirmação
     */
    public function registrarCheckin(Request $request, Response $response): Response
    {
        try {
            $userId = $request->getAttribute('userId');
            $tenantId = $request->getAttribute('tenantId');
            $body = $request->getParsedBody() ?? [];
            
            if (!$tenantId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Nenhum tenant selecionado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Buscar dados do usuário para incluir na resposta
            $usuario = $this->usuarioModel->findById($userId, $tenantId);
            if (!$usuario) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Usuário não encontrado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // ============================================================
            // VALIDAÇÃO CRÍTICA: Garantir que usuário tem acesso ao tenant
            // Evita "dados cruzados" (cross-tenant pollution)
            // ============================================================
            $usuarioTenantModel = new \App\Models\UsuarioTenant($this->db);
            $usuarioTenantValido = $usuarioTenantModel->validarAcesso($userId, $tenantId);
            
            if (!$usuarioTenantValido) {
                error_log("SEGURANÇA: Usuário $userId tentou acessar tenant $tenantId sem permissão");
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Acesso negado: você não tem permissão neste tenant',
                    'code' => 'INVALID_TENANT_ACCESS'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            // Validar turma_id
            $turmaId = $body['turma_id'] ?? null;
            
            if (!$turmaId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'turma_id é obrigatório'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $turmaId = (int) $turmaId;

            // Validar se turma existe e pertence ao tenant
            $turma = $this->turmaModel->findById($turmaId, $tenantId);
            
            if (!$turma) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Turma não encontrada'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Verificar se usuário já fez check-in nesta turma
            if ($this->checkinModel->usuarioTemCheckinNaTurma($userId, $turmaId)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Você já realizou check-in nesta turma'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // VALIDAÇÃO 1: Verificar se usuário já fez check-in NA MESMA MODALIDADE NO MESMO DIA
            $diaAula = $turma['dia_data'] ?? date('Y-m-d'); // Usar data da turma
            $modalidadeTurma = $turma['modalidade_id'] ?? null;
            
            // Contar check-ins apenas na MESMA MODALIDADE no mesmo dia
            $checkinDia = $this->checkinModel->usuarioTemCheckinNoDiaNaModalidade($userId, $diaAula, $modalidadeTurma);
            
            if ($checkinDia['total'] > 0) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Você já realizou um check-in nesta modalidade em ' . $diaAula . '. Máximo 1 check-in por modalidade por dia',
                    'detalhes' => [
                        'limite_diario_modalidade' => 1,
                        'data' => $diaAula,
                        'modalidade_id' => $modalidadeTurma,
                        'checkins_no_dia_nesta_modalidade' => $checkinDia['total'],
                        'ultimo_checkin_id' => $checkinDia['ultimo_checkin_id']
                    ]
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // VALIDAÇÃO 2: Verificar limite de check-ins da semana baseado no plano DA MODALIDADE DA TURMA
            $planoInfo = $this->checkinModel->obterLimiteCheckinsPlano($userId, $tenantId, $modalidadeTurma);
            
            if ($planoInfo['tem_plano'] && $planoInfo['limite'] > 0) {
                // Contar check-ins apenas na mesma modalidade
                $checkinsNaSemana = $this->checkinModel->contarCheckinsNaSemana($userId, $modalidadeTurma);
                
                if ($checkinsNaSemana >= $planoInfo['limite']) {
                    $response->getBody()->write(json_encode([
                        'success' => false,
                        'error' => 'Você atingiu o limite de check-ins desta semana',
                        'detalhes' => [
                            'plano' => $planoInfo['plano_nome'],
                            'limite_semana' => $planoInfo['limite'],
                            'checkins_semana' => $checkinsNaSemana,
                            'mensagem' => 'Seu plano (' . $planoInfo['plano_nome'] . ') permite ' . $planoInfo['limite'] . ' check-in(s) por semana. Você já realizou ' . $checkinsNaSemana . '.'
                        ]
                    ]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
            } elseif (!$planoInfo['tem_plano']) {
                // Usuário não tem plano ativo para esta modalidade
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Você não possui plano ativo para esta modalidade',
                    'detalhes' => [
                        'modalidade_id' => $modalidadeTurma,
                        'modalidade' => $turma['modalidade_nome'] ?? 'Não informada'
                    ]
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Verificar vagas disponíveis
            $alunosCount = $this->turmaModel->contarAlunos($turmaId);
            if ($alunosCount >= (int) $turma['limite_alunos']) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Sem vagas disponíveis nesta turma'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // VALIDAÇÃO 3: Verificar tolerância ANTES da aula
            // Não permite check-in muito cedo
            $agora = new \DateTime();
            
            // Buscar dados do dia para calcular horário da aula
            $dia = null;
            if ($turma['dia_id']) {
                $stmt = $this->db->prepare("SELECT data FROM dias WHERE id = ?");
                $stmt->execute([$turma['dia_id']]);
                $dia = $stmt->fetch(\PDO::FETCH_ASSOC);
            }
            
            if ($dia && $turma['horario_inicio']) {
                // Data e hora do início da aula
                $dataHorarioInicio = new \DateTime($dia['data'] . ' ' . $turma['horario_inicio']);
                
                // Tolerância antes da aula (padrão 480 minutos = 8 horas)
                $toleranciaAntes = (int) ($turma['tolerancia_antes_minutos'] ?? 480);
                
                // Calcular o momento mais cedo que pode fazer check-in
                $dataMaisCedo = clone $dataHorarioInicio;
                $dataMaisCedo->modify("-{$toleranciaAntes} minutes");
                
                // Se agora é ANTES da data mais cedo permitida, bloquear
                if ($agora < $dataMaisCedo) {
                    $minutosAinda = ceil(($dataMaisCedo->getTimestamp() - $agora->getTimestamp()) / 60);
                    $response->getBody()->write(json_encode([
                        'success' => false,
                        'error' => 'Check-in aberto muito cedo. Aguarde o horário de abertura',
                        'detalhes' => [
                            'turma_id' => (int) $turma['id'],
                            'data_aula' => $dia['data'],
                            'horario_inicio' => $turma['horario_inicio'],
                            'tolerancia_minutos' => $toleranciaAntes,
                            'abertura_checkin' => $dataMaisCedo->format('Y-m-d H:i:s'),
                            'tempo_esperar_minutos' => $minutosAinda
                        ]
                    ]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
            }

            // Registrar check-in
            $checkinId = $this->checkinModel->createEmTurma($userId, $turmaId);

            if (!$checkinId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Erro ao registrar check-in (Talvez já exista um check-in registrado)'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Check-in realizado com sucesso!',
                'data' => [
                    'checkin_id' => $checkinId,
                    'usuario' => [
                        'id' => $userId,
                        'nome' => $usuario['nome'],
                        'email' => $usuario['email'],
                        'foto_caminho' => $usuario['foto_caminho'] ?? null
                    ],
                    'turma' => [
                        'id' => (int) $turma['id'],
                        'nome' => $turma['nome'],
                        'professor' => $turma['professor_nome'],
                        'modalidade' => $turma['modalidade_nome']
                    ],
                    'data_checkin' => date('Y-m-d H:i:s'),
                    'vagas_atualizadas' => (int) $turma['limite_alunos'] - ($alunosCount + 1)
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(201);

        } catch (\Exception $e) {
            error_log("Erro em registrarCheckin: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao registrar check-in',
                'message' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * DELETE /mobile/checkin/{checkinId}/desfazer
     * Desfazer check-in com validação de horário
     * Regra: Não pode desfazer se a aula já começou ou passou
     */
    public function desfazerCheckin(Request $request, Response $response, array $args): Response
    {
        try {
            $userId = $request->getAttribute('userId');
            $tenantId = $request->getAttribute('tenantId');
            $checkinId = (int) ($args['checkinId'] ?? $args['id'] ?? 0);

            if (!$checkinId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'checkinId é obrigatório'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Buscar check-in
            $sql = "SELECT c.id, c.usuario_id, c.turma_id, t.dia_id, d.data as dia_data
                    FROM checkins c
                    INNER JOIN turmas t ON c.turma_id = t.id
                    INNER JOIN dias d ON t.dia_id = d.id
                    WHERE c.id = :checkin_id AND t.tenant_id = :tenant_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'checkin_id' => $checkinId,
                'tenant_id' => $tenantId
            ]);
            $checkin = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$checkin) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Check-in não encontrado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Verificar propriedade do check-in
            if ((int) $checkin['usuario_id'] !== (int) $userId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Você não tem permissão para desfazer este check-in'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            // Buscar dados da turma para verificar horário
            $turmaId = (int) $checkin['turma_id'];
            $turma = $this->turmaModel->findById($turmaId, $tenantId);

            if (!$turma) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Turma não encontrada'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Validar se a aula já começou ou passou
            $agora = new \DateTime();
            $diaAula = $checkin['dia_data']; // Data da aula
            $horarioInicio = $turma['horario_inicio']; // Horário de início (HH:MM:SS)

            // Combinar data + horário
            $dataHorarioInicio = new \DateTime($diaAula . ' ' . $horarioInicio);

            // NÃO PODE DESFAZER SE A AULA JÁ COMEÇOU
            if ($agora >= $dataHorarioInicio) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Não é possível desfazer o check-in. A aula já começou ou passou',
                    'detalhes' => [
                        'aula_inicio' => $dataHorarioInicio->format('Y-m-d H:i:s'),
                        'agora' => $agora->format('Y-m-d H:i:s'),
                        'mensagem' => 'O desfazimento só é permitido ANTES do horário de início da aula'
                    ]
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Deletar check-in
            $deleteSql = "DELETE FROM checkins WHERE id = :checkin_id";
            $deleteStmt = $this->db->prepare($deleteSql);
            $deleteStmt->execute(['checkin_id' => $checkinId]);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Check-in desfeito com sucesso',
                'data' => [
                    'checkin_id' => $checkinId,
                    'turma' => [
                        'id' => (int) $turma['id'],
                        'nome' => $turma['nome'],
                        'horario_inicio' => $turma['horario_inicio']
                    ]
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            error_log("Erro em desfazerCheckin: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao desfazer check-in',
                'message' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * GET /mobile/horarios-disponiveis
     * Retorna todos os horários/turmas disponíveis para uma data específica
     * Útil para o app listar as aulas disponíveis para inscrição ou check-in
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @return Response JSON com horários disponíveis
     */
    public function horariosDisponiveis(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $queryParams = $request->getQueryParams();
            
            if (!$tenantId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Nenhum tenant selecionado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Data pode ser passada como query param, senão usa hoje
            $data = $queryParams['data'] ?? date('Y-m-d');
            
            // Validar formato da data
            if (!\DateTime::createFromFormat('Y-m-d', $data)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Formato de data inválido. Use YYYY-MM-DD'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Buscar informações do dia
            $sqlDia = "SELECT id, data, ativo FROM dias WHERE data = :data";
            $stmtDia = $this->db->prepare($sqlDia);
            $stmtDia->execute(['data' => $data]);
            $dia = $stmtDia->fetch(\PDO::FETCH_ASSOC);

            if (!$dia) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'data' => [
                        'dia' => null,
                        'turmas' => [],
                        'total' => 0,
                        'mensagem' => 'Nenhuma turma disponível para esta data'
                    ]
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Buscar turmas disponíveis para este dia (apenas ativas)
            // Nota: O COUNT de check-ins será feito em query separada para evitar problemas com GROUP BY
            $sqlTurmas = "SELECT t.id, t.tenant_id, t.professor_id, t.modalidade_id, t.dia_id, 
                          t.horario_inicio, t.horario_fim, t.nome, t.limite_alunos, t.ativo, 
                          t.created_at, t.updated_at,
                          p.nome as professor_nome,
                          m.nome as modalidade_nome, m.icone as modalidade_icone, m.cor as modalidade_cor,
                          d.data as dia_data
                   FROM turmas t
                   INNER JOIN dias d ON t.dia_id = d.id
                   INNER JOIN professores p ON t.professor_id = p.id
                   INNER JOIN modalidades m ON t.modalidade_id = m.id
                   WHERE d.id = :dia_id AND t.ativo = 1
                   ORDER BY t.horario_inicio ASC";
            
            $stmtTurmas = $this->db->prepare($sqlTurmas);
            $stmtTurmas->execute(['dia_id' => $dia['id']]);
            $turmas = $stmtTurmas->fetchAll(\PDO::FETCH_ASSOC);

            // Para cada turma, contar o número de check-ins (alunos que já marcaram presença)
            $sqlCheckinsCount = "SELECT COUNT(DISTINCT usuario_id) as total_checkins FROM checkins WHERE turma_id = :turma_id";
            $stmtCheckinsCount = $this->db->prepare($sqlCheckinsCount);

            // Formatar turmas
            $turmasFormatadas = array_map(function($turma) use ($stmtCheckinsCount) {
                // Contar check-ins para esta turma
                $stmtCheckinsCount->execute(['turma_id' => $turma['id']]);
                $checkinsData = $stmtCheckinsCount->fetch(\PDO::FETCH_ASSOC);
                $checkinsCount = (int) ($checkinsData['total_checkins'] ?? 0);
                
                return [
                    'id' => (int) $turma['id'],
                    'nome' => $turma['nome'],
                    'professor' => [
                        'id' => (int) $turma['professor_id'],
                        'nome' => $turma['professor_nome']
                    ],
                    'modalidade' => [
                        'id' => (int) $turma['modalidade_id'],
                        'nome' => $turma['modalidade_nome'],
                        'icone' => $turma['modalidade_icone'],
                        'cor' => $turma['modalidade_cor']
                    ],
                    'horario' => [
                        'inicio' => $turma['horario_inicio'],
                        'fim' => $turma['horario_fim']
                    ],
                    'limite_alunos' => (int) $turma['limite_alunos'],
                    'alunos_inscritos' => $checkinsCount,
                    'vagas_disponiveis' => (int) $turma['limite_alunos'] - $checkinsCount,
                    'ativo' => (bool) $turma['ativo'],
                    'created_at' => $turma['created_at'],
                    'updated_at' => $turma['updated_at']
                ];
            }, $turmas);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'dia' => [
                        'id' => (int) $dia['id'],
                        'data' => $dia['data'],
                        'ativo' => (bool) $dia['ativo']
                    ],
                    'turmas' => $turmasFormatadas,
                    'total' => count($turmasFormatadas)
                ]
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            error_log("Erro em horariosDisponiveis: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao carregar horários disponíveis',
                'message' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * GET /mobile/matriculas/{matriculaId}
     * Retorna os detalhes completos de uma matrícula com todos os pagamentos
     * Permite o usuário acompanhar status, vencimentos e histórico de pagamentos
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @param array $args Argumentos da rota (matriculaId)
     * @return Response JSON com detalhes da matrícula e pagamentos
     */
    public function detalheMatricula(Request $request, Response $response, array $args): Response
    {
        try {
            $userId = $request->getAttribute('userId');
            $tenantId = $request->getAttribute('tenantId');
            $matriculaId = $args['matriculaId'] ?? null;
            
            if (!$matriculaId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'ID da matrícula não informado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Buscar detalhes da matrícula com plano
            $sqlMatricula = "SELECT m.id, m.usuario_id, m.plano_id, m.data_matricula, m.data_inicio, m.data_vencimento, m.valor, m.status, m.motivo, u.nome as usuario_nome FROM matriculas m INNER JOIN usuarios u ON m.usuario_id = u.id WHERE m.id = :matricula_id AND m.usuario_id = :user_id AND m.tenant_id = :tenant_id";
            
            $stmtMatricula = $this->db->prepare($sqlMatricula);
            $stmtMatricula->execute([
                'matricula_id' => $matriculaId,
                'user_id' => $userId,
                'tenant_id' => $tenantId
            ]);
            $matricula = $stmtMatricula->fetch(\PDO::FETCH_ASSOC);

            if (!$matricula) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Matrícula não encontrada'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Buscar dados do plano
            $sqlPlano = "SELECT p.id, p.nome, p.valor, p.duracao_dias, p.checkins_semanais FROM planos p WHERE p.id = :plano_id";
            $stmtPlano = $this->db->prepare($sqlPlano);
            $stmtPlano->execute(['plano_id' => $matricula['plano_id']]);
            $plano = $stmtPlano->fetch(\PDO::FETCH_ASSOC);

            // Buscar pagamentos da matrícula
            $sqlPagamentos = "SELECT pp.id, pp.valor, pp.data_vencimento, pp.data_pagamento, sp.nome as status_pagamento_nome, fp.nome as forma_pagamento_nome FROM pagamentos_plano pp INNER JOIN status_pagamento sp ON pp.status_pagamento_id = sp.id LEFT JOIN formas_pagamento fp ON pp.forma_pagamento_id = fp.id WHERE pp.matricula_id = :matricula_id ORDER BY pp.data_vencimento DESC";
            
            $stmtPagamentos = $this->db->prepare($sqlPagamentos);
            $stmtPagamentos->execute(['matricula_id' => $matriculaId]);
            $pagamentos = $stmtPagamentos->fetchAll(\PDO::FETCH_ASSOC);

            // Formatar resposta
            $matriculaFormatada = [
                'id' => (int) $matricula['id'],
                'usuario' => $matricula['usuario_nome'],
                'plano' => $plano ? [
                    'nome' => $plano['nome'],
                    'valor' => (float) $plano['valor'],
                    'duracao_dias' => (int) $plano['duracao_dias'],
                    'checkins_semanais' => (int) $plano['checkins_semanais']
                ] : null,
                'datas' => [
                    'matricula' => $matricula['data_matricula'],
                    'inicio' => $matricula['data_inicio'],
                    'vencimento' => $matricula['data_vencimento']
                ],
                'valor_total' => (float) $matricula['valor'],
                'status' => $matricula['status'],
                'motivo' => $matricula['motivo']
            ];

            $pagamentosFormatados = array_map(function($p) {
                return [
                    'id' => (int) $p['id'],
                    'valor' => (float) $p['valor'],
                    'data_vencimento' => $p['data_vencimento'],
                    'data_pagamento' => $p['data_pagamento'],
                    'status' => $p['status_pagamento_nome'],
                    'forma_pagamento' => $p['forma_pagamento_nome'],
                    'pendente' => $p['data_pagamento'] === null
                ];
            }, $pagamentos);

            $totalPago = array_sum(array_map(function($p) {
                return $p['data_pagamento'] ? (float) $p['valor'] : 0;
            }, $pagamentos));

            $totalPendente = array_sum(array_map(function($p) {
                return !$p['data_pagamento'] ? (float) $p['valor'] : 0;
            }, $pagamentos));

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'matricula' => $matriculaFormatada,
                    'pagamentos' => $pagamentosFormatados,
                    'resumo_financeiro' => [
                        'total_previsto' => (float) $matricula['valor'],
                        'total_pago' => (float) $totalPago,
                        'total_pendente' => (float) $totalPendente,
                        'quantidade_pagamentos' => count($pagamentos),
                        'pagamentos_realizados' => count(array_filter($pagamentos, function($p) {
                            return $p['data_pagamento'] !== null;
                        }))
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            error_log("Erro em detalheMatricula: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao carregar detalhes da matrícula',
                'message' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Visualizar participantes que fizeram check-in em uma turma
     * 
     * Retorna lista de usuários que realizaram check-in na turma especificada,
     * com informações de hora do check-in e dados do usuário.
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @param array $args Argumentos da rota (turma_id)
     * @return Response JSON com lista de participantes
     * 
     * @api GET /mobile/turma/{turma_id}/participantes
     */
    public function participantesTurma(Request $request, Response $response, array $args): Response
    {
        try {
            $userId = $request->getAttribute('userId');
            $tenantId = $request->getAttribute('tenantId');
            $turmaId = $args['turmaId'] ?? null;

            if (!$tenantId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Nenhum tenant selecionado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            if (!$turmaId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'turma_id é obrigatório'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $turmaId = (int) $turmaId;

            // Validar se turma existe e pertence ao tenant
            $turma = $this->turmaModel->findById($turmaId, $tenantId);
            
            if (!$turma) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Turma não encontrada'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Buscar participantes que fizeram check-in
            $sqlParticipantes = "
                SELECT 
                    c.id as checkin_id,
                    c.usuario_id,
                    u.nome as usuario_nome,
                    u.email,
                    c.created_at as data_checkin,
                    TIME_FORMAT(c.created_at, '%H:%i:%s') as hora_checkin,
                    DATE_FORMAT(c.created_at, '%d/%m/%Y') as data_checkin_formatada
                FROM checkins c
                INNER JOIN usuarios u ON c.usuario_id = u.id
                WHERE c.turma_id = :turma_id
                ORDER BY c.created_at DESC
            ";

            $stmtParticipantes = $this->db->prepare($sqlParticipantes);
            $stmtParticipantes->execute(['turma_id' => $turmaId]);
            $participantes = $stmtParticipantes->fetchAll(\PDO::FETCH_ASSOC);

            // Formatar participantes
            $participantesFormatados = array_map(function($p) {
                return [
                    'checkin_id' => (int) $p['checkin_id'],
                    'usuario_id' => (int) $p['usuario_id'],
                    'nome' => $p['usuario_nome'],
                    'email' => $p['email'],
                    'data_checkin' => $p['data_checkin'],
                    'hora_checkin' => $p['hora_checkin'],
                    'data_checkin_formatada' => $p['data_checkin_formatada']
                ];
            }, $participantes);

            // Contar vagas
            $vagasOcupadas = count($participantes);
            $vagasDisponiveis = (int) $turma['limite_alunos'] - $vagasOcupadas;

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'turma' => [
                        'id' => (int) $turma['id'],
                        'nome' => $turma['nome'],
                        'professor' => $turma['professor_nome'],
                        'modalidade' => $turma['modalidade_nome'],
                        'limite_alunos' => (int) $turma['limite_alunos'],
                        'vagas_ocupadas' => $vagasOcupadas,
                        'vagas_disponiveis' => $vagasDisponiveis
                    ],
                    'participantes' => $participantesFormatados,
                    'resumo' => [
                        'total_participantes' => count($participantes),
                        'percentual_ocupacao' => count($participantes) > 0 
                            ? round((count($participantes) / (int) $turma['limite_alunos']) * 100, 1)
                            : 0
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            error_log("Erro em participantesTurma: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao carregar participantes da turma',
                'message' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Retorna detalhes completos de uma turma ao clicar no card
     * Inclui: dados da turma, alunos matriculados, check-ins, limite
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @param array $args Argumentos da rota {turmaId}
     * @return Response JSON com detalhes completos
     * 
     * @api GET /mobile/turma/{turmaId}/detalhes
     */
    public function listarTurmas(Request $request, Response $response): Response
    {
        try {
            $userId = $request->getAttribute('userId');
            $tenantId = $request->getAttribute('tenantId');
            
            if (!$tenantId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Nenhum tenant selecionado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Buscar todas as turmas do tenant com informações básicas
            $sql = "
                SELECT t.id,
                       t.nome,
                       p.id as professor_id,
                       p.nome as professor,
                       m.id as modalidade_id,
                       m.nome as modalidade,
                       m.icone,
                       m.cor,
                       t.horario_id,
                       h.horario_inicio,
                       h.horario_fim,
                       d.data as dia_aula,
                       d.id as dia_id,
                       t.limite_alunos,
                       (SELECT COUNT(*) FROM checkins c 
                        WHERE c.turma_id = t.id) as total_checkins,
                       (SELECT COUNT(*) FROM inscricoes_turmas it 
                        WHERE it.turma_id = t.id AND it.ativo = 1 AND it.status = 'ativa') as alunos_inscritos,
                       (t.limite_alunos - COALESCE((SELECT COUNT(*) FROM inscricoes_turmas it 
                                                    WHERE it.turma_id = t.id AND it.ativo = 1 AND it.status = 'ativa'), 0)) as vagas_disponiveis,
                       t.ativo,
                       t.created_at,
                       t.updated_at
                FROM turmas t
                INNER JOIN professores p ON t.professor_id = p.id
                INNER JOIN modalidades m ON t.modalidade_id = m.id
                INNER JOIN horarios h ON t.horario_id = h.id
                INNER JOIN dias d ON t.dia_id = d.id
                WHERE t.tenant_id = :tenant_id AND t.ativo = 1
                ORDER BY d.data ASC, h.horario_inicio ASC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['tenant_id' => $tenantId]);
            $turmas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $turmasFormatadas = array_map(function($turma) {
                return [
                    'id' => (int) $turma['id'],
                    'nome' => $turma['nome'],
                    'professor' => [
                        'id' => (int) $turma['professor_id'],
                        'nome' => $turma['professor']
                    ],
                    'modalidade' => [
                        'id' => (int) $turma['modalidade_id'],
                        'nome' => $turma['modalidade'],
                        'icone' => $turma['icone'],
                        'cor' => $turma['cor']
                    ],
                    'horario' => [
                        'inicio' => $turma['horario_inicio'],
                        'fim' => $turma['horario_fim']
                    ],
                    'dia_aula' => $turma['dia_aula'],
                    'limite_alunos' => (int) $turma['limite_alunos'],
                    'alunos_inscritos' => (int) $turma['alunos_inscritos'],
                    'vagas_disponiveis' => (int) $turma['vagas_disponiveis'],
                    'total_checkins' => (int) $turma['total_checkins'],
                    'ativo' => (bool) $turma['ativo'],
                    'created_at' => $turma['created_at'],
                    'updated_at' => $turma['updated_at']
                ];
            }, $turmas);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'turmas' => $turmasFormatadas,
                    'total' => count($turmasFormatadas)
                ]
            ]));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            error_log("Erro em listarTurmas: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao listar turmas',
                'message' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * GET /mobile/turma/{turmaId}/detalhes
     * Retorna detalhes completos de uma turma com lista de participantes
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @param array $args Argumentos da rota {turmaId}
     * @return Response JSON com detalhes completos
     * 
     * @api GET /mobile/turma/{turmaId}/detalhes
     */
    public function detalheTurma(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $turmaId = $args['turmaId'] ?? null;

            // Validar tenantId
            if (!$tenantId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Nenhum tenant selecionado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Validar turmaId
            if (!$turmaId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'turma_id é obrigatório'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $turmaId = (int) $turmaId;

            // Buscar turma com detalhes
            $sqlTurma = "
                SELECT 
                    t.id,
                    t.nome,
                    t.limite_alunos,
                    t.horario_inicio,
                    t.horario_fim,
                    t.ativo,
                    p.nome as professor_nome,
                    p.email as professor_email,
                    m.nome as modalidade_nome,
                    d.data as dia_data
                FROM turmas t
                LEFT JOIN usuarios p ON t.professor_id = p.id
                LEFT JOIN modalidades m ON t.modalidade_id = m.id
                LEFT JOIN dias d ON t.dia_id = d.id
                WHERE t.id = :turma_id AND t.tenant_id = :tenant_id
            ";

            $stmtTurma = $this->db->prepare($sqlTurma);
            $stmtTurma->execute([
                'turma_id' => $turmaId,
                'tenant_id' => $tenantId
            ]);
            $turma = $stmtTurma->fetch(\PDO::FETCH_ASSOC);

            if (!$turma) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Turma não encontrada'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Contar alunos matriculados separadamente
            $stmtAlunosCount = $this->db->prepare("
                SELECT COUNT(DISTINCT usuario_id) as total FROM checkins 
                WHERE turma_id = :turma_id
            ");
            $stmtAlunosCount->execute(['turma_id' => $turmaId]);
            $alunosCount = $stmtAlunosCount->fetch(\PDO::FETCH_ASSOC);
            $turma['total_alunos_matriculados'] = (int) ($alunosCount['total'] ?? 0);

            // Contar check-ins separadamente
            $stmtCheckinsCount = $this->db->prepare("
                SELECT COUNT(*) as total FROM checkins 
                WHERE turma_id = :turma_id
            ");
            $stmtCheckinsCount->execute(['turma_id' => $turmaId]);
            $checkinsCount = $stmtCheckinsCount->fetch(\PDO::FETCH_ASSOC);
            $turma['total_checkins'] = (int) ($checkinsCount['total'] ?? 0);

            // Buscar alunos que fizeram check-in (como base de alunos da turma)
            $sqlAlunos = "
                SELECT 
                    u.id,
                    u.nome,
                    u.email,
                    u.foto_caminho,
                    COUNT(c.id) as checkins_do_aluno
                FROM usuarios u
                INNER JOIN checkins c ON u.id = c.usuario_id
                WHERE c.turma_id = :turma_id
                GROUP BY u.id, u.nome, u.email, u.foto_caminho
                ORDER BY u.nome ASC
            ";

            $stmtAlunos = $this->db->prepare($sqlAlunos);
            $stmtAlunos->execute(['turma_id' => $turmaId]);
            $alunos = $stmtAlunos->fetchAll(\PDO::FETCH_ASSOC);

            // Formatar alunos
            $alunosFormatados = array_map(function($a) {
                return [
                    'usuario_id' => (int) $a['id'],
                    'nome' => $a['nome'],
                    'email' => $a['email'],
                    'foto_caminho' => $a['foto_caminho'] ?? null,
                    'checkins' => (int) $a['checkins_do_aluno']
                ];
            }, $alunos);

            // Buscar check-ins recentes
            $sqlCheckins = "
                SELECT 
                    c.id as checkin_id,
                    c.usuario_id,
                    u.nome as usuario_nome,
                    c.created_at as data_checkin,
                    TIME_FORMAT(c.created_at, '%H:%i:%s') as hora_checkin,
                    DATE_FORMAT(c.created_at, '%d/%m/%Y') as data_checkin_formatada
                FROM checkins c
                INNER JOIN usuarios u ON c.usuario_id = u.id
                WHERE c.turma_id = :turma_id
                ORDER BY c.created_at DESC
                LIMIT 10
            ";

            $stmtCheckins = $this->db->prepare($sqlCheckins);
            $stmtCheckins->execute(['turma_id' => $turmaId]);
            $checkins = $stmtCheckins->fetchAll(\PDO::FETCH_ASSOC);

            // Formatar check-ins
            $checkinsFormatados = array_map(function($c) {
                return [
                    'checkin_id' => (int) $c['checkin_id'],
                    'usuario_id' => (int) $c['usuario_id'],
                    'usuario_nome' => $c['usuario_nome'],
                    'data_checkin' => $c['data_checkin'],
                    'hora_checkin' => $c['hora_checkin'],
                    'data_checkin_formatada' => $c['data_checkin_formatada']
                ];
            }, $checkins);

            // Calcular vagas
            $totalAlunos = (int) $turma['total_alunos_matriculados'];
            $limite = (int) $turma['limite_alunos'];
            $vagasDisponiveis = max(0, $limite - $totalAlunos);
            $percentualOcupacao = $limite > 0 ? round(($totalAlunos / $limite) * 100, 1) : 0;

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'turma' => [
                        'id' => (int) $turma['id'],
                        'nome' => $turma['nome'],
                        'professor' => $turma['professor_nome'],
                        'professor_email' => $turma['professor_email'],
                        'modalidade' => $turma['modalidade_nome'],
                        'horario_inicio' => $turma['horario_inicio'],
                        'horario_fim' => $turma['horario_fim'],
                        'dia_aula' => $turma['dia_data'],
                        'ativo' => (bool) $turma['ativo'],
                        'limite_alunos' => $limite,
                        'total_alunos_matriculados' => $totalAlunos,
                        'vagas_disponiveis' => $vagasDisponiveis,
                        'percentual_ocupacao' => $percentualOcupacao,
                        'total_checkins' => (int) $turma['total_checkins']
                    ],
                    'alunos' => [
                        'total' => count($alunosFormatados),
                        'lista' => $alunosFormatados
                    ],
                    'checkins_recentes' => [
                        'total' => count($checkinsFormatados),
                        'lista' => $checkinsFormatados
                    ],
                    'resumo' => [
                        'alunos_ativos' => count($alunosFormatados),
                        'presentes_hoje' => count(array_filter($checkins, function($c) {
                            return date('Y-m-d', strtotime($c['data_checkin'])) === date('Y-m-d');
                        })),
                        'percentual_presenca' => count($alunosFormatados) > 0 
                            ? round((count(array_filter($checkins, function($c) {
                                return date('Y-m-d', strtotime($c['data_checkin'])) === date('Y-m-d');
                            })) / count($alunosFormatados)) * 100, 1)
                            : 0
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            error_log("Erro em detalheTurma: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao carregar detalhes da turma',
                'message' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Retorna o WOD do dia
     * Endpoint para o App Mobile exibir o treino do dia
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @return Response JSON com WOD do dia
     * 
     * @api GET /mobile/wod/hoje
     * @query-param modalidade_id (opcional) - ID da modalidade para filtrar
     * @query-param data (opcional) - Data no formato YYYY-MM-DD (padrão: hoje)
     */
    public function wodDodia(Request $request, Response $response): Response
    {
        try {
            $tenantId = (int)$request->getAttribute('tenantId');
            $userId = (int)$request->getAttribute('userId');
            
            // Pegar query parameters opcionais
            $queryParams = $request->getQueryParams();
            $dataParam = $queryParams['data'] ?? null;
            $modalidadeParam = isset($queryParams['modalidade_id']) ? (int)$queryParams['modalidade_id'] : null;
            
            // Validar data se fornecida
            $dataHoje = date('Y-m-d');
            if ($dataParam) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataParam)) {
                    $response->getBody()->write(json_encode([
                        'success' => false,
                        'error' => 'Formato de data inválido. Use YYYY-MM-DD'
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
                }
                $dataHoje = $dataParam;
            }
            
            // Buscar usuário para obter sua modalidade (se não foi fornecida)
            $usuario = $this->usuarioModel->findById($userId, $tenantId);
            
            if (!$usuario) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Usuário não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404);
            }
            
            // Usar modalidade do parâmetro ou do usuário
            $modalidadeId = $modalidadeParam ?? ($usuario['modalidade_id'] ?? null);
            $wod = null;
            
            // Primeiro: Tenta buscar WOD com a modalidade específica
            if ($modalidadeId) {
                $stmt = $this->db->prepare(
                    "SELECT w.* FROM wods w
                     WHERE w.tenant_id = :tenant_id 
                     AND DATE(w.data) = :data
                     AND w.status = 'published'
                     AND w.modalidade_id = :modalidade_id
                     LIMIT 1"
                );
                
                $stmt->execute([
                    'tenant_id' => $tenantId,
                    'data' => $dataHoje,
                    'modalidade_id' => $modalidadeId
                ]);
                
                $wod = $stmt->fetch(\PDO::FETCH_ASSOC);
            }
            
            // Segundo: Se não encontrou, tenta buscar WOD genérico (sem modalidade)
            if (!$wod) {
                $stmt = $this->db->prepare(
                    "SELECT w.* FROM wods w
                     WHERE w.tenant_id = :tenant_id 
                     AND DATE(w.data) = :data
                     AND w.status = 'published'
                     AND w.modalidade_id IS NULL
                     LIMIT 1"
                );
                
                $stmt->execute([
                    'tenant_id' => $tenantId,
                    'data' => $dataHoje
                ]);
                
                $wod = $stmt->fetch(\PDO::FETCH_ASSOC);
            }
            
            if (!$wod) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'data' => null,
                    'message' => 'Nenhum WOD agendado para esta data'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
            }
            
            // Carregar blocos do WOD
            $blocos = $this->wodBlocoModel->listByWod($wod['id']);
            
            // Carregar variações
            $variacoes = $this->wodVariacaoModel->listByWod($wod['id']);
            
            // Formatar resposta
            $wodFormatado = [
                'id' => (int)$wod['id'],
                'titulo' => $wod['titulo'],
                'descricao' => $wod['descricao'],
                'data' => $wod['data'],
                'status' => $wod['status'],
                'modalidade_id' => $wod['modalidade_id'] ? (int)$wod['modalidade_id'] : null,
                'blocos' => array_map(function($bloco) {
                    return [
                        'id' => (int)$bloco['id'],
                        'ordem' => (int)$bloco['ordem'],
                        'tipo' => $bloco['tipo'],
                        'titulo' => $bloco['titulo'],
                        'conteudo' => $bloco['conteudo'],
                        'tempo_cap' => $bloco['tempo_cap']
                    ];
                }, $blocos),
                'variacoes' => array_map(function($variacao) {
                    return [
                        'id' => (int)$variacao['id'],
                        'nome' => $variacao['nome'],
                        'descricao' => $variacao['descricao']
                    ];
                }, $variacoes)
            ];
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $wodFormatado
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
            
        } catch (\Exception $e) {
            error_log("Erro em wodDodia: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao carregar WOD',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Retorna todos os WODs do dia com dados da modalidade
     * Endpoint para o App Mobile listar todos os treinos disponíveis
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @return Response JSON com array de WODs
     * 
     * @api GET /mobile/wods/hoje
     * @query-param data (opcional) - Data no formato YYYY-MM-DD (padrão: hoje)
     */
    public function wodsDodia(Request $request, Response $response): Response
    {
        try {
            $tenantId = (int)$request->getAttribute('tenantId');
            
            // Pegar query parameter opcional
            $queryParams = $request->getQueryParams();
            $dataParam = $queryParams['data'] ?? null;
            
            // Validar data se fornecida
            $dataHoje = date('Y-m-d');
            if ($dataParam) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataParam)) {
                    $response->getBody()->write(json_encode([
                        'success' => false,
                        'error' => 'Formato de data inválido. Use YYYY-MM-DD'
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
                }
                $dataHoje = $dataParam;
            }
            
            // Buscar todos os WODs publicados do dia com dados da modalidade
            $stmt = $this->db->prepare(
                "SELECT w.*, 
                        m.id as modalidade_id_obj,
                        m.nome as modalidade_nome,
                        m.descricao as modalidade_descricao,
                        m.cor as modalidade_cor,
                        m.icone as modalidade_icone,
                        m.ativo as modalidade_ativo
                 FROM wods w
                 LEFT JOIN modalidades m ON w.modalidade_id = m.id
                 WHERE w.tenant_id = :tenant_id 
                 AND DATE(w.data) = :data
                 AND w.status = 'published'
                 ORDER BY w.modalidade_id ASC, w.created_at ASC"
            );
            
            $stmt->execute([
                'tenant_id' => $tenantId,
                'data' => $dataHoje
            ]);
            
            $wods = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (empty($wods)) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'data' => [],
                    'message' => 'Nenhum WOD agendado para esta data'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
            }
            
            // Formatar WODs com dados da modalidade
            $wodsFormatados = array_map(function($wod) {
                // Carregar blocos do WOD
                $blocos = $this->wodBlocoModel->listByWod($wod['id']);
                
                // Carregar variações
                $variacoes = $this->wodVariacaoModel->listByWod($wod['id']);
                
                return [
                    'id' => (int)$wod['id'],
                    'titulo' => $wod['titulo'],
                    'descricao' => $wod['descricao'],
                    'data' => $wod['data'],
                    'status' => $wod['status'],
                    'modalidade' => $wod['modalidade_id'] ? [
                        'id' => (int)$wod['modalidade_id'],
                        'nome' => $wod['modalidade_nome'],
                        'descricao' => $wod['modalidade_descricao'],
                        'cor' => $wod['modalidade_cor'],
                        'icone' => $wod['modalidade_icone'],
                        'ativo' => (bool)$wod['modalidade_ativo']
                    ] : null,
                    'blocos' => array_map(function($bloco) {
                        return [
                            'id' => (int)$bloco['id'],
                            'ordem' => (int)$bloco['ordem'],
                            'tipo' => $bloco['tipo'],
                            'titulo' => $bloco['titulo'],
                            'conteudo' => $bloco['conteudo'],
                            'tempo_cap' => $bloco['tempo_cap']
                        ];
                    }, $blocos),
                    'variacoes' => array_map(function($variacao) {
                        return [
                            'id' => (int)$variacao['id'],
                            'nome' => $variacao['nome'],
                            'descricao' => $variacao['descricao']
                        ];
                    }, $variacoes)
                ];
            }, $wods);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $wodsFormatados,
                'total' => count($wodsFormatados)
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
            
        } catch (\Exception $e) {
            error_log("Erro em wodsDodia: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao carregar WODs',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * Ranking dos usuários com mais check-ins no mês atual
     * GET /mobile/ranking/mensal?modalidade_id=1
     */
    public function rankingMensal(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();
        $modalidadeId = isset($queryParams['modalidade_id']) ? (int) $queryParams['modalidade_id'] : null;
        
        try {
            $ranking = $this->checkinModel->rankingMesAtual($tenantId, 3, $modalidadeId);
            
            // Formatar resposta com posição no ranking
            $rankingFormatado = array_map(function($item, $index) {
                return [
                    'posicao' => $index + 1,
                    'usuario' => [
                        'id' => (int) $item['usuario_id'],
                        'nome' => $item['nome'],
                        'email' => $item['email'],
                        'foto_caminho' => $item['foto_caminho'] ?? null
                    ],
                    'total_checkins' => (int) $item['total_checkins']
                ];
            }, $ranking, array_keys($ranking));
            
            // Pegar nome do mês atual
            $meses = [
                1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
            ];
            $mesAtual = $meses[(int) date('n')];
            $anoAtual = date('Y');
            
            $responseData = [
                'periodo' => "$mesAtual/$anoAtual",
                'mes' => (int) date('n'),
                'ano' => (int) date('Y'),
                'modalidade_id' => $modalidadeId,
                'ranking' => $rankingFormatado
            ];
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $responseData
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
            
        } catch (\Exception $e) {
            error_log("Erro em rankingMensal: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao carregar ranking',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(500);
        }
    }

    /**
     * POST /mobile/perfil/foto
     * Upload de foto de perfil do usuário
     * Aceita multipart/form-data com campo 'foto'
     * Salva arquivo em public/uploads/fotos/
     */
    public function uploadFotoPerfil(Request $request, Response $response): Response
    {
        try {
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

            // Obter arquivos enviados
            $uploadedFiles = $request->getUploadedFiles();
            
            if (empty($uploadedFiles['foto'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Nenhuma imagem foi enviada. Use o campo "foto" em multipart/form-data'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $uploadedFile = $uploadedFiles['foto'];

            // Validar tipo de arquivo
            $mimeType = $uploadedFile->getClientMediaType();
            $permitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mimeType, $permitidos)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Tipo de arquivo não permitido. Use JPEG, PNG, GIF ou WebP',
                    'mime_enviado' => $mimeType
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Validar tamanho (5MB máximo)
            $tamanhoMaximo = 5 * 1024 * 1024; // 5MB
            if ($uploadedFile->getSize() > $tamanhoMaximo) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Arquivo muito grande. Máximo 5MB',
                    'tamanho_enviado' => $uploadedFile->getSize(),
                    'tamanho_maximo' => $tamanhoMaximo
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Criar pasta de uploads se não existir
            $uploadDir = __DIR__ . '/../../public/uploads/fotos';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Determinar extensão
            $extensoes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp'
            ];
            $ext = $extensoes[$mimeType] ?? 'jpg';

            // Remover foto antiga se existir
            if ($usuario['foto_caminho']) {
                $caminhoAntigo = __DIR__ . '/../../public' . $usuario['foto_caminho'];
                if (file_exists($caminhoAntigo)) {
                    unlink($caminhoAntigo);
                }
            }

            // Gerar nome único do arquivo
            $nomeArquivo = 'usuario_' . $userId . '_' . time() . '.' . $ext;
            $caminhoCompleto = $uploadDir . '/' . $nomeArquivo;
            $caminhoRelativo = '/uploads/fotos/' . $nomeArquivo;

            // Salvar arquivo
            $uploadedFile->moveTo($caminhoCompleto);
            chmod($caminhoCompleto, 0644);

            // Atualizar usuário com o caminho da foto
            $stmt = $this->db->prepare(
                "UPDATE usuarios SET foto_caminho = :foto_caminho, updated_at = NOW() WHERE id = :id"
            );
            $resultado = $stmt->execute([
                'foto_caminho' => $caminhoRelativo,
                'id' => $userId
            ]);

            if (!$resultado) {
                // Remover arquivo se falhar ao salvar no banco
                if (file_exists($caminhoCompleto)) {
                    unlink($caminhoCompleto);
                }
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Erro ao salvar referência da foto no banco de dados'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Foto de perfil atualizada com sucesso',
                'data' => [
                    'usuario_id' => $userId,
                    'tamanho_original' => $uploadedFile->getSize(),
                    'tamanho_final' => filesize($caminhoCompleto),
                    'tipo_arquivo' => $mimeType,
                    'nome_original' => $uploadedFile->getClientFilename(),
                    'caminho_url' => $caminhoRelativo
                ]
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (\Exception $e) {
            error_log("Erro ao fazer upload de foto: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao processar upload',
                'message' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * GET /mobile/perfil/foto
     * Obtém a foto de perfil do usuário autenticado
     */
    public function obterFotoPerfil(Request $request, Response $response): Response
    {
        try {
            $userId = $request->getAttribute('userId');
            $tenantId = $request->getAttribute('tenantId');

            // Buscar dados do usuário
            $usuario = $this->usuarioModel->findById($userId, $tenantId);
            if (!$usuario) {
                return $response->withStatus(404);
            }

            // Se não tem foto, retornar 404
            if (empty($usuario['foto_caminho'])) {
                return $response->withStatus(404);
            }

            $caminhoCompleto = __DIR__ . '/../../public' . $usuario['foto_caminho'];

            // Validar que o arquivo existe
            if (!file_exists($caminhoCompleto)) {
                return $response->withStatus(404);
            }

            // Determinar tipo MIME
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $caminhoCompleto);
            finfo_close($finfo);

            // Validar que é uma imagem
            if (strpos($mimeType, 'image/') !== 0) {
                return $response->withStatus(400);
            }

            // Ler e enviar arquivo
            $conteudo = file_get_contents($caminhoCompleto);
            $response->getBody()->write($conteudo);

            return $response
                ->withHeader('Content-Type', $mimeType)
                ->withHeader('Cache-Control', 'public, max-age=86400')
                ->withStatus(200);

        } catch (\Exception $e) {
            error_log("Erro ao obter foto de perfil: " . $e->getMessage());
            return $response->withStatus(500);
        }
    }
}
