<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Usuario;
use App\Models\Plano;
use OpenApi\Attributes as OA;

/**
 * MatriculaController
 * 
 * CRUD completo para gestão de matrículas de alunos.
 * Controla criação, listagem, cancelamento, exclusão e pagamentos.
 * 
 * Rotas:
 * - POST   /admin/matriculas                              - Criar nova matrícula
 * - GET    /admin/matriculas                              - Listar matrículas
 * - GET    /admin/matriculas/{id}                         - Buscar matrícula por ID
 * - GET    /admin/matriculas/{id}/pagamentos              - Buscar pagamentos da matrícula
 * - GET    /admin/matriculas/{id}/delete-preview          - Prévia de exclusão
 * - DELETE /admin/matriculas/{id}                         - Deletar matrícula
 * - POST   /admin/matriculas/{id}/cancelar                - Cancelar matrícula
 * - POST   /admin/matriculas/contas/{id}/baixa            - Dar baixa em pagamento
 * - PUT    /admin/matriculas/{id}/proxima-data-vencimento - Atualizar data de vencimento
 * - GET    /admin/matriculas/vencimentos/hoje             - Vencimentos do dia
 * - GET    /admin/matriculas/vencimentos/proximos         - Próximos vencimentos
 */
class MatriculaController
{
    /**
     * Criar nova matrícula
     */
    #[OA\Post(
        path: "/admin/matriculas",
        summary: "Criar nova matrícula (individual ou pacote)",
        description: "Cria uma matrícula individual para um aluno, ou múltiplas matrículas via pacote.\n\n**Matrícula individual:** envie plano_id + dia_vencimento.\n**Matrícula via pacote:** envie pacote_id + dia_vencimento + dependentes[]. O aluno informado (aluno_id/usuario_id) é o pagante. O plano, ciclo e valor vêm do pacote. Cada beneficiário (pagante + dependentes) recebe uma matrícula com valor rateado.",
        tags: ["Matrículas"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["dia_vencimento"],
                properties: [
                    new OA\Property(property: "usuario_id", type: "integer", description: "ID do usuário (usado para buscar o aluno)"),
                    new OA\Property(property: "aluno_id", type: "integer", description: "ID do aluno (alternativa ao usuario_id)"),
                    new OA\Property(property: "plano_id", type: "integer", description: "ID do plano (obrigatório para matrícula individual)"),
                    new OA\Property(property: "plano_ciclo_id", type: "integer", nullable: true, description: "ID do ciclo do plano (define valor e duração). Se não informado, usa valores do plano base."),
                    new OA\Property(property: "pacote_id", type: "integer", nullable: true, description: "ID do pacote. Quando informado, cria matrículas para o pagante + dependentes com valor rateado. plano_id e valor são ignorados (vêm do pacote)."),
                    new OA\Property(property: "dependentes", type: "array", items: new OA\Items(type: "integer"), nullable: true, description: "Array de aluno_ids dos dependentes (apenas para pacote). O pagante é incluído automaticamente."),
                    new OA\Property(property: "dia_vencimento", type: "integer", description: "Dia do mês para vencimento (1-31)"),
                    new OA\Property(property: "valor", type: "number", format: "float", nullable: true, description: "Valor da matrícula (se não informado, usa o valor do ciclo ou plano)"),
                    new OA\Property(property: "data_inicio", type: "string", format: "date", nullable: true, description: "Data de início (padrão: hoje)"),
                    new OA\Property(property: "observacoes", type: "string", nullable: true, description: "Observações sobre a matrícula"),
                    new OA\Property(property: "motivo", type: "string", nullable: true, description: "Motivo da matrícula (nova, renovacao, troca_plano)")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Matrícula(s) criada(s) com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Matrícula realizada com sucesso"),
                        new OA\Property(property: "matricula", type: "object", description: "Matrícula criada (individual) ou null (pacote)"),
                        new OA\Property(property: "pagamentos", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "total_pagamentos", type: "number"),
                        new OA\Property(property: "info", type: "string", example: "Acesso garantido até 25/03/2026"),
                        new OA\Property(property: "pacote_contrato_id", type: "integer", nullable: true, description: "ID do contrato de pacote (apenas para pacote)"),
                        new OA\Property(property: "matriculas", type: "array", items: new OA\Items(type: "object"), description: "Lista de matrículas criadas (apenas para pacote)")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Dados inválidos, aluno já possui matrícula ativa na modalidade, ou limite de beneficiários excedido"),
            new OA\Response(response: 404, description: "Plano, ciclo, pacote ou usuário não encontrado"),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
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
        
        if (empty($data['plano_id']) && empty($data['pacote_id'])) {
            $errors[] = 'Plano ou Pacote é obrigatório (envie plano_id ou pacote_id)';
        }

        // Validar dia_vencimento (opcional, mas se informado deve ser entre 1 e 31)
        if (!empty($data['dia_vencimento'])) {
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

        // === BRANCH: PACOTE ===
        // Se pacote_id foi informado, delegar para método específico de pacote
        if (!empty($data['pacote_id'])) {
            return $this->criarMatriculaPacote($request, $response, $db, $tenantId, $adminId, $alunoId, $usuarioId, $data);
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

        // Buscar plano_ciclo se informado
        $planoCicloId = !empty($data['plano_ciclo_id']) ? (int) $data['plano_ciclo_id'] : null;
        $planoCiclo = null;
        
        if ($planoCicloId) {
            $stmtCiclo = $db->prepare("
                SELECT pc.*, af.codigo as frequencia_codigo, af.nome as frequencia_nome, af.meses as frequencia_meses
                FROM plano_ciclos pc
                LEFT JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
                WHERE pc.id = ? AND pc.plano_id = ? AND pc.tenant_id = ? AND pc.ativo = 1
            ");
            $stmtCiclo->execute([$planoCicloId, $planoId, $tenantId]);
            $planoCiclo = $stmtCiclo->fetch();
            
            if (!$planoCiclo) {
                $response->getBody()->write(json_encode(['error' => 'Ciclo de plano não encontrado ou inativo']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
        }

        // Verificar se o plano tem valor 0 (teste) e configurar automaticamente
        $periodoTeste = 0;
        $dataInicioCobranca = null;
        
        // Se tem ciclo selecionado, usar valor e duração do ciclo
        if ($planoCiclo) {
            $valorMatricula = $data['valor'] ?? $planoCiclo['valor'];
            $mesesCiclo = (int) ($planoCiclo['meses'] ?? $planoCiclo['frequencia_meses'] ?? 1);
            $duracaoDias = $mesesCiclo * 30; // aproximação em dias
        } else {
            $valorMatricula = $data['valor'] ?? $plano['valor'];
            $duracaoDias = (int) $plano['duracao_dias'];
        }
        
        // Calcular próxima data de vencimento (data_inicio + duracao)
        $dataInicio = !empty($data['data_inicio']) ? $data['data_inicio'] : date('Y-m-d');
        $dataInicioObj = new \DateTime($dataInicio);
        $proximaDataVencimento = clone $dataInicioObj;
        
        if ($planoCiclo) {
            $mesesCiclo = (int) ($planoCiclo['meses'] ?? $planoCiclo['frequencia_meses'] ?? 1);
            $proximaDataVencimento->modify("+{$mesesCiclo} months");
        } else {
            $proximaDataVencimento->modify("+{$duracaoDias} days");
        }
        
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

        // Buscar matrícula vencida (ou com data vencida) para reutilizar
        $stmtVencida = $db->prepare("
            SELECT m.*, p.modalidade_id, sm.codigo as status_codigo
            FROM matriculas m
            INNER JOIN planos p ON p.id = m.plano_id
            INNER JOIN status_matricula sm ON sm.id = m.status_id
            WHERE m.aluno_id = ? AND m.tenant_id = ? AND p.modalidade_id = ?
            ORDER BY m.updated_at DESC, m.id DESC
            LIMIT 1
        ");
        $stmtVencida->execute([$alunoId, $tenantId, $modalidadeAtual]);
        $matriculaVencida = $stmtVencida->fetch(\PDO::FETCH_ASSOC) ?: null;
        $reutilizandoMatricula = false;

        if ($matriculaVencida) {
            $statusCodigo = $matriculaVencida['status_codigo'] ?? null;
            $vencimentoAtual = $matriculaVencida['proxima_data_vencimento'] ?? $matriculaVencida['data_vencimento'] ?? null;
            $hoje = date('Y-m-d');
            $vencidaPorData = $vencimentoAtual && $vencimentoAtual < $hoje;

            if ($vencidaPorData && $statusCodigo !== 'vencida') {
                $stmtStatusVencida = $db->prepare("SELECT id FROM status_matricula WHERE codigo = 'vencida' LIMIT 1");
                $stmtStatusVencida->execute();
                $statusVencidaId = $stmtStatusVencida->fetchColumn();
                if ($statusVencidaId) {
                    $stmtMarcarVencida = $db->prepare("
                        UPDATE matriculas
                        SET status_id = ?, updated_at = NOW()
                        WHERE id = ? AND tenant_id = ?
                    ");
                    $stmtMarcarVencida->execute([$statusVencidaId, $matriculaVencida['id'], $tenantId]);
                    $statusCodigo = 'vencida';
                }
            }

            if ($statusCodigo === 'vencida' || $vencidaPorData) {
                $reutilizandoMatricula = true;
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
        $dataVencimento = date('Y-m-d', strtotime($dataInicio . " +{$duracaoDias} days"));
        $valor = $valorMatricula;
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

        if ($reutilizandoMatricula && $matriculaVencida) {
            $matriculaId = (int) $matriculaVencida['id'];
            $planoAnteriorId = (int) $matriculaVencida['plano_id'];

            $stmtUpdateMatricula = $db->prepare("
                UPDATE matriculas
                SET plano_id = ?,
                    plano_ciclo_id = ?,
                    data_matricula = ?,
                    data_inicio = ?,
                    data_vencimento = ?,
                    valor = ?,
                    status_id = ?,
                    motivo_id = ?,
                    plano_anterior_id = ?,
                    observacoes = ?,
                    criado_por = ?,
                    dia_vencimento = ?,
                    periodo_teste = ?,
                    data_inicio_cobranca = ?,
                    proxima_data_vencimento = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmtUpdateMatricula->execute([
                $planoId,
                $planoCicloId,
                $dataMatricula,
                $dataInicio,
                $dataVencimento,
                $valorMatricula,
                $statusId,
                $motivoId,
                $planoAnteriorId,
                $data['observacoes'] ?? null,
                $adminId,
                $data['dia_vencimento'],
                $periodoTeste,
                $dataInicioCobranca,
                $proximaDataVencimento->format('Y-m-d'),
                $matriculaId,
                $tenantId
            ]);
        } else {
            // Criar matrícula:
            // - ATIVA se for período teste (permite check-in imediato)
            // - PENDENTE se for paga (primeira parcela precisa ser paga)
            $stmtInsert = $db->prepare("
                INSERT INTO matriculas 
                (tenant_id, aluno_id, plano_id, plano_ciclo_id, data_matricula, data_inicio, data_vencimento, 
                 valor, status_id, motivo_id, matricula_anterior_id, plano_anterior_id, observacoes, criado_por,
                 dia_vencimento, periodo_teste, data_inicio_cobranca, proxima_data_vencimento)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmtInsert->execute([
                $tenantId,
                $alunoId,
                $planoId,
                $planoCicloId,
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
        }
        
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
            try {
                $stmtPagamento = $db->prepare("
                    INSERT INTO pagamentos_plano
                    (tenant_id, aluno_id, matricula_id, plano_id, valor, data_vencimento, 
                     status_pagamento_id, observacoes, criado_por, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, 1, 'Primeiro pagamento da matrícula', ?, NOW(), NOW())
                ");
                
                $stmtPagamento->execute([
                    $tenantId,
                    $alunoId,
                    $matriculaId,
                    $planoId,
                    $valorMatricula,
                    $dataInicio, // primeiro pagamento vence na data de início
                    $adminId
                ]);
                
                $pagamentoId = (int) $db->lastInsertId();
                error_log("Pagamento criado ID: " . $pagamentoId);
            } catch (\Exception $e) {
                error_log("ERRO ao criar pagamento_plano: " . $e->getMessage());
            }
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
     * Criar matrículas via pacote (pagante + dependentes)
     * Método privado chamado pelo criar() quando pacote_id é informado
     */
    private function criarMatriculaPacote(
        Request $request,
        Response $response,
        \PDO $db,
        int $tenantId,
        ?int $adminId,
        int $alunoId,
        int $usuarioId,
        array $data
    ): Response {
        $pacoteId = (int) $data['pacote_id'];
        $dependentesIds = isset($data['dependentes']) ? array_map('intval', (array) $data['dependentes']) : [];
        $diaVencimento = (int) ($data['dia_vencimento'] ?? 10);
        $observacoes = $data['observacoes'] ?? null;
        $dataInicio = !empty($data['data_inicio']) ? $data['data_inicio'] : date('Y-m-d');

        // 1. Buscar pacote
        $stmtPacote = $db->prepare("
            SELECT p.*, pl.duracao_dias, pl.modalidade_id, pl.nome as plano_nome,
                   pc2.meses as ciclo_meses, pc2.valor as ciclo_valor
            FROM pacotes p
            INNER JOIN planos pl ON pl.id = p.plano_id
            LEFT JOIN plano_ciclos pc2 ON pc2.id = p.plano_ciclo_id
            WHERE p.id = ? AND p.tenant_id = ? AND p.ativo = 1
            LIMIT 1
        ");
        $stmtPacote->execute([$pacoteId, $tenantId]);
        $pacote = $stmtPacote->fetch(\PDO::FETCH_ASSOC);

        if (!$pacote) {
            $response->getBody()->write(json_encode(['error' => 'Pacote não encontrado ou inativo'], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // 2. Montar lista de beneficiários (pagante + dependentes)
        // qtd_beneficiarios do pacote = total de pessoas (pagante + dependentes)
        $beneficiariosIds = array_unique(array_merge([$alunoId], $dependentesIds));
        $totalBeneficiarios = count($beneficiariosIds);
        $limiteTotal = (int) $pacote['qtd_beneficiarios'] + 1; // +1 porque o pagante não conta no limite

        if ($totalBeneficiarios > $limiteTotal) {
            $response->getBody()->write(json_encode([
                'error' => "Quantidade total de pessoas ({$totalBeneficiarios}) excede o limite do pacote ({$limiteTotal}: 1 pagante + {$pacote['qtd_beneficiarios']} beneficiário(s))"
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // 3. Validar que todos os dependentes existem como alunos no tenant
        if (!empty($dependentesIds)) {
            $placeholders = implode(',', array_fill(0, count($dependentesIds), '?'));
            $stmtValidaDeps = $db->prepare("
                SELECT a.id
                FROM alunos a
                INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id AND tup.tenant_id = ? AND tup.ativo = 1
                WHERE a.id IN ({$placeholders})
            ");
            $stmtValidaDeps->execute(array_merge([$tenantId], $dependentesIds));
            $depsEncontrados = $stmtValidaDeps->fetchAll(\PDO::FETCH_COLUMN);

            $depsNaoEncontrados = array_diff($dependentesIds, $depsEncontrados);
            if (!empty($depsNaoEncontrados)) {
                $response->getBody()->write(json_encode([
                    'error' => 'Dependentes não encontrados: ' . implode(', ', $depsNaoEncontrados)
                ], JSON_UNESCAPED_UNICODE));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
        }

        // 4. Calcular valores e datas
        $valorTotal = (float) $pacote['valor_total'];
        $valorRateado = round($valorTotal / $totalBeneficiarios, 2);
        $planoId = (int) $pacote['plano_id'];
        // Só usar plano_ciclo_id se o ciclo realmente existe (ciclo_meses vem do LEFT JOIN)
        $planoCicloId = (!empty($pacote['plano_ciclo_id']) && !empty($pacote['ciclo_meses']))
            ? (int) $pacote['plano_ciclo_id']
            : null;

        $dataInicioObj = new \DateTime($dataInicio);
        $proximaDataVencimento = clone $dataInicioObj;

        if (!empty($pacote['ciclo_meses']) && (int) $pacote['ciclo_meses'] > 0) {
            $mesesCiclo = (int) $pacote['ciclo_meses'];
            $proximaDataVencimento->modify("+{$mesesCiclo} months");
            $duracaoDias = $mesesCiclo * 30;
        } else {
            $duracaoDias = max(1, (int) ($pacote['duracao_dias'] ?? 30));
            $proximaDataVencimento->modify("+{$duracaoDias} days");
        }

        $dataVencimento = $proximaDataVencimento->format('Y-m-d');

        // 5. Buscar IDs de status e motivo
        $stmtStatus = $db->prepare("SELECT id FROM status_matricula WHERE codigo = 'pendente'");
        $stmtStatus->execute();
        $statusId = (int) ($stmtStatus->fetchColumn() ?: 5);

        $stmtMotivo = $db->prepare("SELECT id FROM motivo_matricula WHERE codigo = 'nova'");
        $stmtMotivo->execute();
        $motivoId = (int) ($stmtMotivo->fetchColumn() ?: 1);

        // 6. Iniciar transação
        $db->beginTransaction();
        try {
            // 6.1 Criar contrato de pacote
            $stmtContrato = $db->prepare("
                INSERT INTO pacote_contratos
                (tenant_id, pacote_id, pagante_usuario_id, status, valor_total, data_inicio, data_fim, created_at, updated_at)
                VALUES (?, ?, ?, 'pendente', ?, ?, ?, NOW(), NOW())
            ");
            $stmtContrato->execute([
                $tenantId,
                $pacoteId,
                $usuarioId,
                $valorTotal,
                $dataInicio,
                $dataVencimento
            ]);
            $contratoId = (int) $db->lastInsertId();

            // 6.2 Criar matrícula + beneficiário + pagamento para cada beneficiário
            $matriculasCriadas = [];
            $pagamentosCriados = [];

            foreach ($beneficiariosIds as $benAlunoId) {
                // Verificar se já existe matrícula ativa na mesma modalidade
                $stmtAtiva = $db->prepare("
                    SELECT m.id FROM matriculas m
                    INNER JOIN planos p ON p.id = m.plano_id
                    INNER JOIN status_matricula sm ON sm.id = m.status_id
                    WHERE m.aluno_id = ? AND m.tenant_id = ? AND sm.codigo = 'ativa'
                      AND p.modalidade_id = ? AND m.proxima_data_vencimento >= CURDATE()
                    LIMIT 1
                ");
                $stmtAtiva->execute([$benAlunoId, $tenantId, $pacote['modalidade_id']]);
                if ($stmtAtiva->fetchColumn()) {
                    // Buscar nome do aluno para mensagem
                    $stmtNome = $db->prepare("SELECT nome FROM alunos WHERE id = ? LIMIT 1");
                    $stmtNome->execute([$benAlunoId]);
                    $nomeAluno = $stmtNome->fetchColumn() ?: "ID {$benAlunoId}";
                    $db->rollBack();
                    $response->getBody()->write(json_encode([
                        'error' => "Aluno {$nomeAluno} já possui matrícula ativa na modalidade deste pacote"
                    ], JSON_UNESCAPED_UNICODE));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }

                // Verificar se existe matrícula vencida para reutilizar
                $stmtVencida = $db->prepare("
                    SELECT m.id, m.plano_id, sm.codigo as status_codigo
                    FROM matriculas m
                    INNER JOIN planos p ON p.id = m.plano_id
                    INNER JOIN status_matricula sm ON sm.id = m.status_id
                    WHERE m.aluno_id = ? AND m.tenant_id = ? AND p.modalidade_id = ?
                      AND (sm.codigo = 'vencida' OR m.proxima_data_vencimento < CURDATE())
                    ORDER BY m.updated_at DESC, m.id DESC
                    LIMIT 1
                ");
                $stmtVencida->execute([$benAlunoId, $tenantId, $pacote['modalidade_id']]);
                $matVencida = $stmtVencida->fetch(\PDO::FETCH_ASSOC);

                if ($matVencida) {
                    // Reutilizar matrícula existente
                    $matriculaId = (int) $matVencida['id'];
                    $stmtUpdateMat = $db->prepare("
                        UPDATE matriculas
                        SET plano_id = ?,
                            plano_ciclo_id = ?,
                            pacote_contrato_id = ?,
                            data_matricula = ?,
                            data_inicio = ?,
                            data_vencimento = ?,
                            valor = ?,
                            valor_rateado = ?,
                            status_id = ?,
                            motivo_id = ?,
                            plano_anterior_id = ?,
                            observacoes = ?,
                            criado_por = ?,
                            dia_vencimento = ?,
                            proxima_data_vencimento = ?,
                            updated_at = NOW()
                        WHERE id = ? AND tenant_id = ?
                    ");
                    $stmtUpdateMat->execute([
                        $planoId,
                        $planoCicloId,
                        $contratoId,
                        date('Y-m-d'),
                        $dataInicio,
                        $dataVencimento,
                        $valorRateado,
                        $valorRateado,
                        $statusId,
                        $motivoId,
                        (int) $matVencida['plano_id'],
                        $observacoes,
                        $adminId,
                        $diaVencimento,
                        $dataVencimento,
                        $matriculaId,
                        $tenantId
                    ]);
                } else {
                    // Criar nova matrícula
                    $stmtInsertMat = $db->prepare("
                        INSERT INTO matriculas
                        (tenant_id, aluno_id, plano_id, plano_ciclo_id, pacote_contrato_id,
                         data_matricula, data_inicio, data_vencimento, valor, valor_rateado,
                         status_id, motivo_id, observacoes, criado_por,
                         dia_vencimento, proxima_data_vencimento, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmtInsertMat->execute([
                        $tenantId,
                        $benAlunoId,
                        $planoId,
                        $planoCicloId,
                        $contratoId,
                        date('Y-m-d'),
                        $dataInicio,
                        $dataVencimento,
                        $valorRateado,
                        $valorRateado,
                        $statusId,
                        $motivoId,
                        $observacoes,
                        $adminId,
                        $diaVencimento,
                        $dataVencimento
                    ]);
                    $matriculaId = (int) $db->lastInsertId();
                }

                // Criar beneficiário no pacote
                $stmtBen = $db->prepare("
                    INSERT INTO pacote_beneficiarios
                    (tenant_id, pacote_contrato_id, aluno_id, matricula_id, valor_rateado, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 'pendente', NOW(), NOW())
                ");
                $stmtBen->execute([$tenantId, $contratoId, $benAlunoId, $matriculaId, $valorRateado]);

                // Criar primeiro pagamento
                $stmtPag = $db->prepare("
                    INSERT INTO pagamentos_plano
                    (tenant_id, aluno_id, matricula_id, plano_id, valor, data_vencimento,
                     status_pagamento_id, pacote_contrato_id, observacoes, criado_por, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, NOW(), NOW())
                ");
                $stmtPag->execute([
                    $tenantId,
                    $benAlunoId,
                    $matriculaId,
                    $planoId,
                    $valorRateado,
                    $dataInicio,
                    $contratoId,
                    'Pagamento pacote - rateado',
                    $adminId
                ]);

                // Buscar nome do aluno
                $stmtNomeAluno = $db->prepare("SELECT a.nome FROM alunos a WHERE a.id = ? LIMIT 1");
                $stmtNomeAluno->execute([$benAlunoId]);
                $nomeAluno = $stmtNomeAluno->fetchColumn() ?: '';

                $matriculasCriadas[] = [
                    'matricula_id' => $matriculaId,
                    'aluno_id' => $benAlunoId,
                    'aluno_nome' => $nomeAluno,
                    'valor_rateado' => $valorRateado,
                    'is_pagante' => ($benAlunoId === $alunoId),
                    'reutilizada' => (bool) $matVencida
                ];
            }

            // Registrar no histórico
            $stmtHistorico = $db->prepare("
                INSERT INTO historico_planos
                (usuario_id, plano_novo_id, data_inicio, data_vencimento, valor_pago, motivo, observacoes, criado_por)
                VALUES (?, ?, ?, ?, ?, 'nova', ?, ?)
            ");
            $stmtHistorico->execute([
                $usuarioId,
                $planoId,
                $dataInicio,
                $dataVencimento,
                $valorTotal,
                "Pacote: {$pacote['nome']} ({$totalBeneficiarios} beneficiários)",
                $adminId
            ]);

            $db->commit();

            error_log("[MatriculaController] ✅ Pacote #{$pacoteId} contratado: contrato #{$contratoId}, {$totalBeneficiarios} matrícula(s) criada(s)");

            $response->getBody()->write(json_encode([
                'message' => "Pacote contratado com sucesso - {$totalBeneficiarios} matrícula(s) criada(s)",
                'pacote_contrato_id' => $contratoId,
                'pacote' => [
                    'id' => $pacoteId,
                    'nome' => $pacote['nome'],
                    'valor_total' => $valorTotal,
                    'valor_rateado' => $valorRateado,
                    'plano_nome' => $pacote['plano_nome'],
                    'qtd_beneficiarios' => $totalBeneficiarios
                ],
                'matriculas' => $matriculasCriadas,
                'data_inicio' => $dataInicio,
                'data_vencimento' => $dataVencimento,
                'info' => "Pacote ativo até " . $proximaDataVencimento->format('d/m/Y')
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);

        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("[MatriculaController::criarMatriculaPacote] ❌ Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao criar matrícula de pacote: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Listar matrículas
     */
    #[OA\Get(
        path: "/admin/matriculas",
        summary: "Listar matrículas",
        description: "Retorna lista de matrículas do tenant. Suporta paginação, filtro por aluno e por status.",
        tags: ["Matrículas"],
        parameters: [
            new OA\Parameter(
                name: "aluno_id",
                description: "Filtrar por ID do aluno",
                in: "query",
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "status",
                description: "Filtrar por status (ativa, pendente, vencida, cancelada, finalizada)",
                in: "query",
                schema: new OA\Schema(type: "string", enum: ["ativa", "pendente", "vencida", "cancelada", "finalizada"])
            ),
            new OA\Parameter(
                name: "incluir_inativos",
                description: "Incluir alunos inativos (true/false)",
                in: "query",
                schema: new OA\Schema(type: "string", enum: ["true", "false"], default: "false")
            ),
            new OA\Parameter(
                name: "pagina",
                description: "Número da página (ativa paginação)",
                in: "query",
                schema: new OA\Schema(type: "integer", default: 1)
            ),
            new OA\Parameter(
                name: "por_pagina",
                description: "Registros por página",
                in: "query",
                schema: new OA\Schema(type: "integer", default: 50)
            )
        ],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de matrículas",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "matriculas", type: "array", items: new OA\Items(
                            properties: [
                                new OA\Property(property: "id", type: "integer"),
                                new OA\Property(property: "aluno_id", type: "integer"),
                                new OA\Property(property: "usuario_nome", type: "string"),
                                new OA\Property(property: "usuario_email", type: "string"),
                                new OA\Property(property: "plano_id", type: "integer"),
                                new OA\Property(property: "plano_nome", type: "string"),
                                new OA\Property(property: "valor", type: "number"),
                                new OA\Property(property: "data_inicio", type: "string", format: "date"),
                                new OA\Property(property: "proxima_data_vencimento", type: "string", format: "date"),
                                new OA\Property(property: "status_codigo", type: "string"),
                                new OA\Property(property: "status_nome", type: "string"),
                                new OA\Property(property: "modalidade_nome", type: "string", nullable: true)
                            ]
                        )),
                        new OA\Property(property: "total", type: "integer"),
                        new OA\Property(property: "pagina", type: "integer", description: "Presente apenas com paginação"),
                        new OA\Property(property: "por_pagina", type: "integer", description: "Presente apenas com paginação"),
                        new OA\Property(property: "total_paginas", type: "integer", description: "Presente apenas com paginação")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
    public function listar(Request $request, Response $response): Response
    {
        $tenantId = $request->getAttribute('tenantId', 1);
        $params = $request->getQueryParams();
        $db = require __DIR__ . '/../../config/database.php';
        $incluirInativos = isset($params['incluir_inativos']) && $params['incluir_inativos'] === 'true';
        $pagina = isset($params['pagina']) ? max(1, (int) $params['pagina']) : null;
        $porPagina = isset($params['por_pagina']) ? max(1, (int) $params['por_pagina']) : 50;
        $usarPaginacao = $pagina !== null || isset($params['por_pagina']);
        
        // Verificar e atualizar status de matrículas pendentes vencidas
        $this->atualizarStatusMatriculasPendentes($db, $tenantId);

        $baseSql = "
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
            $baseSql .= " AND a.ativo = 1";
        }
        
        if (isset($params['aluno_id'])) {
            $baseSql .= " AND m.aluno_id = ?";
            $executeParams[] = $params['aluno_id'];
        }
        
        if (isset($params['status'])) {
            $baseSql .= " AND m.status_id = (SELECT id FROM status_matricula WHERE codigo = ? LIMIT 1)";
            $executeParams[] = $params['status'];
        }

        $sql = "
            SELECT 
                m.id,
                m.aluno_id,
                a.usuario_id,
                a.nome as usuario_nome,
                u.email as usuario_email,
                m.plano_id,
                p.nome as plano_nome,
                m.valor,
                m.data_inicio,
                m.proxima_data_vencimento,
                m.status_id,
                modalidade.nome as modalidade_nome,
                modalidade.icone as modalidade_icone,
                modalidade.cor as modalidade_cor,
                sm.codigo as status_codigo,
                sm.nome as status_nome
            {$baseSql}
            ORDER BY m.created_at DESC
        ";

        if ($usarPaginacao) {
            $offset = ($pagina ?? 1) - 1;
            $offset = $offset < 0 ? 0 : $offset;
            $sql .= " LIMIT ? OFFSET ?";
            $executeParams[] = $porPagina;
            $executeParams[] = $offset * $porPagina;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($executeParams);
        $matriculas = $stmt->fetchAll();

        
        if ($usarPaginacao) {
            $countSql = "SELECT COUNT(*) as total {$baseSql}";
            $stmtCount = $db->prepare($countSql);
            $stmtCount->execute(array_slice($executeParams, 0, count($executeParams) - 2));
            $total = (int) ($stmtCount->fetchColumn() ?? 0);
            $paginaAtual = $pagina ?? 1;
            $totalPaginas = $porPagina > 0 ? (int) ceil($total / $porPagina) : 0;

            $response->getBody()->write(json_encode([
                'matriculas' => $matriculas,
                'total' => $total,
                'pagina' => $paginaAtual,
                'por_pagina' => $porPagina,
                'total_paginas' => $totalPaginas
            ]));
        } else {
            $response->getBody()->write(json_encode([
                'matriculas' => $matriculas,
                'total' => count($matriculas)
            ]));
        }
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Buscar matrícula por ID
     */
    #[OA\Get(
        path: "/admin/matriculas/{id}",
        summary: "Buscar matrícula por ID",
        description: "Retorna dados completos de uma matrícula, incluindo informações do aluno, plano, modalidade e lista de pagamentos.",
        tags: ["Matrículas"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID da matrícula",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Dados da matrícula",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "matricula", type: "object", description: "Dados completos da matrícula com plano, aluno e modalidade"),
                        new OA\Property(property: "pagamentos", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "total", type: "number", description: "Soma dos valores dos pagamentos")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Matrícula não encontrada"),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
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
                admin_criou.nome as criado_por_nome,
                pc.id as ciclo_id,
                pc.meses as ciclo_meses,
                pc.valor as ciclo_valor,
                pc.valor_mensal_equivalente as ciclo_valor_mensal_equivalente,
                pc.desconto_percentual as ciclo_desconto_percentual,
                pc.permite_recorrencia as ciclo_permite_recorrencia,
                pc.ativo as ciclo_ativo,
                af.id as ciclo_frequencia_id,
                af.codigo as ciclo_frequencia_codigo,
                af.nome as ciclo_frequencia_nome,
                pct.id as contrato_id,
                pct.status as contrato_status,
                pct.data_inicio as contrato_data_inicio,
                pct.data_fim as contrato_data_fim,
                pct.valor_total as contrato_valor_total,
                pct.pagante_usuario_id as contrato_pagante_usuario_id,
                paq.id as pacote_id,
                paq.nome as pacote_nome,
                paq.qtd_beneficiarios as pacote_qtd_beneficiarios,
                paq.valor_total as pacote_valor_total
            FROM matriculas m
            INNER JOIN alunos a ON m.aluno_id = a.id
            INNER JOIN usuarios u ON a.usuario_id = u.id
            INNER JOIN planos p ON m.plano_id = p.id
            INNER JOIN status_matricula sm ON sm.id = m.status_id
            LEFT JOIN modalidades modalidade ON p.modalidade_id = modalidade.id
            LEFT JOIN usuarios admin_criou ON m.criado_por = admin_criou.id
            LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
            LEFT JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
            LEFT JOIN pacote_contratos pct ON pct.id = m.pacote_contrato_id
            LEFT JOIN pacotes paq ON paq.id = pct.pacote_id
            WHERE m.id = ? AND m.tenant_id = ?
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$matriculaId, $tenantId]);
        $matricula = $stmt->fetch();
        
        if (!$matricula) {
            $response->getBody()->write(json_encode(['error' => 'Matrícula não encontrada']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Montar objeto plano_ciclo se existir
        if ($matricula['plano_ciclo_id']) {
            $matricula['plano_ciclo'] = [
                'id' => (int) $matricula['ciclo_id'],
                'meses' => $matricula['ciclo_meses'] !== null ? (int) $matricula['ciclo_meses'] : null,
                'valor' => $matricula['ciclo_valor'],
                'valor_mensal_equivalente' => $matricula['ciclo_valor_mensal_equivalente'],
                'desconto_percentual' => $matricula['ciclo_desconto_percentual'],
                'permite_recorrencia' => $matricula['ciclo_permite_recorrencia'] !== null ? (bool) $matricula['ciclo_permite_recorrencia'] : null,
                'ativo' => $matricula['ciclo_ativo'] !== null ? (bool) $matricula['ciclo_ativo'] : null,
                'frequencia' => $matricula['ciclo_frequencia_id'] ? [
                    'id' => (int) $matricula['ciclo_frequencia_id'],
                    'codigo' => $matricula['ciclo_frequencia_codigo'],
                    'nome' => $matricula['ciclo_frequencia_nome'],
                ] : null,
            ];
        } else {
            $matricula['plano_ciclo'] = null;
        }

        // Montar objeto pacote se existir
        if ($matricula['pacote_contrato_id']) {
            // Buscar beneficiários do contrato
            $stmtBeneficiarios = $db->prepare("
                SELECT pb.aluno_id, pb.valor_rateado, pb.status,
                       al.nome as aluno_nome,
                       CASE WHEN al.usuario_id = ? THEN 1 ELSE 0 END as is_pagante
                FROM pacote_beneficiarios pb
                INNER JOIN alunos al ON al.id = pb.aluno_id
                WHERE pb.pacote_contrato_id = ? AND pb.tenant_id = ?
                ORDER BY is_pagante DESC, al.nome ASC
            ");
            $stmtBeneficiarios->execute([
                $matricula['contrato_pagante_usuario_id'] ?? 0,
                $matricula['pacote_contrato_id'],
                $tenantId
            ]);
            $beneficiarios = $stmtBeneficiarios->fetchAll(\PDO::FETCH_ASSOC);

            // Buscar nome do pagante
            $nomePagante = null;
            if (!empty($matricula['contrato_pagante_usuario_id'])) {
                $stmtPagante = $db->prepare("SELECT nome FROM usuarios WHERE id = ? LIMIT 1");
                $stmtPagante->execute([$matricula['contrato_pagante_usuario_id']]);
                $nomePagante = $stmtPagante->fetchColumn() ?: null;
            }

            $matricula['pacote'] = [
                'pacote_id' => $matricula['pacote_id'] ? (int) $matricula['pacote_id'] : null,
                'pacote_nome' => $matricula['pacote_nome'],
                'pacote_valor_total' => $matricula['pacote_valor_total'] ? (float) $matricula['pacote_valor_total'] : null,
                'pacote_qtd_beneficiarios' => $matricula['pacote_qtd_beneficiarios'] ? (int) $matricula['pacote_qtd_beneficiarios'] : null,
                'contrato_id' => (int) $matricula['contrato_id'],
                'contrato_status' => $matricula['contrato_status'],
                'contrato_data_inicio' => $matricula['contrato_data_inicio'],
                'contrato_data_fim' => $matricula['contrato_data_fim'],
                'contrato_valor_total' => $matricula['contrato_valor_total'] ? (float) $matricula['contrato_valor_total'] : null,
                'pagante_usuario_id' => $matricula['contrato_pagante_usuario_id'] ? (int) $matricula['contrato_pagante_usuario_id'] : null,
                'pagante_nome' => $nomePagante,
                'beneficiarios' => $beneficiarios,
            ];
        } else {
            $matricula['pacote'] = null;
        }

        // Limpar campos auxiliares do ciclo e pacote do objeto principal
        unset(
            $matricula['ciclo_id'],
            $matricula['ciclo_meses'],
            $matricula['ciclo_valor'],
            $matricula['ciclo_valor_mensal_equivalente'],
            $matricula['ciclo_desconto_percentual'],
            $matricula['ciclo_permite_recorrencia'],
            $matricula['ciclo_ativo'],
            $matricula['ciclo_frequencia_id'],
            $matricula['ciclo_frequencia_codigo'],
            $matricula['ciclo_frequencia_nome'],
            $matricula['contrato_id'],
            $matricula['contrato_status'],
            $matricula['contrato_data_inicio'],
            $matricula['contrato_data_fim'],
            $matricula['contrato_valor_total'],
            $matricula['contrato_pagante_usuario_id'],
            $matricula['pacote_id'],
            $matricula['pacote_nome'],
            $matricula['pacote_qtd_beneficiarios'],
            $matricula['pacote_valor_total']
        );

        // Buscar pagamentos da matrícula
        $stmtPagamentos = $db->prepare("
            SELECT 
                pp.id,
                CAST(pp.valor AS DECIMAL(10,2)) as valor,
                pp.data_vencimento,
                pp.data_pagamento,
                pp.status_pagamento_id,
                (SELECT nome FROM status_pagamento WHERE id = pp.status_pagamento_id) as status,
                pp.forma_pagamento_id,
                fp.nome as forma_pagamento_nome,
                pp.observacoes
            FROM pagamentos_plano pp
            LEFT JOIN formas_pagamento fp ON fp.id = pp.forma_pagamento_id
            WHERE pp.matricula_id = ?
            ORDER BY pp.data_vencimento ASC
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
    #[OA\Get(
        path: "/admin/matriculas/{id}/pagamentos",
        summary: "Buscar pagamentos da matrícula",
        description: "Retorna todos os pagamentos vinculados a uma matrícula, com detalhes de status, forma de pagamento e responsáveis.",
        tags: ["Matrículas"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID da matrícula",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de pagamentos",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "pagamentos", type: "array", items: new OA\Items(
                            properties: [
                                new OA\Property(property: "id", type: "integer"),
                                new OA\Property(property: "valor", type: "number"),
                                new OA\Property(property: "data_vencimento", type: "string", format: "date"),
                                new OA\Property(property: "data_pagamento", type: "string", format: "date", nullable: true),
                                new OA\Property(property: "status_pagamento_nome", type: "string"),
                                new OA\Property(property: "forma_pagamento_nome", type: "string", nullable: true),
                                new OA\Property(property: "aluno_nome", type: "string"),
                                new OA\Property(property: "plano_nome", type: "string")
                            ]
                        )),
                        new OA\Property(property: "total", type: "integer")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Matrícula não encontrada"),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
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
    #[OA\Post(
        path: "/admin/matriculas/{id}/cancelar",
        summary: "Cancelar matrícula",
        description: "Cancela uma matrícula ativa ou pendente. Remove o plano do usuário e registra o motivo do cancelamento.",
        tags: ["Matrículas"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID da matrícula",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "motivo_cancelamento", type: "string", nullable: true, description: "Motivo do cancelamento", example: "Aluno solicitou cancelamento")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Matrícula cancelada",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Matrícula cancelada com sucesso"),
                        new OA\Property(property: "matricula", type: "object"),
                        new OA\Property(property: "pagamentos", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "total", type: "number")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Matrícula já está cancelada"),
            new OA\Response(response: 404, description: "Matrícula não encontrada"),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
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
        
        if ($alunoRow && $this->usuariosTemColunasPlano($db)) {
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
     * Prévia completa de exclusão da matrícula
     */
    #[OA\Get(
        path: "/admin/matriculas/{id}/delete-preview",
        summary: "Prévia de exclusão da matrícula",
        description: "Retorna um resumo completo de todos os dados que serão afetados pela exclusão: matrícula, pagamentos, assinaturas e registros no MercadoPago. Use antes de confirmar a exclusão.",
        tags: ["Matrículas"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID da matrícula",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Prévia da exclusão com resumo de impacto",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "resumo", type: "object", properties: [
                            new OA\Property(property: "matricula_id", type: "integer"),
                            new OA\Property(property: "aluno_id", type: "integer"),
                            new OA\Property(property: "status", type: "string"),
                            new OA\Property(property: "total_pagamentos_plano", type: "integer"),
                            new OA\Property(property: "total_pagamentos_provedor", type: "integer"),
                            new OA\Property(property: "total_assinaturas", type: "integer"),
                            new OA\Property(property: "total_assinaturas_mercadopago", type: "integer"),
                            new OA\Property(property: "valor_total_pagamentos_plano", type: "number"),
                            new OA\Property(property: "valor_total_pagamentos_provedor", type: "number"),
                            new OA\Property(property: "impacto", type: "object")
                        ]),
                        new OA\Property(property: "matricula", type: "object"),
                        new OA\Property(property: "aluno", type: "object"),
                        new OA\Property(property: "plano", type: "object"),
                        new OA\Property(property: "pagamentos_plano", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "assinaturas", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "assinaturas_mercadopago", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "pagamentos_mercadopago", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Matrícula não encontrada"),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
    public function deletePreview(Request $request, Response $response, array $args): Response
    {
        $matriculaId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);
        $db = require __DIR__ . '/../../config/database.php';

        // Buscar dados completos da matrícula
        $stmt = $db->prepare("
            SELECT 
                m.id,
                m.tenant_id,
                m.aluno_id,
                a.usuario_id,
                a.nome as aluno_nome,
                u.email as aluno_email,
                u.telefone as aluno_telefone,
                m.plano_id,
                m.plano_ciclo_id,
                m.tipo_cobranca,
                m.data_matricula,
                m.data_inicio,
                m.data_vencimento,
                m.dia_vencimento,
                m.periodo_teste,
                m.data_inicio_cobranca,
                m.proxima_data_vencimento,
                m.valor,
                m.status_id,
                sm.codigo as status_codigo,
                sm.nome as status_nome,
                m.motivo_id,
                mm.codigo as motivo_codigo,
                mm.nome as motivo_nome,
                m.matricula_anterior_id,
                m.plano_anterior_id,
                m.observacoes,
                m.created_at,
                m.updated_at,
                p.nome as plano_nome,
                p.valor as plano_valor,
                p.duracao_dias,
                p.checkins_semanais,
                modalidade.id as modalidade_id,
                modalidade.nome as modalidade_nome,
                modalidade.icone as modalidade_icone,
                modalidade.cor as modalidade_cor
            FROM matriculas m
            INNER JOIN alunos a ON m.aluno_id = a.id
            INNER JOIN usuarios u ON a.usuario_id = u.id
            INNER JOIN planos p ON m.plano_id = p.id
            INNER JOIN status_matricula sm ON sm.id = m.status_id
            LEFT JOIN motivo_matricula mm ON mm.id = m.motivo_id
            LEFT JOIN modalidades modalidade ON p.modalidade_id = modalidade.id
            WHERE m.id = ? AND m.tenant_id = ?
        ");
        $stmt->execute([$matriculaId, $tenantId]);
        $matricula = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$matricula) {
            $response->getBody()->write(json_encode([
                'error' => 'Matrícula não encontrada'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Pagamentos do plano
        $stmtPagamentos = $db->prepare("
            SELECT 
                pp.id,
                CAST(pp.valor AS DECIMAL(10,2)) as valor,
                pp.data_vencimento,
                pp.data_pagamento,
                pp.status_pagamento_id,
                sp.nome as status,
                pp.forma_pagamento_id,
                fp.nome as forma_pagamento_nome,
                pp.observacoes,
                pp.created_at,
                pp.updated_at
            FROM pagamentos_plano pp
            LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
            LEFT JOIN formas_pagamento fp ON fp.id = pp.forma_pagamento_id
            WHERE pp.matricula_id = ? AND pp.tenant_id = ?
            ORDER BY pp.data_vencimento ASC
        ");
        $stmtPagamentos->execute([$matriculaId, $tenantId]);
        $pagamentosPlano = $stmtPagamentos->fetchAll(\PDO::FETCH_ASSOC) ?? [];

        // Assinaturas (tabela genérica)
        $stmtAssinaturas = $db->prepare("
            SELECT 
                a.id,
                a.tenant_id,
                a.aluno_id,
                a.matricula_id,
                a.plano_id,
                g.codigo as gateway_codigo,
                g.nome as gateway_nome,
                a.gateway_assinatura_id,
                a.gateway_cliente_id,
                s.codigo as status_codigo,
                s.nome as status_nome,
                s.cor as status_cor,
                a.status_gateway,
                a.valor,
                a.moeda,
                f.codigo as frequencia_codigo,
                f.nome as frequencia_nome,
                f.dias as frequencia_dias,
                a.data_inicio,
                a.data_fim,
                a.proxima_cobranca,
                a.ultima_cobranca,
                mp.codigo as metodo_pagamento_codigo,
                mp.nome as metodo_pagamento_nome,
                a.cartao_ultimos_digitos,
                a.cartao_bandeira,
                a.tentativas_cobranca,
                a.motivo_cancelamento,
                ct.codigo as cancelado_por_codigo,
                ct.nome as cancelado_por_nome,
                a.criado_em,
                a.atualizado_em
            FROM assinaturas a
            LEFT JOIN assinatura_gateways g ON a.gateway_id = g.id
            LEFT JOIN assinatura_status s ON a.status_id = s.id
            LEFT JOIN assinatura_frequencias f ON a.frequencia_id = f.id
            LEFT JOIN metodos_pagamento mp ON a.metodo_pagamento_id = mp.id
            LEFT JOIN assinatura_cancelamento_tipos ct ON a.cancelado_por_id = ct.id
            WHERE a.matricula_id = ? AND a.tenant_id = ?
            ORDER BY a.id DESC
        ");
        $stmtAssinaturas->execute([$matriculaId, $tenantId]);
        $assinaturas = $stmtAssinaturas->fetchAll(\PDO::FETCH_ASSOC) ?? [];

        // Assinaturas MercadoPago (legado)
        $stmtAssinaturasMp = $db->prepare("
            SELECT 
                id,
                tenant_id,
                matricula_id,
                aluno_id,
                plano_ciclo_id,
                mp_preapproval_id,
                mp_plan_id,
                mp_payer_id,
                status,
                valor,
                moeda,
                dia_cobranca,
                data_inicio,
                data_fim,
                proxima_cobranca,
                ultima_cobranca,
                tentativas_falha,
                motivo_cancelamento,
                cancelado_por,
                data_cancelamento,
                created_at,
                updated_at
            FROM assinaturas_mercadopago
            WHERE matricula_id = ? AND tenant_id = ?
            ORDER BY id DESC
        ");
        $stmtAssinaturasMp->execute([$matriculaId, $tenantId]);
        $assinaturasMp = $stmtAssinaturasMp->fetchAll(\PDO::FETCH_ASSOC) ?? [];

        // Pagamentos MercadoPago (provedor)
        $stmtPagamentosMp = $db->prepare("
            SELECT 
                id,
                tenant_id,
                matricula_id,
                aluno_id,
                usuario_id,
                payment_id,
                external_reference,
                preference_id,
                status,
                status_detail,
                transaction_amount,
                payment_method_id,
                payment_type_id,
                installments,
                date_approved,
                date_created,
                payer_email,
                payer_identification_type,
                payer_identification_number,
                created_at,
                updated_at
            FROM pagamentos_mercadopago
            WHERE matricula_id = ? AND tenant_id = ?
            ORDER BY date_created DESC
        ");
        $stmtPagamentosMp->execute([$matriculaId, $tenantId]);
        $pagamentosMp = $stmtPagamentosMp->fetchAll(\PDO::FETCH_ASSOC) ?? [];

        // Totais e resumo
        $totalPagamentosPlano = count($pagamentosPlano);
        $totalPagamentosMp = count($pagamentosMp);
        $totalAssinaturas = count($assinaturas);
        $totalAssinaturasMp = count($assinaturasMp);
        $valorTotalPlano = (float) array_sum(array_column($pagamentosPlano, 'valor'));
        $valorTotalMp = (float) array_sum(array_column($pagamentosMp, 'transaction_amount'));

        $resumo = [
            'matricula_id' => (int) $matricula['id'],
            'aluno_id' => (int) $matricula['aluno_id'],
            'usuario_id' => (int) $matricula['usuario_id'],
            'status' => $matricula['status_codigo'],
            'total_pagamentos_plano' => $totalPagamentosPlano,
            'total_pagamentos_provedor' => $totalPagamentosMp,
            'total_assinaturas' => $totalAssinaturas,
            'total_assinaturas_mercadopago' => $totalAssinaturasMp,
            'valor_total_pagamentos_plano' => $valorTotalPlano,
            'valor_total_pagamentos_provedor' => $valorTotalMp,
            'impacto' => [
                'pagamentos_plano' => ['acao' => 'deletar', 'total' => $totalPagamentosPlano],
                'pagamentos_mercadopago' => ['acao' => 'deletar', 'total' => $totalPagamentosMp],
                'assinaturas' => ['acao' => 'desvincular', 'total' => $totalAssinaturas],
                'assinaturas_mercadopago' => ['acao' => 'deletar', 'total' => $totalAssinaturasMp]
            ]
        ];

        $response->getBody()->write(json_encode([
            'resumo' => $resumo,
            'matricula' => [
                'id' => (int) $matricula['id'],
                'tenant_id' => (int) $matricula['tenant_id'],
                'aluno_id' => (int) $matricula['aluno_id'],
                'usuario_id' => (int) $matricula['usuario_id'],
                'plano_id' => (int) $matricula['plano_id'],
                'plano_ciclo_id' => $matricula['plano_ciclo_id'],
                'tipo_cobranca' => $matricula['tipo_cobranca'],
                'data_matricula' => $matricula['data_matricula'],
                'data_inicio' => $matricula['data_inicio'],
                'data_vencimento' => $matricula['data_vencimento'],
                'dia_vencimento' => $matricula['dia_vencimento'],
                'periodo_teste' => (int) $matricula['periodo_teste'],
                'data_inicio_cobranca' => $matricula['data_inicio_cobranca'],
                'proxima_data_vencimento' => $matricula['proxima_data_vencimento'],
                'valor' => $matricula['valor'],
                'status_id' => (int) $matricula['status_id'],
                'status_codigo' => $matricula['status_codigo'],
                'status_nome' => $matricula['status_nome'],
                'motivo_id' => $matricula['motivo_id'],
                'motivo_codigo' => $matricula['motivo_codigo'],
                'motivo_nome' => $matricula['motivo_nome'],
                'matricula_anterior_id' => $matricula['matricula_anterior_id'],
                'plano_anterior_id' => $matricula['plano_anterior_id'],
                'observacoes' => $matricula['observacoes'],
                'created_at' => $matricula['created_at'],
                'updated_at' => $matricula['updated_at']
            ],
            'aluno' => [
                'id' => (int) $matricula['aluno_id'],
                'usuario_id' => (int) $matricula['usuario_id'],
                'nome' => $matricula['aluno_nome'],
                'email' => $matricula['aluno_email'],
                'telefone' => $matricula['aluno_telefone']
            ],
            'plano' => [
                'id' => (int) $matricula['plano_id'],
                'nome' => $matricula['plano_nome'],
                'valor' => $matricula['plano_valor'],
                'duracao_dias' => (int) $matricula['duracao_dias'],
                'checkins_semanais' => (int) $matricula['checkins_semanais'],
                'modalidade' => [
                    'id' => $matricula['modalidade_id'],
                    'nome' => $matricula['modalidade_nome'],
                    'icone' => $matricula['modalidade_icone'],
                    'cor' => $matricula['modalidade_cor']
                ]
            ],
            'pagamentos_plano' => $pagamentosPlano,
            'assinaturas' => $assinaturas,
            'assinaturas_mercadopago' => $assinaturasMp,
            'pagamentos_mercadopago' => $pagamentosMp
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Deletar matrícula (hard delete)
     */
    #[OA\Delete(
        path: "/admin/matriculas/{id}",
        summary: "Deletar matrícula",
        description: "Exclui permanentemente uma matrícula e todos os registros vinculados: pagamentos do plano, pagamentos MercadoPago, assinaturas MercadoPago. Desvincula assinaturas genéricas. Use delete-preview antes para ver o impacto.",
        tags: ["Matrículas"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID da matrícula",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Matrícula deletada",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Matrícula deletada com sucesso"),
                        new OA\Property(property: "matricula_id", type: "integer")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Matrícula não encontrada"),
            new OA\Response(response: 500, description: "Erro ao deletar matrícula"),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
    public function delete(Request $request, Response $response, array $args): Response
    {
        $matriculaId = (int) $args['id'];
        $tenantId = $request->getAttribute('tenantId', 1);
        $db = require __DIR__ . '/../../config/database.php';

        $stmt = $db->prepare("
            SELECT m.id, m.aluno_id, m.pacote_contrato_id
            FROM matriculas m
            WHERE m.id = ? AND m.tenant_id = ?
        ");
        $stmt->execute([$matriculaId, $tenantId]);
        $matricula = $stmt->fetch();

        if (!$matricula) {
            $response->getBody()->write(json_encode(['error' => 'Matrícula não encontrada']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Impedir exclusão direta de matrícula que faz parte de um pacote
        if (!empty($matricula['pacote_contrato_id'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Não é possível excluir esta matrícula diretamente pois ela faz parte de um pacote',
                'message' => 'Para excluir, utilize a exclusão do contrato do pacote, que remove todas as matrículas vinculadas',
                'pacote_contrato_id' => (int) $matricula['pacote_contrato_id']
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        try {
            $db->beginTransaction();

            // Desvincular assinaturas (quando houver)
            $stmtAssinaturas = $db->prepare("
                UPDATE assinaturas 
                SET matricula_id = NULL 
                WHERE matricula_id = ? AND tenant_id = ?
            ");
            $stmtAssinaturas->execute([$matriculaId, $tenantId]);

            // Remover assinaturas MercadoPago vinculadas
            $stmtAssinaturasMp = $db->prepare("
                DELETE FROM assinaturas_mercadopago 
                WHERE matricula_id = ? AND tenant_id = ?
            ");
            $stmtAssinaturasMp->execute([$matriculaId, $tenantId]);

            // Remover pagamentos MercadoPago vinculados
            $stmtPagamentosMp = $db->prepare("
                DELETE FROM pagamentos_mercadopago 
                WHERE matricula_id = ? AND tenant_id = ?
            ");
            $stmtPagamentosMp->execute([$matriculaId, $tenantId]);

            // Remover pagamentos do plano vinculados
            $stmtPagamentosPlano = $db->prepare("
                DELETE FROM pagamentos_plano 
                WHERE matricula_id = ? AND tenant_id = ?
            ");
            $stmtPagamentosPlano->execute([$matriculaId, $tenantId]);

            // Deletar matrícula
            $stmtDelete = $db->prepare("
                DELETE FROM matriculas 
                WHERE id = ? AND tenant_id = ?
            ");
            $stmtDelete->execute([$matriculaId, $tenantId]);

            // Remover plano do usuário (se existir)
            $stmtAluno = $db->prepare("SELECT usuario_id FROM alunos WHERE id = ?");
            $stmtAluno->execute([$matricula['aluno_id']]);
            $alunoRow = $stmtAluno->fetch();
            if ($alunoRow && $this->usuariosTemColunasPlano($db)) {
                $stmtUpdateUsuario = $db->prepare("
                    UPDATE usuarios 
                    SET plano_id = NULL, data_vencimento_plano = NULL
                    WHERE id = ?
                ");
                $stmtUpdateUsuario->execute([$alunoRow['usuario_id']]);
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao deletar matrícula',
                'details' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'message' => 'Matrícula deletada com sucesso',
            'matricula_id' => $matriculaId
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Dar baixa em pagamento de plano (marcar como pago)
     */
    #[OA\Post(
        path: "/admin/matriculas/contas/{id}/baixa",
        summary: "Dar baixa em pagamento",
        description: "Marca um pagamento como pago (status 2). Ativa a matrícula se estava pendente. Cria automaticamente a próxima parcela com base na duração do plano.",
        tags: ["Matrículas"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID do pagamento (pagamentos_plano)",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "data_pagamento", type: "string", format: "date", nullable: true, description: "Data do pagamento (padrão: hoje)"),
                    new OA\Property(property: "forma_pagamento_id", type: "integer", nullable: true, description: "ID da forma de pagamento"),
                    new OA\Property(property: "observacoes", type: "string", nullable: true, description: "Observações sobre o pagamento")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Baixa realizada",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Baixa realizada com sucesso"),
                        new OA\Property(property: "pagamento", type: "object", description: "Pagamento atualizado"),
                        new OA\Property(property: "proxima_parcela", type: "object", nullable: true, description: "Próxima parcela gerada automaticamente", properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "data_vencimento", type: "string", format: "date"),
                            new OA\Property(property: "valor", type: "number"),
                            new OA\Property(property: "status", type: "string", example: "Aguardando")
                        ])
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Pagamento já está pago"),
            new OA\Response(response: 404, description: "Pagamento não encontrado"),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
    public function darBaixaConta(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId', 1);
        $adminId = $request->getAttribute('userId', null);
        $data = $request->getParsedBody();
        $db = require __DIR__ . '/../../config/database.php';
        
        $pagamentoId = (int) ($args['id'] ?? 0);
        
        // Buscar pagamento com informações do plano e ciclo
        $stmt = $db->prepare("
            SELECT pp.*, m.plano_id, m.plano_ciclo_id, p.duracao_dias,
                   pc.meses as ciclo_meses, af.meses as frequencia_meses
            FROM pagamentos_plano pp
            INNER JOIN matriculas m ON pp.matricula_id = m.id
            INNER JOIN planos p ON pp.plano_id = p.id
            LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
            LEFT JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
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
            $dataVencimentoAtual = new \DateTime($pagamento['data_vencimento']);
            
            // Se matrícula tem ciclo, usar meses do ciclo; senão, usar duracao_dias do plano
            $mesesCiclo = $pagamento['ciclo_meses'] ?? $pagamento['frequencia_meses'] ?? null;
            if ($mesesCiclo) {
                $proximoVencimento = clone $dataVencimentoAtual;
                $proximoVencimento->modify("+{$mesesCiclo} months");
            } else {
                $duracaoDias = (int) $pagamento['duracao_dias'];
                $proximoVencimento = clone $dataVencimentoAtual;
                $proximoVencimento->add(new \DateInterval("P{$duracaoDias}D"));
            }
            
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
     * Dar baixa em todas as matrículas de um pacote/contrato de uma vez
     */
    #[OA\Post(
        path: "/admin/matriculas/pacote-contrato/{contratoId}/baixa",
        summary: "Dar baixa em pacote (todas as matrículas)",
        description: "Marca como pago todos os pagamentos pendentes do pacote, ativa todas as matrículas e gera as próximas parcelas automaticamente.",
        tags: ["Matrículas"],
        parameters: [
            new OA\Parameter(name: "contratoId", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "data_pagamento", type: "string", format: "date", example: "2026-02-26"),
                    new OA\Property(property: "forma_pagamento_id", type: "integer", example: 2),
                    new OA\Property(property: "observacoes", type: "string", example: "Pago via PIX")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Baixa realizada em todas as matrículas do pacote"),
            new OA\Response(response: 404, description: "Contrato não encontrado"),
            new OA\Response(response: 400, description: "Nenhum pagamento pendente")
        ]
    )]
    public function darBaixaPacote(Request $request, Response $response, array $args): Response
    {
        $tenantId = $request->getAttribute('tenantId', 1);
        $adminId = $request->getAttribute('userId', null);
        $data = $request->getParsedBody() ?? [];
        $db = require __DIR__ . '/../../config/database.php';

        $contratoId = (int) ($args['contratoId'] ?? 0);

        // 1. Buscar contrato
        $stmtContrato = $db->prepare("
            SELECT pc.*, p.nome as pacote_nome, p.plano_id, p.plano_ciclo_id
            FROM pacote_contratos pc
            INNER JOIN pacotes p ON p.id = pc.pacote_id
            WHERE pc.id = ? AND pc.tenant_id = ?
            LIMIT 1
        ");
        $stmtContrato->execute([$contratoId, $tenantId]);
        $contrato = $stmtContrato->fetch(\PDO::FETCH_ASSOC);

        if (!$contrato) {
            $response->getBody()->write(json_encode(['error' => 'Contrato de pacote não encontrado'], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // 2. Buscar pagamentos pendentes do contrato
        $stmtPagamentos = $db->prepare("
            SELECT pp.*, m.plano_ciclo_id, p.duracao_dias,
                   pc2.meses as ciclo_meses, af.meses as frequencia_meses,
                   a.nome as aluno_nome
            FROM pagamentos_plano pp
            INNER JOIN matriculas m ON pp.matricula_id = m.id
            INNER JOIN planos p ON pp.plano_id = p.id
            INNER JOIN alunos a ON pp.aluno_id = a.id
            LEFT JOIN plano_ciclos pc2 ON pc2.id = m.plano_ciclo_id
            LEFT JOIN assinatura_frequencias af ON af.id = pc2.assinatura_frequencia_id
            WHERE pp.pacote_contrato_id = ? AND pp.tenant_id = ? AND pp.status_pagamento_id != 2
            ORDER BY pp.id
        ");
        $stmtPagamentos->execute([$contratoId, $tenantId]);
        $pagamentos = $stmtPagamentos->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($pagamentos)) {
            $response->getBody()->write(json_encode(['error' => 'Nenhum pagamento pendente para este pacote'], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $dataPagamento = $data['data_pagamento'] ?? date('Y-m-d');
        $formaPagamentoId = $data['forma_pagamento_id'] ?? null;
        $observacoes = $data['observacoes'] ?? null;

        $db->beginTransaction();
        try {
            $matriculasAtivadas = [];
            $proximasParcelas = [];

            foreach ($pagamentos as $pagamento) {
                // Marcar pagamento como pago
                $stmtUpdate = $db->prepare("
                    UPDATE pagamentos_plano
                    SET status_pagamento_id = 2,
                        data_pagamento = ?,
                        forma_pagamento_id = ?,
                        observacoes = ?,
                        baixado_por = ?,
                        tipo_baixa_id = 1,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmtUpdate->execute([
                    $dataPagamento,
                    $formaPagamentoId,
                    $observacoes,
                    $adminId,
                    $pagamento['id']
                ]);

                // Ativar matrícula se pendente
                $stmtMatricula = $db->prepare("
                    UPDATE matriculas
                    SET status_id = (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1),
                        updated_at = NOW()
                    WHERE id = ?
                    AND status_id IN (
                        SELECT id FROM status_matricula WHERE codigo IN ('pendente', 'vencida')
                    )
                ");
                $stmtMatricula->execute([$pagamento['matricula_id']]);

                $matriculasAtivadas[] = [
                    'matricula_id' => (int) $pagamento['matricula_id'],
                    'aluno_id' => (int) $pagamento['aluno_id'],
                    'aluno_nome' => $pagamento['aluno_nome'],
                    'valor' => (float) $pagamento['valor']
                ];

                // Gerar próxima parcela
                try {
                    $dataVencimentoAtual = new \DateTime($pagamento['data_vencimento']);
                    $mesesCiclo = $pagamento['ciclo_meses'] ?? $pagamento['frequencia_meses'] ?? null;

                    if ($mesesCiclo) {
                        $proximoVencimento = clone $dataVencimentoAtual;
                        $proximoVencimento->modify("+{$mesesCiclo} months");
                    } else {
                        $duracaoDias = max(1, (int) ($pagamento['duracao_dias'] ?? 30));
                        $proximoVencimento = clone $dataVencimentoAtual;
                        $proximoVencimento->add(new \DateInterval("P{$duracaoDias}D"));
                    }

                    $stmtProxima = $db->prepare("
                        INSERT INTO pagamentos_plano (
                            tenant_id, aluno_id, matricula_id, plano_id, valor,
                            data_vencimento, status_pagamento_id, pacote_contrato_id,
                            observacoes, criado_por, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, 'Parcela gerada automaticamente', ?, NOW(), NOW())
                    ");
                    $stmtProxima->execute([
                        $pagamento['tenant_id'],
                        $pagamento['aluno_id'],
                        $pagamento['matricula_id'],
                        $pagamento['plano_id'],
                        $pagamento['valor'],
                        $proximoVencimento->format('Y-m-d'),
                        $contratoId,
                        $adminId
                    ]);

                    $proximasParcelas[] = [
                        'id' => (int) $db->lastInsertId(),
                        'aluno_nome' => $pagamento['aluno_nome'],
                        'data_vencimento' => $proximoVencimento->format('Y-m-d'),
                        'valor' => (float) $pagamento['valor']
                    ];

                    // Atualizar próxima data de vencimento na matrícula
                    $stmtUpdMat = $db->prepare("
                        UPDATE matriculas SET proxima_data_vencimento = ?, updated_at = NOW() WHERE id = ?
                    ");
                    $stmtUpdMat->execute([$proximoVencimento->format('Y-m-d'), $pagamento['matricula_id']]);

                } catch (\Exception $e) {
                    error_log("[darBaixaPacote] Erro ao gerar próxima parcela para matrícula {$pagamento['matricula_id']}: " . $e->getMessage());
                }
            }

            // Atualizar contrato para ativo
            $stmtUpdContrato = $db->prepare("
                UPDATE pacote_contratos SET status = 'ativo', updated_at = NOW() WHERE id = ? AND tenant_id = ?
            ");
            $stmtUpdContrato->execute([$contratoId, $tenantId]);

            // Atualizar beneficiários para ativo
            $stmtUpdBen = $db->prepare("
                UPDATE pacote_beneficiarios SET status = 'ativo', updated_at = NOW() WHERE pacote_contrato_id = ? AND tenant_id = ?
            ");
            $stmtUpdBen->execute([$contratoId, $tenantId]);

            $db->commit();

            $valorTotal = array_sum(array_column($matriculasAtivadas, 'valor'));

            $response->getBody()->write(json_encode([
                'message' => 'Baixa do pacote realizada com sucesso',
                'pacote' => $contrato['pacote_nome'],
                'contrato_id' => $contratoId,
                'valor_total' => $valorTotal,
                'matriculas_ativadas' => $matriculasAtivadas,
                'proximas_parcelas' => $proximasParcelas,
                'total_beneficiarios' => count($matriculasAtivadas)
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $db->rollBack();
            error_log("[MatriculaController::darBaixaPacote] Erro: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'error' => 'Erro ao dar baixa no pacote: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    private function usuariosTemColunasPlano(\PDO $db): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $stmt = $db->prepare("
            SELECT COUNT(*) AS total
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'usuarios'
              AND COLUMN_NAME = 'plano_id'
        ");
        $stmt->execute();
        $cache = ((int) $stmt->fetchColumn()) > 0;
        return $cache;
    }

    /**
     * Atualizar próxima data de vencimento de uma matrícula
     */
    #[OA\Put(
        path: "/admin/matriculas/{id}/proxima-data-vencimento",
        summary: "Atualizar data de vencimento",
        description: "Atualiza a próxima data de vencimento de uma matrícula. O status é ajustado automaticamente: se a nova data for passada, status muda para 'vencida'; se for futura e status não era 'ativa', muda para 'ativa'.",
        tags: ["Matrículas"],
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "ID da matrícula",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["proxima_data_vencimento"],
                properties: [
                    new OA\Property(property: "proxima_data_vencimento", type: "string", format: "date", description: "Nova data de vencimento (YYYY-MM-DD)", example: "2026-03-15")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Data atualizada",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Data de vencimento atualizada com sucesso"),
                        new OA\Property(property: "matricula_id", type: "integer"),
                        new OA\Property(property: "proxima_data_vencimento_anterior", type: "string", format: "date"),
                        new OA\Property(property: "proxima_data_vencimento_nova", type: "string", format: "date"),
                        new OA\Property(property: "status_anterior", type: "string"),
                        new OA\Property(property: "status_atual", type: "string"),
                        new OA\Property(property: "status_nome", type: "string")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Matrícula não encontrada"),
            new OA\Response(response: 422, description: "Data inválida ou não informada"),
            new OA\Response(response: 500, description: "Erro ao atualizar"),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
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
        } elseif ($dataVencimento >= $hoje && $matricula['status_codigo'] !== 'ativa') {
            // Data válida e status NÃO é ativa → mudar para "ativa" (ex: pendente → ativa)
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
    #[OA\Get(
        path: "/admin/matriculas/vencimentos/hoje",
        summary: "Vencimentos do dia",
        description: "Retorna lista de matrículas ativas com vencimento na data de hoje. Útil para notificações e alertas do painel.",
        tags: ["Matrículas"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de vencimentos",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "vencimentos", type: "array", items: new OA\Items(
                            properties: [
                                new OA\Property(property: "id", type: "integer"),
                                new OA\Property(property: "aluno_id", type: "integer"),
                                new OA\Property(property: "aluno_nome", type: "string"),
                                new OA\Property(property: "aluno_email", type: "string"),
                                new OA\Property(property: "aluno_telefone", type: "string", nullable: true),
                                new OA\Property(property: "plano_nome", type: "string"),
                                new OA\Property(property: "valor", type: "number"),
                                new OA\Property(property: "proxima_data_vencimento", type: "string", format: "date"),
                                new OA\Property(property: "status_codigo", type: "string"),
                                new OA\Property(property: "status_nome", type: "string")
                            ]
                        )),
                        new OA\Property(property: "total", type: "integer"),
                        new OA\Property(property: "data", type: "string", format: "date")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
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
    #[OA\Get(
        path: "/admin/matriculas/vencimentos/proximos",
        summary: "Próximos vencimentos",
        description: "Retorna matrículas ativas com vencimento nos próximos N dias (padrão 7). Inclui a quantidade de dias restantes para cada uma.",
        tags: ["Matrículas"],
        parameters: [
            new OA\Parameter(
                name: "dias",
                description: "Número de dias a considerar (padrão: 7)",
                in: "query",
                schema: new OA\Schema(type: "integer", default: 7)
            )
        ],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de próximos vencimentos",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "vencimentos", type: "array", items: new OA\Items(
                            properties: [
                                new OA\Property(property: "id", type: "integer"),
                                new OA\Property(property: "aluno_id", type: "integer"),
                                new OA\Property(property: "aluno_nome", type: "string"),
                                new OA\Property(property: "aluno_email", type: "string"),
                                new OA\Property(property: "aluno_telefone", type: "string", nullable: true),
                                new OA\Property(property: "plano_nome", type: "string"),
                                new OA\Property(property: "valor", type: "number"),
                                new OA\Property(property: "proxima_data_vencimento", type: "string", format: "date"),
                                new OA\Property(property: "dias_restantes", type: "integer"),
                                new OA\Property(property: "status_codigo", type: "string"),
                                new OA\Property(property: "status_nome", type: "string")
                            ]
                        )),
                        new OA\Property(property: "total", type: "integer"),
                        new OA\Property(property: "periodo", type: "object", properties: [
                            new OA\Property(property: "inicio", type: "string", format: "date"),
                            new OA\Property(property: "fim", type: "string", format: "date"),
                            new OA\Property(property: "dias", type: "integer")
                        ])
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Não autorizado")
        ]
    )]
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
                DATEDIFF(m.proxima_data_vencimento, CURDATE()) as dias_restantes,
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
            AND m.proxima_data_vencimento BETWEEN CURDATE() AND :data_limite
            AND sm.codigo = 'ativa'
            ORDER BY m.proxima_data_vencimento ASC, a.nome ASC
        ");

        $stmt->execute([
            'tenant_id' => $tenantId,
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
