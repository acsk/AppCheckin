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
     * Usado quando aluno_id não está disponível no JWT (compatibilidade)
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

    public function create(int $usuarioId, int $horarioId, ?int $alunoIdParam = null): ?int
    {
        try {
            // Obter tenant_id e aluno_id (usa o parâmetro se fornecido, evitando query extra)
            $tenantId = $this->tenantService->getTenantIdFromUsuario($usuarioId);
            $alunoId = $alunoIdParam ?? $this->getAlunoIdFromUsuario($usuarioId);
            
            if (!$alunoId) {
                throw new \Exception("Aluno não encontrado para usuario_id: $usuarioId");
            }
            
            $stmt = $this->db->prepare(
                "INSERT INTO checkins (aluno_id, horario_id, tenant_id, registrado_por_admin) 
                 VALUES (:aluno_id, :horario_id, :tenant_id, 0)"
            );
            
            $stmt->execute([
                'aluno_id' => $alunoId,
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

    public function createByAdmin(int $usuarioId, int $horarioId, int $adminId, ?int $alunoIdParam = null): ?int
    {
        try {
            // Obter tenant_id e aluno_id (usa o parâmetro se fornecido, evitando query extra)
            $tenantId = $this->tenantService->getTenantIdFromUsuario($usuarioId);
            $alunoId = $alunoIdParam ?? $this->getAlunoIdFromUsuario($usuarioId);
            
            if (!$alunoId) {
                throw new \Exception("Aluno não encontrado para usuario_id: $usuarioId");
            }
            
            $stmt = $this->db->prepare(
                "INSERT INTO checkins (aluno_id, horario_id, tenant_id, registrado_por_admin, admin_id) 
                 VALUES (:aluno_id, :horario_id, :tenant_id, 1, :admin_id)"
            );
            
            $stmt->execute([
                'aluno_id' => $alunoId,
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
                a.usuario_id,
                t.nome as turma_nome,
                t.horario_inicio,
                t.horario_fim,
                t.tolerancia_minutos,
                t.dia_id,
                d.data,
                COALESCE(d.data, DATE(c.created_at)) as data_aula
             FROM checkins c
             LEFT JOIN alunos a ON c.aluno_id = a.id
             LEFT JOIN turmas t ON c.turma_id = t.id
             LEFT JOIN dias d ON t.dia_id = d.id
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
    public function createEmTurma(int $usuarioId, int $turmaId, ?int $alunoIdParam = null): ?int
    {
        try {
            // Obter tenant_id e aluno_id (usa o parâmetro se fornecido, evitando query extra)
            $tenantId = $this->tenantService->getTenantIdFromUsuario($usuarioId);
            $alunoId = $alunoIdParam ?? $this->getAlunoIdFromUsuario($usuarioId);
            
            if (!$alunoId) {
                throw new \Exception("Aluno não encontrado para usuario_id: $usuarioId");
            }
            
            $stmt = $this->db->prepare(
                "INSERT INTO checkins (aluno_id, turma_id, tenant_id, registrado_por_admin) 
                 VALUES (:aluno_id, :turma_id, :tenant_id, 0)"
            );
            
            $stmt->execute([
                'aluno_id' => $alunoId,
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
     * Apenas checkins com presente = true ou NULL (pendentes) são contados
     * Checkins com presente = false (faltas) NÃO contam no limite semanal
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
        
        // presente = 1 (presente) ou presente IS NULL (pendente) contam
        // presente = 0 (falta) NÃO conta - libera crédito para reposição
        $sql .= " WHERE a.usuario_id = :usuario_id
                  AND YEARWEEK(COALESCE(c.data_checkin_date, DATE(c.created_at)), 1) = YEARWEEK(CURDATE(), 1)
                  AND (c.presente IS NULL OR c.presente = 1)";
        
        if ($modalidadeId) {
            $sql .= " AND t.modalidade_id = :modalidade_id";
            $params['modalidade_id'] = $modalidadeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn();
    }

    /**
     * Contar check-ins do usuário no mês atual
     * Apenas checkins com presente = true ou NULL (pendentes) são contados
     * Checkins com presente = false (faltas) NÃO contam no limite mensal
     * @param int $usuarioId ID do usuário
     * @param int|null $modalidadeId Filtrar por modalidade (opcional)
     */
    public function contarCheckinsNoMes(int $usuarioId, ?int $modalidadeId = null): int
    {
        $sql = "SELECT COUNT(*) FROM checkins c
                INNER JOIN alunos a ON a.id = c.aluno_id
                INNER JOIN turmas t ON t.id = c.turma_id
                INNER JOIN dias d ON d.id = t.dia_id";
        $params = ['usuario_id' => $usuarioId];

        $sql .= " WHERE a.usuario_id = :usuario_id
                  AND YEAR(d.data)  = YEAR(CURDATE())
                  AND MONTH(d.data) = MONTH(CURDATE())
                  AND (c.presente IS NULL OR c.presente = 1)";

        if ($modalidadeId) {
            $sql .= " AND t.modalidade_id = :modalidade_id";
            $params['modalidade_id'] = $modalidadeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Listar os check-ins do usuário no mês atual que CONTAM no limite.
     * Usa a mesma regra de contarCheckinsNoMes (mês de dias.data = mês de CURDATE,
     * presente IS NULL ou = 1). Serve para exibir, de forma esclarecedora, quais
     * dias preencheram o limite mensal.
     * @return array<int, array{data:string,horario:string,modalidade:?string,presente:?int,status:string,registrado_por_admin:bool}>
     */
    public function listarCheckinsDoMes(int $usuarioId, ?int $modalidadeId = null): array
    {
        $sql = "SELECT DATE(d.data) AS data_aula,
                       t.horario_inicio,
                       moda.nome AS modalidade_nome,
                       c.presente,
                       c.registrado_por_admin
                FROM checkins c
                INNER JOIN alunos a ON a.id = c.aluno_id
                INNER JOIN turmas t ON t.id = c.turma_id
                INNER JOIN dias d ON d.id = t.dia_id
                LEFT  JOIN modalidades moda ON moda.id = t.modalidade_id
                WHERE a.usuario_id = :usuario_id
                  AND YEAR(d.data)  = YEAR(CURDATE())
                  AND MONTH(d.data) = MONTH(CURDATE())
                  AND (c.presente IS NULL OR c.presente = 1)";

        $params = ['usuario_id' => $usuarioId];
        if ($modalidadeId !== null) {
            $sql .= " AND t.modalidade_id = :modalidade_id";
            $params['modalidade_id'] = $modalidadeId;
        }
        $sql .= " ORDER BY d.data ASC, t.horario_inicio ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->mapearDiasCheckin($rows);
    }

    /**
     * Listar check-ins que CONTAM no limite dentro de um período [inicio, fim).
     * fim é EXCLUSIVO (dia do próximo vencimento não entra neste ciclo).
     */
    public function listarCheckinsPorPeriodo(int $usuarioId, ?int $modalidadeId, string $inicio, string $fim): array
    {
        $sql = "SELECT DATE(d.data) AS data_aula,
                       t.horario_inicio,
                       moda.nome AS modalidade_nome,
                       c.presente,
                       c.registrado_por_admin
                FROM checkins c
                INNER JOIN alunos a ON a.id = c.aluno_id
                INNER JOIN turmas t ON t.id = c.turma_id
                INNER JOIN dias d ON d.id = t.dia_id
                LEFT  JOIN modalidades moda ON moda.id = t.modalidade_id
                WHERE a.usuario_id = :usuario_id
                  AND d.data >= :inicio
                  AND d.data <  :fim
                  AND (c.presente IS NULL OR c.presente = 1)";

        $params = ['usuario_id' => $usuarioId, 'inicio' => $inicio, 'fim' => $fim];
        if ($modalidadeId !== null) {
            $sql .= " AND t.modalidade_id = :modalidade_id";
            $params['modalidade_id'] = $modalidadeId;
        }
        $sql .= " ORDER BY d.data ASC, t.horario_inicio ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->mapearDiasCheckin($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Contar check-ins que contam no limite dentro de um período [inicio, fim).
     */
    public function contarCheckinsNoPeriodo(int $usuarioId, ?int $modalidadeId, string $inicio, string $fim): int
    {
        $sql = "SELECT COUNT(*)
                FROM checkins c
                INNER JOIN alunos a ON a.id = c.aluno_id
                INNER JOIN turmas t ON t.id = c.turma_id
                INNER JOIN dias d ON d.id = t.dia_id
                WHERE a.usuario_id = :usuario_id
                  AND d.data >= :inicio
                  AND d.data <  :fim
                  AND (c.presente IS NULL OR c.presente = 1)";

        $params = ['usuario_id' => $usuarioId, 'inicio' => $inicio, 'fim' => $fim];
        if ($modalidadeId !== null) {
            $sql .= " AND t.modalidade_id = :modalidade_id";
            $params['modalidade_id'] = $modalidadeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Mapeia linhas cruas de check-in para o formato exibível (data/horario/status).
     */
    private function mapearDiasCheckin(array $rows): array
    {
        return array_map(function ($r) {
            $presente = $r['presente'];
            $status = $presente === null ? 'pendente' : ((int) $presente === 1 ? 'presente' : 'falta');
            return [
                'data'                  => $r['data_aula'],
                'horario'               => $r['horario_inicio'] ? substr((string) $r['horario_inicio'], 0, 5) : null,
                'modalidade'            => $r['modalidade_nome'],
                'presente'              => $presente === null ? null : (int) $presente,
                'status'                => $status,
                'registrado_por_admin'  => (bool) $r['registrado_por_admin'],
            ];
        }, $rows);
    }

    /**
     * Data-âncora do ciclo: dia $anchorDay no mês/ano informado, com clamp para
     * o último dia do mês quando o mês tem menos dias (ex.: âncora 31 em fevereiro).
     */
    private function anchorDate(int $year, int $month, int $anchorDay): \DateTime
    {
        $primeiro = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $diasNoMes = (int) $primeiro->format('t');
        $dia = min($anchorDay, $diasNoMes);
        return new \DateTime(sprintf('%04d-%02d-%02d', $year, $month, $dia));
    }

    /**
     * Avança/retrocede $delta meses a partir de uma data-âncora, preservando o
     * dia-âncora (com clamp). Passo por mês de calendário (não 30 dias fixos).
     */
    private function addMonthsAnchor(\DateTime $base, int $delta, int $anchorDay): \DateTime
    {
        $y = (int) $base->format('Y');
        $m = (int) $base->format('n');
        $idx = ($y * 12 + ($m - 1)) + $delta;
        // Divisão com PISO: o '%' do PHP devolve resto negativo para $idx < 0
        // ((-1 % 12) = -1 → mês 0). floor() garante mês sempre em 1..12.
        $ny = (int) floor($idx / 12);
        $nm = $idx - $ny * 12 + 1;
        return $this->anchorDate($ny, $nm, $anchorDay);
    }

    /**
     * Limite de check-ins para uma janela [inicio, fim) com base em dias/semanas.
     *
     * @return array{limite: int, semanas: int, bonus: int}
     */
    private function limiteJanelaCheckins(int $checkinsSemanais, int $diasNaJanela): array
    {
        $semanas = (int) ceil($diasNaJanela / 7);
        $bonus = ($semanas >= 5) ? 1 : 0;

        return [
            'limite'  => $checkinsSemanais * 4 + $bonus,
            'semanas' => $semanas,
            'bonus'   => $bonus,
        ];
    }

    /**
     * Soma o limite mensal (×4 + bônus de 5ª semana) de cada sub-janela de 1 mês
     * ancorada em $anchorDay dentro do contrato [inicio, fim).
     */
    private function calcularLimiteContrato(int $checkinsSemanais, \DateTime $inicio, \DateTime $fim, int $anchorDay): int
    {
        $total = 0;
        $cursor = clone $inicio;

        while ($cursor < $fim) {
            $proximoFim = $this->addMonthsAnchor($cursor, 1, $anchorDay);
            if ($proximoFim > $fim) {
                $proximoFim = clone $fim;
            }
            $dias = (int) $cursor->diff($proximoFim)->days;
            $total += $this->limiteJanelaCheckins($checkinsSemanais, $dias)['limite'];
            $cursor = $proximoFim;
        }

        return $total;
    }

    /**
     * Calcula o CICLO DE CHECK-IN ("mês de cobrança") da matrícula ativa do usuário,
     * o limite efetivo (checkins_semanais × 4 + bônus de 5ª semana) e os check-ins
     * realizados dentro do ciclo.
     *
     * O ciclo é ancorado no dia do vencimento (proxima_data_vencimento), com passo
     * de 1 mês de calendário. Ex.: vencimento dia 26 → ciclo atual [26/05, 26/06)
     * (fim exclusivo). O bônus de 5ª semana é mantido: ciclo com ceil(dias/7) >= 5
     * ganha +1 check-in (janelas de 29–31 dias dão 5; fevereiro de 28 dias dá 4).
     *
     * Planos bimestrais ou mais (plano_ciclos.meses >= 2): o limite do CONTRATO
     * inteiro é a soma das janelas mensais entre data_inicio e o vencimento. O teto
     * mensal só é aplicado no último mês do contrato ou se o total do contrato
     * estourar antes (ver avaliarLimiteMensalReposicao).
     *
     * Observação: só se aplica a planos com permite_reposicao (limite mensal).
     * Seleciona a mesma matrícula que obterLimiteCheckinsPlano (ativa, vigente).
     */
    public function obterCicloCheckins(
        int $usuarioId,
        int $tenantId,
        ?int $modalidadeId = null,
        ?int $matriculaId = null
    ): array {
        $sql = "SELECT p.checkins_semanais, p.nome AS plano_nome, p.modalidade_id,
                       p.duracao_dias,
                       m.data_inicio,
                       COALESCE(m.proxima_data_vencimento, m.data_vencimento) AS vencimento,
                       COALESCE(NULLIF(pc.meses, 0), 1) AS ciclo_meses,
                       CASE
                           WHEN m.plano_ciclo_id IS NOT NULL THEN COALESCE(pc.permite_reposicao, 0)
                           ELSE COALESCE((
                               SELECT MAX(pc2.permite_reposicao)
                               FROM plano_ciclos pc2
                               WHERE pc2.plano_id = p.id
                                 AND pc2.tenant_id = m.tenant_id
                                 AND pc2.ativo = 1
                           ), 0)
                       END AS permite_reposicao
                FROM matriculas m
                INNER JOIN planos p ON m.plano_id = p.id
                LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id AND pc.tenant_id = m.tenant_id
                INNER JOIN alunos a ON a.id = m.aluno_id
                INNER JOIN status_matricula sm ON sm.id = m.status_id
                WHERE a.usuario_id = :usuario_id
                  AND m.tenant_id = :tenant_id";

        $params = ['usuario_id' => $usuarioId, 'tenant_id' => $tenantId];
        if ($matriculaId !== null) {
            // Avaliação pontual (job / aviso com status pendente): não exige ativa/vigente.
            $sql .= " AND m.id = :matricula_id";
            $params['matricula_id'] = $matriculaId;
        } else {
            $sql .= " AND sm.codigo = 'ativa'
                  AND COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= CURDATE()";
        }
        if ($modalidadeId !== null) {
            $sql .= " AND p.modalidade_id = :modalidade_id";
            $params['modalidade_id'] = $modalidadeId;
        }
        $sql .= " ORDER BY m.proxima_data_vencimento DESC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Só "sem plano" quando NÃO existe matrícula ativa/vigente. Se a matrícula
        // existe mas o vencimento está ausente/inválido (estado inconsistente, raro),
        // NÃO desistimos: usamos o mês de calendário como janela do ciclo, mantendo
        // a MESMA contagem por período. Assim evitamos divergir para outra mecânica.
        if (!$row) {
            return ['tem_plano' => false];
        }

        // Diária não usa ciclo mensal de reposição — acesso é só pela vigência.
        if ((int) ($row['duracao_dias'] ?? 0) === 1) {
            return [
                'tem_plano' => true,
                'eh_diaria' => true,
                'plano_nome' => $row['plano_nome'],
                'permite_reposicao' => false,
                'checkins_semanais' => 0,
                'modalidade_id' => $row['modalidade_id'] !== null ? (int) $row['modalidade_id'] : null,
                'meses_ciclo' => 1,
                'contrato_multimes' => false,
                'limite_mensal' => PHP_INT_MAX,
                'checkins_no_ciclo' => 0,
                'dias_checkin' => [],
            ];
        }

        $checkinsSemanais = (int) $row['checkins_semanais'];
        $mesesCiclo = max(1, (int) $row['ciclo_meses']);
        $hoje = new \DateTime(date('Y-m-d'));
        $vencRaw = $row['vencimento'] ?? null;
        $anchorDay = null;

        if (empty($vencRaw) || $vencRaw === '0000-00-00') {
            // Sem âncora de vencimento utilizável → janela = mês de calendário atual.
            $inicio = new \DateTime(date('Y-m-01'));
            $fim    = new \DateTime(date('Y-m-01', strtotime('first day of next month')));
        } else {
            $venc = new \DateTime($vencRaw);
            $anchorDay = (int) $venc->format('j');

            // Encontrar o ciclo [inicio, fim) que contém hoje (fim exclusivo).
            $fim = clone $venc;
            if ($fim <= $hoje) {
                while ($fim <= $hoje) {
                    $fim = $this->addMonthsAnchor($fim, 1, $anchorDay);
                }
            } else {
                while (true) {
                    $anterior = $this->addMonthsAnchor($fim, -1, $anchorDay);
                    if ($anterior > $hoje) {
                        $fim = $anterior;
                    } else {
                        break;
                    }
                }
            }
            $inicio = $this->addMonthsAnchor($fim, -1, $anchorDay);
        }

        $diasNoCiclo = (int) $inicio->diff($fim)->days;
        $janelaMensal = $this->limiteJanelaCheckins($checkinsSemanais, $diasNoCiclo);
        $limiteMensal = $janelaMensal['limite'];

        $inicioStr = $inicio->format('Y-m-d');
        $fimStr = $fim->format('Y-m-d');
        $modalidade = $row['modalidade_id'] !== null ? (int) $row['modalidade_id'] : null;

        $diasCheckin = $this->listarCheckinsPorPeriodo($usuarioId, $modalidade, $inicioStr, $fimStr);

        $resultado = [
            'tem_plano'           => true,
            'plano_nome'          => $row['plano_nome'],
            'permite_reposicao'   => (bool) $row['permite_reposicao'],
            'checkins_semanais'   => $checkinsSemanais,
            'modalidade_id'       => $modalidade,
            'meses_ciclo'         => $mesesCiclo,
            'contrato_multimes'   => false,
            'ciclo_inicio'        => $inicioStr,
            'ciclo_fim'           => $fimStr,
            'dias_no_ciclo'       => $diasNoCiclo,
            'semanas'             => $janelaMensal['semanas'],
            'bonus_cinco_semanas' => $janelaMensal['bonus'] > 0,
            'limite_mensal'       => $limiteMensal,
            'checkins_no_ciclo'   => count($diasCheckin),
            'dias_checkin'        => $diasCheckin,
        ];

        if ($mesesCiclo >= 2 && $anchorDay !== null) {
            $periodoFim = new \DateTime($vencRaw);
            $periodoInicio = $this->addMonthsAnchor($periodoFim, -$mesesCiclo, $anchorDay);

            $dataInicioRaw = $row['data_inicio'] ?? null;
            if (!empty($dataInicioRaw) && $dataInicioRaw !== '0000-00-00') {
                $dataInicio = new \DateTime($dataInicioRaw);
                if ($dataInicio > $periodoInicio) {
                    $periodoInicio = $dataInicio;
                }
            }

            $periodoInicioStr = $periodoInicio->format('Y-m-d');
            $periodoFimStr = $periodoFim->format('Y-m-d');
            $limitePeriodo = $this->calcularLimiteContrato($checkinsSemanais, $periodoInicio, $periodoFim, $anchorDay);
            $checkinsNoPeriodo = $this->contarCheckinsNoPeriodo($usuarioId, $modalidade, $periodoInicioStr, $periodoFimStr);
            $ultimoMesInicio = $this->addMonthsAnchor($periodoFim, -1, $anchorDay);

            $resultado['contrato_multimes'] = true;
            $resultado['periodo_inicio'] = $periodoInicioStr;
            $resultado['periodo_fim'] = $periodoFimStr;
            $resultado['limite_periodo'] = $limitePeriodo;
            $resultado['checkins_no_periodo'] = $checkinsNoPeriodo;
            $resultado['ultimo_mes_contrato'] = $hoje >= $ultimoMesInicio;
        }

        return $resultado;
    }

    /**
     * Avalia o limite mensal (planos com reposição) usando o CICLO DE COBRANÇA.
     *
     * obterCicloCheckins já trata vencimento ausente/inválido usando o mês de
     * calendário como janela do ciclo (mesma contagem por período). O fail-safe
     * abaixo só roda se NÃO houver matrícula no cálculo do ciclo (divergência rara
     * com o chamador) e também valida por período. NUNCA libera sem validar.
     *
     * @param array $planoInfo Retorno de obterLimiteCheckinsPlano (tem_plano + permite_reposicao já validados pelo chamador)
     * @return array|null null se está DENTRO do limite; array de "detalhes" se o limite foi ATINGIDO.
     */
    /**
     * Enriquece o payload de limite mensal com direito/usados/excesso e mensagem
     * amigável (ciclo do plano, não "mês de calendário").
     */
    public static function formatarDetalhesLimiteMensal(array $detalhes, bool $paraAluno = true): array
    {
        $direito = (int) ($detalhes['limite_mensal'] ?? 0);
        $usados = (int) ($detalhes['checkins_mes'] ?? 0);
        $excesso = max(0, $usados - $direito);
        $ciclo = (string) ($detalhes['mes_referencia'] ?? '');
        $plano = (string) ($detalhes['plano'] ?? 'seu plano');
        $sujeito = $paraAluno ? 'Você' : 'O aluno';
        $ref = $ciclo !== '' ? $ciclo : $plano;

        $mensagem = sprintf(
            '%s atingiu o limite de check-ins do ciclo do plano (%s). Direito: %d | Usados: %d | Excedeu: %d.',
            $sujeito,
            $ref,
            $direito,
            $usados,
            $excesso
        );

        $detalhes['direito'] = $direito;
        $detalhes['usados'] = $usados;
        $detalhes['excesso'] = $excesso;
        $detalhes['mensagem'] = $mensagem;

        return $detalhes;
    }

    public function avaliarLimiteMensalReposicao(
        int $usuarioId,
        int $tenantId,
        ?int $modalidadeId,
        array $planoInfo,
        ?int $matriculaId = null
    ): ?array {
        // Diária: sem teto mensal (acesso controlado pela vigência).
        if (!empty($planoInfo['eh_diaria']) || (int) ($planoInfo['duracao_dias'] ?? 0) === 1) {
            return null;
        }

        $ciclo = $this->obterCicloCheckins($usuarioId, $tenantId, $modalidadeId, $matriculaId);

        if (!empty($ciclo['tem_plano'])) {
            if (!empty($ciclo['contrato_multimes'])) {
                if ($ciclo['checkins_no_periodo'] >= $ciclo['limite_periodo']) {
                    return self::formatarDetalhesLimiteMensal([
                        'plano'               => $ciclo['plano_nome'],
                        'checkins_semanais'   => $ciclo['checkins_semanais'],
                        'limite_mensal'       => $ciclo['limite_periodo'],
                        'checkins_mes'        => $ciclo['checkins_no_periodo'],
                        'permite_reposicao'   => (bool) ($ciclo['permite_reposicao'] ?? false),
                        'bonus_cinco_semanas' => false,
                        'limite_contrato'     => true,
                        'meses_ciclo'         => $ciclo['meses_ciclo'],
                        'mes_referencia'      => date('d/m', strtotime($ciclo['periodo_inicio'])) . ' a '
                            . date('d/m', strtotime($ciclo['periodo_fim'] . ' -1 day')),
                        'ciclo_inicio'        => $ciclo['periodo_inicio'],
                        'ciclo_fim'           => $ciclo['periodo_fim'],
                        'dias_checkin'        => $ciclo['dias_checkin'],
                    ]);
                }
                if (empty($ciclo['ultimo_mes_contrato'])) {
                    return null;
                }
            }

            if ($ciclo['checkins_no_ciclo'] < $ciclo['limite_mensal']) {
                return null;
            }
            return self::formatarDetalhesLimiteMensal([
                'plano'               => $ciclo['plano_nome'],
                'checkins_semanais'   => $ciclo['checkins_semanais'],
                'limite_mensal'       => $ciclo['limite_mensal'],
                'checkins_mes'        => $ciclo['checkins_no_ciclo'],
                'permite_reposicao'   => (bool) ($ciclo['permite_reposicao'] ?? false),
                'bonus_cinco_semanas' => (bool) $ciclo['bonus_cinco_semanas'],
                // ciclo_fim é EXCLUSIVO (próximo vencimento). Exibe o último dia
                // INCLUSIVO (fim - 1 dia) para não confundir o usuário sobre quais
                // datas contam no limite. Ex.: [26/05, 26/06) → "26/05 a 25/06".
                'mes_referencia'      => date('d/m', strtotime($ciclo['ciclo_inicio'])) . ' a ' . date('d/m', strtotime($ciclo['ciclo_fim'] . ' -1 day')),
                'ciclo_inicio'        => $ciclo['ciclo_inicio'],
                'ciclo_fim'           => $ciclo['ciclo_fim'],
                'dias_checkin'        => $ciclo['dias_checkin'],
            ]);
        }

        // Fail-safe: só alcançado se NÃO houver matrícula no cálculo do ciclo
        // (divergência rara com o chamador). Valida por mês de calendário usando a
        // MESMA contagem por período [inicio, fim) do caminho normal — uma única
        // mecânica de contagem, evitando resultados diferentes entre os caminhos.
        $inicio = date('Y-m-01');
        $fim    = date('Y-m-01', strtotime('first day of next month'));

        // Mesma fórmula do ciclo (ceil(dias/7)) para o bônus de 5ª semana.
        $diasNoMes    = (int) (new \DateTime($inicio))->format('t');
        $semanasNoMes = (int) ceil($diasNoMes / 7);
        $bonus        = ($semanasNoMes >= 5) ? 1 : 0;

        $limiteMensal  = (int) ($planoInfo['limite_mensal'] ?? ((int) ($planoInfo['limite'] ?? 0) * 4)) + $bonus;
        $diasCheckin   = $this->listarCheckinsPorPeriodo($usuarioId, $modalidadeId, $inicio, $fim);
        $checkinsNoMes = count($diasCheckin);
        if ($checkinsNoMes < $limiteMensal) {
            return null;
        }
        return self::formatarDetalhesLimiteMensal([
            'plano'               => $planoInfo['plano_nome'] ?? 'Plano',
            'checkins_semanais'   => (int) ($planoInfo['limite'] ?? 0),
            'limite_mensal'       => $limiteMensal,
            'checkins_mes'        => $checkinsNoMes,
            'permite_reposicao'   => true,
            'bonus_cinco_semanas' => $bonus > 0,
            // Mesmo formato de intervalo do caminho por ciclo ("d/m a d/m"): mês de
            // calendário inteiro (1º dia ao último dia inclusivo = fim - 1 dia).
            'mes_referencia'      => date('d/m', strtotime($inicio)) . ' a ' . date('d/m', strtotime($fim . ' -1 day')),
            'dias_checkin'        => $diasCheckin,
        ]);
    }

    /**
     * Avalia se a matrícula empatou o limite de check-ins do ciclo (reposição).
     * Funciona com status ativa ou pendente (não exige vigente/ativa no SELECT).
     *
     * @return array|null detalhes formatados se esgotado; null se N/A ou dentro do limite
     */
    public function avaliarLimiteMensalPorMatricula(int $matriculaId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT m.id, m.tenant_id, a.usuario_id, p.modalidade_id, p.checkins_semanais,
                    p.nome AS plano_nome, p.duracao_dias,
                    CASE
                        WHEN m.plano_ciclo_id IS NOT NULL THEN COALESCE(pc.permite_reposicao, 0)
                        ELSE COALESCE((
                            SELECT MAX(pc2.permite_reposicao)
                            FROM plano_ciclos pc2
                            WHERE pc2.plano_id = p.id
                              AND pc2.tenant_id = m.tenant_id
                              AND pc2.ativo = 1
                        ), 0)
                    END AS permite_reposicao
             FROM matriculas m
             INNER JOIN planos p ON p.id = m.plano_id
             LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id AND pc.tenant_id = m.tenant_id
             INNER JOIN alunos a ON a.id = m.aluno_id
             WHERE m.id = :matricula_id
             LIMIT 1"
        );
        $stmt->execute(['matricula_id' => $matriculaId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $ehDiaria = (int) ($row['duracao_dias'] ?? 0) === 1;
        $permiteReposicao = (bool) ($row['permite_reposicao'] ?? false);
        $checkinsSemanais = (int) ($row['checkins_semanais'] ?? 0);
        if ($ehDiaria || !$permiteReposicao || $checkinsSemanais <= 0) {
            return null;
        }

        $usuarioId = (int) $row['usuario_id'];
        $tenantId = (int) $row['tenant_id'];
        $modalidadeId = $row['modalidade_id'] !== null ? (int) $row['modalidade_id'] : null;
        $planoInfo = [
            'tem_plano' => true,
            'permite_reposicao' => true,
            'limite' => $checkinsSemanais,
            'limite_mensal' => $checkinsSemanais * 4,
            'plano_nome' => $row['plano_nome'],
            'eh_diaria' => false,
            'duracao_dias' => (int) ($row['duracao_dias'] ?? 0),
        ];

        return $this->avaliarLimiteMensalReposicao(
            $usuarioId,
            $tenantId,
            $modalidadeId,
            $planoInfo,
            $matriculaId
        );
    }

    /**
     * Se o limite do ciclo estiver empatado, marca a matrícula como pendente (só se estiver ativa).
     */
    public function marcarPendenteSeLimiteCicloEsgotado(int $matriculaId): bool
    {
        if ($this->avaliarLimiteMensalPorMatricula($matriculaId) === null) {
            return false;
        }

        $stmt = $this->db->prepare(
            "UPDATE matriculas m
             INNER JOIN status_matricula sm ON sm.id = m.status_id
             SET m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'pendente' LIMIT 1),
                 m.updated_at = NOW()
             WHERE m.id = :matricula_id
               AND sm.codigo = 'ativa'"
        );
        $stmt->execute(['matricula_id' => $matriculaId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Libera renovação/pagamento antecipado em matrícula ainda ativa quando:
     * - o limite de check-ins do ciclo (reposição) foi atingido, ou
     * - o acesso / parcela em aberto já venceu (ou vence hoje).
     *
     * @return array{liberar: bool, motivo: string, detalhes_limite: ?array}
     */
    public function avaliarLiberacaoPagamentoRenovacao(
        int $usuarioId,
        int $tenantId,
        ?int $modalidadeId = null,
        ?string $acessoAte = null,
        ?string $parcelaVencimento = null
    ): array {
        $hoje = date('Y-m-d');

        if ($acessoAte && $acessoAte <= $hoje) {
            return [
                'liberar' => true,
                'motivo' => 'acesso_vencido',
                'detalhes_limite' => null,
            ];
        }

        if ($parcelaVencimento && $parcelaVencimento <= $hoje) {
            return [
                'liberar' => true,
                'motivo' => 'parcela_vencida',
                'detalhes_limite' => null,
            ];
        }

        $planoInfo = $this->obterLimiteCheckinsPlano($usuarioId, $tenantId, $modalidadeId);
        if (!empty($planoInfo['tem_plano']) && !empty($planoInfo['permite_reposicao']) && (int) ($planoInfo['limite'] ?? 0) > 0) {
            $detalhes = $this->avaliarLimiteMensalReposicao($usuarioId, $tenantId, $modalidadeId, $planoInfo);
            if ($detalhes !== null) {
                return [
                    'liberar' => true,
                    'motivo' => 'limite_checkins_ciclo',
                    'detalhes_limite' => $detalhes,
                ];
            }
        }

        return [
            'liberar' => false,
            'motivo' => 'ciclo_em_andamento',
            'detalhes_limite' => null,
        ];
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
        $sql = "SELECT p.checkins_semanais, p.nome as plano_nome, p.modalidade_id,
                       p.duracao_dias,
                       CASE
                           WHEN m.plano_ciclo_id IS NOT NULL THEN COALESCE(pc.permite_reposicao, 0)
                           ELSE COALESCE((
                               SELECT MAX(pc2.permite_reposicao)
                               FROM plano_ciclos pc2
                               WHERE pc2.plano_id = p.id
                                 AND pc2.tenant_id = m.tenant_id
                                 AND pc2.ativo = 1
                           ), 0)
                       END as permite_reposicao
             FROM matriculas m
             INNER JOIN planos p ON m.plano_id = p.id
             LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id AND pc.tenant_id = m.tenant_id
             INNER JOIN alunos a ON a.id = m.aluno_id
             INNER JOIN status_matricula sm ON sm.id = m.status_id
             WHERE a.usuario_id = :usuario_id
             AND m.tenant_id = :tenant_id
             AND sm.codigo = 'ativa'
             AND COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= CURDATE()";
        
        $params = [
            'usuario_id' => $usuarioId,
            'tenant_id' => $tenantId
        ];
        
        // Se modalidade foi especificada, filtrar por ela. Usa !== null (não
        // truthiness) para tratar o contrato nullable de forma consistente com
        // obterCicloCheckins — ambos selecionam a MESMA matrícula.
        if ($modalidadeId !== null) {
            $sql .= " AND p.modalidade_id = :modalidade_id";
            $params['modalidade_id'] = $modalidadeId;
        }
        
        $sql .= " ORDER BY m.proxima_data_vencimento DESC LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch();
        if ($result === false) {
            return [
                'limite' => 0,
                'plano_nome' => 'Sem plano',
                'tem_plano' => false,
                'modalidade_id' => null,
                'permite_reposicao' => false,
                'limite_mensal' => null,
                'eh_diaria' => false,
                'duracao_dias' => null,
            ];
        }

        // Diária (duracao_dias = 1): não aplica teto semanal/mensal.
        // O acesso é controlado pela vigência da matrícula (e finalização após presença).
        $ehDiaria = (int) ($result['duracao_dias'] ?? 0) === 1;
        if ($ehDiaria) {
            return [
                'limite' => 0,
                'plano_nome' => $result['plano_nome'],
                'tem_plano' => true,
                'modalidade_id' => $result['modalidade_id'] !== null ? (int) $result['modalidade_id'] : null,
                'permite_reposicao' => false,
                'limite_mensal' => null,
                'eh_diaria' => true,
                'duracao_dias' => 1,
            ];
        }

        $permiteReposicao = (bool) $result['permite_reposicao'];
        $checkinsSemanais = (int) $result['checkins_semanais'];
        return [
            'limite' => $checkinsSemanais,
            'plano_nome' => $result['plano_nome'],
            'tem_plano' => true,
            'modalidade_id' => $result['modalidade_id'] !== null ? (int) $result['modalidade_id'] : null,
            'permite_reposicao' => $permiteReposicao,
            // Para planos com reposição, o limite efetivo é mensal (4x o semanal)
            'limite_mensal' => $permiteReposicao ? ($checkinsSemanais * 4) : null,
            'eh_diaria' => false,
            'duracao_dias' => (int) ($result['duracao_dias'] ?? 0),
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
        
        $ok = $stmt->execute([
            'presente' => $presente ? 1 : 0,
            'confirmado_por' => $confirmadoPor,
            'checkin_id' => $checkinId
        ]);

        if ($ok && $presente) {
            $this->finalizarMatriculasDiariasPorCheckins([$checkinId]);
        }

        return $ok;
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

        $atualizados = $stmt->rowCount();
        if ($presente && $atualizados > 0) {
            $this->finalizarMatriculasDiariasPorCheckins($checkinIds);
        }
        
        return $atualizados;
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
            $checkinsPresentes = [];
            
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
                        $checkinsPresentes[] = (int) $checkinId;
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

            if (!empty($checkinsPresentes)) {
                $this->finalizarMatriculasDiariasPorCheckins($checkinsPresentes);
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
     * Finaliza matrículas diárias (duracao_dias = 1) quando presença for confirmada
     * @param array $checkinIds IDs de check-ins confirmados como presentes
     */
    private function finalizarMatriculasDiariasPorCheckins(array $checkinIds): void
    {
        if (empty($checkinIds)) {
            return;
        }

        $statusId = $this->buscarStatusConcluidaId();
        if (!$statusId) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($checkinIds), '?'));

        $sql = "
            UPDATE matriculas m
            INNER JOIN planos p ON p.id = m.plano_id
            INNER JOIN checkins c ON c.aluno_id = m.aluno_id AND c.tenant_id = m.tenant_id
            SET m.status_id = ?,
                m.updated_at = NOW()
            WHERE c.id IN ($placeholders)
              AND c.presente = 1
              AND p.duracao_dias = 1
              AND m.status_id = (SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1)
        ";

        $params = array_merge([$statusId], $checkinIds);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Buscar status "concluida" (fallback para "finalizada")
     */
    private function buscarStatusConcluidaId(): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM status_matricula WHERE codigo = ? LIMIT 1");
        $stmt->execute(['concluida']);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }

        $stmt->execute(['finalizada']);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
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
               AND MONTH(COALESCE(c.data_checkin_date, DATE(c.created_at))) = MONTH(CURRENT_DATE())
               AND YEAR(COALESCE(c.data_checkin_date, DATE(c.created_at))) = YEAR(CURRENT_DATE())
               AND (c.presente IS NULL OR c.presente = 1)";
        
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
                      AND MONTH(COALESCE(c.data_checkin_date, DATE(c.created_at))) = MONTH(CURRENT_DATE())
                      AND YEAR(COALESCE(c.data_checkin_date, DATE(c.created_at))) = YEAR(CURRENT_DATE())
                      AND (c.presente IS NULL OR c.presente = 1)
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
                  AND MONTH(COALESCE(c.data_checkin_date, DATE(c.created_at))) = MONTH(CURRENT_DATE())
                  AND YEAR(COALESCE(c.data_checkin_date, DATE(c.created_at))) = YEAR(CURRENT_DATE())
                  AND (c.presente IS NULL OR c.presente = 1)";
            
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
