<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Usuario;
use App\Models\Plano;

class MatriculaController
{
    /**
     * Criar nova matrícula
     */
    public function criar(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId', 1);
        $adminId = $request->getAttribute('userId', null);
        $data = $request->getParsedBody();
        $db = require __DIR__ . '/../../config/database.php';

        // Validações - aceita tanto aluno_id quanto usuario_id (retrocompatibilidade)
        $errors = [];
        
        $alunoIdInput = $data['aluno_id'] ?? null;
        $usuarioIdInput = $data['usuario_id'] ?? null;
        
        if (empty($alunoIdInput) && empty($usuarioIdInput)) {
            $errors[] = 'Aluno é obrigatório (envie aluno_id ou usuario_id)';
        }
        
        if (empty($data['plano_id'])) {
            $errors[] = 'Plano é obrigatório';
        }

        // Validar dia_vencimento (obrigatório)
        if (empty($data['dia_vencimento'])) {
            $errors[] = 'Dia de vencimento é obrigatório';
        } else {
            $diaVencimento = (int) $data['dia_vencimento'];
            if ($diaVencimento < 1 || $diaVencimento > 31) {
                $errors[] = 'Dia de vencimento deve estar entre 1 e 31';
            }
        }
        
        if (!empty($errors)) {
            $response->getBody()->write(json_encode(['errors' => $errors]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Se recebeu aluno_id, buscar usuario_id
        if ($alunoIdInput) {
            $stmtBuscaUsuario = $db->prepare("SELECT usuario_id FROM alunos WHERE id = ?");
            $stmtBuscaUsuario->execute([$alunoIdInput]);
            $alunoRow = $stmtBuscaUsuario->fetch();
            if (!$alunoRow) {
                $response->getBody()->write(json_encode(['error' => 'Aluno não encontrado']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            $usuarioId = $alunoRow['usuario_id'];
            $alunoId = $alunoIdInput;
        } else {
            $usuarioId = $usuarioIdInput;
            // Buscar aluno_id a partir do usuario_id
            $stmtAluno = $db->prepare("SELECT id FROM alunos WHERE usuario_id = ?");
            $stmtAluno->execute([$usuarioId]);
            $alunoRow = $stmtAluno->fetch();
            if (!$alunoRow) {
                $response->getBody()->write(json_encode(['error' => 'Aluno não encontrado na tabela de alunos']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            $alunoId = $alunoRow['id'];
        }

        $planoId = $data['plano_id'];
        
        // Verificar se usuário existe e é aluno
        $stmtUsuarioExiste = $db->prepare("
            SELECT u.* FROM usuarios u
            INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id AND tup.ativo = 1
            WHERE u.id = ? AND tup.papel_id = 1
        ");
        $stmtUsuarioExiste->execute([$usuarioId]);
        $usuarioExiste = $stmtUsuarioExiste->fetch();
        
        if (!$usuarioExiste) {
            $response->getBody()->write(json_encode(['error' => 'Usuário não encontrado ou não é aluno']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        // Verificar se existe vínculo com o tenant, se não existir criar
        $stmtVinculo = $db->prepare("
            SELECT * FROM tenant_usuario_papel 
            WHERE usuario_id = ? AND tenant_id = ? AND papel_id = 1
        ");
        $stmtVinculo->execute([$usuarioId, $tenantId]);
        $vinculo = $stmtVinculo->fetch();
        
        if (!$vinculo) {
            // Criar vínculo automaticamente como aluno (papel_id = 1)
            $stmtCriarVinculo = $db->prepare("
                INSERT INTO tenant_usuario_papel (usuario_id, tenant_id, papel_id, ativo, created_at)
                VALUES (?, ?, 1, 1, NOW())
            ");
            $stmtCriarVinculo->execute([$usuarioId, $tenantId]);
            error_log("Vínculo tenant_usuario_papel criado automaticamente: usuario_id={$usuarioId}, tenant_id={$tenantId}, papel_id=1");
        } elseif ($vinculo['ativo'] != 1) {
            // Reativar vínculo se estava inativo
            $stmtReativar = $db->prepare("
                UPDATE tenant_usuario_papel 
                SET ativo = 1, updated_at = NOW() 
                WHERE usuario_id = ? AND tenant_id = ? AND papel_id = 1
            ");
            $stmtReativar->execute([$usuarioId, $tenantId]);
            error_log("Vínculo tenant_usuario_papel reativado: usuario_id={$usuarioId}, tenant_id={$tenantId}");
        }
        
        // Buscar aluno (agora garantimos que o vínculo existe)
        $stmtUsuario = $db->prepare("
            SELECT u.* 
            FROM usuarios u
            INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id AND tup.ativo = 1
            WHERE u.id = ? AND tup.tenant_id = ? AND tup.papel_id = 1
        ");
        $stmtUsuario->execute([$usuarioId, $tenantId]);
        $usuario = $stmtUsuario->fetch();
        
        if (!$usuario) {
            $response->getBody()->write(json_encode(['error' => 'Erro ao vincular aluno ao tenant']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        // Buscar plano
        $stmtPlano = $db->prepare("SELECT * FROM planos WHERE id = ? AND tenant_id = ?");
        $stmtPlano->execute([$planoId, $tenantId]);
        $plano = $stmtPlano->fetch();
        
        if (!$plano) {
            $response->getBody()->write(json_encode(['error' => 'Plano não encontrado']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Verificar se o plano tem valor 0 (teste) e configurar automaticamente
        $periodoTeste = 0;
        $dataInicioCobranca = null;
        $valorMatricula = $data['valor'] ?? $plano['valor'];
        
        // Calcular próxima data de vencimento (data_inicio + duracao_dias)
        $dataInicio = !empty($data['data_inicio']) ? $data['data_inicio'] : date('Y-m-d');
        $dataInicioObj = new \DateTime($dataInicio);
        $duracaoDias = (int) $plano['duracao_dias'];
        $proximaDataVencimento = clone $dataInicioObj;
        $proximaDataVencimento->modify("+{$duracaoDias} days");
        
        if ($plano['valor'] == 0) {
            // Plano teste: marcar como período teste e definir data de início de cobrança
            $periodoTeste = 1;
            
            // Se não informou data_inicio_cobranca, usar 1º dia do próximo mês
            if (!empty($data['data_inicio_cobranca'])) {
                $dataInicioCobranca = $data['data_inicio_cobranca'];
            } else {
                $proximoMes = new \DateTime('first day of next month');
                $dataInicioCobranca = $proximoMes->format('Y-m-d');
            }
        }

        // Verificar se já existe matrícula ativa NA MESMA MODALIDADE
        // Nota: Apenas status 'ativa' bloqueia nova matrícula. Status 'vencida' permite renovação.
        $stmtAtiva = $db->prepare("
            SELECT m.*, p.modalidade_id 
            FROM matriculas m
            INNER JOIN planos p ON p.id = m.plano_id
            INNER JOIN status_matricula sm ON sm.id = m.status_id
            WHERE m.aluno_id = ? AND m.tenant_id = ? AND sm.codigo = 'ativa' AND m.proxima_data_vencimento >= CURDATE()
            ORDER BY m.created_at DESC
        ");
        $stmtAtiva->execute([$alunoId, $tenantId]);
        $matriculasAtivas = $stmtAtiva->fetchAll();
        
        // Buscar modalidade do novo plano
        $modalidadeAtual = $plano['modalidade_id'];
        
        // Verificar se existe matrícula ativa na mesma modalidade
        $matriculaMesmaModalidade = null;
        foreach ($matriculasAtivas as $mat) {
            if ($mat['modalidade_id'] == $modalidadeAtual) {
                $matriculaMesmaModalidade = $mat;
                break;
            }
        }

        // VALIDAÇÃO: Só validar se for mudança na MESMA modalidade
        if ($matriculaMesmaModalidade && $matriculaMesmaModalidade['plano_id'] != $planoId) {
            // Verificar se a matrícula está dentro do período de validade
            $dataVencimentoMatricula = $matriculaMesmaModalidade['data_vencimento'];
            $hoje = date('Y-m-d');
            
            if ($dataVencimentoMatricula >= $hoje) {
                // Verificar se aluno tem pagamento ativo
                $stmtPagamentoAtivo = $db->prepare("
                    SELECT COUNT(*) as tem_pagamento FROM contas_receber 
                    WHERE usuario_id = ? 
                    AND tenant_id = ?
                    AND status = 'pago'
                    AND data_vencimento <= CURDATE()
                    AND DATE_ADD(data_vencimento, INTERVAL intervalo_dias DAY) >= CURDATE()
                ");
                $stmtPagamentoAtivo->execute([$usuarioId, $tenantId]);
                $pagamentoAtivo = $stmtPagamentoAtivo->fetch();
                
                if ($pagamentoAtivo && $pagamentoAtivo['tem_pagamento'] > 0) {
                    $dataVencimentoFormatada = date('d/m/Y', strtotime($dataVencimentoMatricula));
                    $response->getBody()->write(json_encode([
                        'error' => "Não é possível alterar o plano enquanto o aluno estiver ativo. O plano atual vence em {$dataVencimentoFormatada}. Aguarde o vencimento ou cancele a matrícula atual."
                    ]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
            }
        }

        // Configurar timezone Brasil
        date_default_timezone_set('America/Sao_Paulo');
        
        // Determinar datas e motivo
        $dataMatricula = date('Y-m-d');
        $dataInicio = $data['data_inicio'] ?? $dataMatricula;
        $dataVencimento = date('Y-m-d', strtotime($dataInicio . " +{$plano['duracao_dias']} days"));
        $valor = $data['valor'] ?? $plano['valor'];
        $motivo = $data['motivo'] ?? 'nova';
        $matriculaAnteriorId = null;
        $planoAnteriorId = null;

        // Se já tem matrícula ativa NA MESMA MODALIDADE, determinar motivo e finalizar
        if ($matriculaMesmaModalidade) {
            $planoAnteriorId = $matriculaMesmaModalidade['plano_id'];
            $matriculaAnteriorId = $matriculaMesmaModalidade['id'];
            
            // REGRA: Qualquer nova matrícula (renovação ou troca) só é permitida no dia do vencimento ou após
            $dataVencimentoAtual = $matriculaMesmaModalidade['data_vencimento'];
            $hoje = date('Y-m-d');
            
            if ($hoje < $dataVencimentoAtual) {
                $dataFormatada = date('d/m/Y', strtotime($dataVencimentoAtual));
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => "Já existe um plano vigente nesta modalidade com vencimento em {$dataFormatada}. Aguarde o vencimento para renovar ou trocar de plano."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            if ($planoId == $planoAnteriorId) {
                $motivo = 'renovacao';
            } else {
                $planoAnterior = $db->prepare("SELECT * FROM planos WHERE id = ?");
                $planoAnterior->execute([$planoAnteriorId]);
                $planoAnt = $planoAnterior->fetch();
                
                if ($planoAnt) {
                    $motivo = $plano['valor'] > $planoAnt['valor'] ? 'upgrade' : 'downgrade';
                }
            }
            
            // Verificar se tem parcelas em atraso antes de finalizar
            $stmtVerificarAtraso = $db->prepare("
                SELECT COUNT(*) as total_atraso
                FROM pagamentos_plano
                WHERE matricula_id = ? 
                AND status_pagamento_id = 1 
                AND data_vencimento < CURDATE()
            ");
            $stmtVerificarAtraso->execute([$matriculaMesmaModalidade['id']]);
            $atrasos = $stmtVerificarAtraso->fetch();
            
            if ($atrasos && $atrasos['total_atraso'] > 0) {
                $response->getBody()->write(json_encode([
                    'type' => 'error',
                    'message' => "Não é possível alterar o plano. Existem {$atrasos['total_atraso']} parcela(s) em atraso na matrícula atual. Por favor, regularize os pagamentos antes de prosseguir."
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Finalizar apenas a matrícula da mesma modalidade
            $stmtFinalizar = $db->prepare("
                UPDATE matriculas 
                SET status_id = (SELECT id FROM status_matricula WHERE codigo = 'finalizada'), updated_at = NOW()
                WHERE id = ?
            ");
            $stmtFinalizar->execute([$matriculaMesmaModalidade['id']]);
        }

        // Buscar IDs de status e motivo
        // Se for período teste, criar como ATIVA. Caso contrário, PENDENTE
        $codigoStatus = $periodoTeste == 1 ? 'ativa' : 'pendente';
        $stmtStatus = $db->prepare("SELECT id FROM status_matricula WHERE codigo = ?");
        $stmtStatus->execute([$codigoStatus]);
        $statusRow = $stmtStatus->fetch();
        $statusId = $statusRow ? $statusRow['id'] : ($periodoTeste == 1 ? 1 : 5);

        $stmtMotivo = $db->prepare("SELECT id FROM motivo_matricula WHERE codigo = ?");
        $stmtMotivo->execute([$motivo]);
        $motivoRow = $stmtMotivo->fetch();
        $motivoId = $motivoRow ? $motivoRow['id'] : 1; // 1 = nova

        // Criar matrícula:
        // - ATIVA se for período teste (permite check-in imediato)
        // - PENDENTE se for paga (primeira parcela precisa ser paga)
        $stmtInsert = $db->prepare("
            INSERT INTO matriculas 
            (tenant_id, aluno_id, plano_id, data_matricula, data_inicio, data_vencimento, 
             valor, status_id, motivo_id, matricula_anterior_id, plano_anterior_id, observacoes, criado_por,
             dia_vencimento, periodo_teste, data_inicio_cobranca, proxima_data_vencimento)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmtInsert->execute([
            $tenantId,
            $alunoId,
            $planoId,
            $dataMatricula,
            $dataInicio,
            $dataVencimento,
            $valorMatricula,
            $statusId,
            $motivoId,
            $matriculaAnteriorId,
            $planoAnteriorId,
            $data['observacoes'] ?? null,
            $adminId,
            $data['dia_vencimento'],
            $periodoTeste,
            $dataInicioCobranca,
            $proximaDataVencimento->format('Y-m-d')
        ]);
        
        $matriculaId = (int) $db->lastInsertId();
        
        // DEBUG LOG
        error_log("=== MATRICULA CRIADA ===");
        error_log("Matrícula ID: " . $matriculaId);
        error_log("Tenant ID: " . $tenantId);
        error_log("Usuário ID: " . $usuarioId);
        error_log("Plano ID: " . $planoId);
        error_log("Valor: " . $valorMatricula);
        error_log("Data Início: " . $dataInicio);
        error_log("Dia Vencimento: " . $data['dia_vencimento']);
        error_log("Período Teste: " . $periodoTeste);
        error_log("Data Início Cobrança: " . ($dataInicioCobranca ?? 'null'));
        error_log("Próxima Data Vencimento: " . $proximaDataVencimento->format('Y-m-d'));

        // Registrar no histórico de planos
        $stmtHistorico = $db->prepare("
            INSERT INTO historico_planos 
            (usuario_id, plano_anterior_id, plano_novo_id, data_inicio, 
             data_vencimento, valor_pago, motivo, observacoes, criado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmtHistorico->execute([
            $usuarioId,
            $planoAnteriorId,
            $planoId,
            $dataInicio,
            $dataVencimento,
            $valor,
            $motivo,
            $data['observacoes'] ?? null,
            $adminId
        ]);
        
        $historicoId = (int) $db->lastInsertId();

        // Criar primeiro pagamento do plano APENAS se NÃO for período teste
        if ($periodoTeste != 1) {
            $stmtPagamento = $db->prepare("
                INSERT INTO pagamentos_plano
                (tenant_id, aluno_id, matricula_id, plano_id, valor, data_vencimento, 
                 status_pagamento_id, observacoes, criado_por)
                VALUES (?, ?, ?, ?, ?, ?, 1, 'Primeiro pagamento da matrícula', ?)
            ");
            
            $stmtPagamento->execute([
                $tenantId,
                $alunoId,
                $matriculaId,
                $planoId,
                $valor,
                $dataInicio, // primeiro pagamento vence na data de início
                $adminId
            ]);
            
            $pagamentoId = (int) $db->lastInsertId();
            error_log("Pagamento criado ID: " . $pagamentoId);
        } else {
            error_log("Período teste: pagamento NÃO criado (ativado automaticamente)");
        }

        // Buscar matrícula criada
        $stmtMatricula = $db->prepare("
            SELECT 
                m.*,
                a.nome as aluno_nome,
                u.email as aluno_email,
                p.nome as plano_nome,
                p.duracao_dias,
                admin.nome as criado_por_nome
            FROM matriculas m
            INNER JOIN alunos a ON m.aluno_id = a.id
            INNER JOIN usuarios u ON a.usuario_id = u.id
            INNER JOIN planos p ON m.plano_id = p.id
            LEFT JOIN usuarios admin ON m.criado_por = admin.id
            WHERE m.id = ?
        ");
        $stmtMatricula->execute([$matriculaId]);
        $matricula = $stmtMatricula->fetch();

        // Buscar pagamentos da matrícula
        $stmtPagamentos = $db->prepare("
            SELECT 
                id,
                CAST(valor AS DECIMAL(10,2)) as valor,
                data_vencimento,
                data_pagamento,
                status_pagamento_id,
                (SELECT nome FROM status_pagamento WHERE id = status_pagamento_id) as status,
                observacoes
            FROM pagamentos_plano
            WHERE matricula_id = ?
            ORDER BY data_vencimento ASC
        ");
        $stmtPagamentos->execute([$matriculaId]);
        $pagamentos = $stmtPagamentos->fetchAll();

        // Calcular total de pagamentos
        $totalPagamentos = (float) array_sum(array_column($pagamentos, 'valor'));

        $response->getBody()->write(json_encode([
            'message' => 'Matrícula realizada com sucesso',
            'matricula' => $matricula,
            'pagamentos' => $pagamentos,
            'total_pagamentos' => $totalPagamentos,
            'info' => $periodoTeste == 1 
                ? "Período teste - Cobrança iniciará em {$dataInicioCobranca}. Acesso garantido até " . $proximaDataVencimento->format('d/m/Y')
                : "Acesso garantido até " . $proximaDataVencimento->format('d/m/Y')
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    /**
     * Listar matrículas
     */
    public function listar(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId', 1);
        $params = $request->getQueryParams();
        $db = require __DIR__ . '/../../config/database.php';
        $incluirInativos = isset($params['incluir_inativos']) && $params['incluir_inativos'] === 'true';
        
        // Verificar e atualizar status de matrículas pendentes vencidas
        $this->atualizarStatusMatriculasPendentes($db, $tenantId);
        
        $sql = "
            SELECT 
                m.*,
                a.nome as usuario_nome,
                u.email as usuario_email,
                a.usuario_id,
                p.nome as plano_nome,
                p.valor as plano_valor,
                p.duracao_dias,
                p.checkins_semanais,
                modalidade.nome as modalidade_nome,
                modalidade.icone as modalidade_icone,
                modalidade.cor as modalidade_cor,
                sm.codigo as status_codigo,
                sm.nome as status_nome,
                admin_criou.nome as criado_por_nome
            FROM matriculas m
            INNER JOIN alunos a ON m.aluno_id = a.id
            INNER JOIN usuarios u ON a.usuario_id = u.id
            INNER JOIN planos p ON m.plano_id = p.id
            INNER JOIN status_matricula sm ON sm.id = m.status_id
            LEFT JOIN modalidades modalidade ON p.modalidade_id = modalidade.id
            LEFT JOIN usuarios admin_criou ON m.criado_por = admin_criou.id
            WHERE m.tenant_id = ?
        ";
        
        $executeParams = [$tenantId];

        if (!$incluirInativos) {
            $sql .= " AND a.ativo = 1";
        }
        
        if (isset($params['aluno_id'])) {
            $sql .= " AND m.aluno_id = ?";
            $executeParams[] = $params['aluno_id'];
        }
        
        if (isset($params['status'])) {
            $sql .= " AND m.status_id = (SELECT id FROM status_matricula WHERE codigo = ? LIMIT 1)";
            $executeParams[] = $params['status'];
        }
        
        $sql .= " ORDER BY m.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($executeParams);
        $matriculas = $stmt->fetchAll();

        // Adicionar pagamentos a cada matrícula
        foreach ($matriculas as &$matricula) {
            $stmtPagamentos = $db->prepare("
                SELECT 
                    id,
                    CAST(valor AS DECIMAL(10,2)) as valor,
                    data_vencimento,
                    data_pagamento,
                    status_pagamento_id,
                    (SELECT nome FROM status_pagamento WHERE id = status_pagamento_id) as status,
                    observacoes
                FROM pagamentos_plano
                WHERE matricula_id = ?
                ORDER BY data_vencimento ASC
            ");
            $stmtPagamentos->execute([$matricula['id']]);
            $matricula['pagamentos'] = $stmtPagamentos->fetchAll() ?? [];
            $matricula['total_pagamentos'] = (float) array_sum(array_column($matricula['pagamentos'], 'valor'));
        }
        
        $response->getBody()->write(json_encode([
            'matriculas' => $matriculas,
            'total' => count($matriculas)
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Buscar matrícula por ID
     */
    public function buscar(Request $request, Response $response, array $args): Response
    {
        $matriculaId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);
        $db = require __DIR__ . '/../../config/database.php';
        
        // Verificar e atualizar status de matrículas pendentes vencidas
        $this->atualizarStatusMatriculasPendentes($db, $tenantId);
        
        $sql = "
            SELECT 
                m.*,
                a.nome as usuario_nome,
                u.email as usuario_email,
                a.usuario_id,
                p.nome as plano_nome,
                p.valor,
                p.duracao_dias,
                p.checkins_semanais,
                modalidade.nome as modalidade_nome,
                modalidade.icone as modalidade_icone,
                modalidade.cor as modalidade_cor,
                sm.codigo as status_codigo,
                sm.nome as status_nome,
                admin_criou.nome as criado_por_nome
            FROM matriculas m
            INNER JOIN alunos a ON m.aluno_id = a.id
            INNER JOIN usuarios u ON a.usuario_id = u.id
            INNER JOIN planos p ON m.plano_id = p.id
            INNER JOIN status_matricula sm ON sm.id = m.status_id
            LEFT JOIN modalidades modalidade ON p.modalidade_id = modalidade.id
            LEFT JOIN usuarios admin_criou ON m.criado_por = admin_criou.id
            WHERE m.id = ? AND m.tenant_id = ?
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$matriculaId, $tenantId]);
        $matricula = $stmt->fetch();
        
        if (!$matricula) {
            $response->getBody()->write(json_encode(['error' => 'Matrícula não encontrada']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Buscar pagamentos da matrícula
        $stmtPagamentos = $db->prepare("
            SELECT 
                id,
                CAST(valor AS DECIMAL(10,2)) as valor,
                data_vencimento,
                data_pagamento,
                status_pagamento_id,
                (SELECT nome FROM status_pagamento WHERE id = status_pagamento_id) as status,
                observacoes
            FROM pagamentos_plano
            WHERE matricula_id = ?
            ORDER BY data_vencimento ASC
        ");
        $stmtPagamentos->execute([$matriculaId]);
        $matricula['pagamentos'] = $stmtPagamentos->fetchAll() ?? [];
        $matricula['total_pagamentos'] = (float) array_sum(array_column($matricula['pagamentos'], 'valor'));
        
        $response->getBody()->write(json_encode([
            'matricula' => $matricula,
            'pagamentos' => $matricula['pagamentos'],
            'total' => $matricula['total_pagamentos']
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Verificar e marcar como PENDENTE matrículas com qualquer parcela vencida e não paga
     */
    private function atualizarStatusMatriculasPendentes($db, $tenantId): void
    {
        // 1. Atualizar para VENCIDA: matrículas com pagamento vencido há mais de 1 dia (mas menos de 5)
        $sqlVencida = "
            UPDATE matriculas m
            SET 
                m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'vencida' LIMIT 1),
                m.updated_at = NOW()
            WHERE m.tenant_id = :tenant_id 
            AND m.status_id IN (SELECT id FROM status_matricula WHERE codigo IN ('ativa', 'vencida'))
            AND EXISTS (
                SELECT 1
                FROM pagamentos_plano pp
                WHERE pp.matricula_id = m.id
                AND pp.status_pagamento_id IN (1, 3)  -- Aguardando ou Atrasado
                AND pp.data_vencimento < CURDATE()
                AND DATEDIFF(CURDATE(), pp.data_vencimento) >= 1
                AND DATEDIFF(CURDATE(), pp.data_vencimento) < 5
            )
        ";
        
        $stmt = $db->prepare($sqlVencida);
        $stmt->execute(['tenant_id' => $tenantId]);

        // 2. Atualizar para CANCELADA: matrículas com pagamento vencido há 5+ dias
        $sqlCancelada = "
            UPDATE matriculas m
            SET 
                m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'cancelada' LIMIT 1),
                m.updated_at = NOW()
            WHERE m.tenant_id = :tenant_id 
            AND m.status_id IN (SELECT id FROM status_matricula WHERE codigo IN ('ativa', 'vencida'))
            AND EXISTS (
                SELECT 1
                FROM pagamentos_plano pp
                WHERE pp.matricula_id = m.id
                AND pp.status_pagamento_id IN (1, 3)  -- Aguardando ou Atrasado
                AND pp.data_vencimento < CURDATE()
                AND DATEDIFF(CURDATE(), pp.data_vencimento) >= 5
            )
        ";
        
        $stmt = $db->prepare($sqlCancelada);
        $stmt->execute(['tenant_id' => $tenantId]);

        // 3. Também atualizar os pagamentos em atraso para status_pagamento_id = 3 (Atrasado)
        $sqlAtualizarPagamentos = "
            UPDATE pagamentos_plano pp
            SET 
                pp.status_pagamento_id = 3,  -- Atrasado
                pp.updated_at = NOW()
            WHERE pp.tenant_id = :tenant_id
            AND pp.status_pagamento_id = 1  -- Aguardando
            AND pp.data_vencimento < CURDATE()
            AND pp.data_pagamento IS NULL
        ";
        
        $stmt = $db->prepare($sqlAtualizarPagamentos);
        $stmt->execute(['tenant_id' => $tenantId]);
    }

    /**
     * Buscar pagamentos de uma matrícula
     */
    public function buscarPagamentos(Request $request, Response $response, array $args): Response
    {
        $matriculaId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);
        $db = require __DIR__ . '/../../config/database.php';
        
        // Verificar se a matrícula existe e pertence ao tenant
        $stmtMatricula = $db->prepare("SELECT * FROM matriculas WHERE id = ? AND tenant_id = ?");
        $stmtMatricula->execute([$matriculaId, $tenantId]);
        $matricula = $stmtMatricula->fetch();
        
        if (!$matricula) {
            $response->getBody()->write(json_encode(['error' => 'Matrícula não encontrada']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        // Buscar pagamentos relacionados à matrícula
        $sql = "
            SELECT 
                pp.*,
                sp.nome as status_pagamento_nome,
                fp.nome as forma_pagamento_nome,
                a.nome as aluno_nome,
                pl.nome as plano_nome,
                criador.nome as criado_por_nome,
                baixador.nome as baixado_por_nome,
                tb.nome as tipo_baixa_nome
            FROM pagamentos_plano pp
            INNER JOIN status_pagamento sp ON pp.status_pagamento_id = sp.id
            LEFT JOIN formas_pagamento fp ON pp.forma_pagamento_id = fp.id
            INNER JOIN alunos a ON pp.aluno_id = a.id
            INNER JOIN planos pl ON pp.plano_id = pl.id
            LEFT JOIN usuarios criador ON pp.criado_por = criador.id
            LEFT JOIN usuarios baixador ON pp.baixado_por = baixador.id
            LEFT JOIN tipos_baixa tb ON pp.tipo_baixa_id = tb.id
            WHERE pp.matricula_id = ? AND pp.tenant_id = ?
            ORDER BY pp.data_vencimento DESC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$matriculaId, $tenantId]);
        $pagamentos = $stmt->fetchAll();
        
        $response->getBody()->write(json_encode([
            'pagamentos' => $pagamentos,
            'total' => count($pagamentos)
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Cancelar matrícula
     */
    public function cancelar(Request $request, Response $response, array $args): Response
    {
        $matriculaId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);
        $adminId = $request->getAttribute('userId', null);
        $data = $request->getParsedBody();
        $db = require __DIR__ . '/../../config/database.php';
        
        $stmt = $db->prepare("
            SELECT m.*, sm.codigo as status_codigo 
            FROM matriculas m 
            LEFT JOIN status_matricula sm ON sm.id = m.status_id 
            WHERE m.id = ? AND m.tenant_id = ?
        ");
        $stmt->execute([$matriculaId, $tenantId]);
        $matricula = $stmt->fetch();
        
        if (!$matricula) {
            $response->getBody()->write(json_encode(['error' => 'Matrícula não encontrada']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        if ($matricula['status_codigo'] === 'cancelada') {
            $response->getBody()->write(json_encode(['error' => 'Matrícula já está cancelada']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $motivoCancelamento = $data['motivo_cancelamento'] ?? 'Cancelado pelo admin';
        
        $stmtUpdate = $db->prepare("
            UPDATE matriculas 
            SET status_id = (SELECT id FROM status_matricula WHERE codigo = 'cancelada'),
                cancelado_por = ?,
                data_cancelamento = CURDATE(),
                motivo_cancelamento = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmtUpdate->execute([$adminId, $motivoCancelamento, $matriculaId]);
        
        // Remover plano do aluno - buscar usuario_id via aluno
        $stmtAluno = $db->prepare("SELECT usuario_id FROM alunos WHERE id = ?");
        $stmtAluno->execute([$matricula['aluno_id']]);
        $alunoRow = $stmtAluno->fetch();
        
        if ($alunoRow) {
            $stmtUpdateUsuario = $db->prepare("
                UPDATE usuarios 
                SET plano_id = NULL, data_vencimento_plano = NULL
                WHERE id = ?
            ");
            $stmtUpdateUsuario->execute([$alunoRow['usuario_id']]);
        }

        // Buscar matrícula atualizada com pagamentos
        $stmtMatricula = $db->prepare("
            SELECT 
                m.*,
                a.nome as usuario_nome,
                u.email as usuario_email,
                p.nome as plano_nome
            FROM matriculas m
            INNER JOIN alunos a ON m.aluno_id = a.id
            INNER JOIN usuarios u ON a.usuario_id = u.id
            INNER JOIN planos p ON m.plano_id = p.id
            WHERE m.id = ?
        ");
        $stmtMatricula->execute([$matriculaId]);
        $matriculaAtualizada = $stmtMatricula->fetch();

        // Buscar pagamentos
        $stmtPagamentos = $db->prepare("
            SELECT 
                id,
                CAST(valor AS DECIMAL(10,2)) as valor,
                data_vencimento,
                data_pagamento,
                status_pagamento_id,
                (SELECT nome FROM status_pagamento WHERE id = status_pagamento_id) as status,
                observacoes
            FROM pagamentos_plano
            WHERE matricula_id = ?
            ORDER BY data_vencimento ASC
        ");
        $stmtPagamentos->execute([$matriculaId]);
        $pagamentos = $stmtPagamentos->fetchAll() ?? [];
        
        $response->getBody()->write(json_encode([
            'message' => 'Matrícula cancelada com sucesso',
            'matricula' => $matriculaAtualizada,
            'pagamentos' => $pagamentos,
            'total' => (float) array_sum(array_column($pagamentos, 'valor'))
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Dar baixa em pagamento de plano (marcar como pago)
     */
    public function darBaixaConta(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId', 1);
        $adminId = $request->getAttribute('userId', null);
        $data = $request->getParsedBody();
        $db = require __DIR__ . '/../../config/database.php';
        
        $pagamentoId = (int) ($args['id'] ?? 0);
        
        // Buscar pagamento com informações do plano
        $stmt = $db->prepare("
            SELECT pp.*, m.plano_id, p.duracao_dias
            FROM pagamentos_plano pp
            INNER JOIN matriculas m ON pp.matricula_id = m.id
            INNER JOIN planos p ON pp.plano_id = p.id
            WHERE pp.id = ? AND pp.tenant_id = ?
        ");
        $stmt->execute([$pagamentoId, $tenantId]);
        $pagamento = $stmt->fetch();
        
        if (!$pagamento) {
            $response->getBody()->write(json_encode([
                'error' => 'Pagamento não encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        // Verificar se já está pago (status_pagamento_id = 2)
        if ($pagamento['status_pagamento_id'] == 2) {
            $response->getBody()->write(json_encode(['error' => 'Pagamento já está marcado como pago']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $dataPagamento = $data['data_pagamento'] ?? date('Y-m-d');
        $formaPagamentoId = $data['forma_pagamento_id'] ?? null;
        $observacoes = $data['observacoes'] ?? null;
        $tipoBaixaId = 1; // 1 = Manual (assumindo que existe na tabela tipos_baixa)
        
        // Atualizar pagamento para pago (status_pagamento_id = 2)
        $stmtUpdate = $db->prepare("
            UPDATE pagamentos_plano 
            SET status_pagamento_id = 2,
                data_pagamento = ?,
                forma_pagamento_id = ?,
                observacoes = ?,
                baixado_por = ?,
                tipo_baixa_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmtUpdate->execute([
            $dataPagamento,
            $formaPagamentoId,
            $observacoes,
            $adminId,
            $tipoBaixaId,
            $pagamentoId
        ]);
        
        // Atualizar status da matrícula para 'ativa' se era 'pendente'
        $stmtMatricula = $db->prepare("
            UPDATE matriculas 
            SET status_id = (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1),
                updated_at = NOW()
            WHERE id = ? 
            AND status_id = (SELECT id FROM status_matricula WHERE codigo = 'pendente' LIMIT 1)
        ");
        $stmtMatricula->execute([$pagamento['matricula_id']]);
        
        // Criar próxima parcela automaticamente
        try {
            $duracaoDias = (int) $pagamento['duracao_dias']; // 30, 60, 90, etc
            $dataVencimentoAtual = new \DateTime($pagamento['data_vencimento']);
            $proximoVencimento = $dataVencimentoAtual->add(new \DateInterval("P{$duracaoDias}D"));
            
            $stmtProxima = $db->prepare("
                INSERT INTO pagamentos_plano (
                    tenant_id,
                    aluno_id,
                    matricula_id,
                    plano_id,
                    valor,
                    data_vencimento,
                    status_pagamento_id,
                    observacoes,
                    criado_por,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, NOW())
            ");
            
            $stmtProxima->execute([
                $pagamento['tenant_id'],
                $pagamento['aluno_id'],
                $pagamento['matricula_id'],
                $pagamento['plano_id'],
                $pagamento['valor'],
                $proximoVencimento->format('Y-m-d'),
                'Pagamento gerado automaticamente após confirmação',
                $adminId
            ]);
            
            $proximaParcela = [
                'id' => $db->lastInsertId(),
                'data_vencimento' => $proximoVencimento->format('Y-m-d'),
                'valor' => $pagamento['valor'],
                'status' => 'Aguardando'
            ];
            
            error_log("Próxima parcela criada com sucesso: ID " . $proximaParcela['id']);
            
        } catch (\Exception $e) {
            error_log("Erro ao criar próxima parcela: " . $e->getMessage());
            // Não falha a operação se houver erro ao criar próxima parcela
        }
        
        // Buscar pagamento atualizado
        $stmt->execute([$pagamentoId, $tenantId]);
        $pagamentoAtualizado = $stmt->fetch();
        
        $response->getBody()->write(json_encode([
            'message' => 'Baixa realizada com sucesso',
            'pagamento' => $pagamentoAtualizado,
            'proxima_parcela' => $proximaParcela ?? null
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Atualizar próxima data de vencimento de uma matrícula
     */
    public function atualizarProximaDataVencimento(Request $request, Response $response, array $args): Response
    {
        $db = require __DIR__ . '/../../config/database.php';
        $tenantId = $request->getAttribute('tenantId');
        $matriculaId = $args['id'];
        $data = $request->getParsedBody();

        // Validar se a data foi enviada
        if (empty($data['proxima_data_vencimento'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Data de vencimento é obrigatória'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Validar formato da data
        $dataVencimento = $data['proxima_data_vencimento'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataVencimento)) {
            $response->getBody()->write(json_encode([
                'error' => 'Formato de data inválido. Use YYYY-MM-DD'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        // Verificar se a matrícula existe e pertence ao tenant
        $stmt = $db->prepare("
            SELECT m.id, m.aluno_id, m.plano_id, m.proxima_data_vencimento, m.status_id,
                   sm.codigo as status_codigo
            FROM matriculas m
            INNER JOIN status_matricula sm ON sm.id = m.status_id
            WHERE m.id = :id AND m.tenant_id = :tenant_id
        ");
        $stmt->execute([
            'id' => $matriculaId,
            'tenant_id' => $tenantId
        ]);
        $matricula = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$matricula) {
            $response->getBody()->write(json_encode([
                'error' => 'Matrícula não encontrada'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Determinar o novo status baseado na data
        $hoje = date('Y-m-d');
        $novoStatusCodigo = null;
        
        if ($dataVencimento < $hoje) {
            // Data vencida → status "vencida"
            $novoStatusCodigo = 'vencida';
        } elseif ($dataVencimento >= $hoje && $matricula['status_codigo'] === 'vencida') {
            // Data válida e status era vencida → mudar para "ativa"
            $novoStatusCodigo = 'ativa';
        }

        // Atualizar a data e o status (se necessário)
        if ($novoStatusCodigo) {
            $stmtUpdate = $db->prepare("
                UPDATE matriculas
                SET proxima_data_vencimento = :proxima_data_vencimento,
                    status_id = (SELECT id FROM status_matricula WHERE codigo = :status_codigo LIMIT 1),
                    updated_at = NOW()
                WHERE id = :id AND tenant_id = :tenant_id
            ");
            
            $resultado = $stmtUpdate->execute([
                'proxima_data_vencimento' => $dataVencimento,
                'status_codigo' => $novoStatusCodigo,
                'id' => $matriculaId,
                'tenant_id' => $tenantId
            ]);
        } else {
            // Atualizar apenas a data
            $stmtUpdate = $db->prepare("
                UPDATE matriculas
                SET proxima_data_vencimento = :proxima_data_vencimento,
                    updated_at = NOW()
                WHERE id = :id AND tenant_id = :tenant_id
            ");
            
            $resultado = $stmtUpdate->execute([
                'proxima_data_vencimento' => $dataVencimento,
                'id' => $matriculaId,
                'tenant_id' => $tenantId
            ]);
        }

        if ($resultado) {
            // Buscar o novo status atualizado
            $stmtStatus = $db->prepare("
                SELECT sm.codigo as status_codigo, sm.nome as status_nome
                FROM matriculas m
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                WHERE m.id = :id
            ");
            $stmtStatus->execute(['id' => $matriculaId]);
            $statusAtualizado = $stmtStatus->fetch(\PDO::FETCH_ASSOC);
            
            $response->getBody()->write(json_encode([
                'message' => 'Data de vencimento atualizada com sucesso',
                'matricula_id' => $matriculaId,
                'proxima_data_vencimento_anterior' => $matricula['proxima_data_vencimento'],
                'proxima_data_vencimento_nova' => $dataVencimento,
                'status_anterior' => $matricula['status_codigo'],
                'status_atual' => $statusAtualizado['status_codigo'] ?? $matricula['status_codigo'],
                'status_nome' => $statusAtualizado['status_nome'] ?? null
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode([
            'error' => 'Erro ao atualizar data de vencimento'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    /**
     * Listar matrículas com vencimento hoje (para notificações)
     */
    public function vencimentosHoje(Request $request, Response $response): Response
    {
        $db = require __DIR__ . '/../../config/database.php';
        $tenantId = $request->getAttribute('tenantId');
        $hoje = date('Y-m-d');

        $stmt = $db->prepare("
            SELECT 
                m.id,
                m.aluno_id,
                m.plano_id,
                m.proxima_data_vencimento,
                m.valor,
                m.dia_vencimento,
                m.periodo_teste,
                a.nome as aluno_nome,
                u.email as aluno_email,
                u.telefone as aluno_telefone,
                p.nome as plano_nome,
                p.valor as plano_valor,
                sm.nome as status_nome,
                sm.codigo as status_codigo
            FROM matriculas m
            INNER JOIN alunos a ON m.aluno_id = a.id
            INNER JOIN usuarios u ON a.usuario_id = u.id
            INNER JOIN planos p ON m.plano_id = p.id
            INNER JOIN status_matricula sm ON m.status_id = sm.id
            WHERE m.tenant_id = :tenant_id
            AND m.proxima_data_vencimento = :hoje
            AND sm.codigo = 'ativa'
            ORDER BY a.nome ASC
        ");

        $stmt->execute([
            'tenant_id' => $tenantId,
            'hoje' => $hoje
        ]);

        $vencimentos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode([
            'vencimentos' => $vencimentos,
            'total' => count($vencimentos),
            'data' => $hoje
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Listar próximos vencimentos (próximos N dias)
     */
    public function proximosVencimentos(Request $request, Response $response): Response
    {
        $db = require __DIR__ . '/../../config/database.php';
        $tenantId = $request->getAttribute('tenantId');
        
        $params = $request->getQueryParams();
        $dias = (int) ($params['dias'] ?? 7);
        
        $hoje = date('Y-m-d');
        $dataLimite = date('Y-m-d', strtotime("+{$dias} days"));

        $stmt = $db->prepare("
            SELECT 
                m.id,
                m.aluno_id,
                m.plano_id,
                m.proxima_data_vencimento,
                m.valor,
                m.dia_vencimento,
                m.periodo_teste,
                DATEDIFF(m.proxima_data_vencimento, :hoje) as dias_restantes,
                a.nome as aluno_nome,
                u.email as aluno_email,
                u.telefone as aluno_telefone,
                p.nome as plano_nome,
                p.valor as plano_valor,
                sm.nome as status_nome,
                sm.codigo as status_codigo
            FROM matriculas m
            INNER JOIN alunos a ON m.aluno_id = a.id
            INNER JOIN usuarios u ON a.usuario_id = u.id
            INNER JOIN planos p ON m.plano_id = p.id
            INNER JOIN status_matricula sm ON m.status_id = sm.id
            WHERE m.tenant_id = :tenant_id
            AND m.proxima_data_vencimento BETWEEN :hoje AND :data_limite
            AND sm.codigo = 'ativa'
            ORDER BY m.proxima_data_vencimento ASC, a.nome ASC
        ");

        $stmt->execute([
            'tenant_id' => $tenantId,
            'hoje' => $hoje,
            'data_limite' => $dataLimite
        ]);

        $vencimentos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode([
            'vencimentos' => $vencimentos,
            'total' => count($vencimentos),
            'periodo' => [
                'inicio' => $hoje,
                'fim' => $dataLimite,
                'dias' => $dias
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}

