<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Checkin;
use App\Models\Turma;
use App\Models\Usuario;

class CheckinController
{
    private Checkin $checkinModel;
    private Turma $turmaModel;
    private Usuario $usuarioModel;

    public function __construct()
    {
        $db = require __DIR__ . '/../../config/database.php';
        $this->checkinModel = new Checkin($db);
        $this->turmaModel = new Turma($db);
        $this->usuarioModel = new Usuario($db);
    }

    public function store(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $data = $request->getParsedBody();

        // Verificar se usuário é admin/professor sem papel de aluno
        $usuario = $this->usuarioModel->findById($userId);
        $tenantId = (int) ($request->getAttribute('tenantId') ?? 1);
        $db = require __DIR__ . '/../../config/database.php';
        $temPapelAluno = false;
        if ($tenantId > 0) {
            $stmtPapel = $db->prepare("
                SELECT 1 FROM tenant_usuario_papel
                WHERE usuario_id = :usuario_id AND tenant_id = :tenant_id
                  AND papel_id = 1 AND ativo = 1
                LIMIT 1
            ");
            $stmtPapel->execute([
                'usuario_id' => $userId,
                'tenant_id' => $tenantId,
            ]);
            $temPapelAluno = (bool) $stmtPapel->fetchColumn();
        }

        if (
            $usuario
            && isset($usuario['papel_id'])
            && ($usuario['papel_id'] == 2 || $usuario['papel_id'] == 3)
            && !$temPapelAluno
        ) {
            $response->getBody()->write(json_encode([
                'error' => 'Administradores não podem fazer check-in próprio. Use o painel admin para registrar check-ins de alunos.'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Validação - aceita turma_id
        if (empty($data['turma_id'])) {
            $response->getBody()->write(json_encode([
                'error' => 'turma_id é obrigatório'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $turmaId = (int) $data['turma_id'];

        // ✅ VALIDAR matrícula mais recente (status, bloqueio e vencimento)
        $matricula = $this->buscarMatriculaMaisRecente($db, $userId, $tenantId);
        $erroMatricula = $this->validarMatriculaParaCheckin($matricula, true);
        if ($erroMatricula !== null) {
            $response->getBody()->write(json_encode($erroMatricula));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Verificar se usuário já tem check-in nesta turma
        if ($this->checkinModel->usuarioTemCheckin($userId, $turmaId)) {
            $response->getBody()->write(json_encode([
                'error' => 'Você já tem check-in nesta turma'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Buscar dados da turma
        $turma = $this->turmaModel->findById($turmaId);
        
        if (!$turma) {
            $response->getBody()->write(json_encode([
                'error' => 'Turma não encontrada'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Verificar limite de check-ins conforme plano (se houver)
        $modalidadeTurma = $turma['modalidade_id'] ?? null;
        $planoInfo = $this->checkinModel->obterLimiteCheckinsPlano($userId, $tenantId, $modalidadeTurma);
        if ($planoInfo['tem_plano'] && $planoInfo['limite'] > 0) {
            if ($planoInfo['permite_reposicao']) {
                // Limite mensal por CICLO DE COBRANÇA (mês a partir do vencimento),
                // com fallback fail-safe para mês de calendário.
                $detalhesLimite = $this->checkinModel->avaliarLimiteMensalReposicao($userId, $tenantId, $modalidadeTurma, $planoInfo);
                if ($detalhesLimite !== null) {
                    if (!empty($matricula['id'])) {
                        $this->checkinModel->marcarPendenteSeLimiteCicloEsgotado((int) $matricula['id']);
                    }
                    $usados = (int) ($detalhesLimite['usados'] ?? $detalhesLimite['checkins_mes'] ?? 0);
                    $direito = (int) ($detalhesLimite['direito'] ?? $detalhesLimite['limite_mensal'] ?? 0);
                    $mensagem = \App\Models\Checkin::montarMensagemLimiteCicloParaAluno($detalhesLimite);
                    $response->getBody()->write(json_encode([
                        'error' => $mensagem,
                        'codigo' => 'LIMITE_CHECKINS_CICLO',
                        'detalhes' => $detalhesLimite
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
                }
            } else {
                $checkinsNaSemana = $this->checkinModel->contarCheckinsNaSemana($userId, $modalidadeTurma);
                if ($checkinsNaSemana >= $planoInfo['limite']) {
                    $response->getBody()->write(json_encode([
                        'error' => 'Você atingiu o limite de check-ins desta semana',
                        'detalhes' => [
                            'plano' => $planoInfo['plano_nome'],
                            'limite_semana' => $planoInfo['limite'],
                            'checkins_semana' => $checkinsNaSemana
                        ]
                    ]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
            }
        }

        // Criar check-in com timestamp do momento exato
        $checkinId = $this->checkinModel->create($userId, $turmaId);

        if (!$checkinId) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao realizar check-in'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $checkin = $this->checkinModel->findById($checkinId);

        // Empatou o limite do ciclo → pendente (próximo check-in recebe o aviso).
        if (!empty($matricula['id'])) {
            $this->checkinModel->marcarPendenteSeLimiteCicloEsgotado((int) $matricula['id']);
        }

        $response->getBody()->write(json_encode([
            'message' => 'Check-in realizado com sucesso',
            'checkin' => $checkin
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function myCheckins(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');

        $checkins = $this->checkinModel->getByUsuarioId($userId);

        $response->getBody()->write(json_encode([
            'checkins' => $checkins
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId');
        $checkinId = (int) $args['id'];

        // Verificar se check-in existe
        $checkin = $this->checkinModel->findById($checkinId);

        if (!$checkin) {
            $response->getBody()->write(json_encode([
                'error' => 'Check-in não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Verificar se check-in pertence ao usuário
        if ($checkin['usuario_id'] != $userId) {
            $response->getBody()->write(json_encode([
                'error' => 'Você não tem permissão para cancelar este check-in'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Deletar check-in
        $deleted = $this->checkinModel->delete($checkinId);

        if (!$deleted) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao cancelar check-in'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'message' => 'Check-in cancelado com sucesso'
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * DELETE /checkin/{id}/desfazer
     * Desfazer check-in com validações de horário
     * Não pode desfazer após o horário + tolerância
     */
    public function desfazer(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId');
        $checkinId = (int) $args['id'];

        // Verificar se check-in existe
        $checkin = $this->checkinModel->findById($checkinId);

        if (!$checkin) {
            $response->getBody()->write(json_encode([
                'error' => 'Check-in não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Verificar se check-in pertence ao usuário
        if ($checkin['usuario_id'] != $userId) {
            $response->getBody()->write(json_encode([
                'error' => 'Você não tem permissão para desfazer este check-in'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // Validar se ainda é possível desfazer
        // Se a turma foi deletada ou não tem dados, permitir desfazer
        if ($checkin['turma_id'] && $checkin['horario_inicio'] && $checkin['data']) {
            // Verificar se a aula já começou + tolerância
            $agora = new \DateTime();
            $dataHorarioInicio = new \DateTime($checkin['data'] . ' ' . $checkin['horario_inicio']);
            
            // Tolerar até X minutos após o início da aula
            $tolerancia = (int) ($checkin['tolerancia_minutos'] ?? 10);
            $dataLimiteDesfazer = clone $dataHorarioInicio;
            $dataLimiteDesfazer->modify("+{$tolerancia} minutes");

            // Se já passou do horário + tolerância, não pode desfazer
            if ($agora > $dataLimiteDesfazer) {
                $response->getBody()->write(json_encode([
                    'error' => 'Não é possível desfazer o check-in. O prazo expirou (a aula já começou)',
                    'turma' => [
                        'data' => $checkin['data'],
                        'inicio' => $checkin['horario_inicio'],
                        'tolerancia_minutos' => $tolerancia,
                        'limite_para_desfazer' => $dataLimiteDesfazer->format('Y-m-d H:i:s')
                    ]
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Verificar se a aula ainda está acontecendo
            $dataHorarioFim = new \DateTime($checkin['data'] . ' ' . $checkin['horario_fim']);
            
            if ($agora > $dataHorarioFim) {
                $response->getBody()->write(json_encode([
                    'error' => 'Não é possível desfazer o check-in. A aula já terminou',
                    'turma' => [
                        'data' => $checkin['data'],
                        'inicio' => $checkin['horario_inicio'],
                        'fim' => $checkin['horario_fim']
                    ]
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        }

        // Desfazer check-in (deletar)
        $deleted = $this->checkinModel->delete($checkinId);

        if (!$deleted) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao desfazer check-in'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'message' => 'Check-in desfeito com sucesso',
            'checkin_id' => $checkinId,
            'horario' => [
                'data' => $checkin['data'] ?? 'Data não disponível',
                'inicio' => $checkin['horario_inicio'] ?? 'Horário não disponível',
                'fim' => $checkin['horario_fim'] ?? 'Horário não disponível'
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /admin/checkins/registrar
     * Admin registra check-in para um aluno em qualquer turma
     */
    public function registrarPorAdmin(Request $request, Response $response): Response
    {
        $adminId = $request->getAttribute('userId');
        $data = $request->getParsedBody();

        // Validações
        if (empty($data['usuario_id']) || empty($data['turma_id'])) {
            $response->getBody()->write(json_encode([
                'error' => 'usuario_id e turma_id são obrigatórios'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        $usuarioId = (int) $data['usuario_id'];
        $turmaId = (int) $data['turma_id'];

        // Verificar se aluno existe e é realmente aluno (papel_id = 1)
        $aluno = $this->usuarioModel->findById($usuarioId);
        if (!$aluno) {
            $response->getBody()->write(json_encode([
                'error' => 'Aluno não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // ✅ VALIDAR matrícula mais recente (status, bloqueio e vencimento)
        $db = require __DIR__ . '/../../config/database.php';
        $tenantId = $request->getAttribute('tenantId', 1);
        $matricula = $this->buscarMatriculaMaisRecente($db, $usuarioId, $tenantId);
        $erroMatricula = $this->validarMatriculaParaCheckin($matricula, false);
        if ($erroMatricula !== null) {
            $response->getBody()->write(json_encode($erroMatricula));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if (($aluno['papel_id'] ?? null) != 1) {
            $response->getBody()->write(json_encode([
                'error' => 'Usuário não é um aluno'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Verificar se aluno já tem check-in nesta turma
        if ($this->checkinModel->usuarioTemCheckin($usuarioId, $turmaId)) {
            $response->getBody()->write(json_encode([
                'error' => 'Aluno já tem check-in nesta turma'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Admin pode registrar em qualquer turma (sem validação de tolerância)
        // Mas ainda validamos se a turma existe e está ativa
        $turma = $this->turmaModel->findById($turmaId);
        if (!$turma || !$turma['ativo']) {
            $response->getBody()->write(json_encode([
                'error' => 'Turma inválida ou inativa'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Verificar limite de check-ins conforme plano (se houver)
        $modalidadeTurma = $turma['modalidade_id'] ?? null;
        $planoInfo = $this->checkinModel->obterLimiteCheckinsPlano($usuarioId, $tenantId, $modalidadeTurma);
        if ($planoInfo['tem_plano'] && $planoInfo['limite'] > 0) {
            if ($planoInfo['permite_reposicao']) {
                // Limite mensal por CICLO DE COBRANÇA (mês a partir do vencimento),
                // com fallback fail-safe para mês de calendário.
                $detalhesLimite = $this->checkinModel->avaliarLimiteMensalReposicao($usuarioId, $tenantId, $modalidadeTurma, $planoInfo);
                if ($detalhesLimite !== null) {
                    $detalhesLimite = \App\Models\Checkin::formatarDetalhesLimiteMensal($detalhesLimite, false);
                    $response->getBody()->write(json_encode([
                        'error' => $detalhesLimite['mensagem'] ?? 'Aluno atingiu o limite de check-ins do ciclo do plano',
                        'detalhes' => $detalhesLimite
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
                }
            } else {
                $checkinsNaSemana = $this->checkinModel->contarCheckinsNaSemana($usuarioId, $modalidadeTurma);
                if ($checkinsNaSemana >= $planoInfo['limite']) {
                    $response->getBody()->write(json_encode([
                        'error' => 'Aluno atingiu o limite de check-ins desta semana',
                        'detalhes' => [
                            'plano' => $planoInfo['plano_nome'],
                            'limite_semana' => $planoInfo['limite'],
                            'checkins_semana' => $checkinsNaSemana
                        ]
                    ]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
            }
        }

        // Criar check-in registrado pelo admin
        $checkinId = $this->checkinModel->createByAdmin($usuarioId, $turmaId, $adminId);

        if (!$checkinId) {
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao registrar check-in'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $checkin = $this->checkinModel->findById($checkinId);

        if (!empty($matricula['id'])) {
            $this->checkinModel->marcarPendenteSeLimiteCicloEsgotado((int) $matricula['id']);
        }

        $response->getBody()->write(json_encode([
            'message' => 'Check-in registrado com sucesso',
            'checkin' => $checkin
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    /**
     * Matrícula mais recente do aluno no tenant (sem filtrar por status).
     */
    private function buscarMatriculaMaisRecente(\PDO $db, int $usuarioId, int $tenantId): ?array
    {
        $stmt = $db->prepare("
            SELECT m.id, m.tenant_id, m.proxima_data_vencimento, m.periodo_teste,
                   sm.codigo as status_codigo, sm.nome as status_nome,
                   sm.permite_checkin, sm.ativo as status_ativo
            FROM matriculas m
            INNER JOIN alunos a ON a.id = m.aluno_id
            INNER JOIN status_matricula sm ON sm.id = m.status_id
            WHERE a.usuario_id = :usuario_id
            AND m.tenant_id = :tenant_id
            ORDER BY m.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId,
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Valida a matrícula mais recente para check-in.
     * Retorna payload de erro ou null se permitido.
     */
    private function validarMatriculaParaCheckin(?array $matricula, bool $mensagemParaAluno): ?array
    {
        if (!$matricula) {
            return [
                'error' => $mensagemParaAluno
                    ? 'Você não possui matrícula ativa'
                    : 'Aluno não possui matrícula ativa',
                'codigo' => 'SEM_MATRICULA',
            ];
        }

        $statusCodigo = $matricula['status_codigo'] ?? '';

        if ($statusCodigo === 'bloqueado') {
            return [
                'error' => $mensagemParaAluno
                    ? 'Sua matrícula está bloqueada. Entre em contato com a academia.'
                    : 'Matrícula do aluno está bloqueada',
                'codigo' => 'MATRICULA_BLOQUEADA',
            ];
        }

        if ((int) ($matricula['permite_checkin'] ?? 0) !== 1 || (int) ($matricula['status_ativo'] ?? 0) !== 1) {
            $statusNome = $matricula['status_nome'] ?? $statusCodigo;
            $acessoAte = $matricula['proxima_data_vencimento'] ?? $matricula['data_vencimento'] ?? null;
            $hoje = date('Y-m-d');
            $vencTxt = \App\Helpers\DateBr::vencimentoSuffix(
                is_string($acessoAte) ? $acessoAte : null
            );
            $matriculaId = (int) ($matricula['id'] ?? 0);

            // Pendente: check-ins primeiro, depois financeiro.
            if ($statusCodigo === 'pendente' && $matriculaId > 0) {
                $detalhesLimite = $this->checkinModel->avaliarLimiteMensalPorMatricula($matriculaId);
                if ($detalhesLimite !== null) {
                    $mensagem = $mensagemParaAluno
                        ? \App\Models\Checkin::montarMensagemLimiteCicloParaAluno($detalhesLimite)
                        : (\App\Models\Checkin::formatarDetalhesLimiteMensal($detalhesLimite, false)['mensagem']
                            ?? 'Aluno atingiu o limite de check-ins do ciclo.');

                    return [
                        'error' => $mensagem,
                        'codigo' => 'LIMITE_CHECKINS_CICLO',
                        'status' => $statusNome,
                        'status_codigo' => $statusCodigo,
                        'matricula_id' => $matriculaId,
                        'data_vencimento' => $acessoAte,
                        'detalhes' => $detalhesLimite,
                    ];
                }

                $temParcelaVencida = false;
                try {
                    $stmtParc = $this->db->prepare("
                        SELECT 1 FROM pagamentos_plano
                        WHERE matricula_id = ?
                          AND status_pagamento_id IN (1, 3)
                          AND data_pagamento IS NULL
                          AND data_vencimento IS NOT NULL
                          AND data_vencimento <= CURDATE()
                        LIMIT 1
                    ");
                    $stmtParc->execute([$matriculaId]);
                    $temParcelaVencida = (bool) $stmtParc->fetchColumn();
                } catch (\Throwable $e) {
                    // ignore
                }

                if ($temParcelaVencida) {
                    return [
                        'error' => $mensagemParaAluno
                            ? ('Há pagamento em atraso na sua matrícula.' . $vencTxt . ' Regularize para voltar a fazer check-in.')
                            : ('Matrícula com pagamento em atraso.' . $vencTxt),
                        'codigo' => 'MATRICULA_PENDENTE',
                        'status' => $statusNome,
                        'status_codigo' => $statusCodigo,
                        'matricula_id' => $matriculaId,
                        'data_vencimento' => $acessoAte,
                    ];
                }

                if (is_string($acessoAte) && $acessoAte !== '0000-00-00' && $acessoAte >= $hoje) {
                    if ($this->checkinModel->matriculaPendenteAindaTemSaldoCiclo($matriculaId)) {
                        $this->checkinModel->reativarDePendenteParaAtiva($matriculaId);
                        return null;
                    }

                    $tenantIdRestricao = (int) ($matricula['tenant_id'] ?? 0);
                    $temPagamentoPago = $tenantIdRestricao > 0
                        && $this->checkinModel->matriculaTemPagamentoPago($matriculaId, $tenantIdRestricao);
                    if (!\App\Models\Checkin::pendenteDeveInformarLimiteCiclo($tenantIdRestricao, $temPagamentoPago)) {
                        return [
                            'error' => $mensagemParaAluno
                                ? ('Sua matrícula está aguardando pagamento.' . $vencTxt . ' Conclua o pagamento para ativar o check-in.')
                                : ('Matrícula aguardando pagamento.' . $vencTxt),
                            'codigo' => 'MATRICULA_PENDENTE',
                            'status' => $statusNome,
                            'status_codigo' => $statusCodigo,
                            'matricula_id' => $matriculaId,
                            'data_vencimento' => $acessoAte,
                        ];
                    }

                    $resumoCiclo = $this->checkinModel->obterResumoCicloPorMatricula($matriculaId);
                    return [
                        'error' => $mensagemParaAluno
                            ? \App\Models\Checkin::montarMensagemLimiteCicloParaAluno($resumoCiclo ?? [])
                            : 'Aluno atingiu o limite de check-ins do ciclo.',
                        'codigo' => 'LIMITE_CHECKINS_CICLO',
                        'status' => $statusNome,
                        'status_codigo' => $statusCodigo,
                        'matricula_id' => $matriculaId,
                        'data_vencimento' => $acessoAte,
                        'detalhes' => $resumoCiclo,
                    ];
                }

                return [
                    'error' => $mensagemParaAluno
                        ? ('Sua matrícula está aguardando pagamento.' . $vencTxt . ' Conclua o pagamento para ativar o check-in.')
                        : ('Matrícula aguardando pagamento.' . $vencTxt),
                    'codigo' => 'MATRICULA_PENDENTE',
                    'status' => $statusNome,
                    'status_codigo' => $statusCodigo,
                    'matricula_id' => $matriculaId,
                    'data_vencimento' => $acessoAte,
                ];
            }

            return [
                'error' => $mensagemParaAluno
                    ? "Sua matrícula está {$statusNome}.{$vencTxt} Não é possível fazer check-in."
                    : "Matrícula do aluno está {$statusNome}.{$vencTxt}",
                'codigo' => $this->codigoErroPorStatusMatricula($statusCodigo),
                'status' => $statusNome,
                'status_codigo' => $statusCodigo,
                'matricula_id' => $matriculaId,
                'data_vencimento' => $acessoAte,
            ];
        }

        $hoje = date('Y-m-d');
        if (!empty($matricula['proxima_data_vencimento']) && $matricula['proxima_data_vencimento'] < $hoje) {
            $dataVencimento = \App\Helpers\DateBr::format((string) $matricula['proxima_data_vencimento'])
                ?? (string) $matricula['proxima_data_vencimento'];
            return [
                'error' => $mensagemParaAluno
                    ? "Seu acesso expirou em {$dataVencimento}. Por favor, renove sua matrícula."
                    : "Acesso do aluno expirou em {$dataVencimento}",
                'codigo' => 'MATRICULA_VENCIDA',
                'data_vencimento' => $matricula['proxima_data_vencimento'],
            ];
        }

        return null;
    }

    /**
     * Código de erro quando a matrícula existe mas não permite check-in.
     */
    private function codigoErroPorStatusMatricula(string $statusCodigo): string
    {
        return match ($statusCodigo) {
            'cancelada' => 'MATRICULA_CANCELADA',
            'finalizada' => 'MATRICULA_FINALIZADA',
            'pendente' => 'MATRICULA_PENDENTE',
            'vencida' => 'MATRICULA_VENCIDA',
            default => 'MATRICULA_INATIVA',
        };
    }
}
