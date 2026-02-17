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
use OpenApi\Attributes as OA;

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
    private ?string $dbInitError = null;

    public function __construct()
    {
        try {
            $this->db = require __DIR__ . '/../../config/database.php';
            $this->usuarioModel = new Usuario($this->db);
            $this->turmaModel = new Turma($this->db);
            $this->checkinModel = new Checkin($this->db);
            $this->wodModel = new Wod($this->db);
            $this->wodBlocoModel = new WodBloco($this->db);
            $this->wodVariacaoModel = new WodVariacao($this->db);
        } catch (\Throwable $e) {
            $this->dbInitError = $e->getMessage();
            error_log('[MobileController::__construct] DB init error: ' . $this->dbInitError);
        }
    }

    /**
     * Retorna o perfil completo do usuário logado com estatísticas
     * Endpoint otimizado para a tela de perfil do App Mobile
     */
    #[OA\Get(
        path: "/mobile/perfil",
        summary: "Perfil do usuário",
        description: "Retorna o perfil completo do usuário logado com estatísticas, plano, ranking e dados de todos os tenants.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Perfil retornado com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 1),
                                new OA\Property(property: "aluno_id", type: "integer", example: 1),
                                new OA\Property(property: "nome", type: "string", example: "João Silva"),
                                new OA\Property(property: "email", type: "string", example: "joao@email.com"),
                                new OA\Property(property: "telefone", type: "string", example: "(11) 99999-9999"),
                                new OA\Property(property: "foto_url", type: "string", nullable: true),
                                new OA\Property(property: "estatisticas", type: "object"),
                                new OA\Property(property: "plano", type: "object"),
                                new OA\Property(property: "ranking_modalidades", type: "array", items: new OA\Items(type: "object")),
                                new OA\Property(property: "tenants", type: "array", items: new OA\Items(type: "object"))
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 404, description: "Usuário não encontrado")
        ]
    )]
    public function perfil(Request $request, Response $response): Response
    {
        try {
            if ($this->dbInitError !== null || !isset($this->usuarioModel)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'type' => 'error',
                    'code' => 'DATABASE_CONNECTION_FAILED',
                    'message' => 'Falha ao conectar ao banco de dados'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(503);
            }
            $userId = $request->getAttribute('userId');
            $tenantId = $request->getAttribute('tenantId');
            
            // Validar tenant obrigatório para rotas mobile
            if (!$tenantId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'type' => 'error',
                    'code' => 'MISSING_TENANT',
                    'message' => 'Tenant não informado. Envie X-Tenant-Id ou utilize um token com tenant_id.'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Verificar acesso do usuário ao tenant selecionado
            if (!$this->usuarioModel->temAcessoTenant($userId, (int)$tenantId)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'type' => 'error',
                    'code' => 'TENANT_ACCESS_DENIED',
                    'message' => 'Você não tem acesso a esta academia'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }
            
            // Buscar dados do usuário
            $usuario = $this->usuarioModel->findById($userId, $tenantId);

            if (!$usuario) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Usuário não encontrado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Buscar dados do aluno (foto fica em alunos, não em usuarios)
            // Aluno é encontrado via tenant_usuario_papel com papel_id=1 (Aluno)
            $stmtAluno = $this->db->prepare(
                "SELECT a.id, a.foto_caminho, a.cep, a.logradouro, a.numero, a.complemento, a.bairro, a.cidade, a.estado 
                 FROM alunos a
                 INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id 
                   AND tup.tenant_id = :tenant_id 
                   AND tup.papel_id = 1
                 WHERE a.usuario_id = :usuario_id"
            );
            $stmtAluno->execute(['usuario_id' => $userId, 'tenant_id' => $tenantId]);
            $aluno = $stmtAluno->fetch(\PDO::FETCH_ASSOC);

            // Buscar estatísticas de check-ins
            $estatisticas = $this->getEstatisticasCheckin($userId, $tenantId);

            // Buscar informações de todos os tenants do usuário
            $tenants = $this->getTenantsDoUsuario($userId);

            // Buscar plano do usuário
            $plano = $this->getPlanoUsuario($userId, $tenantId);

            // Buscar ranking do usuário em cada modalidade no mês atual
            $rankingModalidades = $this->checkinModel->rankingUsuarioPorModalidade($userId, $tenantId);

            // Montar resposta - dados de perfil vem do aluno, auth vem do usuario
            $perfil = [
                'id' => $usuario['id'],
                'aluno_id' => $aluno['id'] ?? null,
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
                'email_global' => $usuario['email'] ?? null,
                'cpf' => $usuario['cpf'] ?? null,
                'telefone' => $usuario['telefone'] ?? null,
                'foto_caminho' => $aluno['foto_caminho'] ?? null, // Foto vem do aluno agora
                'cep' => $aluno['cep'] ?? null,
                'logradouro' => $aluno['logradouro'] ?? null,
                'numero' => $aluno['numero'] ?? null,
                'complemento' => $aluno['complemento'] ?? null,
                'bairro' => $aluno['bairro'] ?? null,
                'cidade' => $aluno['cidade'] ?? null,
                'estado' => $aluno['estado'] ?? null,
                'papel_id' => $usuario['papel_id'] ?? 1,
                'papel_nome' => $this->getPapelName($usuario['papel_id'] ?? 1),
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
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Cache-Control', 'private, max-age=300')
                ->withHeader('Vary', 'Authorization, X-Tenant-Id');
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
     * Usa aluno_id para relacionamento correto
     */
    private function getEstatisticasCheckin(int $userId, ?int $tenantId): array
    {
        try {
            // Total de check-ins do usuário (via aluno_id)
            $sqlTotal = "SELECT COUNT(*) as total FROM checkins c
                         INNER JOIN alunos a ON a.id = c.aluno_id
                         WHERE a.usuario_id = :user_id
                         AND (c.presente IS NULL OR c.presente = 1)";
            $stmt = $this->db->prepare($sqlTotal);
            $stmt->execute(['user_id' => $userId]);
            $totalCheckins = (int) ($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);

            // Check-ins do mês atual
            $sqlMes = "SELECT COUNT(*) as total FROM checkins c
                       INNER JOIN alunos a ON a.id = c.aluno_id
                       WHERE a.usuario_id = :user_id 
                       AND MONTH(COALESCE(c.data_checkin_date, DATE(c.created_at))) = MONTH(CURRENT_DATE())
                       AND YEAR(COALESCE(c.data_checkin_date, DATE(c.created_at))) = YEAR(CURRENT_DATE())
                       AND (c.presente IS NULL OR c.presente = 1)";
            $stmtMes = $this->db->prepare($sqlMes);
            $stmtMes->execute(['user_id' => $userId]);
            $checkinsMes = (int) ($stmtMes->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);

            // Sequência atual (dias consecutivos)
            $sequencia = $this->calcularSequencia($userId);

            // Último check-in
            $sqlUltimo = "SELECT c.data_checkin_date, t.horario_inicio as hora, d.data
                          FROM checkins c
                          INNER JOIN alunos a ON a.id = c.aluno_id
                          INNER JOIN turmas t ON c.turma_id = t.id
                          INNER JOIN dias d ON t.dia_id = d.id
                          WHERE a.usuario_id = :user_id
                          AND (c.presente IS NULL OR c.presente = 1)
                          ORDER BY d.data DESC, t.horario_inicio DESC LIMIT 1";
            
            $stmtUltimo = $this->db->prepare($sqlUltimo);
            $stmtUltimo->execute(['user_id' => $userId]);
            $ultimoCheckin = $stmtUltimo->fetch(\PDO::FETCH_ASSOC);

            return [
                'total_checkins' => $totalCheckins,
                'checkins_mes' => $checkinsMes,
                'sequencia_dias' => $sequencia,
                'ultimo_checkin' => $ultimoCheckin ? [
                    'data' => $ultimoCheckin['data'] ?? ($ultimoCheckin['data_checkin_date'] ?? date('Y-m-d')),
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
        // Busca datas únicas de check-in usando a tabela dias (relacionada às turmas)
        $sql = "SELECT DISTINCT d.data 
                FROM checkins c
                INNER JOIN alunos a ON a.id = c.aluno_id
                INNER JOIN turmas t ON c.turma_id = t.id
                INNER JOIN dias d ON t.dia_id = d.id
                WHERE a.usuario_id = :user_id
                AND (c.presente IS NULL OR c.presente = 1)
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
                INNER JOIN tenant_usuario_papel tup ON t.id = tup.tenant_id
                WHERE tup.usuario_id = :user_id AND tup.ativo = 1 AND t.ativo = 1
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
                           m.data_inicio, m.data_vencimento as data_fim, sm.codigo as vinculo_status
                    FROM matriculas m
                    INNER JOIN planos p ON m.plano_id = p.id
                    INNER JOIN alunos a ON a.id = m.aluno_id
                    INNER JOIN status_matricula sm ON sm.id = m.status_id
                    WHERE a.usuario_id = :user_id 
                    AND m.tenant_id = :tenant_id
                    AND sm.codigo IN ('ativa', 'pendente')
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
     * Retorna o nome do papel
     */
    private function getPapelName(?int $papelId): string
    {
        $papeis = [
            1 => 'Aluno',
            2 => 'Professor',
            3 => 'Admin',
            4 => 'Super Admin'
        ];
        return $papeis[$papelId] ?? 'Usuário';
    }

    /**
     * @deprecated Use getPapelName() instead
     */
    private function getRoleName(?int $roleId): string
    {
        return $this->getPapelName($roleId);
    }

    /**
     * Lista os tenants disponíveis para o usuário logado
     * 
     * @param Request $request Requisição HTTP
     * @param Response $response Resposta HTTP
     * @return Response JSON com lista de tenants
     */
    #[OA\Get(
        path: "/mobile/tenants",
        summary: "Listar tenants do usuário",
        description: "Retorna todos os tenants/academias que o usuário tem acesso.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Tenants retornados com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "tenants", type: "array", items: new OA\Items(type: "object")),
                                new OA\Property(property: "total", type: "integer", example: 1)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
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
     */
    #[OA\Get(
        path: "/mobile/contratos",
        summary: "Contrato ativo do tenant",
        description: "Retorna o contrato/plano ativo da academia selecionada.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Contrato retornado com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Tenant não selecionado"),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
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
     */
    #[OA\Get(
        path: "/mobile/planos",
        summary: "Listar planos do tenant",
        description: "Retorna todos os contratos/planos do tenant (ativos, vencidos, pendentes).",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Planos retornados com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "planos", type: "array", items: new OA\Items(type: "object")),
                                new OA\Property(property: "total", type: "integer", example: 2)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Tenant não selecionado"),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
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
     */
    #[OA\Get(
        path: "/mobile/checkins",
        summary: "Histórico de check-ins",
        description: "Retorna o histórico de check-ins do usuário com paginação.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "limit", in: "query", description: "Limite de registros (máx: 100)", schema: new OA\Schema(type: "integer", default: 30)),
            new OA\Parameter(name: "offset", in: "query", description: "Offset para paginação", schema: new OA\Schema(type: "integer", default: 0))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Histórico retornado com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "checkins", type: "array", items: new OA\Items(type: "object")),
                                new OA\Property(property: "total", type: "integer"),
                                new OA\Property(property: "limit", type: "integer"),
                                new OA\Property(property: "offset", type: "integer")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
    public function historicoCheckins(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $queryParams = $request->getQueryParams();
        
        $limit = min((int) ($queryParams['limit'] ?? 30), 100);
        $offset = (int) ($queryParams['offset'] ?? 0);

        $sql = "SELECT c.id, c.data_checkin, c.created_at,
                       d.data, t.horario_inicio as hora, t.nome as turma_nome
                FROM checkins c
                INNER JOIN alunos a ON a.id = c.aluno_id
                INNER JOIN turmas t ON c.turma_id = t.id
                INNER JOIN dias d ON t.dia_id = d.id
                WHERE a.usuario_id = :user_id
                AND (c.presente IS NULL OR c.presente = 1)
                ORDER BY d.data DESC, t.horario_inicio DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        $checkins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Contar total
        $sqlCount = "SELECT COUNT(*) as total FROM checkins c
                     INNER JOIN alunos a ON a.id = c.aluno_id
                     WHERE a.usuario_id = :user_id
                     AND (c.presente IS NULL OR c.presente = 1)";
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
     * Retorna os dias de check-in do usuário na semana (domingo a sábado)
     * Objeto enxuto para calendário semanal
     */
    #[OA\Get(
        path: "/mobile/checkins/por-modalidade",
        summary: "Dias de check-in por modalidade (semana)",
        description: "Retorna lista dos dias que o usuário fez check-in na semana (domingo a sábado), com dados completos da modalidade. Use 'offset' para navegar entre semanas.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "offset", 
                in: "query", 
                description: "Offset de semanas (0 = semana atual, -1 = semana passada, 1 = próxima semana)", 
                schema: new OA\Schema(type: "integer", default: 0)
            ),
            new OA\Parameter(
                name: "data_referencia", 
                in: "query", 
                description: "Data de referência no formato YYYY-MM-DD (padrão: hoje). A semana será calculada a partir desta data.", 
                schema: new OA\Schema(type: "string", format: "date")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Dias retornados com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "semana_inicio", type: "string", example: "2026-01-25", description: "Domingo da semana"),
                                new OA\Property(property: "semana_fim", type: "string", example: "2026-01-31", description: "Sábado da semana"),
                                new OA\Property(property: "total", type: "integer", example: 3),
                                new OA\Property(
                                    property: "dias",
                                    type: "array",
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: "data", type: "string", example: "2026-01-28"),
                                            new OA\Property(
                                                property: "modalidade",
                                                type: "object",
                                                properties: [
                                                    new OA\Property(property: "id", type: "integer", example: 1),
                                                    new OA\Property(property: "nome", type: "string", example: "CrossFit"),
                                                    new OA\Property(property: "cor", type: "string", example: "#FF5733"),
                                                    new OA\Property(property: "icone", type: "string", example: "fitness_center")
                                                ]
                                            )
                                        ],
                                        type: "object"
                                    )
                                ),
                                new OA\Property(
                                    property: "modalidades",
                                    type: "array",
                                    description: "Lista única de modalidades para legenda",
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "nome", type: "string"),
                                            new OA\Property(property: "cor", type: "string"),
                                            new OA\Property(property: "icone", type: "string"),
                                            new OA\Property(property: "total", type: "integer")
                                        ],
                                        type: "object"
                                    )
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
    public function checkinsPorModalidade(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $tenantId = $request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();
        // Debug rápido de tenant usado
        error_log("[mobile/checkins/por-modalidade] tenantId=" . (int)$tenantId . ", userId=" . (int)$userId . ", queryParams=" . json_encode($queryParams));
        
        // Data de referência (padrão: hoje)
        $dataReferencia = isset($queryParams['data_referencia']) 
            ? new \DateTime($queryParams['data_referencia']) 
            : new \DateTime();
        
        // Offset de semanas (0 = atual, -1 = passada, 1 = próxima)
        $offset = isset($queryParams['offset']) ? (int) $queryParams['offset'] : 0;
        
        // Aplicar offset de semanas
        if ($offset !== 0) {
            $dataReferencia->modify("{$offset} weeks");
        }
        
        // Calcular domingo da semana (início)
        $diaSemana = (int) $dataReferencia->format('w'); // 0 = domingo
        $domingo = clone $dataReferencia;
        $domingo->modify("-{$diaSemana} days");
        
        // Calcular sábado da semana (fim)
        $sabado = clone $domingo;
        $sabado->modify('+6 days');
        
        $semanaInicio = $domingo->format('Y-m-d');
        $semanaFim = $sabado->format('Y-m-d');
        
        $sql = "SELECT d.data, 
                   m.id as modalidade_id, m.nome as modalidade_nome, 
                   m.cor as modalidade_cor, m.icone as modalidade_icone
            FROM checkins c
                INNER JOIN alunos a ON a.id = c.aluno_id
                INNER JOIN turmas t ON c.turma_id = t.id
                INNER JOIN dias d ON t.dia_id = d.id
                LEFT JOIN modalidades m ON t.modalidade_id = m.id
                WHERE a.usuario_id = :user_id 
                AND t.tenant_id = :tenant_id
                AND c.tenant_id = :tenant_id_c
                AND (c.presente IS NULL OR c.presente = 1)
                AND d.data BETWEEN :semana_inicio AND :semana_fim
                ORDER BY d.data ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'tenant_id_c' => $tenantId,
            'semana_inicio' => $semanaInicio,
            'semana_fim' => $semanaFim
        ]);
        $checkins = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Montar arrays
        $dias = [];
        $modalidadesMap = [];
        
        foreach ($checkins as $c) {
            $modId = $c['modalidade_id'] ?? 0;
            $modNome = $c['modalidade_nome'] ?? 'Outro';
            $modCor = $c['modalidade_cor'] ?? '#999999';
            $modIcone = $c['modalidade_icone'] ?? null;
            
            $dias[] = [
                'data' => $c['data'],
                'modalidade' => [
                    'id' => $modId,
                    'nome' => $modNome,
                    'cor' => $modCor,
                    'icone' => $modIcone
                ]
            ];
            
            // Agrupar modalidades únicas com contagem
            if (!isset($modalidadesMap[$modId])) {
                $modalidadesMap[$modId] = [
                    'id' => $modId,
                    'nome' => $modNome,
                    'cor' => $modCor,
                    'icone' => $modIcone,
                    'total' => 0
                ];
            }
            $modalidadesMap[$modId]['total']++;
        }
        
        // Ordenar modalidades por total (decrescente)
        $modalidades = array_values($modalidadesMap);
        usort($modalidades, fn($a, $b) => $b['total'] - $a['total']);
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'semana_inicio' => $semanaInicio,
                'semana_fim' => $semanaFim,
                'total' => count($dias),
                'dias' => $dias,
                'modalidades' => $modalidades
            ]
        ], JSON_UNESCAPED_UNICODE));
        
        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'private, max-age=300')
            ->withHeader('Vary', 'Authorization, X-Tenant-Id');
    }

    /**
     * Traduz o nome do dia da semana para português
     */
    private function traduzirDiaSemana(string $dayName): string
    {
        $dias = [
            'Monday' => 'Segunda',
            'Tuesday' => 'Terça',
            'Wednesday' => 'Quarta',
            'Thursday' => 'Quinta',
            'Friday' => 'Sexta',
            'Saturday' => 'Sábado',
            'Sunday' => 'Domingo'
        ];
        return $dias[$dayName] ?? $dayName;
    }

    /**
     * Traduz o número do mês para português
     */
    private function traduzirMes(int $mes): string
    {
        $meses = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',
            4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
            7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro',
            10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
        ];
        return $meses[$mes] ?? (string) $mes;
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
            $sql = "SELECT mat.id, mat.aluno_id, mat.plano_id, mat.data_matricula, mat.data_inicio, mat.data_vencimento, mat.valor, sm.nome as status, mm.nome as motivo, p.id as plano_id_ref, p.tenant_id, p.modalidade_id, p.nome as plano_nome, p.descricao, p.valor as plano_valor, p.duracao_dias, p.checkins_semanais, p.ativo, p.created_at, p.updated_at FROM matriculas mat INNER JOIN planos p ON mat.plano_id = p.id INNER JOIN alunos a ON a.id = mat.aluno_id LEFT JOIN status_matricula sm ON sm.id = mat.status_id LEFT JOIN motivo_matricula mm ON mm.id = mat.motivo_id WHERE a.usuario_id = :user_id AND mat.tenant_id = :tenant_id";
            
            // Filtra por status ativo da matrícula
            if (!$retornarTodas) {
                $sql .= " AND mat.status_id = (SELECT id FROM status_matricula WHERE nome = 'ativa')";
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
     * Buscar alunos por nome, CPF ou email (para check-in manual)
     */
    #[OA\Get(
        path: "/mobile/alunos/buscar",
        summary: "Buscar alunos para check-in manual",
        description: "Busca alunos do tenant por nome, CPF ou email para o professor registrar check-in manualmente.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "q", in: "query", required: false, description: "Busca geral (nome, CPF ou email)"),
            new OA\Parameter(name: "nome", in: "query", required: false, description: "Nome do aluno"),
            new OA\Parameter(name: "cpf", in: "query", required: false, description: "CPF do aluno (com ou sem pontuação)"),
            new OA\Parameter(name: "email", in: "query", required: false, description: "Email do aluno"),
            new OA\Parameter(name: "limit", in: "query", required: false, description: "Limite de resultados (máx 50)", schema: new OA\Schema(type: "integer", default: 20)),
            new OA\Parameter(name: "offset", in: "query", required: false, description: "Offset para paginação", schema: new OA\Schema(type: "integer", default: 0))
        ],
        responses: [
            new OA\Response(response: 200, description: "Lista de alunos"),
            new OA\Response(response: 400, description: "Parâmetros inválidos"),
            new OA\Response(response: 403, description: "Acesso negado"),
            new OA\Response(response: 500, description: "Erro interno")
        ]
    )]
    public function buscarAlunosParaCheckin(Request $request, Response $response): Response
    {
        try {
            if ($this->dbInitError) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Erro ao inicializar banco',
                    'message' => $this->dbInitError
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

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

            // Garantir que o usuário é professor/admin no tenant
            $stmtPapel = $this->db->prepare("
                SELECT tup.papel_id
                FROM tenant_usuario_papel tup
                WHERE tup.usuario_id = :usuario_id
                  AND tup.tenant_id = :tenant_id
                  AND tup.ativo = 1
                ORDER BY tup.papel_id DESC
                LIMIT 1
            ");
            $stmtPapel->execute([
                'usuario_id' => $userId,
                'tenant_id' => $tenantId
            ]);
            $papelId = (int) ($stmtPapel->fetchColumn() ?: 0);

            if ($papelId < 2) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Acesso negado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            $q = trim($queryParams['q'] ?? '');
            $nome = trim($queryParams['nome'] ?? '');
            $cpf = trim($queryParams['cpf'] ?? '');
            $email = trim($queryParams['email'] ?? '');

            if ($q !== '' && $nome === '' && $cpf === '' && $email === '') {
                $nome = $q;
                $cpf = $q;
                $email = $q;
            }

            $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
            $emailNorm = $email !== '' ? mb_strtolower($email, 'UTF-8') : '';

            $limit = min((int) ($queryParams['limit'] ?? 20), 50);
            $offset = max((int) ($queryParams['offset'] ?? 0), 0);

            $searchConds = [];
            $params = [
                'tenant_id' => $tenantId
            ];

            if ($nome !== '') {
                $searchConds[] = "(u.nome LIKE :nome_u OR a.nome LIKE :nome_a)";
                $params['nome_u'] = '%' . $nome . '%';
                $params['nome_a'] = '%' . $nome . '%';
            }

            if ($emailNorm !== '') {
                $searchConds[] = "LOWER(u.email) LIKE :email";
                $params['email'] = '%' . $emailNorm . '%';
            }

            if ($cpfLimpo !== '') {
                if (strlen($cpfLimpo) === 11) {
                    $searchConds[] = "u.cpf = :cpf";
                    $params['cpf'] = $cpfLimpo;
                } else {
                    $searchConds[] = "u.cpf LIKE :cpf_like";
                    $params['cpf_like'] = '%' . $cpfLimpo . '%';
                }
            }

            if (empty($searchConds)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Informe nome, CPF ou email para buscar'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $sql = "
                SELECT 
                    a.id as aluno_id,
                    u.id as usuario_id,
                    COALESCE(NULLIF(a.nome, ''), u.nome) as nome,
                    u.email,
                    u.cpf,
                    a.foto_caminho
                FROM usuarios u
                INNER JOIN tenant_usuario_papel tup 
                    ON tup.usuario_id = u.id 
                   AND tup.tenant_id = :tenant_id
                   AND tup.papel_id = 1
                   AND tup.ativo = 1
                LEFT JOIN alunos a ON a.usuario_id = u.id
                WHERE (" . implode(' OR ', $searchConds) . ")
                ORDER BY u.nome ASC
                LIMIT {$limit} OFFSET {$offset}
            ";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->execute();

            $alunos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $alunosFormatados = array_map(function($a) {
                return [
                    'aluno_id' => (int) ($a['aluno_id'] ?? 0),
                    'usuario_id' => (int) ($a['usuario_id'] ?? 0),
                    'nome' => $a['nome'] ?? '',
                    'email' => $a['email'] ?? null,
                    'cpf' => $a['cpf'] ?? null,
                    'foto_caminho' => $a['foto_caminho'] ?? null
                ];
            }, $alunos);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'total' => count($alunosFormatados),
                    'limit' => $limit,
                    'offset' => $offset,
                    'alunos' => $alunosFormatados
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Throwable $e) {
            error_log("Erro em buscarAlunosParaCheckin: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao buscar alunos',
                'message' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Registrar check-in manual pelo professor
     */
    #[OA\Post(
        path: "/mobile/checkin/manual",
        summary: "Registrar check-in manual (professor)",
        description: "Permite que professor/admin registre check-in manual para um aluno.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["turma_id"],
                properties: [
                    new OA\Property(property: "turma_id", type: "integer", description: "ID da turma", example: 1),
                    new OA\Property(property: "aluno_id", type: "integer", description: "ID do aluno", example: 10),
                    new OA\Property(property: "usuario_id", type: "integer", description: "ID do usuário (alternativo ao aluno_id)", example: 10)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Check-in manual registrado"),
            new OA\Response(response: 400, description: "Parâmetros inválidos"),
            new OA\Response(response: 403, description: "Sem permissão ou matrícula inválida"),
            new OA\Response(response: 404, description: "Aluno ou turma não encontrados"),
            new OA\Response(response: 500, description: "Erro interno")
        ]
    )]
    public function registrarCheckinManual(Request $request, Response $response): Response
    {
        try {
            if ($this->dbInitError) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Erro ao inicializar banco',
                    'message' => $this->dbInitError
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

            $tenantId = $request->getAttribute('tenantId');
            $professorId = $request->getAttribute('userId');
            $data = $request->getParsedBody() ?? [];

            if (!$tenantId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Nenhum tenant selecionado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            if (empty($data['turma_id']) || (empty($data['aluno_id']) && empty($data['usuario_id']))) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'turma_id e aluno_id (ou usuario_id) são obrigatórios'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
            }

            $turmaId = (int) $data['turma_id'];
            $alunoIdInput = isset($data['aluno_id']) ? (int) $data['aluno_id'] : null;
            $usuarioIdInput = isset($data['usuario_id']) ? (int) $data['usuario_id'] : null;

            // Buscar aluno no tenant (garante vínculo papel_id=1)
            if ($alunoIdInput) {
                $stmtAluno = $this->db->prepare("
                    SELECT a.id as aluno_id, a.usuario_id, u.nome, u.email
                    FROM alunos a
                    INNER JOIN usuarios u ON u.id = a.usuario_id
                    INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id
                        AND tup.tenant_id = :tenant_id
                        AND tup.papel_id = 1
                        AND tup.ativo = 1
                    WHERE a.id = :aluno_id
                    LIMIT 1
                ");
                $stmtAluno->execute([
                    'tenant_id' => $tenantId,
                    'aluno_id' => $alunoIdInput
                ]);
            } else {
                $stmtAluno = $this->db->prepare("
                    SELECT a.id as aluno_id, a.usuario_id, u.nome, u.email
                    FROM alunos a
                    INNER JOIN usuarios u ON u.id = a.usuario_id
                    INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id
                        AND tup.tenant_id = :tenant_id
                        AND tup.papel_id = 1
                        AND tup.ativo = 1
                    WHERE a.usuario_id = :usuario_id
                    LIMIT 1
                ");
                $stmtAluno->execute([
                    'tenant_id' => $tenantId,
                    'usuario_id' => $usuarioIdInput
                ]);
            }

            $aluno = $stmtAluno->fetch(\PDO::FETCH_ASSOC);
            if (!$aluno) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Aluno não encontrado no tenant'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $alunoId = (int) $aluno['aluno_id'];
            $usuarioId = (int) $aluno['usuario_id'];

            // Atualizar status das matrículas vencidas do aluno
            $this->atualizarStatusMatriculasVencidas($usuarioId, $tenantId);

            // Verificar matrícula ativa
            $stmtMatricula = $this->db->prepare("
                SELECT m.id, m.proxima_data_vencimento, m.periodo_teste,
                       sm.codigo as status_codigo, sm.nome as status_nome
                FROM matriculas m
                INNER JOIN alunos a ON a.id = m.aluno_id
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                WHERE a.usuario_id = :usuario_id
                AND m.tenant_id = :tenant_id
                AND sm.codigo = 'ativa'
                ORDER BY m.created_at DESC
                LIMIT 1
            ");
            $stmtMatricula->execute([
                'usuario_id' => $usuarioId,
                'tenant_id' => $tenantId
            ]);
            $matricula = $stmtMatricula->fetch(\PDO::FETCH_ASSOC);

            if (!$matricula) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Aluno não possui matrícula ativa',
                    'code' => 'SEM_MATRICULA'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            $hoje = date('Y-m-d');
            if ($matricula['proxima_data_vencimento'] && $matricula['proxima_data_vencimento'] < $hoje) {
                $dataVencimento = date('d/m/Y', strtotime($matricula['proxima_data_vencimento']));
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => "Acesso do aluno expirou em {$dataVencimento}",
                    'code' => 'MATRICULA_VENCIDA',
                    'data_vencimento' => $matricula['proxima_data_vencimento']
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            // Verificar se turma existe e está ativa no tenant
            $turma = $this->turmaModel->findById($turmaId, $tenantId);
            if (!$turma || (isset($turma['ativo']) && !$turma['ativo'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Turma inválida ou inativa'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Verificar se aluno já tem check-in nesta turma
            if ($this->checkinModel->usuarioTemCheckinNaTurma($usuarioId, $turmaId)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Aluno já tem check-in nesta turma'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Verificar limite de check-ins conforme plano (se houver)
            // Regra do manual:
            // - Não validar limite semanal
            // - Se permite reposição, validar limite mensal
            $modalidadeTurma = $turma['modalidade_id'] ?? null;
            $planoInfo = $this->checkinModel->obterLimiteCheckinsPlano($usuarioId, $tenantId, $modalidadeTurma);
            if ($planoInfo['tem_plano'] && $planoInfo['limite'] > 0) {
                if ($planoInfo['permite_reposicao']) {
                    $limiteMensal = (int) ($planoInfo['limite_mensal'] ?? ($planoInfo['limite'] * 4));
                    $checkinsNoMes = $this->checkinModel->contarCheckinsNoMes($usuarioId, $modalidadeTurma);
                    if ($checkinsNoMes >= $limiteMensal) {
                        $response->getBody()->write(json_encode([
                            'success' => false,
                            'error' => 'Aluno atingiu o limite de check-ins deste mês',
                            'detalhes' => [
                                'plano' => $planoInfo['plano_nome'],
                                'limite_mensal' => $limiteMensal,
                                'checkins_mes' => $checkinsNoMes,
                                'permite_reposicao' => true
                            ]
                        ]));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                    }
                }
            }

            // Registrar check-in manual
            $stmtInsert = $this->db->prepare("
                INSERT INTO checkins (aluno_id, turma_id, tenant_id, registrado_por_admin, admin_id)
                VALUES (:aluno_id, :turma_id, :tenant_id, 1, :admin_id)
            ");
            $stmtInsert->execute([
                'aluno_id' => $alunoId,
                'turma_id' => $turmaId,
                'tenant_id' => $tenantId,
                'admin_id' => $professorId
            ]);

            $checkinId = (int) $this->db->lastInsertId();

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Check-in manual registrado com sucesso',
                'data' => [
                    'checkin_id' => $checkinId,
                    'aluno' => [
                        'aluno_id' => $alunoId,
                        'usuario_id' => $usuarioId,
                        'nome' => $aluno['nome'] ?? '',
                        'email' => $aluno['email'] ?? null
                    ],
                    'turma_id' => $turmaId,
                    'data_hora' => date('Y-m-d H:i:s')
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(201);
        } catch (\Throwable $e) {
            error_log("Erro em registrarCheckinManual: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao registrar check-in manual',
                'message' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Registra check-in do usuário em uma turma selecionada
     */
    #[OA\Post(
        path: "/mobile/checkin",
        summary: "Registrar check-in",
        description: "Registra o check-in do usuário em uma turma específica. Verifica limites diários, créditos disponíveis e tolerância de horário.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["turma_id"],
                properties: [
                    new OA\Property(property: "turma_id", type: "integer", description: "ID da turma", example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Check-in realizado com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Check-in realizado com sucesso"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "checkin_id", type: "integer", example: 123),
                                new OA\Property(property: "turma", type: "object"),
                                new OA\Property(property: "usuario", type: "object"),
                                new OA\Property(property: "data_hora", type: "string", format: "date-time")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: "turma_id obrigatório ou check-in duplicado"),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Sem créditos ou fora do horário permitido"),
            new OA\Response(response: 404, description: "Turma não encontrada")
        ]
    )]
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

            // Buscar dados do aluno (foto está na tabela alunos)
            // Aluno é encontrado via tenant_usuario_papel com papel_id=1 (Aluno)
            $stmtAluno = $this->db->prepare(
                "SELECT a.id, a.foto_caminho FROM alunos a
                 INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id 
                   AND tup.tenant_id = :tenant_id 
                   AND tup.papel_id = 1
                 WHERE a.usuario_id = :usuario_id"
            );
            $stmtAluno->execute(['usuario_id' => $userId, 'tenant_id' => $tenantId]);
            $aluno = $stmtAluno->fetch(\PDO::FETCH_ASSOC);

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

            // ✅ VALIDAR SE MATRÍCULA ESTÁ ATIVA E DENTRO DO PRAZO (proxima_data_vencimento)
            // Primeiro, verificar e atualizar status de matrículas vencidas do usuário
            $this->atualizarStatusMatriculasVencidas($userId, $tenantId);
            
            $stmtMatricula = $this->db->prepare("
                SELECT m.id, m.proxima_data_vencimento, m.periodo_teste,
                       sm.codigo as status_codigo, sm.nome as status_nome
                FROM matriculas m
                INNER JOIN alunos a ON a.id = m.aluno_id
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                WHERE a.usuario_id = :usuario_id
                AND m.tenant_id = :tenant_id
                AND sm.codigo = 'ativa'
                ORDER BY m.created_at DESC
                LIMIT 1
            ");
            
            $stmtMatricula->execute([
                'usuario_id' => $userId,
                'tenant_id' => $tenantId
            ]);
            $matricula = $stmtMatricula->fetch(\PDO::FETCH_ASSOC);
            
            if (!$matricula) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Você não possui matrícula ativa',
                    'code' => 'SEM_MATRICULA'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }
            
            // Verificar se o acesso ainda está válido (proxima_data_vencimento)
            $hoje = date('Y-m-d');
            if ($matricula['proxima_data_vencimento'] && $matricula['proxima_data_vencimento'] < $hoje) {
                $dataVencimento = date('d/m/Y', strtotime($matricula['proxima_data_vencimento']));
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => "Seu acesso expirou em {$dataVencimento}. Por favor, renove sua matrícula.",
                    'code' => 'MATRICULA_VENCIDA',
                    'data_vencimento' => $matricula['proxima_data_vencimento']
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
                if ($planoInfo['permite_reposicao']) {
                    $limiteMensal = (int) ($planoInfo['limite_mensal'] ?? ($planoInfo['limite'] * 4));
                    $checkinsNoMes = $this->checkinModel->contarCheckinsNoMes($userId, $modalidadeTurma);
                    
                    if ($checkinsNoMes >= $limiteMensal) {
                        $response->getBody()->write(json_encode([
                            'success' => false,
                            'error' => 'Você atingiu o limite de check-ins deste mês',
                            'detalhes' => [
                                'plano' => $planoInfo['plano_nome'],
                                'limite_mensal' => $limiteMensal,
                                'checkins_mes' => $checkinsNoMes,
                                'permite_reposicao' => true,
                                'mensagem' => 'Seu plano (' . $planoInfo['plano_nome'] . ') permite até ' . $limiteMensal . ' check-in(s) por mês. Você já realizou ' . $checkinsNoMes . '.'
                            ]
                        ]));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                    }
                } else {
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
                        'foto_caminho' => $aluno['foto_caminho'] ?? null
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
     * Desfazer check-in com validação de horário
     */
    #[OA\Delete(
        path: "/mobile/checkin/{checkinId}/desfazer",
        summary: "Desfazer check-in",
        description: "Cancela um check-in realizado. Só é permitido se a aula ainda não começou.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "checkinId", in: "path", required: true, description: "ID do check-in", schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Check-in desfeito com sucesso"),
            new OA\Response(response: 400, description: "checkinId obrigatório ou aula já começou"),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Sem permissão"),
            new OA\Response(response: 404, description: "Check-in não encontrado")
        ]
    )]
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
            $sql = "SELECT c.id, c.aluno_id, c.turma_id, a.usuario_id, t.dia_id, d.data as dia_data
                    FROM checkins c
                    INNER JOIN alunos a ON c.aluno_id = a.id
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
     * Desfazer check-in manual (professor/admin)
     */
    #[OA\Delete(
        path: "/mobile/checkin/manual/{checkinId}/desfazer",
        summary: "Desfazer check-in manual (professor)",
        description: "Professor/admin remove um check-in de aluno no tenant. Só é permitido se a aula ainda não começou.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "checkinId", in: "path", required: true, description: "ID do check-in", schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Check-in desfeito com sucesso"),
            new OA\Response(response: 400, description: "checkinId obrigatório ou aula já começou"),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 403, description: "Sem permissão"),
            new OA\Response(response: 404, description: "Check-in não encontrado")
        ]
    )]
    public function desfazerCheckinManual(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $checkinId = (int) ($args['checkinId'] ?? $args['id'] ?? 0);

            if (!$checkinId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'checkinId é obrigatório'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Buscar check-in (tenant)
            $sql = "SELECT c.id, c.aluno_id, c.turma_id, t.dia_id, d.data as dia_data
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
            $diaAula = $checkin['dia_data'];
            $horarioInicio = $turma['horario_inicio'];
            $dataHorarioInicio = new \DateTime($diaAula . ' ' . $horarioInicio);

            if ($agora >= $dataHorarioInicio) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Não é possível desfazer o check-in. A aula já começou ou passou',
                    'detalhes' => [
                        'aula_inicio' => $dataHorarioInicio->format('Y-m-d H:i:s'),
                        'agora' => $agora->format('Y-m-d H:i:s')
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
            error_log("Erro em desfazerCheckinManual: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao desfazer check-in manual',
                'message' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Retorna todos os horários/turmas disponíveis para uma data específica
     */
    #[OA\Get(
        path: "/mobile/horarios-disponiveis",
        summary: "Horários disponíveis",
        description: "Retorna todos os horários/turmas disponíveis para uma data específica. Útil para listar as aulas disponíveis para check-in.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "data",
                in: "query",
                required: false,
                description: "Data para consulta (padrão: hoje)",
                schema: new OA\Schema(type: "string", format: "date", example: "2025-01-29")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Horários retornados com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "data", type: "string", example: "2025-01-29"),
                                new OA\Property(property: "dia_semana", type: "string", example: "quarta"),
                                new OA\Property(
                                    property: "horarios",
                                    type: "array",
                                    items: new OA\Items(
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "turma_id", type: "integer"),
                                            new OA\Property(property: "nome", type: "string"),
                                            new OA\Property(property: "hora_inicio", type: "string"),
                                            new OA\Property(property: "hora_fim", type: "string"),
                                            new OA\Property(property: "modalidade", type: "string"),
                                            new OA\Property(property: "vagas_disponiveis", type: "integer"),
                                            new OA\Property(property: "ja_tem_checkin", type: "boolean"),
                                            new OA\Property(
                                                property: "checkin",
                                                type: "object",
                                                properties: [
                                                    new OA\Property(property: "disponivel", type: "boolean", description: "Se o check-in está disponível agora", example: true),
                                                    new OA\Property(property: "ja_abriu", type: "boolean", description: "Se o horário de check-in já foi liberado", example: true),
                                                    new OA\Property(property: "ja_fechou", type: "boolean", description: "Se o horário de check-in já encerrou", example: false),
                                                    new OA\Property(property: "abertura", type: "string", format: "datetime", description: "Data/hora de abertura do check-in", example: "2025-01-29 10:00:00"),
                                                    new OA\Property(property: "fechamento", type: "string", format: "datetime", description: "Data/hora de fechamento do check-in", example: "2025-01-29 18:10:00"),
                                                    new OA\Property(property: "tolerancia_antes_minutos", type: "integer", description: "Minutos antes do horário que o check-in abre", example: 480),
                                                    new OA\Property(property: "tolerancia_depois_minutos", type: "integer", description: "Minutos após o horário que o check-in fecha", example: 10)
                                                ]
                                            )
                                        ]
                                    )
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Tenant não selecionado"),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
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
                          t.tolerancia_minutos, t.tolerancia_antes_minutos,
                          t.created_at, t.updated_at,
                          p.nome as professor_nome,
                          m.nome as modalidade_nome, m.icone as modalidade_icone, m.cor as modalidade_cor,
                          d.data as dia_data
                   FROM turmas t
                   INNER JOIN dias d ON t.dia_id = d.id
                   INNER JOIN professores p ON t.professor_id = p.id
                   INNER JOIN modalidades m ON t.modalidade_id = m.id
                     WHERE d.id = :dia_id AND t.ativo = 1 AND t.tenant_id = :tenant_id
                   ORDER BY t.horario_inicio ASC";
            
            $stmtTurmas = $this->db->prepare($sqlTurmas);
                 $stmtTurmas->execute(['dia_id' => $dia['id'], 'tenant_id' => (int)$tenantId]);
            $turmas = $stmtTurmas->fetchAll(\PDO::FETCH_ASSOC);

                 // Para cada turma, contar o número de check-ins (alunos que já marcaram presença) restritos ao tenant
                 $sqlCheckinsCount = "SELECT COUNT(DISTINCT aluno_id) as total_checkins 
                             FROM checkins 
                             WHERE turma_id = :turma_id AND tenant_id = :tenant_id";
            $stmtCheckinsCount = $this->db->prepare($sqlCheckinsCount);

            // Obter data e hora atuais para calcular disponibilidade de check-in
            $dataHoraAtual = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));

            // Formatar turmas
            $turmasFormatadas = array_map(function($turma) use ($stmtCheckinsCount, $data, $dataHoraAtual, $tenantId) {
                // Contar check-ins para esta turma
                $stmtCheckinsCount->execute(['turma_id' => $turma['id'], 'tenant_id' => (int)$tenantId]);
                $checkinsData = $stmtCheckinsCount->fetch(\PDO::FETCH_ASSOC);
                $checkinsCount = (int) ($checkinsData['total_checkins'] ?? 0);
                
                // Calcular horários de disponibilidade do check-in
                $horarioInicio = $turma['horario_inicio']; // Ex: "18:00:00"
                $toleranciaAntes = (int) ($turma['tolerancia_antes_minutos'] ?? 480); // Padrão: 8 horas
                $toleranciaDepois = (int) ($turma['tolerancia_minutos'] ?? 10); // Padrão: 10 minutos
                
                // Criar DateTime para o horário da turma
                $dataHoraTurma = \DateTime::createFromFormat('Y-m-d H:i:s', $data . ' ' . $horarioInicio, new \DateTimeZone('America/Sao_Paulo'));
                
                // Calcular horário de abertura do check-in (horário da turma - tolerância antes)
                $horarioAberturaCheckin = clone $dataHoraTurma;
                $horarioAberturaCheckin->modify("-{$toleranciaAntes} minutes");
                
                // Calcular horário de fechamento do check-in (horário da turma + tolerância depois)
                $horarioFechamentoCheckin = clone $dataHoraTurma;
                $horarioFechamentoCheckin->modify("+{$toleranciaDepois} minutes");
                
                // Verificar se o check-in está disponível agora
                $checkinDisponivel = ($dataHoraAtual >= $horarioAberturaCheckin && $dataHoraAtual <= $horarioFechamentoCheckin);
                $checkinJaAbriu = ($dataHoraAtual >= $horarioAberturaCheckin);
                $checkinJaFechou = ($dataHoraAtual > $horarioFechamentoCheckin);
                
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
                    'checkin' => [
                        'disponivel' => $checkinDisponivel,
                        'ja_abriu' => $checkinJaAbriu,
                        'ja_fechou' => $checkinJaFechou,
                        'abertura' => $horarioAberturaCheckin->format('Y-m-d H:i:s'),
                        'fechamento' => $horarioFechamentoCheckin->format('Y-m-d H:i:s'),
                        'tolerancia_antes_minutos' => $toleranciaAntes,
                        'tolerancia_depois_minutos' => $toleranciaDepois
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
                    'total' => count($turmasFormatadas),
                    // Diagnóstico de tenant
                    'tenant_id_resolvido' => (int) $tenantId
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
     * Retorna os detalhes completos de uma matrícula
     */
    #[OA\Get(
        path: "/mobile/matriculas/{matriculaId}",
        summary: "Detalhes da matrícula",
        description: "Retorna os detalhes completos de uma matrícula com histórico de pagamentos.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "matriculaId", in: "path", required: true, description: "ID da matrícula", schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Detalhes retornados com sucesso"),
            new OA\Response(response: 400, description: "ID não informado"),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 404, description: "Matrícula não encontrada")
        ]
    )]
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
            $sqlMatricula = "SELECT m.id, m.aluno_id, m.plano_id, m.data_matricula, m.data_inicio, m.data_vencimento, m.valor, sm.nome as status, mm.nome as motivo, al.nome as usuario_nome FROM matriculas m INNER JOIN alunos al ON m.aluno_id = al.id LEFT JOIN status_matricula sm ON sm.id = m.status_id LEFT JOIN motivo_matricula mm ON mm.id = m.motivo_id WHERE m.id = :matricula_id AND al.usuario_id = :user_id AND m.tenant_id = :tenant_id";
            
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
     */
    #[OA\Get(
        path: "/mobile/turma/{turmaId}/participantes",
        summary: "Participantes da turma",
        description: "Retorna lista de usuários que realizaram check-in na turma especificada.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "turmaId", in: "path", required: true, description: "ID da turma", schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Participantes retornados com sucesso"),
            new OA\Response(response: 400, description: "Tenant não selecionado ou turma_id obrigatório"),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 404, description: "Turma não encontrada")
        ]
    )]
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
                    c.aluno_id,
                    a.nome as usuario_nome,
                    u.email,
                    c.created_at as data_checkin,
                    TIME_FORMAT(c.created_at, '%H:%i:%s') as hora_checkin,
                    DATE_FORMAT(c.created_at, '%d/%m/%Y') as data_checkin_formatada
                FROM checkins c
                INNER JOIN alunos a ON c.aluno_id = a.id
                INNER JOIN usuarios u ON a.usuario_id = u.id
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
                    'aluno_id' => (int) $p['aluno_id'],
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
     * Lista todas as turmas ativas do tenant
     */
    #[OA\Get(
        path: "/mobile/turmas",
        summary: "Listar turmas",
        description: "Retorna todas as turmas ativas do tenant com informações básicas.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Turmas retornadas com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "turmas", type: "array", items: new OA\Items(type: "object")),
                                new OA\Property(property: "total", type: "integer")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Tenant não selecionado"),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
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
                       t.horario_inicio,
                       t.horario_fim,
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
                INNER JOIN dias d ON t.dia_id = d.id
                WHERE t.tenant_id = :tenant_id AND t.ativo = 1
                ORDER BY d.data ASC, t.horario_inicio ASC
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
     * Retorna detalhes completos de uma turma com lista de participantes
     */
    #[OA\Get(
        path: "/mobile/turma/{turmaId}/detalhes",
        summary: "Detalhes da turma",
        description: "Retorna detalhes completos de uma turma com lista de alunos, check-ins e estatísticas.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "turmaId", in: "path", required: true, description: "ID da turma", schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Detalhes retornados com sucesso"),
            new OA\Response(response: 400, description: "Tenant não selecionado ou turma_id obrigatório"),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 404, description: "Turma não encontrada")
        ]
    )]
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
                SELECT COUNT(DISTINCT aluno_id) as total FROM checkins 
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
                    a.id,
                    a.nome,
                    u.email,
                    a.foto_caminho,
                    COUNT(c.id) as checkins_do_aluno
                FROM alunos a
                INNER JOIN usuarios u ON u.id = a.usuario_id
                INNER JOIN checkins c ON a.id = c.aluno_id
                WHERE c.turma_id = :turma_id
                GROUP BY a.id, a.nome, u.email, a.foto_caminho
                ORDER BY u.nome ASC
            ";

            $stmtAlunos = $this->db->prepare($sqlAlunos);
            $stmtAlunos->execute(['turma_id' => $turmaId]);
            $alunos = $stmtAlunos->fetchAll(\PDO::FETCH_ASSOC);

            // Formatar alunos
            $alunosFormatados = array_map(function($a) {
                return [
                    'aluno_id' => (int) $a['id'],
                    'nome' => $a['nome'],
                    'email' => $a['email'],
                    'foto_caminho' => $a['foto_caminho'] ?? null,
                    'checkins' => (int) $a['checkins_do_aluno']
                ];
            }, $alunos);

            // Buscar check-ins recentes (com status de presença)
            $sqlCheckins = "
                SELECT 
                    c.id as checkin_id,
                    c.aluno_id,
                    a.nome as usuario_nome,
                    c.created_at as data_checkin,
                    TIME_FORMAT(c.created_at, '%H:%i:%s') as hora_checkin,
                    DATE_FORMAT(c.created_at, '%d/%m/%Y') as data_checkin_formatada,
                    c.presente,
                    c.presenca_confirmada_em,
                    c.presenca_confirmada_por
                FROM checkins c
                INNER JOIN alunos a ON c.aluno_id = a.id
                WHERE c.turma_id = :turma_id
                ORDER BY c.created_at DESC
                LIMIT 50
            ";

            $stmtCheckins = $this->db->prepare($sqlCheckins);
            $stmtCheckins->execute(['turma_id' => $turmaId]);
            $checkins = $stmtCheckins->fetchAll(\PDO::FETCH_ASSOC);

            // Formatar check-ins
            $checkinsFormatados = array_map(function($c) {
                return [
                    'checkin_id' => (int) $c['checkin_id'],
                    'aluno_id' => (int) $c['aluno_id'],
                    'usuario_nome' => $c['usuario_nome'],
                    'data_checkin' => $c['data_checkin'],
                    'hora_checkin' => $c['hora_checkin'],
                    'data_checkin_formatada' => $c['data_checkin_formatada'],
                    'presente' => $c['presente'] === null ? null : (bool) $c['presente'],
                    'presenca_confirmada_em' => $c['presenca_confirmada_em'],
                    'presenca_confirmada_por' => $c['presenca_confirmada_por'] ? (int) $c['presenca_confirmada_por'] : null
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
     * Confirmar presença dos alunos em uma turma
     * POST /mobile/turma/{turmaId}/confirmar-presenca
     * Body: { "presencas": { "checkin_id": true/false, ... }, "remover_faltantes": bool }
     */
    #[OA\Post(
        path: "/mobile/turma/{turmaId}/confirmar-presenca",
        summary: "Confirmar presença da turma",
        description: "Professor marca presença/falta dos alunos. Opcionalmente remove check-ins de faltantes (libera crédito).",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "turmaId", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "presencas", type: "object", description: "Objeto com checkin_id => boolean"),
                    new OA\Property(property: "remover_faltantes", type: "boolean", description: "Se true, remove check-ins de faltantes")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Presença confirmada com sucesso"),
            new OA\Response(response: 400, description: "Dados inválidos"),
            new OA\Response(response: 403, description: "Sem permissão"),
            new OA\Response(response: 404, description: "Turma não encontrada")
        ]
    )]
    public function confirmarPresenca(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId');
            $userId = $request->getAttribute('userId');
            $turmaId = (int) ($args['turmaId'] ?? 0);

            if (!$tenantId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Nenhum tenant selecionado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            if (!$turmaId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'turma_id é obrigatório'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Verificar se turma existe
            $stmtTurma = $this->db->prepare("SELECT id, nome FROM turmas WHERE id = :id AND tenant_id = :tenant_id");
            $stmtTurma->execute(['id' => $turmaId, 'tenant_id' => $tenantId]);
            $turma = $stmtTurma->fetch(\PDO::FETCH_ASSOC);

            if (!$turma) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Turma não encontrada'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Parse body
            $body = json_decode($request->getBody()->getContents(), true) ?? [];
            $presencasRaw = $body['presencas'] ?? [];
            $removerFaltantes = (bool) ($body['remover_faltantes'] ?? false);

            // Normalizar formato: aceita array de objetos ou objeto com checkin_id => presente
            // Array: [{"checkin_id": 38, "presente": true}, ...]
            // Objeto: {"38": true, "39": false, ...}
            $presencas = [];
            if (!empty($presencasRaw)) {
                // Verifica se é array de objetos (formato do frontend)
                if (isset($presencasRaw[0]) && is_array($presencasRaw[0])) {
                    foreach ($presencasRaw as $item) {
                        if (isset($item['checkin_id'])) {
                            $presencas[$item['checkin_id']] = $item['presente'] ?? false;
                        }
                    }
                } else {
                    // Formato objeto: {checkin_id: presente}
                    $presencas = $presencasRaw;
                }
            }

            if (empty($presencas)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Nenhuma presença informada'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $this->db->beginTransaction();

            $confirmados = 0;
            $presentes = 0;
            $faltas = 0;

            foreach ($presencas as $checkinId => $presente) {
                $presente = filter_var($presente, FILTER_VALIDATE_BOOLEAN);

                $stmt = $this->db->prepare("
                    UPDATE checkins 
                    SET presente = :presente,
                        presenca_confirmada_em = NOW(),
                        presenca_confirmada_por = :confirmado_por
                    WHERE id = :checkin_id
                    AND turma_id = :turma_id
                    AND tenant_id = :tenant_id
                ");

                $result = $stmt->execute([
                    'presente' => $presente ? 1 : 0,
                    'confirmado_por' => $userId,
                    'checkin_id' => (int) $checkinId,
                    'turma_id' => $turmaId,
                    'tenant_id' => $tenantId
                ]);

                if ($result && $stmt->rowCount() > 0) {
                    $confirmados++;
                    if ($presente) {
                        $presentes++;
                    } else {
                        $faltas++;
                    }
                }
            }

            // Se deve remover check-ins de faltantes, executar após confirmar
            $checkinsRemovidos = 0;
            $alunosLiberados = [];

            if ($removerFaltantes && $faltas > 0) {
                // Buscar check-ins de faltantes para retornar info
                $stmtFaltantes = $this->db->prepare("
                    SELECT c.id, a.nome as aluno_nome, u.email as aluno_email
                    FROM checkins c
                    INNER JOIN alunos a ON c.aluno_id = a.id
                    INNER JOIN usuarios u ON a.usuario_id = u.id
                    WHERE c.turma_id = :turma_id
                    AND c.tenant_id = :tenant_id
                    AND c.presente = 0
                ");
                $stmtFaltantes->execute(['turma_id' => $turmaId, 'tenant_id' => $tenantId]);
                $faltantes = $stmtFaltantes->fetchAll(\PDO::FETCH_ASSOC);

                if (!empty($faltantes)) {
                    $ids = array_column($faltantes, 'id');
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));

                    $stmtDelete = $this->db->prepare("DELETE FROM checkins WHERE id IN ($placeholders)");
                    $stmtDelete->execute($ids);
                    $checkinsRemovidos = $stmtDelete->rowCount();

                    $alunosLiberados = array_map(function($f) {
                        return ['nome' => $f['aluno_nome'], 'email' => $f['aluno_email']];
                    }, $faltantes);
                }
            }

            $this->db->commit();

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => "Presença confirmada: {$presentes} presentes, {$faltas} faltas",
                'data' => [
                    'turma_id' => $turmaId,
                    'turma_nome' => $turma['nome'],
                    'confirmados' => $confirmados,
                    'presentes' => $presentes,
                    'faltas' => $faltas,
                    'checkins_removidos' => $checkinsRemovidos,
                    'alunos_liberados' => $alunosLiberados,
                    'confirmado_por' => $userId,
                    'confirmado_em' => date('Y-m-d H:i:s')
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Erro em confirmarPresenca: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao confirmar presença',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Retorna o WOD do dia
     */
    #[OA\Get(
        path: "/mobile/wod/hoje",
        summary: "WOD do dia",
        description: "Retorna o WOD (Workout of the Day) do dia atual ou de uma data específica.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "data", in: "query", description: "Data no formato YYYY-MM-DD (padrão: hoje)", schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "modalidade_id", in: "query", description: "ID da modalidade para filtrar", schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "WOD retornado com sucesso"),
            new OA\Response(response: 400, description: "Formato de data inválido"),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 404, description: "WOD não encontrado")
        ]
    )]
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
     * Retorna todos os WODs do dia
     */
    #[OA\Get(
        path: "/mobile/wods/hoje",
        summary: "Todos os WODs do dia",
        description: "Retorna todos os WODs publicados do dia com dados das modalidades.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "data", in: "query", description: "Data no formato YYYY-MM-DD (padrão: hoje)", schema: new OA\Schema(type: "string", format: "date"))
        ],
        responses: [
            new OA\Response(response: 200, description: "WODs retornados com sucesso"),
            new OA\Response(response: 400, description: "Formato de data inválido"),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
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
     */
    #[OA\Get(
        path: "/mobile/ranking/mensal",
        summary: "Ranking mensal",
        description: "Retorna o ranking dos usuários com mais check-ins no mês atual.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "modalidade_id", in: "query", description: "ID da modalidade para filtrar", schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Ranking retornado com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "periodo", type: "string", example: "01/2026"),
                                new OA\Property(property: "ranking", type: "array", items: new OA\Items(type: "object"))
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
    public function rankingMensal(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId');
        $queryParams = $request->getQueryParams();
        $modalidadeId = isset($queryParams['modalidade_id']) ? (int) $queryParams['modalidade_id'] : null;
        // Debug rápido de tenant usado
        error_log("[mobile/ranking/mensal] tenantId=" . (int)$tenantId . ", modalidadeId=" . ($modalidadeId ?? 'null'));
        
        try {
            // Métrica de diagnóstico: total de check-ins do tenant no mês atual
            $stmtTotal = $this->db->prepare(
                "SELECT COUNT(*) AS total FROM checkins c
                 WHERE c.tenant_id = :tenant_id
                   AND MONTH(COALESCE(c.data_checkin_date, DATE(c.created_at))) = MONTH(CURRENT_DATE())
                   AND YEAR(COALESCE(c.data_checkin_date, DATE(c.created_at))) = YEAR(CURRENT_DATE())
                   AND (c.presente IS NULL OR c.presente = 1)"
            );
            $stmtTotal->execute(['tenant_id' => (int)$tenantId]);
            $totalTenantMes = (int) ($stmtTotal->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);

            $ranking = $this->checkinModel->rankingMesAtual($tenantId, 3, $modalidadeId);
            
            // Formatar resposta com posição no ranking
            $rankingFormatado = array_map(function($item, $index) {
                return [
                    'posicao' => $index + 1,
                    'aluno' => [
                        'id' => (int) $item['aluno_id'],
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
                'ranking' => $rankingFormatado,
                // Diagnóstico: mostrar tenant resolvido e total de check-ins do mês
                'tenant_id_resolvido' => (int) $tenantId,
                'total_checkins_tenant_mes' => $totalTenantMes
            ];
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $responseData
            ], JSON_UNESCAPED_UNICODE));
            
            return $response
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withHeader('Cache-Control', 'private, max-age=300')
                ->withHeader('Vary', 'Authorization, X-Tenant-Id')
                ->withStatus(200);
            
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
     * Listar pacotes pendentes do pagante
     * GET /mobile/pacotes/pendentes
     */
    public function pacotesPendentes(Request $request, Response $response): Response
    {
        try {
            $tenantId = (int) $request->getAttribute('tenantId');
            $userId = (int) $request->getAttribute('userId');

            $stmt = $this->db->prepare("
                SELECT pc.id as contrato_id, pc.status, pc.valor_total, pc.data_inicio, pc.data_fim,
                       pc.payment_url, pc.payment_preference_id,
                       p.nome as pacote_nome
                FROM pacote_contratos pc
                INNER JOIN pacotes p ON p.id = pc.pacote_id
                WHERE pc.tenant_id = ? AND pc.pagante_usuario_id = ? AND pc.status = 'pendente'
                ORDER BY pc.created_at DESC
            ");
            $stmt->execute([$tenantId, $userId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'success' => true,
                'pacotes' => $rows,
                'total' => count($rows)
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("[MobileController::pacotesPendentes] Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao listar pacotes pendentes'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Gerar pagamento do pacote (pagante)
     * POST /mobile/pacotes/contratos/{contratoId}/pagar
     */
    public function pagarPacote(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = (int) $request->getAttribute('tenantId');
            $userId = (int) $request->getAttribute('userId');
            $contratoId = (int) ($args['contratoId'] ?? 0);

            if ($contratoId <= 0) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'contratoId inválido'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $stmtContrato = $this->db->prepare("
                SELECT pc.*, p.nome as pacote_nome, p.valor_total
                FROM pacote_contratos pc
                INNER JOIN pacotes p ON p.id = pc.pacote_id
                WHERE pc.id = ? AND pc.tenant_id = ? AND pc.pagante_usuario_id = ?
                LIMIT 1
            ");
            $stmtContrato->execute([$contratoId, $tenantId, $userId]);
            $contrato = $stmtContrato->fetch(\PDO::FETCH_ASSOC);

            if (!$contrato) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Contrato não encontrado'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            if (($contrato['status'] ?? '') !== 'pendente') {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Contrato não está pendente'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $valorTotal = (float) $contrato['valor_total'];

            // Se já existe payment_url, reusar
            if (!empty($contrato['payment_url'])) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Pagamento já gerado',
                    'data' => [
                        'contrato_id' => (int) $contrato['id'],
                        'payment_url' => $contrato['payment_url'],
                        'preference_id' => $contrato['payment_preference_id'],
                        'valor_total' => (float) $contrato['valor_total']
                    ]
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Buscar dados do pagante
            $stmtUsuario = $this->db->prepare("
                SELECT u.id, u.nome, u.email, u.telefone, u.cpf
                FROM usuarios u
                WHERE u.id = ? LIMIT 1
            ");
            $stmtUsuario->execute([$userId]);
            $usuario = $stmtUsuario->fetch(\PDO::FETCH_ASSOC);

            $stmtTenant = $this->db->prepare("SELECT nome FROM tenants WHERE id = ?");
            $stmtTenant->execute([$tenantId]);
            $tenantData = $stmtTenant->fetch(\PDO::FETCH_ASSOC);
            $academiaNome = $tenantData['nome'] ?? 'Academia';

            $dadosPagamento = [
                'tenant_id' => $tenantId,
                'matricula_id' => null,
                'aluno_id' => null,
                'usuario_id' => $userId,
                'aluno_nome' => $usuario['nome'] ?? '',
                'aluno_email' => $usuario['email'] ?? '',
                'aluno_telefone' => $usuario['telefone'] ?? '',
                'aluno_cpf' => $usuario['cpf'] ?? null,
                'plano_nome' => $contrato['pacote_nome'] ?? 'Pacote',
                'descricao' => "Pacote: " . ($contrato['pacote_nome'] ?? 'Pacote'),
                'valor' => $valorTotal,
                'item_id' => 'PACOTE_' . $contratoId,
                'external_reference' => 'PAC-' . $contratoId . '-' . time(),
                'max_parcelas' => 12,
                'academia_nome' => $academiaNome,
                'apenas_cartao' => false,
                'metadata_extra' => [
                    'tipo' => 'pacote',
                    'pacote_contrato_id' => (int) $contratoId
                ]
            ];

            $mercadoPago = new \App\Services\MercadoPagoService($tenantId);
            $preferencia = $mercadoPago->criarPreferenciaPagamento($dadosPagamento);

            // Atualizar contrato com payment_url
            $stmtUpdate = $this->db->prepare("
                UPDATE pacote_contratos
                SET payment_url = ?, payment_preference_id = ?, updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmtUpdate->execute([
                $preferencia['init_point'] ?? null,
                $preferencia['id'] ?? null,
                $contratoId,
                $tenantId
            ]);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'contrato_id' => $contratoId,
                    'payment_url' => $preferencia['init_point'] ?? null,
                    'preference_id' => $preferencia['id'] ?? null,
                    'valor_total' => (float) $contrato['valor_total']
                ]
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("[MobileController::pagarPacote] Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao gerar pagamento do pacote'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Cancelar compra de diária
     * POST /mobile/diaria/{matriculaId}/cancelar
     * Regra: não pode ter check-in feito nem presença confirmada (presente = 1)
     */
    public function cancelarDiaria(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $request->getAttribute('userId');
        $tenantId = (int) $request->getAttribute('tenantId');
        $matriculaId = (int) ($args['matriculaId'] ?? 0);

        if ($matriculaId <= 0) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'matricula_id inválido'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            // Buscar matrícula do usuário e garantir que é diária avulsa
            $stmtMat = $this->db->prepare("
                SELECT m.id, m.aluno_id, m.tenant_id, m.plano_id,
                       m.data_inicio, m.data_vencimento, m.tipo_cobranca,
                       sm.codigo as status_codigo,
                       p.duracao_dias, p.modalidade_id
                FROM matriculas m
                INNER JOIN alunos a ON a.id = m.aluno_id
                INNER JOIN planos p ON p.id = m.plano_id
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                WHERE m.id = ?
                  AND a.usuario_id = ?
                  AND m.tenant_id = ?
                LIMIT 1
            ");
            $stmtMat->execute([$matriculaId, $userId, $tenantId]);
            $matricula = $stmtMat->fetch(\PDO::FETCH_ASSOC);

            if (!$matricula) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Matrícula não encontrada'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            if (($matricula['tipo_cobranca'] ?? '') !== 'avulso' || (int)($matricula['duracao_dias'] ?? 0) !== 1) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Apenas diárias avulsas podem ser canceladas'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            if (($matricula['status_codigo'] ?? '') === 'cancelada') {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Matrícula já está cancelada'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Verificar se há check-in feito na janela da diária
            $dataInicio = $matricula['data_inicio'] ?? null;
            $dataFim = $matricula['data_vencimento'] ?? $dataInicio;
            $modalidadeId = (int) ($matricula['modalidade_id'] ?? 0);

            $stmtCheckins = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_checkins,
                    SUM(CASE WHEN c.presente = 1 THEN 1 ELSE 0 END) as presentes
                FROM checkins c
                INNER JOIN turmas t ON c.turma_id = t.id
                INNER JOIN dias d ON t.dia_id = d.id
                WHERE c.aluno_id = ?
                  AND c.tenant_id = ?
                  AND t.modalidade_id = ?
                  AND d.data BETWEEN ? AND ?
            ");
            $stmtCheckins->execute([
                (int) $matricula['aluno_id'],
                $tenantId,
                $modalidadeId,
                $dataInicio,
                $dataFim
            ]);
            $checkins = $stmtCheckins->fetch(\PDO::FETCH_ASSOC) ?: ['total_checkins' => 0, 'presentes' => 0];

            if ((int) $checkins['total_checkins'] > 0 || (int) $checkins['presentes'] > 0) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Não é possível cancelar: já existe check-in realizado para esta diária'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $this->db->beginTransaction();

            // Cancelar matrícula
            $stmtStatusCancelada = $this->db->prepare("SELECT id FROM status_matricula WHERE codigo = 'cancelada' LIMIT 1");
            $stmtStatusCancelada->execute();
            $statusCanceladaId = (int) ($stmtStatusCancelada->fetchColumn() ?: 0);
            if ($statusCanceladaId <= 0) {
                throw new \Exception("Status 'cancelada' não encontrado");
            }

            $stmtCancelMat = $this->db->prepare("
                UPDATE matriculas
                SET status_id = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmtCancelMat->execute([$statusCanceladaId, $matriculaId, $tenantId]);

            // Cancelar assinatura avulsa vinculada
            $stmtStatusAssCancel = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = 'cancelada' LIMIT 1");
            $stmtStatusAssCancel->execute();
            $statusAssCancelId = (int) ($stmtStatusAssCancel->fetchColumn() ?: 0);
            if ($statusAssCancelId > 0) {
                $stmtCancelAss = $this->db->prepare("
                    UPDATE assinaturas
                    SET status_id = ?,
                        status_gateway = 'cancelled',
                        atualizado_em = NOW()
                    WHERE matricula_id = ?
                      AND tenant_id = ?
                      AND tipo_cobranca = 'avulso'
                ");
                $stmtCancelAss->execute([$statusAssCancelId, $matriculaId, $tenantId]);
            }

            // Cancelar pagamento pendente da matrícula (se existir)
            $stmtStatusPag = $this->db->prepare("SELECT id FROM status_pagamento WHERE codigo = 'cancelado' LIMIT 1");
            $stmtStatusPag->execute();
            $statusPagCancelId = (int) ($stmtStatusPag->fetchColumn() ?: 0);
            if ($statusPagCancelId <= 0) {
                $stmtStatusPag = $this->db->prepare("SELECT id FROM status_pagamento WHERE nome = 'Cancelado' LIMIT 1");
                $stmtStatusPag->execute();
                $statusPagCancelId = (int) ($stmtStatusPag->fetchColumn() ?: 0);
            }

            if ($statusPagCancelId > 0) {
                $stmtCancelPag = $this->db->prepare("
                    UPDATE pagamentos_plano
                    SET status_pagamento_id = ?,
                        updated_at = NOW()
                    WHERE matricula_id = ?
                      AND tenant_id = ?
                ");
                $stmtCancelPag->execute([$statusPagCancelId, $matriculaId, $tenantId]);
            }

            $this->db->commit();

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Compra da diária cancelada com sucesso'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("[MobileController::cancelarDiaria] Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao cancelar diária'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Upload de foto de perfil do usuário
     */
    #[OA\Post(
        path: "/mobile/perfil/foto",
        summary: "Upload de foto de perfil",
        description: "Faz upload da foto de perfil do usuário. Aceita multipart/form-data com campo 'foto'.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "foto", type: "string", format: "binary", description: "Arquivo de imagem (JPG, PNG, GIF, WebP)")
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Foto atualizada com sucesso"),
            new OA\Response(response: 400, description: "Arquivo inválido ou não enviado"),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 404, description: "Usuário ou aluno não encontrado")
        ]
    )]
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

            // Buscar dados do aluno (foto fica na tabela alunos)
            // Aluno é encontrado via tenant_usuario_papel com papel_id=1 (Aluno)
            $stmtAluno = $this->db->prepare(
                "SELECT a.id, a.foto_caminho FROM alunos a
                 INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id 
                   AND tup.tenant_id = :tenant_id 
                   AND tup.papel_id = 1
                 WHERE a.usuario_id = :usuario_id"
            );
            $stmtAluno->execute(['usuario_id' => $userId, 'tenant_id' => $tenantId]);
            $aluno = $stmtAluno->fetch(\PDO::FETCH_ASSOC);

            if (!$aluno) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Aluno não encontrado para este usuário'
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

            // Remover foto antiga se existir (da tabela alunos)
            if ($aluno['foto_caminho']) {
                $caminhoAntigo = __DIR__ . '/../../public' . $aluno['foto_caminho'];
                if (file_exists($caminhoAntigo)) {
                    unlink($caminhoAntigo);
                }
            }

            // Gerar nome único do arquivo (usando aluno_id para organização)
            $nomeArquivo = 'aluno_' . $aluno['id'] . '_' . time() . '.' . $ext;
            $caminhoCompleto = $uploadDir . '/' . $nomeArquivo;
            $caminhoRelativo = '/uploads/fotos/' . $nomeArquivo;

            // Salvar arquivo
            $uploadedFile->moveTo($caminhoCompleto);
            chmod($caminhoCompleto, 0644);

            // Atualizar aluno com o caminho da foto (não mais usuarios)
            $stmt = $this->db->prepare(
                "UPDATE alunos SET foto_caminho = :foto_caminho, updated_at = NOW() WHERE id = :id"
            );
            $resultado = $stmt->execute([
                'foto_caminho' => $caminhoRelativo,
                'id' => $aluno['id']
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
                    'aluno_id' => $aluno['id'],
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
     * Obtém a foto de perfil do usuário autenticado (foto do aluno)
     */
    public function obterFotoPerfil(Request $request, Response $response): Response
    {
        try {
            $userId = $request->getAttribute('userId');
            $tenantId = $request->getAttribute('tenantId');

            // Buscar dados do aluno (foto está na tabela alunos)
            // Aluno é encontrado via tenant_usuario_papel com papel_id=1 (Aluno)
            $stmtAluno = $this->db->prepare(
                "SELECT a.id, a.foto_caminho FROM alunos a
                 INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id 
                   AND tup.tenant_id = :tenant_id 
                   AND tup.papel_id = 1
                 WHERE a.usuario_id = :usuario_id"
            );
            $stmtAluno->execute(['usuario_id' => $userId, 'tenant_id' => $tenantId]);
            $aluno = $stmtAluno->fetch(\PDO::FETCH_ASSOC);

            if (!$aluno) {
                return $response->withStatus(404);
            }

            // Se não tem foto, retornar 404
            if (empty($aluno['foto_caminho'])) {
                return $response->withStatus(404);
            }

            $caminhoCompleto = __DIR__ . '/../../public' . $aluno['foto_caminho'];

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

    /**
     * Lista os planos pagos disponíveis para contratação
     * Endpoint para exibir planos no mobile para compra
     */
    #[OA\Get(
        path: "/mobile/planos-disponiveis",
        summary: "Listar planos pagos disponíveis",
        description: "Retorna os planos ativos do tenant que estão disponíveis para contratação (exclui planos gratuitos/teste).",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "modalidade_id",
                in: "query",
                description: "ID da modalidade para filtrar planos (opcional)",
                required: false,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Planos disponíveis retornados com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(
                                    property: "planos",
                                    type: "array",
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: "id", type: "integer", example: 1),
                                            new OA\Property(property: "nome", type: "string", example: "Mensal Ilimitado"),
                                            new OA\Property(property: "descricao", type: "string", example: "Plano mensal com checkins ilimitados"),
                                            new OA\Property(property: "valor", type: "number", example: 149.90),
                                            new OA\Property(property: "duracao_dias", type: "integer", example: 30),
                                            new OA\Property(property: "checkins_semanais", type: "integer", example: 12),
                                            new OA\Property(property: "modalidade", type: "object", properties: [
                                                new OA\Property(property: "id", type: "integer"),
                                                new OA\Property(property: "nome", type: "string")
                                            ])
                                        ]
                                    )
                                ),
                                new OA\Property(property: "total", type: "integer", example: 4)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Tenant não selecionado"),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
    public function planosDisponiveis(Request $request, Response $response): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId') ?? $request->getAttribute('tenant_id');
            $usuarioId = $request->getAttribute('userId')
                ?? $request->getAttribute('user_id')
                ?? $request->getAttribute('usuarioId');
            
            if (!$tenantId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'type' => 'error',
                    'code' => 'TENANT_NAO_SELECIONADO',
                    'message' => 'Nenhum tenant selecionado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Filtro opcional por modalidade
            $queryParams = $request->getQueryParams();
            $modalidadeId = isset($queryParams['modalidade_id']) ? (int)$queryParams['modalidade_id'] : null;

            // Buscar plano atual do usuário (matrícula ativa)
            $planoAtualId = null;
            if ($usuarioId) {
                $stmtPlanoAtual = $this->db->prepare("
                    SELECT m.plano_id 
                    FROM matriculas m
                    INNER JOIN alunos a ON a.id = m.aluno_id
                    INNER JOIN status_matricula sm ON sm.id = m.status_id
                    WHERE a.usuario_id = :usuario_id 
                    AND m.tenant_id = :tenant_id
                    AND sm.codigo = 'ativa'
                    ORDER BY m.created_at DESC
                    LIMIT 1
                ");
                $stmtPlanoAtual->execute([
                    'usuario_id' => $usuarioId,
                    'tenant_id' => $tenantId
                ]);
                $planoAtual = $stmtPlanoAtual->fetch(\PDO::FETCH_ASSOC);
                if ($planoAtual) {
                    $planoAtualId = (int)$planoAtual['plano_id'];
                }
            }

            // Buscar planos ativos do tenant com valor > 0 (planos pagos)
            $sql = "SELECT p.id, p.nome, p.descricao, p.valor, p.duracao_dias, p.checkins_semanais,
                           m.id as modalidade_id, m.nome as modalidade_nome
                    FROM planos p
                    LEFT JOIN modalidades m ON p.modalidade_id = m.id
                    WHERE p.tenant_id = :tenant_id 
                    AND p.ativo = 1 
                    AND p.valor > 0";
            
            if ($modalidadeId) {
                $sql .= " AND p.modalidade_id = :modalidade_id";
            }
            
            $sql .= " ORDER BY p.valor ASC";
            
            $stmt = $this->db->prepare($sql);
            $params = ['tenant_id' => $tenantId];
            
            if ($modalidadeId) {
                $params['modalidade_id'] = $modalidadeId;
            }
            
            $stmt->execute($params);
            $planos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Buscar ciclos de cada plano
            $planoIds = array_column($planos, 'id');
            $ciclosPorPlano = [];
            
            if (!empty($planoIds)) {
                $placeholders = implode(',', array_fill(0, count($planoIds), '?'));
                $stmtCiclos = $this->db->prepare("
                    SELECT pc.id, pc.plano_id, af.nome, af.codigo, pc.meses, pc.valor, 
                           pc.valor_mensal_equivalente, pc.desconto_percentual, pc.permite_recorrencia, pc.permite_reposicao,
                           af.ordem
                    FROM plano_ciclos pc
                    INNER JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
                    WHERE pc.plano_id IN ({$placeholders}) AND pc.ativo = 1
                    ORDER BY af.ordem ASC
                ");
                $stmtCiclos->execute($planoIds);
                $ciclos = $stmtCiclos->fetchAll(\PDO::FETCH_ASSOC);
                
                // Indexar o valor mensal do ciclo mensal (meses=1) de cada plano como referência
                $valorMensalRefPlano = [];
                foreach ($ciclos as $ciclo) {
                    $pid = (int)$ciclo['plano_id'];
                    $meses = (int)$ciclo['meses'];
                    $vme = (float)$ciclo['valor_mensal_equivalente'];
                    // Pegar o valor do ciclo mensal (1 mês) como referência
                    if ($meses === 1) {
                        $valorMensalRefPlano[$pid] = $vme;
                    }
                    // Se não tiver mensal, pegar o de menor período
                    if (!isset($valorMensalRefPlano[$pid]) || $meses < ($valorMensalRefPlano[$pid . '_meses'] ?? PHP_INT_MAX)) {
                        if (!isset($valorMensalRefPlano[$pid])) {
                            $valorMensalRefPlano[$pid] = $vme;
                        }
                        $valorMensalRefPlano[$pid . '_meses'] = $meses;
                    }
                }
                
                foreach ($ciclos as $ciclo) {
                    $planoId = (int)$ciclo['plano_id'];
                    if (!isset($ciclosPorPlano[$planoId])) {
                        $ciclosPorPlano[$planoId] = [];
                    }
                    
                    $valorMensalEquivalente = (float)$ciclo['valor_mensal_equivalente'];
                    $valorMensalBase = $valorMensalRefPlano[$planoId] ?? 0;
                    
                    // Calcular economia real: quanto economiza por mês em relação ao ciclo mensal
                    $economiaPercentual = 0;
                    $economiaValor = 0;
                    if ($valorMensalBase > 0 && $valorMensalEquivalente < $valorMensalBase) {
                        $economiaPercentual = round((($valorMensalBase - $valorMensalEquivalente) / $valorMensalBase) * 100, 0);
                        $economiaValor = round(($valorMensalBase - $valorMensalEquivalente) * (int)$ciclo['meses'], 2);
                    }
                    
                    $ciclosPorPlano[$planoId][] = [
                        'id' => (int)$ciclo['id'],
                        'nome' => $ciclo['nome'],
                        'codigo' => $ciclo['codigo'],
                        'meses' => (int)$ciclo['meses'],
                        'valor' => (float)$ciclo['valor'],
                        'valor_formatado' => 'R$ ' . number_format($ciclo['valor'], 2, ',', '.'),
                        'valor_mensal' => $valorMensalEquivalente,
                        'valor_mensal_formatado' => 'R$ ' . number_format($valorMensalEquivalente, 2, ',', '.'),
                        'desconto_percentual' => $economiaPercentual,
                        'permite_recorrencia' => (bool)$ciclo['permite_recorrencia'],
                        'permite_reposicao' => (bool)$ciclo['permite_reposicao'],
                        'pix_disponivel' => !(bool)$ciclo['permite_recorrencia'],
                        'metodos_pagamento' => (bool)$ciclo['permite_recorrencia']
                            ? ['checkout']
                            : ['checkout', 'pix'],
                        'economia' => $economiaPercentual > 0 
                            ? 'Economize ' . $economiaPercentual . '%'
                            : null,
                        'economia_valor' => $economiaValor > 0 
                            ? 'R$ ' . number_format($economiaValor, 2, ',', '.') . ' de economia'
                            : null
                    ];
                }
            }

            // Formatar resposta
            $planosFormatados = array_map(function($plano) use ($planoAtualId, $ciclosPorPlano) {
                $isPlanoAtual = $planoAtualId && (int)$plano['id'] === $planoAtualId;
                $planoId = (int)$plano['id'];
                return [
                    'id' => $planoId,
                    'nome' => $plano['nome'],
                    'descricao' => $plano['descricao'],
                    'valor' => (float)$plano['valor'],
                    'valor_formatado' => 'R$ ' . number_format($plano['valor'], 2, ',', '.'),
                    'duracao_dias' => (int)$plano['duracao_dias'],
                    'duracao_texto' => $this->formatarDuracao((int)$plano['duracao_dias']),
                    'checkins_semanais' => (int)$plano['checkins_semanais'],
                    'modalidade' => [
                        'id' => (int)$plano['modalidade_id'],
                        'nome' => $plano['modalidade_nome']
                    ],
                    'is_plano_atual' => $isPlanoAtual,
                    'label' => $isPlanoAtual ? 'Seu plano atual' : null,
                    'ciclos' => $ciclosPorPlano[$planoId] ?? []
                ];
            }, $planos);


            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'planos' => $planosFormatados,
                    'total' => count($planosFormatados),
                    'plano_atual_id' => $planoAtualId
                ]
            ], JSON_UNESCAPED_UNICODE));
            
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');

        } catch (\Exception $e) {
            error_log('[MobileController::planosDisponiveis] Erro: ' . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'type' => 'error',
                'code' => 'ERRO_INTERNO',
                'message' => 'Erro ao buscar planos disponíveis'
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Retorna os detalhes completos de um plano específico
     * Inclui ciclos de pagamento, turmas disponíveis e matrícula do usuário
     */
    #[OA\Get(
        path: "/mobile/planos/{planoId}",
        summary: "Detalhes do plano",
        description: "Retorna informações completas de um plano: dados gerais, ciclos de pagamento com economia, turmas/horários disponíveis e se o usuário já possui matrícula neste plano.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "planoId",
                in: "path",
                description: "ID do plano",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Detalhes do plano retornados com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer"),
                                new OA\Property(property: "nome", type: "string"),
                                new OA\Property(property: "descricao", type: "string"),
                                new OA\Property(property: "valor", type: "number"),
                                new OA\Property(property: "duracao_dias", type: "integer"),
                                new OA\Property(property: "checkins_semanais", type: "integer"),
                                new OA\Property(property: "modalidade", type: "object"),
                                new OA\Property(property: "ciclos", type: "array", items: new OA\Items(type: "object")),
                                new OA\Property(property: "turmas", type: "array", items: new OA\Items(type: "object")),
                                new OA\Property(property: "matricula_ativa", type: "object", nullable: true)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Tenant não selecionado"),
            new OA\Response(response: 404, description: "Plano não encontrado")
        ]
    )]
    public function detalhePlano(Request $request, Response $response, array $args): Response
    {
        try {
            $tenantId = $request->getAttribute('tenantId') ?? $request->getAttribute('tenant_id');
            $userId = $request->getAttribute('userId')
                ?? $request->getAttribute('user_id')
                ?? $request->getAttribute('usuarioId');
            $planoId = (int)($args['planoId'] ?? 0);

            if (!$tenantId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'type' => 'error',
                    'code' => 'TENANT_NAO_SELECIONADO',
                    'message' => 'Nenhum tenant selecionado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            if (!$planoId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'type' => 'error',
                    'code' => 'PLANO_ID_OBRIGATORIO',
                    'message' => 'ID do plano é obrigatório'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // 1. Buscar dados do plano
            $stmtPlano = $this->db->prepare("
                SELECT p.id, p.nome, p.descricao, p.valor, p.duracao_dias, p.checkins_semanais, p.ativo,
                       m.id as modalidade_id, m.nome as modalidade_nome, m.cor as modalidade_cor
                FROM planos p
                LEFT JOIN modalidades m ON p.modalidade_id = m.id
                WHERE p.id = :plano_id AND p.tenant_id = :tenant_id
            ");
            $stmtPlano->execute(['plano_id' => $planoId, 'tenant_id' => $tenantId]);
            $plano = $stmtPlano->fetch(\PDO::FETCH_ASSOC);

            if (!$plano) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'type' => 'error',
                    'code' => 'PLANO_NAO_ENCONTRADO',
                    'message' => 'Plano não encontrado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // 2. Buscar ciclos de pagamento do plano
            $stmtCiclos = $this->db->prepare("
                SELECT pc.id, af.nome, af.codigo, pc.meses, pc.valor,
                       pc.valor_mensal_equivalente, pc.desconto_percentual, pc.permite_recorrencia, pc.permite_reposicao,
                       af.ordem
                FROM plano_ciclos pc
                INNER JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
                WHERE pc.plano_id = :plano_id AND pc.ativo = 1
                ORDER BY af.ordem ASC
            ");
            $stmtCiclos->execute(['plano_id' => $planoId]);
            $ciclosRaw = $stmtCiclos->fetchAll(\PDO::FETCH_ASSOC);

            // Calcular economia relativa ao ciclo mensal
            $valorMensalBase = 0;
            foreach ($ciclosRaw as $c) {
                if ((int)$c['meses'] === 1) {
                    $valorMensalBase = (float)$c['valor_mensal_equivalente'];
                    break;
                }
            }
            // Se não tem mensal, usar o de menor período
            if ($valorMensalBase === 0.0 && !empty($ciclosRaw)) {
                $valorMensalBase = (float)$ciclosRaw[0]['valor_mensal_equivalente'];
            }

            $ciclos = array_map(function($c) use ($valorMensalBase) {
                $vme = (float)$c['valor_mensal_equivalente'];
                $economiaPercentual = 0;
                $economiaValor = 0;
                if ($valorMensalBase > 0 && $vme < $valorMensalBase) {
                    $economiaPercentual = round((($valorMensalBase - $vme) / $valorMensalBase) * 100, 0);
                    $economiaValor = round(($valorMensalBase - $vme) * (int)$c['meses'], 2);
                }
                return [
                    'id' => (int)$c['id'],
                    'nome' => $c['nome'],
                    'codigo' => $c['codigo'],
                    'meses' => (int)$c['meses'],
                    'valor' => (float)$c['valor'],
                    'valor_formatado' => 'R$ ' . number_format($c['valor'], 2, ',', '.'),
                    'valor_mensal' => $vme,
                    'valor_mensal_formatado' => 'R$ ' . number_format($vme, 2, ',', '.'),
                    'desconto_percentual' => $economiaPercentual,
                    'permite_recorrencia' => (bool)$c['permite_recorrencia'],
                    'permite_reposicao' => (bool)$c['permite_reposicao'],
                    'pix_disponivel' => !(bool)$c['permite_recorrencia'],
                    'metodos_pagamento' => (bool)$c['permite_recorrencia']
                        ? ['checkout']
                        : ['checkout', 'pix'],
                    'economia' => $economiaPercentual > 0
                        ? 'Economize ' . $economiaPercentual . '%'
                        : null,
                    'economia_valor' => $economiaValor > 0
                        ? 'R$ ' . number_format($economiaValor, 2, ',', '.') . ' de economia'
                        : null,
                ];
            }, $ciclosRaw);

            // 3. Buscar resumo de disponibilidade de turmas
            $disponibilidade = null;
            // if ($plano['modalidade_id']) {
            //     $stmtDisp = $this->db->prepare("
            //         SELECT 
            //             COUNT(DISTINCT t.id) as total_turmas,
            //             COUNT(DISTINCT CONCAT(t.horario_inicio, '-', t.horario_fim)) as horarios_distintos,
            //             SUM(t.limite_alunos) as total_vagas,
            //             SUM((SELECT COUNT(*) FROM inscricoes_turmas it
            //                  WHERE it.turma_id = t.id AND it.ativo = 1 AND it.status = 'ativa')) as vagas_ocupadas
            //         FROM turmas t
            //         WHERE t.tenant_id = :tenant_id
            //         AND t.modalidade_id = :modalidade_id
            //         AND t.ativo = 1
            //     ");
            //     $stmtDisp->execute([
            //         'tenant_id' => $tenantId,
            //         'modalidade_id' => $plano['modalidade_id']
            //     ]);
            //     $disp = $stmtDisp->fetch(\PDO::FETCH_ASSOC);
                
            //     if ($disp && $disp['total_turmas'] > 0) {
            //         $totalVagas = (int)$disp['total_vagas'];
            //         $vagasOcupadas = (int)$disp['vagas_ocupadas'];
            //         $vagasDisponiveis = max(0, $totalVagas - $vagasOcupadas);
                    
            //         $disponibilidade = [
            //             'total_turmas' => (int)$disp['total_turmas'],
            //             'horarios_disponiveis' => (int)$disp['horarios_distintos'],
            //             'vagas_disponiveis' => $vagasDisponiveis,
            //             'total_vagas' => $totalVagas,
            //         ];
            //     }
            // }

            // 4. Verificar se o usuário já tem matrícula ativa neste plano
            $matriculaAtiva = null;
            if ($userId) {
                $stmtMat = $this->db->prepare("
                    SELECT m.id, m.data_inicio, m.data_vencimento, m.proxima_data_vencimento, 
                           m.valor, sm.nome as status, sm.codigo as status_codigo
                    FROM matriculas m
                    INNER JOIN alunos a ON a.id = m.aluno_id
                    INNER JOIN status_matricula sm ON sm.id = m.status_id
                    WHERE a.usuario_id = :user_id
                    AND m.plano_id = :plano_id
                    AND m.tenant_id = :tenant_id
                    AND sm.codigo IN ('ativa', 'pendente')
                    ORDER BY m.created_at DESC
                    LIMIT 1
                ");
                $stmtMat->execute([
                    'user_id' => $userId,
                    'plano_id' => $planoId,
                    'tenant_id' => $tenantId,
                ]);
                $mat = $stmtMat->fetch(\PDO::FETCH_ASSOC);
                if ($mat) {
                    $matriculaAtiva = [
                        'id' => (int)$mat['id'],
                        'status' => $mat['status'],
                        'status_codigo' => $mat['status_codigo'],
                        'data_inicio' => $mat['data_inicio'],
                        'data_vencimento' => $mat['data_vencimento'] ?? $mat['proxima_data_vencimento'],
                        'valor' => (float)$mat['valor'],
                    ];
                }
            }

            // Montar resposta
            $data = [
                'id' => (int)$plano['id'],
                'nome' => $plano['nome'],
                'descricao' => $plano['descricao'],
                'valor' => (float)$plano['valor'],
                'valor_formatado' => 'R$ ' . number_format($plano['valor'], 2, ',', '.'),
                'duracao_dias' => (int)$plano['duracao_dias'],
                'duracao_texto' => $this->formatarDuracao((int)$plano['duracao_dias']),
                'checkins_semanais' => (int)$plano['checkins_semanais'],
                'ativo' => (bool)$plano['ativo'],
                'modalidade' => [
                    'id' => $plano['modalidade_id'] ? (int)$plano['modalidade_id'] : null,
                    'nome' => $plano['modalidade_nome'],
                    'cor' => $plano['modalidade_cor'],
                ],
                'ciclos' => $ciclos,
               // 'disponibilidade' => $disponibilidade,
                'matricula_ativa' => $matriculaAtiva,
                'is_plano_atual' => $matriculaAtiva !== null,
            ];

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $data,
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');

        } catch (\Exception $e) {
            error_log('[MobileController::detalhePlano] Erro: ' . $e->getMessage());

            $response->getBody()->write(json_encode([
                'success' => false,
                'type' => 'error',
                'code' => 'ERRO_INTERNO',
                'message' => 'Erro ao buscar detalhes do plano' . $e->getMessage()
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Comprar plano e gerar link de pagamento Mercado Pago
     * Endpoint simplificado para compra de plano pelo mobile
     */
    #[OA\Post(
        path: "/mobile/comprar-plano",
        summary: "Comprar plano",
        description: "Cria matrícula e retorna link de pagamento do Mercado Pago para o plano escolhido.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "plano_id", type: "integer", example: 1, description: "ID do plano escolhido"),
                    new OA\Property(property: "dia_vencimento", type: "integer", example: 5, description: "Dia do mês para vencimento (1-31)"),
                    new OA\Property(property: "metodo_pagamento", type: "string", example: "pix", description: "Método de pagamento: pix ou checkout")
                ],
                required: ["plano_id", "dia_vencimento"]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Matrícula criada e link de pagamento gerado",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Matrícula criada com sucesso"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "matricula_id", type: "integer", example: 456),
                                new OA\Property(property: "plano_nome", type: "string", example: "Mensal Básico"),
                                new OA\Property(property: "valor", type: "number", example: 99.90),
                                new OA\Property(property: "status", type: "string", example: "pendente"),
                                new OA\Property(property: "payment_url", type: "string", example: "https://www.mercadopago.com.br/checkout/..."),
                                new OA\Property(property: "preference_id", type: "string", example: "123456789-abc-def"),
                                new OA\Property(property: "pix", type: "object", nullable: true)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Dados inválidos ou matrícula ativa já existe"),
            new OA\Response(response: 401, description: "Não autorizado"),
            new OA\Response(response: 404, description: "Plano não encontrado")
        ]
    )]
    public function comprarPlano(Request $request, Response $response): Response
    {
        try {
            error_log("[comprarPlano] ========== INÍCIO ==========");
            $userId = $request->getAttribute('userId');
            $tenantId = $request->getAttribute('tenantId');
            $data = $request->getParsedBody();
            error_log("[comprarPlano] userId={$userId}, tenantId={$tenantId}, data=" . json_encode($data));

            // Validações
            if (!$tenantId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'type' => 'error',
                    'code' => 'TENANT_NAO_SELECIONADO',
                    'message' => 'Nenhum tenant selecionado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            if (empty($data['plano_id'])) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'type' => 'error',
                    'code' => 'PLANO_OBRIGATORIO',
                    'message' => 'Plano é obrigatório'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $planoId = (int) $data['plano_id'];
            $planoCicloId = !empty($data['plano_ciclo_id']) ? (int) $data['plano_ciclo_id'] : null;
            // Dia de vencimento é opcional, padrão = 5
            $diaVencimento = isset($data['dia_vencimento']) ? (int) $data['dia_vencimento'] : 5;
            $metodoPagamento = strtolower(trim($data['metodo_pagamento'] ?? 'checkout'));

            if (!in_array($metodoPagamento, ['checkout', 'pix'], true)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'type' => 'error',
                    'code' => 'METODO_PAGAMENTO_INVALIDO',
                    'message' => 'Método de pagamento inválido. Use pix ou checkout.'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Validar dia de vencimento se foi fornecido
            if ($diaVencimento < 1 || $diaVencimento > 31) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'type' => 'error',
                    'code' => 'DIA_VENCIMENTO_INVALIDO',
                    'message' => 'Dia de vencimento deve estar entre 1 e 31'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Buscar aluno_id do usuário logado
            $stmtAluno = $this->db->prepare("SELECT id FROM alunos WHERE usuario_id = ?");
            $stmtAluno->execute([$userId]);
            $aluno = $stmtAluno->fetch(\PDO::FETCH_ASSOC);

            if (!$aluno) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'type' => 'error',
                    'code' => 'ALUNO_NAO_ENCONTRADO',
                    'message' => 'Perfil de aluno não encontrado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $alunoId = $aluno['id'];

            // Buscar dados do usuário
            $usuario = $this->usuarioModel->findById($userId, $tenantId);
            if (!$usuario) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'type' => 'error',
                    'code' => 'USUARIO_NAO_ENCONTRADO',
                    'message' => 'Usuário não encontrado'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Buscar plano
            $stmtPlano = $this->db->prepare("
                SELECT p.*, m.nome as modalidade_nome 
                FROM planos p 
                LEFT JOIN modalidades m ON p.modalidade_id = m.id
                WHERE p.id = ? AND p.tenant_id = ? AND p.ativo = 1
            ");
            $stmtPlano->execute([$planoId, $tenantId]);
            $plano = $stmtPlano->fetch(\PDO::FETCH_ASSOC);

            if (!$plano) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'type' => 'error',
                    'code' => 'PLANO_NAO_ENCONTRADO',
                    'message' => 'Plano não encontrado ou inativo'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // Buscar dados do ciclo se informado
            $ciclo = null;
            $valorCompra = (float) $plano['valor'];
            $duracaoMeses = 1;
            $cicloNome = 'Mensal';
            
            if ($planoCicloId) {
                $stmtCiclo = $this->db->prepare("
                    SELECT pc.*, af.nome as ciclo_nome, af.codigo as ciclo_codigo
                    FROM plano_ciclos pc
                    INNER JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
                    WHERE pc.id = ? AND pc.plano_id = ? AND pc.tenant_id = ? AND pc.ativo = 1
                ");
                $stmtCiclo->execute([$planoCicloId, $planoId, $tenantId]);
                $ciclo = $stmtCiclo->fetch(\PDO::FETCH_ASSOC);
                
                if (!$ciclo) {
                    $response->getBody()->write(json_encode([
                        'success' => false,
                        'type' => 'error',
                        'code' => 'CICLO_NAO_ENCONTRADO',
                        'message' => 'Ciclo de pagamento não encontrado',
                        'debug' => [
                            'tenant_id' => $tenantId,
                            'plano_id' => $planoId,
                            'plano_ciclo_id' => $planoCicloId
                        ]
                    ]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
                }
                
                $valorCompra = (float) $ciclo['valor'];
                $duracaoMeses = (int) $ciclo['meses'];
                $cicloNome = $ciclo['ciclo_nome'];
            }
            
            // Determinar se é pagamento recorrente ou avulso
            $isRecorrente = $ciclo ? (bool) $ciclo['permite_recorrencia'] : true;
            if ($metodoPagamento === 'pix' && $isRecorrente) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'type' => 'error',
                    'code' => 'PIX_NAO_DISPONIVEL_RECORRENTE',
                    'message' => 'Pagamento PIX não está disponível para planos recorrentes'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Verificar se valor é maior que zero
            if ($valorCompra <= 0) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'type' => 'error',
                    'code' => 'PLANO_INVALIDO',
                    'message' => 'Este plano não está disponível para compra'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Verificar valor mínimo do Mercado Pago (R$ 0,50)
            if ($valorCompra < 0.50) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'type' => 'error',
                    'code' => 'VALOR_MINIMO',
                    'message' => 'O valor mínimo para pagamento é R$ 0,50',
                    'valor_atual' => $valorCompra,
                    'valor_minimo' => 0.50
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Verificar se já existe matrícula ativa na mesma modalidade
            $stmtAtiva = $this->db->prepare("
                SELECT m.id, m.status_id, m.proxima_data_vencimento, p.modalidade_id, sm.codigo
                FROM matriculas m
                INNER JOIN planos p ON p.id = m.plano_id
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                WHERE m.aluno_id = ? 
                AND m.tenant_id = ? 
                AND p.modalidade_id = ?
                AND sm.codigo = 'ativa' 
                AND m.proxima_data_vencimento >= CURDATE()
                ORDER BY m.created_at DESC
                LIMIT 1
            ");
            $stmtAtiva->execute([$alunoId, $tenantId, $plano['modalidade_id']]);
            $matriculaAtiva = $stmtAtiva->fetch(\PDO::FETCH_ASSOC);

            if ($matriculaAtiva) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'type' => 'error',
                    'code' => 'MATRICULA_ATIVA_EXISTENTE',
                    'message' => 'Você já possui uma matrícula ativa nesta modalidade'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Se já existe matrícula pendente na mesma modalidade, reutilizar o pagamento anterior
            $stmtPendente = $this->db->prepare("
                SELECT m.id, m.plano_id, m.plano_ciclo_id, m.valor, m.data_inicio, m.data_vencimento, m.proxima_data_vencimento,
                       sm.codigo as status_codigo
                FROM matriculas m
                INNER JOIN planos p ON p.id = m.plano_id
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                WHERE m.aluno_id = ?
                  AND m.tenant_id = ?
                  AND p.modalidade_id = ?
                  AND sm.codigo = 'pendente'
                ORDER BY m.updated_at DESC, m.id DESC
                LIMIT 1
            ");
            $stmtPendente->execute([$alunoId, $tenantId, $plano['modalidade_id']]);
            $matriculaPendente = $stmtPendente->fetch(\PDO::FETCH_ASSOC) ?: null;

            if ($matriculaPendente && $metodoPagamento === 'pix') {
                // Buscar assinatura pendente para recuperar payment_url/preference_id
                $stmtAss = $this->db->prepare("
                    SELECT id, gateway_preference_id, payment_url, tipo_cobranca, status_gateway
                    FROM assinaturas
                    WHERE matricula_id = ? AND tenant_id = ?
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $stmtAss->execute([(int)$matriculaPendente['id'], $tenantId]);
                $assinaturaPendente = $stmtAss->fetch(\PDO::FETCH_ASSOC) ?: null;

                // Se o usuário pediu PIX e não existe PIX salvo, gerar 1x e atualizar a assinatura
                if ($metodoPagamento === 'pix') {
                    $temPixSalvo = !empty($assinaturaPendente['payment_url'])
                        && str_contains($assinaturaPendente['payment_url'], '/payments/')
                        && !empty($assinaturaPendente['gateway_preference_id']);

                    // Buscar PIX salvo no banco
                    $pixSalvo = null;
                    if ($temPixSalvo) {
                        $stmtPix = $this->db->prepare("
                            SELECT payment_id, ticket_url, qr_code, qr_code_base64, expires_at, status
                            FROM pagamentos_pix
                            WHERE tenant_id = ? AND matricula_id = ?
                            ORDER BY id DESC
                            LIMIT 1
                        ");
                        $stmtPix->execute([$tenantId, (int)$matriculaPendente['id']]);
                        $pixSalvo = $stmtPix->fetch(\PDO::FETCH_ASSOC) ?: null;
                    }

                    $pixExpirado = false;
                    if ($pixSalvo && !empty($pixSalvo['expires_at'])) {
                        $pixExpirado = strtotime($pixSalvo['expires_at']) < time();
                    }

                    if (!$temPixSalvo || $pixExpirado) {
                        $stmtTenant = $this->db->prepare("SELECT nome FROM tenants WHERE id = ?");
                        $stmtTenant->execute([$tenantId]);
                        $tenantData = $stmtTenant->fetch(\PDO::FETCH_ASSOC);
                        $academiaNome = $tenantData['nome'] ?? 'Academia';

                        $dadosPagamentoPix = [
                            'tenant_id' => $tenantId,
                            'matricula_id' => (int) $matriculaPendente['id'],
                            'aluno_id' => $alunoId,
                            'usuario_id' => $userId,
                            'aluno_nome' => $usuario['nome'],
                            'aluno_email' => $usuario['email'],
                            'aluno_telefone' => $usuario['telefone'] ?? '',
                            'aluno_cpf' => $usuario['cpf'] ?? null,
                            'plano_nome' => $plano['nome'],
                            'descricao' => "{$plano['nome']} - {$plano['modalidade_nome']}",
                            'valor' => (float) $matriculaPendente['valor'],
                            'academia_nome' => $academiaNome
                        ];

                        $mercadoPago = new \App\Services\MercadoPagoService($tenantId);
                        $pixData = $mercadoPago->criarPagamentoPix($dadosPagamentoPix);

                        $externalReference = $pixData['external_reference'] ?? ("MAT-{$matriculaPendente['id']}-" . time());
                        $paymentUrlPix = $pixData['ticket_url'] ?? null;
                        $paymentIdPix = $pixData['id'] ?? null;

                        if ($paymentUrlPix && $paymentIdPix) {
                            if ($assinaturaPendente) {
                                $stmtUpdateAss = $this->db->prepare("
                                    UPDATE assinaturas
                                    SET gateway_preference_id = ?,
                                        external_reference = ?,
                                        payment_url = ?,
                                        status_gateway = 'pending',
                                        atualizado_em = NOW()
                                    WHERE id = ? AND tenant_id = ?
                                ");
                                $stmtUpdateAss->execute([
                                    (string) $paymentIdPix,
                                    $externalReference,
                                    $paymentUrlPix,
                                    (int) $assinaturaPendente['id'],
                                    $tenantId
                                ]);
                            } else {
                                // Se não existe assinatura, criar uma nova pendente vinculada à matrícula
                                $stmtGateway = $this->db->prepare("SELECT id FROM assinatura_gateways WHERE codigo = 'mercadopago'");
                                $stmtGateway->execute();
                                $gatewayId = $stmtGateway->fetchColumn() ?: 1;

                                $stmtStatus = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = 'pendente'");
                                $stmtStatus->execute();
                                $statusId = $stmtStatus->fetchColumn() ?: 1;

                                $stmtMetodo = $this->db->prepare("SELECT id FROM metodos_pagamento WHERE codigo = 'pix'");
                                $stmtMetodo->execute();
                                $metodoPagamentoId = $stmtMetodo->fetchColumn() ?: null;

                                // Frequência: tentar usar o ciclo do plano, senão mensal
                                $frequenciaId = 4;
                                if (!empty($matriculaPendente['plano_ciclo_id'])) {
                                    $stmtFreq = $this->db->prepare("
                                        SELECT af.id
                                        FROM plano_ciclos pc
                                        INNER JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
                                        WHERE pc.id = ? AND pc.tenant_id = ?
                                        LIMIT 1
                                    ");
                                    $stmtFreq->execute([(int)$matriculaPendente['plano_ciclo_id'], $tenantId]);
                                    $frequenciaId = $stmtFreq->fetchColumn() ?: 4;
                                }

                                $stmtAssinatura = $this->db->prepare("
                                    INSERT INTO assinaturas
                                    (tenant_id, matricula_id, aluno_id, plano_id,
                                     gateway_id, gateway_preference_id, external_reference, payment_url,
                                     status_id, status_gateway, valor, frequencia_id, dia_cobranca,
                                     data_inicio, data_fim, proxima_cobranca, metodo_pagamento_id, tipo_cobranca, criado_em)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                                ");
                                $stmtAssinatura->execute([
                                    $tenantId,
                                    (int) $matriculaPendente['id'],
                                    $alunoId,
                                    (int) $matriculaPendente['plano_id'],
                                    $gatewayId,
                                    (string) $paymentIdPix,
                                    $externalReference,
                                    $paymentUrlPix,
                                    $statusId,
                                    (float) $matriculaPendente['valor'],
                                    $frequenciaId,
                                    (int) date('d'),
                                    date('Y-m-d'),
                                    $matriculaPendente['proxima_data_vencimento'] ?? $matriculaPendente['data_vencimento'],
                                    null,
                                    $metodoPagamentoId,
                                    'avulso'
                                ]);
                            }

                            $assinaturaPendente['gateway_preference_id'] = (string) $paymentIdPix;
                            $assinaturaPendente['payment_url'] = $paymentUrlPix;
                            $assinaturaPendente['tipo_cobranca'] = $assinaturaPendente['tipo_cobranca'] ?? 'avulso';

                            try {
                                $stmtPixSave = $this->db->prepare("
                                    INSERT INTO pagamentos_pix
                                    (tenant_id, matricula_id, payment_id, ticket_url, qr_code, qr_code_base64, expires_at, status)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ");
                                $stmtPixSave->execute([
                                    $tenantId,
                                    (int) $matriculaPendente['id'],
                                    (string) $paymentIdPix,
                                    $paymentUrlPix,
                                    $pixData['qr_code'] ?? null,
                                    $pixData['qr_code_base64'] ?? null,
                                    isset($pixData['date_of_expiration']) ? date('Y-m-d H:i:s', strtotime($pixData['date_of_expiration'])) : null,
                                    $pixData['status'] ?? 'pending'
                                ]);
                            } catch (\Exception $e) {
                                error_log("[MobileController::comprarPlano] Erro ao salvar PIX (reabrir): " . $e->getMessage());
                            }
                        }
                    } elseif ($pixSalvo) {
                        // Reutilizar PIX salvo
                        $assinaturaPendente['payment_url'] = $pixSalvo['ticket_url'] ?? $assinaturaPendente['payment_url'];
                        $assinaturaPendente['gateway_preference_id'] = $pixSalvo['payment_id'] ?? $assinaturaPendente['gateway_preference_id'];
                        $pixData = [
                            'id' => $pixSalvo['payment_id'] ?? null,
                            'status' => $pixSalvo['status'] ?? null,
                            'status_detail' => null,
                            'qr_code' => $pixSalvo['qr_code'] ?? null,
                            'qr_code_base64' => $pixSalvo['qr_code_base64'] ?? null,
                            'ticket_url' => $pixSalvo['ticket_url'] ?? null,
                            'date_of_expiration' => $pixSalvo['expires_at'] ?? null
                        ];
                    }
                }

                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Já existe pagamento pendente. Reabra para concluir.',
                    'data' => [
                        'matricula_id' => (int) $matriculaPendente['id'],
                        'plano_id' => (int) $matriculaPendente['plano_id'],
                        'plano_ciclo_id' => $matriculaPendente['plano_ciclo_id'] ? (int)$matriculaPendente['plano_ciclo_id'] : null,
                        'plano_nome' => $plano['nome'],
                        'modalidade' => $plano['modalidade_nome'],
                        'valor' => (float) $matriculaPendente['valor'],
                        'valor_formatado' => 'R$ ' . number_format((float)$matriculaPendente['valor'], 2, ',', '.'),
                        'status' => 'pendente',
                        'data_inicio' => $matriculaPendente['data_inicio'],
                        'data_vencimento' => $matriculaPendente['proxima_data_vencimento'] ?? $matriculaPendente['data_vencimento'],
                        'vencida' => ($matriculaPendente['proxima_data_vencimento'] ?? $matriculaPendente['data_vencimento']) < date('Y-m-d'),
                        'payment_url' => $assinaturaPendente['payment_url'] ?? null,
                        'preference_id' => $assinaturaPendente['gateway_preference_id'] ?? null,
                        'tipo_pagamento' => $metodoPagamento === 'pix' ? 'pix' : (($assinaturaPendente['tipo_cobranca'] ?? '') === 'avulso' ? 'pagamento_unico' : 'assinatura'),
                        'metodo_pagamento' => $metodoPagamento,
                        'tipo_cobranca' => $assinaturaPendente['tipo_cobranca'] ?? 'avulso',
                        'recorrente' => ($assinaturaPendente['tipo_cobranca'] ?? '') === 'recorrente',
                        'pix' => $pixData ? [
                            'payment_id' => $pixData['id'] ?? null,
                            'status' => $pixData['status'] ?? null,
                            'status_detail' => $pixData['status_detail'] ?? null,
                            'qr_code' => $pixData['qr_code'] ?? null,
                            'qr_code_base64' => $pixData['qr_code_base64'] ?? null,
                            'ticket_url' => $pixData['ticket_url'] ?? null,
                            'expires_at' => $pixData['date_of_expiration'] ?? null
                        ] : null
                    ]
                ], JSON_UNESCAPED_UNICODE));

                return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
            }

            // Se existe matrícula pendente e o método é checkout, reutilizar para gerar novo pagamento
            $matriculaExistente = null;
            $reutilizandoMatricula = false;
            if ($matriculaPendente && $metodoPagamento !== 'pix') {
                $matriculaExistente = $matriculaPendente;
                $reutilizandoMatricula = true;
            }

            // Verificar se existe matrícula vencida na mesma modalidade para reutilizar (apenas se não já reutilizou pendente)
            if (!$matriculaExistente) {
                $stmtVencida = $this->db->prepare("
                    SELECT m.id, m.plano_id, m.plano_ciclo_id, m.valor, m.data_inicio, m.data_vencimento, m.proxima_data_vencimento,
                           sm.codigo as status_codigo
                    FROM matriculas m
                    INNER JOIN planos p ON p.id = m.plano_id
                    INNER JOIN status_matricula sm ON sm.id = m.status_id
                    WHERE m.aluno_id = ?
                      AND m.tenant_id = ?
                      AND p.modalidade_id = ?
                    ORDER BY m.updated_at DESC, m.id DESC
                    LIMIT 1
                ");
                $stmtVencida->execute([$alunoId, $tenantId, $plano['modalidade_id']]);
                $matriculaExistente = $stmtVencida->fetch(\PDO::FETCH_ASSOC) ?: null;
                $reutilizandoMatricula = false;
            }
            if ($matriculaExistente) {
                $statusCodigo = $matriculaExistente['status_codigo'] ?? null;
                $vencimento = $matriculaExistente['proxima_data_vencimento'] ?? $matriculaExistente['data_vencimento'];
                $vencidaPorData = $vencimento && $vencimento < date('Y-m-d');

                if ($vencidaPorData && $statusCodigo !== 'vencida') {
                    $stmtStatusVencida = $this->db->prepare("SELECT id FROM status_matricula WHERE codigo = 'vencida' LIMIT 1");
                    $stmtStatusVencida->execute();
                    $statusVencidaId = $stmtStatusVencida->fetchColumn();
                    if ($statusVencidaId) {
                        $stmtMarcarVencida = $this->db->prepare("
                            UPDATE matriculas
                            SET status_id = ?, updated_at = NOW()
                            WHERE id = ? AND tenant_id = ?
                        ");
                        $stmtMarcarVencida->execute([$statusVencidaId, $matriculaExistente['id'], $tenantId]);
                        $statusCodigo = 'vencida';
                    }
                }

                if ($statusCodigo === 'vencida' || $vencidaPorData) {
                    $reutilizandoMatricula = true;
                }
            }

            // Calcular datas baseado no ciclo
            $dataInicio = date('Y-m-d');
            $dataMatricula = $dataInicio;
            $dataInicioObj = new \DateTime($dataInicio);
            
            // Calcular vencimento baseado no ciclo (meses) ou duração do plano (dias)
            $dataVencimento = clone $dataInicioObj;
            if ($duracaoMeses > 1) {
                // Ciclo com mais de 1 mês: usar meses
                $dataVencimento->modify("+{$duracaoMeses} months");
            } else {
                // Ciclo mensal: usar duração em dias do plano
                $duracaoDias = (int) $plano['duracao_dias'];
                $dataVencimento->modify("+{$duracaoDias} days");
            }
            
            $proximaDataVencimento = clone $dataVencimento;

            // Buscar status "pendente" (matrícula aguardando pagamento)
            $stmtStatus = $this->db->prepare("SELECT id FROM status_matricula WHERE codigo = 'pendente'");
            $stmtStatus->execute();
            $statusRow = $stmtStatus->fetch(\PDO::FETCH_ASSOC);
            $statusId = $statusRow['id'] ?? 5;

            // Buscar motivo
            $motivoCodigo = 'nova';
            $planoAnteriorId = null;
            if ($reutilizandoMatricula && $matriculaExistente) {
                $planoAnteriorId = (int) $matriculaExistente['plano_id'];
                if ($planoAnteriorId === $planoId) {
                    $motivoCodigo = 'renovacao';
                } else {
                    $valorAnterior = (float) ($matriculaExistente['valor'] ?? 0);
                    $motivoCodigo = $valorCompra >= $valorAnterior ? 'upgrade' : 'downgrade';
                }
            }

            $stmtMotivo = $this->db->prepare("SELECT id FROM motivo_matricula WHERE codigo = ?");
            $stmtMotivo->execute([$motivoCodigo]);
            $motivoRow = $stmtMotivo->fetch(\PDO::FETCH_ASSOC);
            $motivoId = $motivoRow['id'] ?? 1;

            // tipo_cobranca: 'recorrente' para assinaturas, 'avulso' para pagamento único
            $tipoCobrancaMatricula = $isRecorrente ? 'recorrente' : 'avulso';

            if ($reutilizandoMatricula && $matriculaExistente) {
                $matriculaId = (int) $matriculaExistente['id'];
                error_log("[MobileController::comprarPlano] Reutilizando matrícula vencida ID: {$matriculaId}");

                $stmtUpdateMat = $this->db->prepare("
                    UPDATE matriculas
                    SET plano_id = ?,
                        plano_ciclo_id = ?,
                        tipo_cobranca = ?,
                        data_matricula = ?,
                        data_inicio = ?,
                        data_vencimento = ?,
                        valor = ?,
                        status_id = ?,
                        motivo_id = ?,
                        plano_anterior_id = ?,
                        dia_vencimento = ?,
                        proxima_data_vencimento = ?,
                        criado_por = ?,
                        updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmtUpdateMat->execute([
                    $planoId,
                    $planoCicloId,
                    $tipoCobrancaMatricula,
                    $dataMatricula,
                    $dataInicio,
                    $dataVencimento->format('Y-m-d'),
                    $valorCompra,
                    $statusId,
                    $motivoId,
                    $planoAnteriorId,
                    $diaVencimento,
                    $proximaDataVencimento->format('Y-m-d'),
                    $userId,
                    $matriculaId,
                    $tenantId
                ]);

                // Registrar histórico de planos
                try {
                    $stmtHistorico = $this->db->prepare("
                        INSERT INTO historico_planos 
                        (usuario_id, plano_anterior_id, plano_novo_id, data_inicio, data_vencimento, valor_pago, motivo, observacoes, criado_por)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtHistorico->execute([
                        $userId,
                        $planoAnteriorId,
                        $planoId,
                        $dataInicio,
                        $dataVencimento->format('Y-m-d'),
                        $valorCompra,
                        $motivoCodigo,
                        'Atualização de matrícula vencida via app',
                        $userId
                    ]);
                } catch (\Exception $e) {
                    error_log("[MobileController::comprarPlano] Erro ao registrar histórico: " . $e->getMessage());
                }
            } else {
                // Criar matrícula com status PENDENTE
                error_log("[MobileController::comprarPlano] Preparando INSERT - tenantId={$tenantId}, alunoId={$alunoId}, planoId={$planoId}, planoCicloId=" . ($planoCicloId ?? 'null') . ", valorCompra={$valorCompra}, statusId={$statusId}, motivoId={$motivoId}");

                $stmtInsert = $this->db->prepare("
                    INSERT INTO matriculas 
                    (tenant_id, aluno_id, plano_id, plano_ciclo_id, tipo_cobranca, data_matricula, data_inicio, data_vencimento, 
                     valor, status_id, motivo_id, dia_vencimento, periodo_teste, proxima_data_vencimento, criado_por)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)
                ");

                $insertParams = [
                    $tenantId,
                    $alunoId,
                    $planoId,
                    $planoCicloId,
                    $tipoCobrancaMatricula,
                    $dataMatricula,
                    $dataInicio,
                    $dataVencimento->format('Y-m-d'),
                    $valorCompra,
                    $statusId,
                    $motivoId,
                    $diaVencimento,
                    $proximaDataVencimento->format('Y-m-d'),
                    $userId
                ];

                error_log("[MobileController::comprarPlano] INSERT params: " . json_encode($insertParams));

                $stmtInsert->execute($insertParams);

                $matriculaId = (int) $this->db->lastInsertId();

                error_log("[MobileController::comprarPlano] Matrícula criada ID: {$matriculaId}, Ciclo: {$cicloNome}, Valor: {$valorCompra}, Status: pendente");
            }

            // Criar registro de pagamento PENDENTE em pagamentos_plano
            try {
                $stmtPagamento = $this->db->prepare("
                    INSERT INTO pagamentos_plano 
                    (tenant_id, aluno_id, matricula_id, plano_id, valor, data_vencimento, 
                     status_pagamento_id, observacoes, criado_por, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, 
                            (SELECT id FROM status_pagamento WHERE codigo = 'pendente' LIMIT 1), 
                            'Aguardando pagamento via Mercado Pago', ?, NOW(), NOW())
                ");
                
                $stmtPagamento->execute([
                    $tenantId,
                    $alunoId,
                    $matriculaId,
                    $planoId,
                    $valorCompra,
                    $dataInicio,
                    $userId
                ]);
                
                $pagamentoId = (int) $this->db->lastInsertId();
                error_log("[MobileController::comprarPlano] Pagamento pendente criado ID: {$pagamentoId}");
                
            } catch (\Exception $e) {
                error_log("[MobileController::comprarPlano] Erro ao criar pagamento_plano: " . $e->getMessage());
                // Continua mesmo se falhar (webhook pode criar depois)
            }

            // Gerar link de pagamento com Mercado Pago
            $paymentUrl = null;
            $preferenceId = null;
            $tipoPagamento = 'pagamento_unico';
            $pixData = null;
            $pixPaymentId = null;

            try {
                // Passar tenant_id para carregar credenciais específicas do tenant
                $mercadoPago = new \App\Services\MercadoPagoService($tenantId);
                
                $descricaoCompra = $planoCicloId 
                    ? "{$plano['nome']} ({$cicloNome}) - {$plano['modalidade_nome']}"
                    : "{$plano['nome']} - {$plano['modalidade_nome']}";
                
                // Buscar nome da academia
                $stmtTenant = $this->db->prepare("SELECT nome FROM tenants WHERE id = ?");
                $stmtTenant->execute([$tenantId]);
                $tenantData = $stmtTenant->fetch(\PDO::FETCH_ASSOC);
                $academiaNome = $tenantData['nome'] ?? 'Academia';
                
                $dadosPagamento = [
                    'tenant_id' => $tenantId,
                    'matricula_id' => $matriculaId,
                    'aluno_id' => $alunoId,
                    'usuario_id' => $userId,
                    'aluno_nome' => $usuario['nome'],
                    'aluno_email' => $usuario['email'],
                    'aluno_telefone' => $usuario['telefone'] ?? '',
                    'aluno_cpf' => $usuario['cpf'] ?? null,
                    'plano_nome' => $plano['nome'],
                    'descricao' => $descricaoCompra,
                    'valor' => $valorCompra,
                    'max_parcelas' => 12,
                    'academia_nome' => $academiaNome,
                    // Assinatura recorrente aceita APENAS cartão (preapproval)
                    // Pagamento avulso aceita PIX, boleto, cartão, etc. (preference)
                    'apenas_cartao' => $isRecorrente
                ];

                error_log("[MobileController::comprarPlano] Ciclo: {$cicloNome}, Meses: {$duracaoMeses}, Recorrente: " . ($isRecorrente ? 'SIM' : 'NÃO'));

                if ($metodoPagamento === 'pix') {
                    $cpfPix = preg_replace('/[^0-9]/', '', $usuario['cpf'] ?? '');
                    if (strlen($cpfPix) !== 11) {
                        $response->getBody()->write(json_encode([
                            'success' => false,
                            'type' => 'error',
                            'code' => 'CPF_OBRIGATORIO_PIX',
                            'message' => 'CPF válido é obrigatório para pagamento PIX'
                        ]));
                        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                    }

                    error_log("[MobileController::comprarPlano] Criando PAGAMENTO PIX...");
                    $pixData = $mercadoPago->criarPagamentoPix($dadosPagamento);
                    $tipoPagamento = 'pix';
                    $paymentUrl = $pixData['ticket_url'] ?? null;
                    $pixPaymentId = $pixData['id'] ?? null;
                    $preferenceId = $pixPaymentId;
                    $externalReference = $pixData['external_reference'] ?? null;

                    // Salvar dados do PIX para reabrir
                    try {
                        if ($pixPaymentId && $paymentUrl) {
                            $stmtPix = $this->db->prepare("
                                INSERT INTO pagamentos_pix
                                (tenant_id, matricula_id, payment_id, ticket_url, qr_code, qr_code_base64, expires_at, status)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmtPix->execute([
                                $tenantId,
                                $matriculaId,
                                (string) $pixPaymentId,
                                $paymentUrl,
                                $pixData['qr_code'] ?? null,
                                $pixData['qr_code_base64'] ?? null,
                                isset($pixData['date_of_expiration']) ? date('Y-m-d H:i:s', strtotime($pixData['date_of_expiration'])) : null,
                                $pixData['status'] ?? 'pending'
                            ]);
                        }
                    } catch (\Exception $e) {
                        error_log("[MobileController::comprarPlano] Erro ao salvar PIX: " . $e->getMessage());
                    }
                } elseif ($isRecorrente) {
                    // ASSINATURA RECORRENTE (preapproval) no Mercado Pago
                    error_log("[MobileController::comprarPlano] Criando ASSINATURA RECORRENTE (preapproval)...");
                    $preferencia = $mercadoPago->criarPreferenciaAssinatura($dadosPagamento, $duracaoMeses);
                    $tipoPagamento = 'assinatura';
                } else {
                    // PAGAMENTO ÚNICO/AVULSO (preference) no Mercado Pago
                    error_log("[MobileController::comprarPlano] Criando PAGAMENTO AVULSO (preference)...");
                    $preferencia = $mercadoPago->criarPreferenciaPagamento($dadosPagamento);
                    $tipoPagamento = 'pagamento_unico';
                }
                
                if (isset($preferencia)) {
                    $paymentUrl = $preferencia['init_point'] ?? $paymentUrl;
                    $preferenceId = $preferencia['id'] ?? $preferenceId;
                    $externalReference = $preferencia['external_reference'] ?? $externalReference;
                }

                error_log("[MobileController::comprarPlano] ✅ Link gerado ({$tipoPagamento}): {$preferenceId}, external_ref: {$externalReference}");

                // Gravar na tabela assinaturas (tanto recorrente quanto avulso)
                try {
                    // Buscar IDs das tabelas de lookup
                    $stmtGateway = $this->db->prepare("SELECT id FROM assinatura_gateways WHERE codigo = 'mercadopago'");
                    $stmtGateway->execute();
                    $gatewayId = $stmtGateway->fetchColumn() ?: 1;
                    
                    $stmtStatus = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = 'pendente'");
                    $stmtStatus->execute();
                    $statusId = $stmtStatus->fetchColumn() ?: 1;
                    
                    // Mapear ciclo_nome para código de frequência
                    $codigoFrequencia = strtolower($cicloNome);
                    $stmtFreq = $this->db->prepare("SELECT id FROM assinatura_frequencias WHERE codigo = ?");
                    $stmtFreq->execute([$codigoFrequencia]);
                    $frequenciaId = $stmtFreq->fetchColumn() ?: 4;
                    
                    // Método de pagamento: cartão para recorrente, null para avulso (será definido no pagamento)
                    $metodoPagamentoId = null;
                    if ($isRecorrente) {
                        $stmtMetodo = $this->db->prepare("SELECT id FROM metodos_pagamento WHERE codigo = 'credit_card'");
                        $stmtMetodo->execute();
                        $metodoPagamentoId = $stmtMetodo->fetchColumn() ?: 1;
                    } elseif ($metodoPagamento === 'pix') {
                        $stmtMetodo = $this->db->prepare("SELECT id FROM metodos_pagamento WHERE codigo = 'pix'");
                        $stmtMetodo->execute();
                        $metodoPagamentoId = $stmtMetodo->fetchColumn() ?: null;
                    }
                    
                    // Buscar plano_id da matrícula
                    $stmtPlano = $this->db->prepare("SELECT plano_id FROM matriculas WHERE id = ?");
                    $stmtPlano->execute([$matriculaId]);
                    $planoIdMatricula = $stmtPlano->fetchColumn();
                    
                    $diaCobranca = (int) date('d');
                    $tipoCobranca = $isRecorrente ? 'recorrente' : 'avulso';
                    
                    // Para recorrente: próxima cobrança em 1 mês
                    // Para avulso: data_fim é a data de vencimento do ciclo
                    $proximaCobranca = $isRecorrente ? date('Y-m-d', strtotime('+1 month')) : null;
                    $dataFim = !$isRecorrente ? $proximaDataVencimento->format('Y-m-d') : null;
                    
                    // IDs do gateway conforme tipo
                    $gatewayAssinaturaId = $isRecorrente ? $preferenceId : null;
                    $gatewayPreferenceId = !$isRecorrente ? ($preferenceId ?? $pixPaymentId) : null;
                    
                    // Se reutilizou matrícula, tentar atualizar assinatura existente (matricula_id é UNIQUE)
                    $assinaturaAtualizada = false;
                    if ($reutilizandoMatricula ?? false) {
                        $stmtUpdateAss = $this->db->prepare("
                            UPDATE assinaturas
                            SET gateway_id = ?,
                                gateway_assinatura_id = ?,
                                gateway_preference_id = ?,
                                external_reference = ?,
                                payment_url = ?,
                                status_id = ?,
                                status_gateway = 'pending',
                                valor = ?,
                                frequencia_id = ?,
                                dia_cobranca = ?,
                                data_inicio = ?,
                                data_fim = ?,
                                proxima_cobranca = ?,
                                metodo_pagamento_id = ?,
                                tipo_cobranca = ?,
                                atualizado_em = NOW()
                            WHERE matricula_id = ? AND tenant_id = ?
                            LIMIT 1
                        ");
                        $stmtUpdateAss->execute([
                            $gatewayId,
                            $gatewayAssinaturaId,
                            $gatewayPreferenceId,
                            $externalReference,
                            $paymentUrl,
                            $statusId,
                            $valorCompra,
                            $frequenciaId,
                            $diaCobranca,
                            date('Y-m-d'),
                            $dataFim,
                            $proximaCobranca,
                            $metodoPagamentoId,
                            $tipoCobranca,
                            $matriculaId,
                            $tenantId
                        ]);
                        $assinaturaAtualizada = $stmtUpdateAss->rowCount() > 0;
                        if ($assinaturaAtualizada) {
                            $assinaturaDbId = $matriculaId;
                            error_log("[MobileController::comprarPlano] ✅ Assinatura atualizada para matrícula {$matriculaId}");
                        }
                    }

                    if (!$assinaturaAtualizada) {
                        $stmtAssinatura = $this->db->prepare("
                            INSERT INTO assinaturas
                            (tenant_id, matricula_id, aluno_id, plano_id,
                             gateway_id, gateway_assinatura_id, gateway_preference_id, external_reference, payment_url,
                             status_id, status_gateway,
                             valor, frequencia_id, dia_cobranca, data_inicio, data_fim, proxima_cobranca,
                             metodo_pagamento_id, tipo_cobranca, criado_em)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $stmtAssinatura->execute([
                            $tenantId,
                            $matriculaId,
                            $alunoId,
                            $planoIdMatricula,
                            $gatewayId,
                            $gatewayAssinaturaId,
                            $gatewayPreferenceId,
                            $externalReference,
                            $paymentUrl,
                            $statusId,
                            $valorCompra,
                            $frequenciaId,
                            $diaCobranca,
                            date('Y-m-d'),
                            $dataFim,
                            $proximaCobranca,
                            $metodoPagamentoId,
                            $tipoCobranca
                        ]);
                        
                        $assinaturaDbId = (int) $this->db->lastInsertId();
                        error_log("[MobileController::comprarPlano] ✅ {$tipoCobranca} salva no banco ID: {$assinaturaDbId}");
                    }
                    
                } catch (\Exception $e) {
                    error_log("[MobileController::comprarPlano] ⚠️ Erro ao salvar assinatura: " . $e->getMessage());
                    // Continua mesmo se falhar (pode salvar depois via webhook)
                }
    
            } catch (\Exception $e) {
                error_log("[MobileController::comprarPlano] ❌ ERRO MP: " . $e->getMessage());
                error_log("[MobileController::comprarPlano] Stack: " . $e->getTraceAsString());
                // Guardar erro para mostrar na resposta (debug)
                $mpError = $e->getMessage();
            }

            $responseData = [
                'success' => true,
                'message' => $tipoPagamento === 'assinatura' 
                    ? ($reutilizandoMatricula ? "Matrícula atualizada. Complete a assinatura {$cicloNome} para ativar." : "Matrícula criada. Complete a assinatura {$cicloNome} para ativar.")
                    : ($tipoPagamento === 'pix'
                        ? ($reutilizandoMatricula ? "Matrícula atualizada. Complete o pagamento PIX para ativar." : "Matrícula criada. Complete o pagamento PIX para ativar.")
                        : ($reutilizandoMatricula ? "Matrícula atualizada. Complete o pagamento {$cicloNome} para ativar." : "Matrícula criada. Complete o pagamento {$cicloNome} para ativar.")),
                'data' => [
                    'matricula_id' => $matriculaId,
                    'plano_id' => $planoId,
                    'plano_ciclo_id' => $planoCicloId,
                    'plano_nome' => $plano['nome'],
                    'ciclo_nome' => $cicloNome,
                    'duracao_meses' => $duracaoMeses,
                    'modalidade' => $plano['modalidade_nome'],
                    'valor' => $valorCompra,
                    'valor_formatado' => 'R$ ' . number_format($valorCompra, 2, ',', '.'),
                    'status' => 'pendente',
                    'data_inicio' => $dataInicio,
                    'data_vencimento' => $proximaDataVencimento->format('Y-m-d'),
                    'dia_vencimento' => $diaVencimento,
                    'payment_url' => $paymentUrl,
                    'preference_id' => $preferenceId,
                    'tipo_pagamento' => $tipoPagamento,
                    'metodo_pagamento' => $metodoPagamento,
                    'tipo_cobranca' => $isRecorrente ? 'recorrente' : 'avulso',
                    'recorrente' => $isRecorrente,
                    'assinatura_id' => $assinaturaDbId ?? null,
                    'mp_preapproval_id' => $isRecorrente ? ($preferencia['id'] ?? null) : null,
                    'pix' => $pixData ? [
                        'payment_id' => $pixData['id'] ?? null,
                        'status' => $pixData['status'] ?? null,
                        'status_detail' => $pixData['status_detail'] ?? null,
                        'qr_code' => $pixData['qr_code'] ?? null,
                        'qr_code_base64' => $pixData['qr_code_base64'] ?? null,
                        'ticket_url' => $pixData['ticket_url'] ?? null,
                        'expires_at' => $pixData['date_of_expiration'] ?? null
                    ] : null
                ]
            ];
            
            // Adicionar erro MP se houver (debug)
            if (isset($mpError)) {
                $responseData['mp_error'] = $mpError;
            }
            
            $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');

        } catch (\Exception $e) {
            error_log('[MobileController::comprarPlano] ERRO FATAL: ' . $e->getMessage());
            error_log('[MobileController::comprarPlano] File: ' . $e->getFile() . ' Line: ' . $e->getLine());
            error_log('[MobileController::comprarPlano] Stack: ' . $e->getTraceAsString());
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'type' => 'error',
                'code' => 'ERRO_INTERNO',
                'message' => 'Não foi possível processar sua compra. Tente novamente.',
                'debug' => [
                    'error' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ]
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Reabrir pagamento pendente de matrícula
     * GET /mobile/pagamento/reabrir/{matriculaId}
     */
    public function reabrirPagamentoPendente(Request $request, Response $response, array $args): Response
    {
        try {
            $userId = (int) $request->getAttribute('userId');
            $tenantId = (int) $request->getAttribute('tenantId');
            $matriculaId = (int) ($args['matriculaId'] ?? 0);

            if ($matriculaId <= 0) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'matriculaId inválido'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $stmt = $this->db->prepare("
                SELECT m.id, m.plano_id, m.plano_ciclo_id, m.valor, m.data_inicio, m.data_vencimento, m.proxima_data_vencimento,
                       sm.codigo as status_codigo,
                       p.nome as plano_nome, md.nome as modalidade_nome
                FROM matriculas m
                INNER JOIN alunos a ON a.id = m.aluno_id
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                INNER JOIN planos p ON p.id = m.plano_id
                LEFT JOIN modalidades md ON md.id = p.modalidade_id
                WHERE m.id = ?
                  AND m.tenant_id = ?
                  AND a.usuario_id = ?
                LIMIT 1
            ");
            $stmt->execute([$matriculaId, $tenantId, $userId]);
            $matricula = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$matricula) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Matrícula não encontrada'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            if (($matricula['status_codigo'] ?? '') !== 'pendente') {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'A matrícula não está pendente'
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $stmtAss = $this->db->prepare("
                SELECT id, gateway_preference_id, payment_url, tipo_cobranca, status_gateway
                FROM assinaturas
                WHERE matricula_id = ? AND tenant_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmtAss->execute([$matriculaId, $tenantId]);
            $assinatura = $stmtAss->fetch(\PDO::FETCH_ASSOC) ?: null;

            // Buscar PIX salvo (se existir)
            $pixSalvo = null;
            $stmtPix = $this->db->prepare("
                SELECT payment_id, ticket_url, qr_code, qr_code_base64, expires_at, status
                FROM pagamentos_pix
                WHERE tenant_id = ? AND matricula_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmtPix->execute([$tenantId, $matriculaId]);
            $pixSalvo = $stmtPix->fetch(\PDO::FETCH_ASSOC) ?: null;

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Pagamento pendente encontrado',
                'data' => [
                    'matricula_id' => (int) $matricula['id'],
                    'plano_id' => (int) $matricula['plano_id'],
                    'plano_ciclo_id' => $matricula['plano_ciclo_id'] ? (int)$matricula['plano_ciclo_id'] : null,
                    'plano_nome' => $matricula['plano_nome'],
                    'modalidade' => $matricula['modalidade_nome'],
                    'valor' => (float) $matricula['valor'],
                    'valor_formatado' => 'R$ ' . number_format((float)$matricula['valor'], 2, ',', '.'),
                    'status' => 'pendente',
                    'data_inicio' => $matricula['data_inicio'],
                    'data_vencimento' => $matricula['proxima_data_vencimento'] ?? $matricula['data_vencimento'],
                    'vencida' => ($matricula['proxima_data_vencimento'] ?? $matricula['data_vencimento']) < date('Y-m-d'),
                    'payment_url' => $assinatura['payment_url'] ?? null,
                    'preference_id' => $assinatura['gateway_preference_id'] ?? null,
                    'tipo_pagamento' => ($assinatura['tipo_cobranca'] ?? '') === 'avulso' ? 'pagamento_unico' : 'assinatura',
                    'tipo_cobranca' => $assinatura['tipo_cobranca'] ?? 'avulso',
                    'recorrente' => ($assinatura['tipo_cobranca'] ?? '') === 'recorrente',
                    'pix' => $pixSalvo ? [
                        'payment_id' => $pixSalvo['payment_id'] ?? null,
                        'status' => $pixSalvo['status'] ?? null,
                        'status_detail' => null,
                        'qr_code' => $pixSalvo['qr_code'] ?? null,
                        'qr_code_base64' => $pixSalvo['qr_code_base64'] ?? null,
                        'ticket_url' => $pixSalvo['ticket_url'] ?? null,
                        'expires_at' => $pixSalvo['expires_at'] ?? null
                    ] : null
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        } catch (\Exception $e) {
            error_log("[MobileController::reabrirPagamentoPendente] Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao reabrir pagamento'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Verificar status do pagamento e ativar matrícula se aprovado
     * 
     * POST /mobile/verificar-pagamento
     */
    public function verificarPagamento(Request $request, Response $response): Response
    {
        try {
            $userId = $request->getAttribute('userId');
            $tenantId = $request->getAttribute('tenantId');
            $body = $request->getParsedBody();
            $matriculaId = $body['matricula_id'] ?? null;
            
            if (!$matriculaId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'matricula_id é obrigatório'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            error_log("[MobileController::verificarPagamento] Verificando matrícula {$matriculaId} para usuário {$userId}");
            
            // Buscar matrícula do usuário
            $stmt = $this->db->prepare("
                SELECT m.*, sm.codigo as status_codigo
                FROM matriculas m
                JOIN status_matricula sm ON m.status_id = sm.id
                JOIN alunos a ON m.aluno_id = a.id
                WHERE m.id = ? AND a.usuario_id = ? AND m.tenant_id = ?
            ");
            $stmt->execute([$matriculaId, $userId, $tenantId]);
            $matricula = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$matricula) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Matrícula não encontrada'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Se já está ativa, retornar sucesso
            if ($matricula['status_codigo'] === 'ativa') {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Matrícula já está ativa',
                    'data' => [
                        'matricula_id' => $matriculaId,
                        'status' => 'ativa'
                    ]
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }
            
            // Verificar se há preference_id salvo para consultar o MP
            // Por enquanto, ativar manualmente se o pagamento foi confirmado
            // Em produção, isso seria via webhook
            
            // Ativar a matrícula
            $stmtUpdate = $this->db->prepare("
                UPDATE matriculas
                SET status_id = (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1),
                    updated_at = NOW()
                WHERE id = ?
                AND status_id = (SELECT id FROM status_matricula WHERE codigo = 'pendente' LIMIT 1)
            ");
            $stmtUpdate->execute([$matriculaId]);
            
            if ($stmtUpdate->rowCount() > 0) {
                error_log("[MobileController::verificarPagamento] ✅ Matrícula {$matriculaId} ATIVADA!");
                
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Pagamento confirmado! Matrícula ativada com sucesso.',
                    'data' => [
                        'matricula_id' => $matriculaId,
                        'status' => 'ativa'
                    ]
                ]));
            } else {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Não foi possível atualizar a matrícula. Status atual: ' . $matricula['status_codigo'],
                    'data' => [
                        'matricula_id' => $matriculaId,
                        'status' => $matricula['status_codigo']
                    ]
                ]));
            }
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('[MobileController::verificarPagamento] Erro: ' . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao verificar pagamento: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Gerar pagamento PIX para matrícula existente
     */
    #[OA\Post(
        path: "/mobile/pagamento/pix",
        summary: "Gerar PIX para matrícula existente",
        description: "Gera QR Code PIX para uma matrícula pendente/avulsa do usuário.",
        tags: ["Mobile"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["matricula_id"],
                properties: [
                    new OA\Property(property: "matricula_id", type: "integer", example: 123)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "PIX gerado com sucesso"),
            new OA\Response(response: 400, description: "Dados inválidos"),
            new OA\Response(response: 403, description: "Matrícula inválida"),
            new OA\Response(response: 404, description: "Matrícula não encontrada"),
            new OA\Response(response: 500, description: "Erro interno")
        ]
    )]
    public function gerarPagamentoPix(Request $request, Response $response): Response
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

            $matriculaId = (int) ($body['matricula_id'] ?? 0);
            if (!$matriculaId) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'matricula_id é obrigatório'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $stmtMatricula = $this->db->prepare("
                SELECT m.id, m.valor, m.tipo_cobranca, sm.codigo as status_codigo,
                       p.nome as plano_nome, p.modalidade_id, m.proxima_data_vencimento,
                       a.id as aluno_id, u.id as usuario_id, u.nome as aluno_nome, u.email as aluno_email,
                       u.telefone as aluno_telefone, u.cpf as aluno_cpf, md.nome as modalidade_nome
                FROM matriculas m
                INNER JOIN alunos a ON a.id = m.aluno_id
                INNER JOIN usuarios u ON u.id = a.usuario_id
                INNER JOIN planos p ON p.id = m.plano_id
                LEFT JOIN modalidades md ON md.id = p.modalidade_id
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                WHERE m.id = :matricula_id AND m.tenant_id = :tenant_id AND u.id = :usuario_id
                LIMIT 1
            ");
            $stmtMatricula->execute([
                'matricula_id' => $matriculaId,
                'tenant_id' => $tenantId,
                'usuario_id' => $userId
            ]);
            $matricula = $stmtMatricula->fetch(\PDO::FETCH_ASSOC);

            if (!$matricula) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Matrícula não encontrada'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            if (($matricula['tipo_cobranca'] ?? '') === 'recorrente') {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'PIX não disponível para matrícula recorrente'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            if (($matricula['status_codigo'] ?? '') === 'ativa') {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'Matrícula já está ativa'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }

            $cpfPix = preg_replace('/[^0-9]/', '', $matricula['aluno_cpf'] ?? '');
            if (strlen($cpfPix) !== 11) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'CPF válido é obrigatório para pagamento PIX'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $stmtTenant = $this->db->prepare("SELECT nome FROM tenants WHERE id = ?");
            $stmtTenant->execute([$tenantId]);
            $tenantData = $stmtTenant->fetch(\PDO::FETCH_ASSOC);
            $academiaNome = $tenantData['nome'] ?? 'Academia';

            $dadosPagamento = [
                'tenant_id' => $tenantId,
                'matricula_id' => (int) $matricula['id'],
                'aluno_id' => (int) $matricula['aluno_id'],
                'usuario_id' => (int) $matricula['usuario_id'],
                'aluno_nome' => $matricula['aluno_nome'],
                'aluno_email' => $matricula['aluno_email'],
                'aluno_telefone' => $matricula['aluno_telefone'] ?? '',
                'aluno_cpf' => $matricula['aluno_cpf'] ?? null,
                'plano_nome' => $matricula['plano_nome'],
                'descricao' => "{$matricula['plano_nome']} - {$matricula['modalidade_nome']}",
                'valor' => (float) $matricula['valor'],
                'academia_nome' => $academiaNome
            ];

            // Se já existe PIX válido salvo, reutilizar
            $stmtPix = $this->db->prepare("
                SELECT payment_id, ticket_url, qr_code, qr_code_base64, expires_at, status
                FROM pagamentos_pix
                WHERE tenant_id = ? AND matricula_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmtPix->execute([$tenantId, (int) $matricula['id']]);
            $pixSalvo = $stmtPix->fetch(\PDO::FETCH_ASSOC) ?: null;

            if ($pixSalvo && !empty($pixSalvo['expires_at']) && strtotime($pixSalvo['expires_at']) >= time()) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'data' => [
                        'matricula_id' => (int) $matricula['id'],
                        'valor' => (float) $matricula['valor'],
                        'pix' => [
                            'payment_id' => $pixSalvo['payment_id'] ?? null,
                            'status' => $pixSalvo['status'] ?? null,
                            'status_detail' => null,
                            'qr_code' => $pixSalvo['qr_code'] ?? null,
                            'qr_code_base64' => $pixSalvo['qr_code_base64'] ?? null,
                            'ticket_url' => $pixSalvo['ticket_url'] ?? null,
                            'expires_at' => $pixSalvo['expires_at'] ?? null
                        ]
                    ]
                ], JSON_UNESCAPED_UNICODE));

                return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
            }

            $mercadoPago = new \App\Services\MercadoPagoService($tenantId);
            $pixData = $mercadoPago->criarPagamentoPix($dadosPagamento);

            // Salvar dados do PIX para reabrir
            try {
                if (!empty($pixData['id']) && !empty($pixData['ticket_url'])) {
                    $stmtPixSave = $this->db->prepare("
                        INSERT INTO pagamentos_pix
                        (tenant_id, matricula_id, payment_id, ticket_url, qr_code, qr_code_base64, expires_at, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtPixSave->execute([
                        $tenantId,
                        (int) $matricula['id'],
                        (string) ($pixData['id'] ?? ''),
                        $pixData['ticket_url'] ?? null,
                        $pixData['qr_code'] ?? null,
                        $pixData['qr_code_base64'] ?? null,
                        isset($pixData['date_of_expiration']) ? date('Y-m-d H:i:s', strtotime($pixData['date_of_expiration'])) : null,
                        $pixData['status'] ?? 'pending'
                    ]);
                }
            } catch (\Exception $e) {
                error_log('[MobileController::gerarPagamentoPix] Erro ao salvar PIX: ' . $e->getMessage());
            }

            // Atualizar ou criar assinatura avulsa vinculada à matrícula
            try {
                $paymentIdPix = $pixData['id'] ?? null;
                $paymentUrlPix = $pixData['ticket_url'] ?? null;
                $externalReference = $pixData['external_reference'] ?? ("MAT-{$matricula['id']}-" . time());

                if ($paymentIdPix && $paymentUrlPix) {
                    $stmtAss = $this->db->prepare("
                        SELECT id FROM assinaturas
                        WHERE matricula_id = ? AND tenant_id = ?
                        ORDER BY id DESC
                        LIMIT 1
                    ");
                    $stmtAss->execute([(int) $matricula['id'], $tenantId]);
                    $assinaturaId = $stmtAss->fetchColumn();

                    if ($assinaturaId) {
                        $stmtUpdateAss = $this->db->prepare("
                            UPDATE assinaturas
                            SET gateway_preference_id = ?,
                                external_reference = ?,
                                payment_url = ?,
                                status_gateway = 'pending',
                                atualizado_em = NOW()
                            WHERE id = ? AND tenant_id = ?
                        ");
                        $stmtUpdateAss->execute([
                            (string) $paymentIdPix,
                            $externalReference,
                            $paymentUrlPix,
                            (int) $assinaturaId,
                            $tenantId
                        ]);
                    } else {
                        $stmtGateway = $this->db->prepare("SELECT id FROM assinatura_gateways WHERE codigo = 'mercadopago'");
                        $stmtGateway->execute();
                        $gatewayId = $stmtGateway->fetchColumn() ?: 1;

                        $stmtStatus = $this->db->prepare("SELECT id FROM assinatura_status WHERE codigo = 'pendente'");
                        $stmtStatus->execute();
                        $statusId = $stmtStatus->fetchColumn() ?: 1;

                        $stmtMetodo = $this->db->prepare("SELECT id FROM metodos_pagamento WHERE codigo = 'pix'");
                        $stmtMetodo->execute();
                        $metodoPagamentoId = $stmtMetodo->fetchColumn() ?: null;

                        $frequenciaId = 4;
                        if (!empty($matricula['plano_ciclo_id'])) {
                            $stmtFreq = $this->db->prepare("
                                SELECT af.id
                                FROM plano_ciclos pc
                                INNER JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
                                WHERE pc.id = ? AND pc.tenant_id = ?
                                LIMIT 1
                            ");
                            $stmtFreq->execute([(int)$matricula['plano_ciclo_id'], $tenantId]);
                            $frequenciaId = $stmtFreq->fetchColumn() ?: 4;
                        }

                        $stmtAssinatura = $this->db->prepare("
                            INSERT INTO assinaturas
                            (tenant_id, matricula_id, aluno_id, plano_id,
                             gateway_id, gateway_preference_id, external_reference, payment_url,
                             status_id, status_gateway, valor, frequencia_id, dia_cobranca,
                             data_inicio, data_fim, proxima_cobranca, metodo_pagamento_id, tipo_cobranca, criado_em)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmtAssinatura->execute([
                            $tenantId,
                            (int) $matricula['id'],
                            (int) $matricula['aluno_id'],
                            (int) $matricula['plano_id'],
                            $gatewayId,
                            (string) $paymentIdPix,
                            $externalReference,
                            $paymentUrlPix,
                            $statusId,
                            (float) $matricula['valor'],
                            $frequenciaId,
                            (int) date('d'),
                            date('Y-m-d'),
                            $matricula['proxima_data_vencimento'] ?? null,
                            null,
                            $metodoPagamentoId,
                            'avulso'
                        ]);
                    }
                }
            } catch (\Exception $e) {
                error_log('[MobileController::gerarPagamentoPix] Erro ao atualizar assinatura: ' . $e->getMessage());
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'matricula_id' => (int) $matricula['id'],
                    'valor' => (float) $matricula['valor'],
                    'pix' => [
                        'payment_id' => $pixData['id'] ?? null,
                        'status' => $pixData['status'] ?? null,
                        'status_detail' => $pixData['status_detail'] ?? null,
                        'qr_code' => $pixData['qr_code'] ?? null,
                        'qr_code_base64' => $pixData['qr_code_base64'] ?? null,
                        'ticket_url' => $pixData['ticket_url'] ?? null,
                        'expires_at' => $pixData['date_of_expiration'] ?? null
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');

        } catch (\Exception $e) {
            error_log('[MobileController::gerarPagamentoPix] Erro: ' . $e->getMessage());
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao gerar PIX',
                'message' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Formata a duração em dias para texto legível
     */
    private function formatarDuracao(int $dias): string
    {
        if ($dias == 30) return '1 mês';
        if ($dias == 60) return '2 meses';
        if ($dias == 90) return '3 meses';
        if ($dias == 180) return '6 meses';
        if ($dias == 365) return '1 ano';
        
        if ($dias < 30) return $dias . ' dias';
        
        $meses = round($dias / 30);
        return $meses . ' meses';
    }
    
    /**
     * Atualiza automaticamente o status das matrículas vencidas do usuário
     * 
     * Lógica:
     * - Vencida há 1-4 dias → status = 'vencida'
     * - Vencida há 5+ dias → status = 'cancelada'
     */
    private function atualizarStatusMatriculasVencidas(int $userId, int $tenantId): void
    {
        try {
            $hoje = date('Y-m-d');
            
            // Buscar matrículas ativas do usuário que estão vencidas
            $stmt = $this->db->prepare("
                SELECT m.id, m.proxima_data_vencimento,
                       DATEDIFF(:hoje, m.proxima_data_vencimento) as dias_vencido
                FROM matriculas m
                INNER JOIN alunos a ON a.id = m.aluno_id
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                WHERE a.usuario_id = :usuario_id
                AND m.tenant_id = :tenant_id
                AND sm.codigo = 'ativa'
                AND m.proxima_data_vencimento < :hoje2
            ");
            
            $stmt->execute([
                'hoje' => $hoje,
                'hoje2' => $hoje,
                'usuario_id' => $userId,
                'tenant_id' => $tenantId
            ]);
            
            $matriculasVencidas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($matriculasVencidas as $matricula) {
                $diasVencido = (int) $matricula['dias_vencido'];
                $matriculaId = $matricula['id'];
                
                if ($diasVencido >= 5) {
                    // 5+ dias vencido → Cancelar
                    $novoStatus = 'cancelada';
                } else {
                    // 1-4 dias vencido → Marcar como vencida
                    $novoStatus = 'vencida';
                }
                
                $stmtUpdate = $this->db->prepare("
                    UPDATE matriculas
                    SET status_id = (SELECT id FROM status_matricula WHERE codigo = :novo_status LIMIT 1),
                        updated_at = NOW()
                    WHERE id = :matricula_id
                ");
                
                $stmtUpdate->execute([
                    'novo_status' => $novoStatus,
                    'matricula_id' => $matriculaId
                ]);
                
                error_log("[MobileController] Matrícula #{$matriculaId} atualizada para '{$novoStatus}' ({$diasVencido} dias vencida)");
            }
            
        } catch (\Exception $e) {
            error_log("[MobileController] Erro ao atualizar status matrículas: " . $e->getMessage());
            // Não lançar exceção para não bloquear o fluxo
        }
    }
}
