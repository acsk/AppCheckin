<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Assinaturas do tenant (painel admin).
 *
 * Paridade com AssinaturaController::listarAssinaturasAdmin (Slim).
 */
class AdminAssinaturaRepository
{
    /**
     * @param  array{status?: string, tipo_cobranca?: string, busca?: string, gateway?: string, tenant_id?: int|string}  $filtros
     * @return array{total: int, rows: list<array<string, mixed>>}
     */
    public function listar(?int $tenantId, array $filtros, int $page, int $perPage): array
    {
        [$where, $binds] = $this->montarWhere($tenantId, $filtros);
        $from = $this->sqlFromListagem($tenantId === null && empty($filtros['tenant_id']));

        $total = (int) (DB::selectOne("SELECT COUNT(*) as total {$from} {$where}", $binds)->total ?? 0);

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
     * Contagens por status (mesmos filtros da listagem, sem o recorte de status).
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, int>
     */
    public function contagensPorStatus(?int $tenantId, array $filtros): array
    {
        $filtrosSemStatus = $filtros;
        $filtrosSemStatus['status'] = '';
        [$where, $binds] = $this->montarWhere($tenantId, $filtrosSemStatus);
        $from = $this->sqlFromListagem($tenantId === null && empty($filtros['tenant_id']));

        $rows = DB::select(
            "SELECT COALESCE(s.codigo, '') as codigo, COUNT(*) as total {$from} {$where} GROUP BY s.codigo",
            $binds
        );

        $counts = ['todas' => 0];
        foreach ($rows as $row) {
            $codigo = (string) ($row->codigo ?? '');
            $total = (int) ($row->total ?? 0);
            $counts[$codigo] = $total;
            $counts['todas'] += $total;
        }

        return $counts;
    }

    /**
     * KPIs da tela de assinaturas (independentes dos chips de status).
     *
     * @return array<string, mixed>
     */
    public function resumoPainel(?int $tenantId): array
    {
        $tenantSql = $tenantId !== null ? ' AND a.tenant_id = ?' : '';
        $binds = $tenantId !== null ? [$tenantId] : [];

        $recorrentes = DB::selectOne(
            "SELECT COUNT(*) as total, COALESCE(SUM(a.valor), 0) as valor
             FROM assinaturas a
             LEFT JOIN assinatura_status s ON s.id = a.status_id
             WHERE a.tipo_cobranca = 'recorrente' AND s.codigo = 'ativa'{$tenantSql}",
            $binds
        );

        $pendentes = DB::selectOne(
            "SELECT COUNT(*) as total, COALESCE(SUM(a.valor), 0) as valor
             FROM assinaturas a
             LEFT JOIN assinatura_status s ON s.id = a.status_id
             WHERE s.codigo = 'pendente'{$tenantSql}",
            $binds
        );

        $pagasMes = ['total' => 0, 'valor' => 0.0];
        $webhooksErro = 0;
        if ($tenantId !== null) {
            $pagasRow = DB::selectOne(
                'SELECT COUNT(*) as total, COALESCE(SUM(valor), 0) as valor
                 FROM pagamentos_plano
                 WHERE tenant_id = ?
                   AND status_pagamento_id = 2
                   AND data_pagamento >= DATE_FORMAT(CURDATE(), \'%Y-%m-01\')',
                [$tenantId]
            );
            $pagasMes = [
                'total' => (int) ($pagasRow->total ?? 0),
                'valor' => (float) ($pagasRow->valor ?? 0),
            ];

            if (Schema::hasTable('webhook_payloads_mercadopago')) {
                $webhooksErro = (int) (DB::selectOne(
                    "SELECT COUNT(*) as total FROM webhook_payloads_mercadopago
                     WHERE tenant_id = ?
                       AND erro_processamento IS NOT NULL
                       AND TRIM(erro_processamento) <> ''",
                    [$tenantId]
                )->total ?? 0);
            }
        }

        return [
            'recorrentes_ativas' => [
                'total' => (int) ($recorrentes->total ?? 0),
                'valor' => (float) ($recorrentes->valor ?? 0),
            ],
            'pagas_este_mes' => $pagasMes,
            'pendentes' => [
                'total' => (int) ($pendentes->total ?? 0),
                'valor' => (float) ($pendentes->valor ?? 0),
            ],
            'problemas' => [
                'total' => $webhooksErro,
                'webhooks_erro' => $webhooksErro,
                'valores_divergentes' => 0,
            ],
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
            'plano_checkins' => isset($row['plano_checkins']) ? (int) $row['plano_checkins'] : null,
            'ciclo' => [
                'nome' => $row['ciclo_nome'] ?? 'Mensal',
                'meses' => (int) ($row['ciclo_meses'] ?? 1),
            ],
            'gateway' => $row['gateway_nome'] ?? null,
            'gateway_codigo' => $row['gateway_codigo'] ?? null,
            'data_inicio' => $row['data_inicio'],
            'data_fim' => $row['data_fim'],
            'proxima_cobranca' => $row['proxima_cobranca'],
            'ultima_cobranca' => $row['ultima_cobranca'],
            'external_reference' => $row['external_reference'] ?? null,
            'mp_preapproval_id' => $row['mp_preapproval_id'] ?? null,
            'preference_id' => $row['gateway_preference_id'] ?? null,
            'provedor_id' => $row['mp_preapproval_id'] ?? $row['gateway_preference_id'] ?? null,
            'payment_url' => $row['payment_url'] ?? null,
            'criado_em' => $row['criado_em'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{0: string, 1: list<mixed>}
     */
    private function montarWhere(?int $tenantId, array $filtros): array
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

        $statusFiltro = trim((string) ($filtros['status'] ?? ''));
        if ($statusFiltro !== '') {
            $where .= ' AND s.codigo = ?';
            $binds[] = $statusFiltro;
        }

        $tipoCobranca = trim((string) ($filtros['tipo_cobranca'] ?? ''));
        if ($tipoCobranca !== '') {
            $where .= ' AND a.tipo_cobranca = ?';
            $binds[] = $tipoCobranca;
        }

        $gateway = strtolower(trim((string) ($filtros['gateway'] ?? '')));
        if ($gateway === 'asaas') {
            $where .= ' AND (LOWER(COALESCE(g.codigo, \'\')) LIKE \'%asaas%\' OR a.gateway_preference_id LIKE \'pay_%\')';
        } elseif ($gateway === 'mercadopago') {
            $where .= ' AND NOT (LOWER(COALESCE(g.codigo, \'\')) LIKE \'%asaas%\' OR a.gateway_preference_id LIKE \'pay_%\')';
        }

        $busca = trim((string) ($filtros['busca'] ?? ''));
        if ($busca !== '') {
            $like = "%{$busca}%";
            if ($tenantId === null && empty($filtros['tenant_id'])) {
                $where .= ' AND (al.nome LIKE ? OR u.nome LIKE ? OR p.nome LIKE ? OR tn.nome LIKE ?)';
                array_push($binds, $like, $like, $like, $like);
            } else {
                $where .= ' AND (al.nome LIKE ? OR u.nome LIKE ? OR p.nome LIKE ?)';
                array_push($binds, $like, $like, $like);
            }
        }

        return [$where, $binds];
    }

    private function sqlFromListagem(bool $incluirTenant = false): string
    {
        $tenantJoin = $incluirTenant ? ' LEFT JOIN tenants tn ON tn.id = a.tenant_id' : '';

        return "FROM assinaturas a
             LEFT JOIN assinatura_status s ON s.id = a.status_id
             LEFT JOIN alunos al ON al.id = a.aluno_id
             LEFT JOIN usuarios u ON u.id = al.usuario_id
             LEFT JOIN assinatura_gateways g ON g.id = a.gateway_id
             LEFT JOIN planos p ON p.id = a.plano_id
             {$tenantJoin}";
    }

    private function sqlListagem(bool $incluirTenant = false): string
    {
        $tenantSelect = $incluirTenant ? ', tn.nome as tenant_nome' : '';
        $from = $this->sqlFromListagem($incluirTenant);

        return 'SELECT a.id, a.tenant_id, a.aluno_id, a.plano_id, a.matricula_id, a.valor, a.tipo_cobranca,
                    a.data_inicio, a.data_fim, a.proxima_cobranca, a.ultima_cobranca,
                    a.gateway_assinatura_id as mp_preapproval_id,
                    a.gateway_preference_id,
                    a.external_reference, a.payment_url, a.status_gateway,
                    a.criado_em,
                    s.codigo as status_codigo, s.nome as status_nome, s.cor as status_cor,
                    f.nome as ciclo_nome, f.meses as ciclo_meses,
                    g.nome as gateway_nome, g.codigo as gateway_codigo,
                    p.nome as plano_nome,
                    p.checkins_semanais as plano_checkins,
                    mo.nome as modalidade_nome,
                    COALESCE(al.nome, u.nome) as aluno_nome,
                    u.email as aluno_email'.$tenantSelect.'
             '.$from.'
             LEFT JOIN assinatura_frequencias f ON f.id = a.frequencia_id
             LEFT JOIN modalidades mo ON mo.id = p.modalidade_id';
    }
}
