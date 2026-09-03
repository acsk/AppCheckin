<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Assinaturas do tenant (painel admin).
 *
 * Paridade com AssinaturaController::listarAssinaturasAdmin (Slim).
 */
class AdminAssinaturaRepository
{
    /**
     * @param  array{status?: string, tipo_cobranca?: string, busca?: string, tenant_id?: int|string}  $filtros
     * @return array{total: int, rows: list<array<string, mixed>>}
     */
    public function listar(?int $tenantId, array $filtros, int $page, int $perPage): array
    {
        $where = 'WHERE 1=1';
        $binds = [];

        if ($tenantId !== null) {
            $where .= ' AND a.tenant_id = ?';
            $binds[] = $tenantId;
        } elseif (! empty($filtros['tenant_id'])) {
            $where .= ' AND a.tenant_id = ?';
            $binds[] = (int) $filtros['tenant_id'];
        }

        $statusFiltro = trim($filtros['status'] ?? '');
        if ($statusFiltro !== '') {
            $where .= ' AND s.codigo = ?';
            $binds[] = $statusFiltro;
        }

        $tipoCobranca = trim($filtros['tipo_cobranca'] ?? '');
        if ($tipoCobranca !== '') {
            $where .= ' AND a.tipo_cobranca = ?';
            $binds[] = $tipoCobranca;
        }

        $busca = trim($filtros['busca'] ?? '');
        if ($busca !== '') {
            if ($tenantId === null && empty($filtros['tenant_id'])) {
                $where .= ' AND (al.nome LIKE ? OR u.nome LIKE ? OR tn.nome LIKE ?)';
                $binds[] = "%{$busca}%";
                $binds[] = "%{$busca}%";
                $binds[] = "%{$busca}%";
            } else {
                $where .= ' AND (al.nome LIKE ? OR u.nome LIKE ?)';
                $binds[] = "%{$busca}%";
                $binds[] = "%{$busca}%";
            }
        }

        $joinTenants = ($tenantId === null && empty($filtros['tenant_id']))
            ? ' LEFT JOIN tenants tn ON tn.id = a.tenant_id'
            : '';

        $totalRow = DB::selectOne(
            "SELECT COUNT(*) as total
             FROM assinaturas a
             LEFT JOIN assinatura_status s ON s.id = a.status_id
             LEFT JOIN alunos al ON al.id = a.aluno_id
             LEFT JOIN usuarios u ON u.id = al.usuario_id
             {$joinTenants}
             {$where}",
            $binds
        );
        $total = (int) ($totalRow->total ?? 0);

        $offset = ($page - 1) * $perPage;
        $sql = $this->sqlListagem($tenantId === null && empty($filtros['tenant_id']));
        $rows = DB::select(
            "{$sql} {$where}
             ORDER BY a.criado_em DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $binds
        );

        return [
            'total' => $total,
            'rows' => array_map(fn ($row) => (array) $row, $rows),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id, ?int $tenantId): ?array
    {
        $sql = $this->sqlListagem().' WHERE a.id = ?';
        $binds = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND a.tenant_id = ?';
            $binds[] = $tenantId;
        }

        $row = DB::selectOne($sql, $binds);

        return $row ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByMatriculaId(int $matriculaId, int $tenantId): ?array
    {
        $row = DB::selectOne(
            $this->sqlListagem().' WHERE a.matricula_id = ? AND a.tenant_id = ? LIMIT 1',
            [$matriculaId, $tenantId]
        );

        return $row ? (array) $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPorAluno(int $tenantId, int $alunoId): array
    {
        $rows = DB::select(
            $this->sqlListagem().' WHERE a.tenant_id = ? AND a.aluno_id = ? ORDER BY a.criado_em DESC',
            [$tenantId, $alunoId]
        );

        return array_map(fn ($row) => (array) $row, $rows);
    }

    /**
     * @return array{total: int, rows: list<array<string, mixed>>}
     */
    public function listarSemMatricula(int $tenantId, int $page, int $perPage): array
    {
        $where = 'WHERE a.tenant_id = ? AND a.matricula_id IS NULL';
        $binds = [$tenantId];

        $total = (int) (DB::selectOne(
            "SELECT COUNT(*) as total FROM assinaturas a {$where}",
            $binds
        )->total ?? 0);

        $offset = ($page - 1) * $perPage;
        $rows = DB::select(
            $this->sqlListagem()." {$where} ORDER BY a.criado_em DESC LIMIT {$perPage} OFFSET {$offset}",
            $binds
        );

        return [
            'total' => $total,
            'rows' => array_map(fn ($row) => (array) $row, $rows),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarProximasVencer(int $tenantId, int $dias, int $limite): array
    {
        $rows = DB::select(
            $this->sqlListagem().'
             WHERE a.tenant_id = ?
               AND a.proxima_cobranca IS NOT NULL
               AND a.proxima_cobranca <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
               AND s.codigo IN (\'pendente\', \'ativa\')
             ORDER BY a.proxima_cobranca ASC
             LIMIT ?',
            [$tenantId, $dias, $limite]
        );

        return array_map(fn ($row) => (array) $row, $rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function relatorioResumo(int $tenantId): array
    {
        $rows = DB::select('
            SELECT s.codigo, s.nome, COUNT(*) as total, COALESCE(SUM(a.valor), 0) as valor_total
            FROM assinaturas a
            INNER JOIN assinatura_status s ON s.id = a.status_id
            WHERE a.tenant_id = ?
            GROUP BY s.codigo, s.nome
            ORDER BY total DESC
        ', [$tenantId]);

        $porStatus = array_map(fn ($r) => [
            'status' => $r->codigo,
            'status_nome' => $r->nome,
            'total' => (int) $r->total,
            'valor_total' => (float) $r->valor_total,
        ], $rows);

        $semMatricula = (int) (DB::selectOne(
            'SELECT COUNT(*) as total FROM assinaturas WHERE tenant_id = ? AND matricula_id IS NULL',
            [$tenantId]
        )->total ?? 0);

        return [
            'por_status' => $porStatus,
            'sem_matricula' => $semMatricula,
            'total' => array_sum(array_column($porStatus, 'total')),
        ];
    }

    public function statusIdPorCodigo(string $codigo, int $fallback = 1): int
    {
        $row = DB::selectOne('SELECT id FROM assinatura_status WHERE codigo = ? LIMIT 1', [$codigo]);

        return $row ? (int) $row->id : $fallback;
    }

    /**
     * @param  array<string, mixed>  $matricula
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function criarParaMatricula(
        int $tenantId,
        int $matriculaId,
        int $alunoId,
        int $planoId,
        array $matricula,
        array $data,
        ?int $adminId
    ): ?array {
        $statusId = $this->statusIdPorCodigo('pendente', 1);
        $gatewayId = (int) (DB::selectOne("SELECT id FROM assinatura_gateways WHERE codigo = 'mercadopago' LIMIT 1")->id ?? 1);
        $frequenciaId = (int) (DB::selectOne("SELECT id FROM assinatura_frequencias WHERE codigo = 'mensal' LIMIT 1")->id ?? 4);

        $dataInicio = $data['data_inicio'] ?? $matricula['data_inicio'] ?? date('Y-m-d');
        $valor = isset($data['valor']) ? (float) $data['valor'] : (float) ($matricula['valor'] ?? 0);
        $proximaCobranca = $matricula['proxima_data_vencimento'] ?? $matricula['data_vencimento'] ?? null;
        $matriculaIdDb = $matriculaId > 0 ? $matriculaId : null;

        DB::insert('
            INSERT INTO assinaturas
            (tenant_id, matricula_id, aluno_id, plano_id, gateway_id, status_id, status_gateway,
             valor, tipo_cobranca, frequencia_id, dia_cobranca, data_inicio, proxima_cobranca, criado_em, atualizado_em)
            VALUES (?, ?, ?, ?, ?, ?, \'pending\', ?, \'recorrente\', ?, ?, ?, ?, NOW(), NOW())
        ', [
            $tenantId,
            $matriculaIdDb,
            $alunoId,
            $planoId,
            $gatewayId,
            $statusId,
            $valor,
            $frequenciaId,
            (int) date('d', strtotime($dataInicio)),
            $dataInicio,
            $proximaCobranca,
        ]);

        $id = (int) DB::getPdo()->lastInsertId();

        return $this->findById($id, $tenantId);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function atualizar(int $id, int $tenantId, array $fields): bool
    {
        $allowed = ['valor', 'data_inicio', 'data_fim', 'proxima_cobranca', 'ultima_cobranca', 'motivo_cancelamento'];
        $sets = [];
        $binds = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $fields)) {
                $sets[] = "{$field} = ?";
                $binds[] = $fields[$field];
            }
        }

        if (array_key_exists('status_codigo', $fields)) {
            $sets[] = 'status_id = ?';
            $binds[] = $this->statusIdPorCodigo((string) $fields['status_codigo']);
        }

        if ($sets === []) {
            return false;
        }

        $sets[] = 'atualizado_em = NOW()';
        $binds[] = $id;
        $binds[] = $tenantId;

        return DB::update(
            'UPDATE assinaturas SET '.implode(', ', $sets).' WHERE id = ? AND tenant_id = ?',
            $binds
        ) > 0;
    }

    public function atualizarStatus(int $id, int $tenantId, string $statusCodigo, ?string $statusGateway = null, ?string $motivo = null): void
    {
        $sets = ['status_id = ?', 'atualizado_em = NOW()'];
        $binds = [$this->statusIdPorCodigo($statusCodigo)];

        if ($statusGateway !== null) {
            $sets[] = 'status_gateway = ?';
            $binds[] = $statusGateway;
        }

        if ($motivo !== null) {
            $sets[] = 'motivo_cancelamento = ?';
            $binds[] = $motivo;
        }

        $binds[] = $id;
        $binds[] = $tenantId;

        DB::update(
            'UPDATE assinaturas SET '.implode(', ', $sets).' WHERE id = ? AND tenant_id = ?',
            $binds
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function mapearDetalhe(array $row): array
    {
        $statusGateway = $row['status_gateway'] ?? 'pendente';

        return [
            'id' => (int) $row['id'],
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'tenant_nome' => $row['tenant_nome'] ?? null,
            'aluno_id' => (int) $row['aluno_id'],
            'aluno_nome' => $row['aluno_nome'] ?? '',
            'aluno_email' => $row['aluno_email'] ?? '',
            'matricula_id' => $row['matricula_id'] ? (int) $row['matricula_id'] : null,
            'plano_id' => isset($row['plano_id']) ? (int) $row['plano_id'] : null,
            'valor' => (float) $row['valor'],
            'tipo_cobranca' => $row['tipo_cobranca'] ?? 'recorrente',
            'status' => [
                'codigo' => $row['status_codigo'] ?? $statusGateway,
                'nome' => $row['status_nome'] ?? ucfirst($statusGateway),
                'cor' => $row['status_cor'] ?? '#FFA500',
            ],
            'status_gateway' => $row['status_gateway'],
            'plano_nome' => $row['plano_nome'] ?? '',
            'modalidade_nome' => $row['modalidade_nome'] ?? '',
            'ciclo' => [
                'nome' => $row['ciclo_nome'] ?? 'Mensal',
                'meses' => (int) ($row['ciclo_meses'] ?? 1),
            ],
            'gateway' => $row['gateway_nome'] ?? 'Mercado Pago',
            'data_inicio' => $row['data_inicio'],
            'data_fim' => $row['data_fim'],
            'proxima_cobranca' => $row['proxima_cobranca'],
            'ultima_cobranca' => $row['ultima_cobranca'],
            'external_reference' => $row['external_reference'] ?? null,
            'mp_preapproval_id' => $row['mp_preapproval_id'] ?? null,
            'payment_url' => $row['payment_url'] ?? null,
            'criado_em' => $row['criado_em'],
        ];
    }

    private function sqlListagem(bool $incluirTenant = false): string
    {
        $tenantSelect = $incluirTenant ? ', tn.nome as tenant_nome' : '';
        $tenantJoin = $incluirTenant ? ' LEFT JOIN tenants tn ON tn.id = a.tenant_id' : '';

        return 'SELECT a.id, a.tenant_id, a.aluno_id, a.plano_id, a.matricula_id, a.valor, a.tipo_cobranca,
                    a.data_inicio, a.data_fim, a.proxima_cobranca, a.ultima_cobranca,
                    a.gateway_assinatura_id as mp_preapproval_id,
                    a.external_reference, a.payment_url, a.status_gateway,
                    a.criado_em,
                    s.codigo as status_codigo, s.nome as status_nome, s.cor as status_cor,
                    f.nome as ciclo_nome, f.meses as ciclo_meses,
                    g.nome as gateway_nome,
                    p.nome as plano_nome,
                    mo.nome as modalidade_nome,
                    COALESCE(al.nome, u.nome) as aluno_nome,
                    u.email as aluno_email'.$tenantSelect.'
             FROM assinaturas a
             LEFT JOIN assinatura_status s ON s.id = a.status_id
             LEFT JOIN assinatura_frequencias f ON f.id = a.frequencia_id
             LEFT JOIN assinatura_gateways g ON g.id = a.gateway_id
             LEFT JOIN planos p ON p.id = a.plano_id
             LEFT JOIN modalidades mo ON mo.id = p.modalidade_id
             LEFT JOIN alunos al ON al.id = a.aluno_id
             LEFT JOIN usuarios u ON u.id = al.usuario_id'.$tenantJoin;
    }
}
