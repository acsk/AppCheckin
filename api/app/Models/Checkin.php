<?php

namespace App\Models;

use PDO;
use App\Services\TenantService;

class Checkin
{
    private PDO $db;
    private TenantService $tenantService;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->tenantService = new TenantService($db);
    }

    /**
     * Buscar aluno_id a partir do usuario_id
     * Necessário pois o JWT contém usuario_id, mas checkins usam aluno_id
     */
    public function getAlunoIdFromUsuario(int $usuarioId, ?int $tenantId = null): ?int
    {
        $sql = "SELECT id FROM alunos WHERE usuario_id = :usuario_id";
        $params = ['usuario_id' => $usuarioId];
        
        // Se tenant_id foi especificado, usa ele para garantir isolamento
        // Nota: alunos não tem tenant_id direto, mas podemos validar via tenant_usuario_papel
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchColumn();
        
        return $result ? (int) $result : null;
    }

    public function create(int $usuarioId, int $horarioId): ?int
    {
        try {
            // Obter tenant_id e aluno_id
            $tenantId = $this->tenantService->getTenantIdFromUsuario($usuarioId);
            $alunoId = $this->getAlunoIdFromUsuario($usuarioId);
            
            if (!$alunoId) {
                throw new \Exception("Aluno não encontrado para usuario_id: $usuarioId");
            }
            
            $stmt = $this->db->prepare(
                "INSERT INTO checkins (aluno_id, usuario_id, horario_id, tenant_id, registrado_por_admin) 
                 VALUES (:aluno_id, :usuario_id, :horario_id, :tenant_id, 0)"
            );
            
            $stmt->execute([
                'aluno_id' => $alunoId,
                'usuario_id' => $usuarioId,
                'horario_id' => $horarioId,
                'tenant_id' => $tenantId
            ]);

            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            // Viola constraint de unique (usuário já tem check-in nesse horário)
            if ($e->getCode() == 23000) {
                return null;
            }
            throw $e;
        }
    }

    public function createByAdmin(int $usuarioId, int $horarioId, int $adminId): ?int
    {
        try {
            // Obter tenant_id e aluno_id
            $tenantId = $this->tenantService->getTenantIdFromUsuario($usuarioId);
            $alunoId = $this->getAlunoIdFromUsuario($usuarioId);
            
            if (!$alunoId) {
                throw new \Exception("Aluno não encontrado para usuario_id: $usuarioId");
            }
            
            $stmt = $this->db->prepare(
                "INSERT INTO checkins (aluno_id, usuario_id, horario_id, tenant_id, registrado_por_admin, admin_id) 
                 VALUES (:aluno_id, :usuario_id, :horario_id, :tenant_id, 1, :admin_id)"
            );
            
            $stmt->execute([
                'aluno_id' => $alunoId,
                'usuario_id' => $usuarioId,
                'horario_id' => $horarioId,
                'tenant_id' => $tenantId,
                'admin_id' => $adminId
            ]);

            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            // Viola constraint de unique (usuário já tem check-in nesse horário)
            if ($e->getCode() == 23000) {
                return null;
            }
            throw $e;
        }
    }

    public function getByUsuarioId(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, 
                    h.hora, 
                    d.data,
                    CONCAT(d.data, ' ', h.hora) as data_hora_completa
             FROM checkins c
             INNER JOIN alunos a ON a.id = c.aluno_id
             INNER JOIN horarios h ON c.horario_id = h.id
             INNER JOIN dias d ON h.dia_id = d.id
             WHERE a.usuario_id = :usuario_id
             ORDER BY d.data DESC, h.hora DESC"
        );
        $stmt->execute(['usuario_id' => $usuarioId]);
        
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT 
                c.*, 
                h.hora, 
                h.horario_inicio,
                h.horario_fim,
                h.tolerancia_minutos,
                d.data,
                COALESCE(d.data, DATE(c.created_at)) as data_aula
             FROM checkins c
             LEFT JOIN horarios h ON c.horario_id = h.id
             LEFT JOIN dias d ON h.dia_id = d.id
             WHERE c.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $checkin = $stmt->fetch();
        
        return $checkin ?: null;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM checkins WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function usuarioTemCheckin(int $usuarioId, int $horarioId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM checkins c
             INNER JOIN alunos a ON a.id = c.aluno_id
             WHERE a.usuario_id = :usuario_id AND c.horario_id = :horario_id"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'horario_id' => $horarioId
        ]);
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Criar check-in em turma (novo método para mobile app)
     * Usa aluno_id para relacionamento correto
     */
    public function createEmTurma(int $usuarioId, int $turmaId): ?int
    {
        try {
            // Obter tenant_id e aluno_id
            $tenantId = $this->tenantService->getTenantIdFromUsuario($usuarioId);
            $alunoId = $this->getAlunoIdFromUsuario($usuarioId);
            
            if (!$alunoId) {
                throw new \Exception("Aluno não encontrado para usuario_id: $usuarioId");
            }
            
            $stmt = $this->db->prepare(
                "INSERT INTO checkins (aluno_id, usuario_id, turma_id, tenant_id, registrado_por_admin) 
                 VALUES (:aluno_id, :usuario_id, :turma_id, :tenant_id, 0)"
            );
            
            $stmt->execute([
                'aluno_id' => $alunoId,
                'usuario_id' => $usuarioId,
                'turma_id' => $turmaId,
                'tenant_id' => $tenantId
            ]);

            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            // Viola constraint de unique (usuário já tem check-in nessa turma)
            if ($e->getCode() == 23000) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Verificar se usuário já tem check-in em uma turma específica
     * Busca via aluno_id (relacionamento correto)
     */
    public function usuarioTemCheckinNaTurma(int $usuarioId, int $turmaId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM checkins c
             INNER JOIN alunos a ON a.id = c.aluno_id
             WHERE a.usuario_id = :usuario_id AND c.turma_id = :turma_id"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'turma_id' => $turmaId
        ]);
        
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Verificar se usuário já tem check-in no mesmo dia (diferente turmas, mesma data)
     */
    public function usuarioTemCheckinNoDia(int $usuarioId, string $data): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total, MAX(c.id) as ultimo_checkin_id
             FROM checkins c
             INNER JOIN alunos a ON a.id = c.aluno_id
             INNER JOIN turmas t ON c.turma_id = t.id
             INNER JOIN dias d ON t.dia_id = d.id
             WHERE a.usuario_id = :usuario_id
             AND DATE(d.data) = :data"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'data' => $data
        ]);
        
        $result = $stmt->fetch();
        return [
            'total' => (int) ($result['total'] ?? 0),
            'ultimo_checkin_id' => $result['ultimo_checkin_id'] ? (int) $result['ultimo_checkin_id'] : null
        ];
    }

    /**
     * Verificar se usuário já tem check-in na mesma modalidade no mesmo dia
     * Permite múltiplas modalidades no mesmo dia, mas apenas 1 por modalidade
     */
    public function usuarioTemCheckinNoDiaNaModalidade(int $usuarioId, string $data, ?int $modalidadeId): array
    {
        $sql = "SELECT COUNT(*) as total, MAX(c.id) as ultimo_checkin_id
                FROM checkins c
                INNER JOIN alunos a ON a.id = c.aluno_id
                INNER JOIN turmas t ON c.turma_id = t.id
                INNER JOIN dias d ON t.dia_id = d.id
                WHERE a.usuario_id = :usuario_id
                AND DATE(d.data) = :data";
        
        $params = [
            'usuario_id' => $usuarioId,
            'data' => $data
        ];
        
        if ($modalidadeId !== null) {
            $sql .= " AND t.modalidade_id = :modalidade_id";
            $params['modalidade_id'] = $modalidadeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch();
        return [
            'total' => (int) ($result['total'] ?? 0),
            'ultimo_checkin_id' => $result['ultimo_checkin_id'] ? (int) $result['ultimo_checkin_id'] : null
        ];
    }

    /**
     * Contar check-ins do usuário na semana atual
     */
    /**
     * Contar check-ins do usuário na semana atual
     * @param int $usuarioId ID do usuário
     * @param int|null $modalidadeId Filtrar por modalidade (opcional)
     */
    public function contarCheckinsNaSemana(int $usuarioId, ?int $modalidadeId = null): int
    {
        $sql = "SELECT COUNT(*) FROM checkins c
                INNER JOIN alunos a ON a.id = c.aluno_id";
        $params = ['usuario_id' => $usuarioId];
        
        if ($modalidadeId) {
            $sql .= " INNER JOIN turmas t ON c.turma_id = t.id";
        }
        
        $sql .= " WHERE a.usuario_id = :usuario_id
                  AND YEARWEEK(c.created_at, 1) = YEARWEEK(NOW(), 1)";
        
        if ($modalidadeId) {
            $sql .= " AND t.modalidade_id = :modalidade_id";
            $params['modalidade_id'] = $modalidadeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn();
    }

    /**
     * Obter limite de check-ins do plano do usuário
     */
    /**
     * Obter limite de check-ins semanal baseado no plano do usuário
     * @param int $usuarioId ID do usuário
     * @param int $tenantId ID do tenant
     * @param int|null $modalidadeId Filtrar por modalidade (para check-in em turma específica)
     */
    public function obterLimiteCheckinsPlano(int $usuarioId, int $tenantId, ?int $modalidadeId = null): array
    {
        $sql = "SELECT p.checkins_semanais, p.nome as plano_nome, p.modalidade_id
             FROM matriculas m
             INNER JOIN planos p ON m.plano_id = p.id
             INNER JOIN alunos a ON a.id = m.aluno_id
             WHERE a.usuario_id = :usuario_id
             AND m.tenant_id = :tenant_id
             AND m.status_id = (SELECT id FROM status_matricula WHERE nome = 'ativa')
             AND m.data_inicio <= CURDATE()
             AND m.data_vencimento >= CURDATE()";
        
        $params = [
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId
        ];
        
        // Se modalidade foi especificada, filtrar por ela
        if ($modalidadeId) {
            $sql .= " AND p.modalidade_id = :modalidade_id";
            $params['modalidade_id'] = $modalidadeId;
        }
        
        $sql .= " LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch();
        return [
            'limite' => $result ? (int) $result['checkins_semanais'] : 0,
            'plano_nome' => $result ? $result['plano_nome'] : 'Sem plano',
            'tem_plano' => $result !== false,
            'modalidade_id' => $result ? (int) $result['modalidade_id'] : null
        ];
    }

    /**
     * Listar check-ins de uma turma para controle de presença
     */
    public function listarCheckinsTurma(int $turmaId, int $tenantId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.id, c.aluno_id, c.turma_id, c.data_checkin, 
                    c.presente, c.presenca_confirmada_em, c.presenca_confirmada_por,
                    a.nome as aluno_nome, u.email as aluno_email,
                    conf.nome as confirmado_por_nome
             FROM checkins c
             INNER JOIN alunos a ON c.aluno_id = a.id
             INNER JOIN usuarios u ON a.usuario_id = u.id
             LEFT JOIN usuarios conf ON c.presenca_confirmada_por = conf.id
             WHERE c.turma_id = :turma_id
             ORDER BY a.nome ASC"
        );
        $stmt->execute(['turma_id' => $turmaId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Marcar presença de um aluno (presente ou falta)
     */
    public function marcarPresenca(int $checkinId, bool $presente, int $confirmadoPor): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE checkins 
             SET presente = :presente,
                 presenca_confirmada_em = NOW(),
                 presenca_confirmada_por = :confirmado_por
             WHERE id = :checkin_id"
        );
        
        return $stmt->execute([
            'presente' => $presente ? 1 : 0,
            'confirmado_por' => $confirmadoPor,
            'checkin_id' => $checkinId
        ]);
    }

    /**
     * Marcar presença em lote (todos os alunos de uma turma)
     */
    public function marcarPresencaEmLote(array $checkinIds, bool $presente, int $confirmadoPor): int
    {
        if (empty($checkinIds)) {
            return 0;
        }
        
        $placeholders = implode(',', array_fill(0, count($checkinIds), '?'));
        
        $sql = "UPDATE checkins 
                SET presente = ?,
                    presenca_confirmada_em = NOW(),
                    presenca_confirmada_por = ?
                WHERE id IN ($placeholders)";
        
        $params = array_merge([$presente ? 1 : 0, $confirmadoPor], $checkinIds);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }

    /**
     * Verificar se o check-in pertence a uma turma do professor
     */
    public function checkinPertenceAoProfessor(int $checkinId, int $professorId, int $tenantId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total
             FROM checkins c
             INNER JOIN turmas t ON c.turma_id = t.id
             WHERE c.id = :checkin_id
             AND t.professor_id = :professor_id
             AND t.tenant_id = :tenant_id"
        );
        $stmt->execute([
            'checkin_id' => $checkinId,
            'professor_id' => $professorId,
            'tenant_id' => $tenantId
        ]);
        
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Obter estatísticas de presença de uma turma
     */
    public function estatisticasPresencaTurma(int $turmaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT 
                COUNT(*) as total_checkins,
                SUM(CASE WHEN presente = 1 THEN 1 ELSE 0 END) as presentes,
                SUM(CASE WHEN presente = 0 THEN 1 ELSE 0 END) as faltas,
                SUM(CASE WHEN presente IS NULL THEN 1 ELSE 0 END) as nao_verificados
             FROM checkins
             WHERE turma_id = :turma_id"
        );
        $stmt->execute(['turma_id' => $turmaId]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Remover check-ins de alunos marcados como falta
     * Isso libera o crédito do aluno para que ele possa remarcar em outra turma
     * @param int $turmaId ID da turma
     * @param int $tenantId ID do tenant
     * @return array IDs dos check-ins removidos e informações dos alunos
     */
    public function removerCheckinsFaltantes(int $turmaId, int $tenantId): array
    {
        // Primeiro, buscar os check-ins que serão removidos para retornar informações
        $stmt = $this->db->prepare(
            "SELECT c.id, c.aluno_id, c.turma_id, c.data_checkin,
                    a.nome as aluno_nome, u.email as aluno_email
             FROM checkins c
             INNER JOIN alunos a ON c.aluno_id = a.id
             INNER JOIN usuarios u ON a.usuario_id = u.id
             WHERE c.turma_id = :turma_id
             AND c.tenant_id = :tenant_id
             AND c.presente = 0"
        );
        $stmt->execute(['turma_id' => $turmaId, 'tenant_id' => $tenantId]);
        $checkinsFaltantes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($checkinsFaltantes)) {
            return [
                'removidos' => 0,
                'checkins' => []
            ];
        }
        
        // Extrair IDs para deletar
        $ids = array_column($checkinsFaltantes, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        // Deletar os check-ins de faltantes
        $stmtDelete = $this->db->prepare(
            "DELETE FROM checkins WHERE id IN ($placeholders)"
        );
        $stmtDelete->execute($ids);
        $removidos = $stmtDelete->rowCount();
        
        return [
            'removidos' => $removidos,
            'checkins' => $checkinsFaltantes
        ];
    }

    /**
     * Confirmar presença da turma inteira
     * Marca todos os check-ins não verificados como presente ou falta
     * @param int $turmaId ID da turma
     * @param int $tenantId ID do tenant
     * @param array $presencas Array com [checkin_id => presente (true/false)]
     * @param int $confirmadoPor ID do usuário que está confirmando (professor/admin)
     * @param bool $removerFaltantes Se true, remove check-ins de faltantes após confirmar
     * @return array Resultado da operação
     */
    public function confirmarPresencaTurma(
        int $turmaId, 
        int $tenantId, 
        array $presencas, 
        int $confirmadoPor,
        bool $removerFaltantes = false
    ): array {
        $this->db->beginTransaction();
        
        try {
            $confirmados = 0;
            $faltasRegistradas = 0;
            $presencasRegistradas = 0;
            
            foreach ($presencas as $checkinId => $presente) {
                $presente = filter_var($presente, FILTER_VALIDATE_BOOLEAN);
                
                $stmt = $this->db->prepare(
                    "UPDATE checkins 
                     SET presente = :presente,
                         presenca_confirmada_em = NOW(),
                         presenca_confirmada_por = :confirmado_por
                     WHERE id = :checkin_id
                     AND turma_id = :turma_id
                     AND tenant_id = :tenant_id"
                );
                
                $result = $stmt->execute([
                    'presente' => $presente ? 1 : 0,
                    'confirmado_por' => $confirmadoPor,
                    'checkin_id' => $checkinId,
                    'turma_id' => $turmaId,
                    'tenant_id' => $tenantId
                ]);
                
                if ($result && $stmt->rowCount() > 0) {
                    $confirmados++;
                    if ($presente) {
                        $presencasRegistradas++;
                    } else {
                        $faltasRegistradas++;
                    }
                }
            }
            
            // Se deve remover check-ins de faltantes, executar após confirmar
            $checkinsRemovidos = ['removidos' => 0, 'checkins' => []];
            if ($removerFaltantes && $faltasRegistradas > 0) {
                $checkinsRemovidos = $this->removerCheckinsFaltantes($turmaId, $tenantId);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'confirmados' => $confirmados,
                'presencas' => $presencasRegistradas,
                'faltas' => $faltasRegistradas,
                'checkins_removidos' => $checkinsRemovidos
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Listar turmas do professor com check-ins pendentes de confirmação
     * @param int $professorId ID do professor na tabela professores
     * @param int $tenantId ID do tenant
     * @return array Turmas com check-ins pendentes
     */
    public function listarTurmasComCheckinsPendentes(int $professorId, int $tenantId): array
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT t.id, t.nome, t.horario_inicio, t.horario_fim,
                    d.data as dia_data,
                    m.nome as modalidade_nome,
                    m.icone as modalidade_icone,
                    m.cor as modalidade_cor,
                    (SELECT COUNT(*) FROM checkins c2 WHERE c2.turma_id = t.id AND c2.presente IS NULL) as pendentes,
                    (SELECT COUNT(*) FROM checkins c3 WHERE c3.turma_id = t.id) as total_checkins
             FROM turmas t
             INNER JOIN dias d ON t.dia_id = d.id
             INNER JOIN modalidades m ON t.modalidade_id = m.id
             INNER JOIN checkins c ON c.turma_id = t.id
             WHERE t.professor_id = :professor_id
             AND t.tenant_id = :tenant_id
             AND t.ativo = 1
             AND c.presente IS NULL
             ORDER BY d.data DESC, t.horario_inicio DESC"
        );
        $stmt->execute([
            'professor_id' => $professorId,
            'tenant_id' => $tenantId
        ]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Verificar se turma pertence ao professor
     * @param int $turmaId ID da turma
     * @param int $professorId ID do professor
     * @param int $tenantId ID do tenant
     * @return bool
     */
    public function turmaPertenceAoProfessor(int $turmaId, int $professorId, int $tenantId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM turmas 
             WHERE id = :turma_id 
             AND professor_id = :professor_id 
             AND tenant_id = :tenant_id"
        );
        $stmt->execute([
            'turma_id' => $turmaId,
            'professor_id' => $professorId,
            'tenant_id' => $tenantId
        ]);
        
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Ranking de usuários com mais check-ins no mês atual
     * @param int $tenantId ID do tenant
     * @param int $limite Quantidade de resultados (default 3)
     * @param int|null $modalidadeId Filtrar por modalidade (opcional)
     */
    public function rankingMesAtual(int $tenantId, int $limite = 3, ?int $modalidadeId = null): array
    {
        $sql = "SELECT 
                a.id as aluno_id,
                a.nome,
                u.email,
                a.foto_caminho,
                COUNT(c.id) as total_checkins
             FROM checkins c
             INNER JOIN alunos a ON c.aluno_id = a.id
             INNER JOIN usuarios u ON a.usuario_id = u.id
             INNER JOIN turmas t ON c.turma_id = t.id AND t.tenant_id = c.tenant_id
             WHERE c.tenant_id = :tenant_id
               AND MONTH(c.data_checkin_date) = MONTH(CURRENT_DATE())
               AND YEAR(c.data_checkin_date) = YEAR(CURRENT_DATE())";
        
        if ($modalidadeId) {
            $sql .= " AND t.modalidade_id = :modalidade_id";
        }
        
        $sql .= " GROUP BY a.id, a.nome, u.email, a.foto_caminho
                  ORDER BY total_checkins DESC
                  LIMIT :limite";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, \PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        
        if ($modalidadeId) {
            $stmt->bindValue(':modalidade_id', $modalidadeId, \PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Ranking do usuário em cada modalidade no mês atual
     * Retorna a posição do usuário em cada modalidade que ele pratica
     */
    public function rankingUsuarioPorModalidade(int $userId, int $tenantId): array
    {
        // Primeiro, busca as modalidades que o usuário fez check-in no mês
        $sqlModalidades = "SELECT DISTINCT 
                t.modalidade_id,
                m.nome as modalidade_nome,
                m.icone as modalidade_icone,
                m.cor as modalidade_cor
             FROM checkins c
             INNER JOIN alunos a ON c.aluno_id = a.id
             INNER JOIN turmas t ON c.turma_id = t.id AND t.tenant_id = c.tenant_id
             INNER JOIN modalidades m ON t.modalidade_id = m.id
             WHERE a.usuario_id = :usuario_id
               AND c.tenant_id = :tenant_id
               AND MONTH(c.data_checkin_date) = MONTH(CURRENT_DATE())
               AND YEAR(c.data_checkin_date) = YEAR(CURRENT_DATE())";
        
        $stmt = $this->db->prepare($sqlModalidades);
        $stmt->execute(['usuario_id' => $userId, 'tenant_id' => $tenantId]);
        $modalidades = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $rankings = [];
        
        foreach ($modalidades as $modalidade) {
            // Para cada modalidade, calcular a posição do usuário
            $sqlRanking = "SELECT 
                    aluno_id,
                    total_checkins,
                    posicao
                FROM (
                    SELECT 
                        c.aluno_id,
                        COUNT(c.id) as total_checkins,
                        RANK() OVER (ORDER BY COUNT(c.id) DESC) as posicao
                    FROM checkins c
                    INNER JOIN turmas t ON c.turma_id = t.id AND t.tenant_id = c.tenant_id
                    WHERE c.tenant_id = :tenant_id
                      AND t.modalidade_id = :modalidade_id
                      AND MONTH(c.data_checkin_date) = MONTH(CURRENT_DATE())
                      AND YEAR(c.data_checkin_date) = YEAR(CURRENT_DATE())
                    GROUP BY c.aluno_id
                ) ranking
                WHERE aluno_id = (SELECT id FROM alunos WHERE usuario_id = :usuario_id LIMIT 1)";
            
            $stmtRanking = $this->db->prepare($sqlRanking);
            $stmtRanking->execute([
                'tenant_id' => $tenantId,
                'modalidade_id' => $modalidade['modalidade_id'],
                'usuario_id' => $userId
            ]);
            $posicao = $stmtRanking->fetch(\PDO::FETCH_ASSOC);
            
            // Contar total de participantes na modalidade
            $sqlTotal = "SELECT COUNT(DISTINCT c.aluno_id) as total
                FROM checkins c
                INNER JOIN turmas t ON c.turma_id = t.id AND t.tenant_id = c.tenant_id
                WHERE c.tenant_id = :tenant_id
                  AND t.modalidade_id = :modalidade_id
                  AND MONTH(c.data_checkin_date) = MONTH(CURRENT_DATE())
                  AND YEAR(c.data_checkin_date) = YEAR(CURRENT_DATE())";
            
            $stmtTotal = $this->db->prepare($sqlTotal);
            $stmtTotal->execute([
                'tenant_id' => $tenantId,
                'modalidade_id' => $modalidade['modalidade_id']
            ]);
            $totalParticipantes = (int) $stmtTotal->fetchColumn();
            
            if ($posicao) {
                $rankings[] = [
                    'modalidade_id' => (int) $modalidade['modalidade_id'],
                    'modalidade_nome' => $modalidade['modalidade_nome'],
                    'modalidade_icone' => $modalidade['modalidade_icone'],
                    'modalidade_cor' => $modalidade['modalidade_cor'],
                    'posicao' => (int) $posicao['posicao'],
                    'total_checkins' => (int) $posicao['total_checkins'],
                    'total_participantes' => $totalParticipantes
                ];
            }
        }
        
        // Ordenar por posição (melhores primeiro)
        usort($rankings, fn($a, $b) => $a['posicao'] <=> $b['posicao']);
        
        return $rankings;
    }
}
