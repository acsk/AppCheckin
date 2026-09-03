<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

/**
 * Descontos de matrícula (paridade com Slim MatriculaDescontoController + Models\MatriculaDesconto).
 *
 * Tipos: `primeira_mensalidade` (só na 1ª parcela) e `recorrente` (toda parcela dentro da vigência).
 */
class AdminMatriculaDescontoService
{
    /** Campos aceitos no UPDATE, igual ao model da Slim. */
    private const CAMPOS_ATUALIZAVEIS = [
        'tipo', 'valor', 'percentual', 'vigencia_inicio', 'vigencia_fim',
        'parcelas_restantes', 'motivo', 'ativo', 'autorizado_por',
    ];

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listar(int $tenantId, int $matriculaId): array
    {
        return [
            'status' => 200,
            'body' => ['descontos' => $this->listarPorMatricula($tenantId, $matriculaId)],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buscar(int $tenantId, int $id): array
    {
        $desconto = $this->buscarPorId($tenantId, $id);
        if (! $desconto) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Desconto não encontrado']];
        }

        return ['status' => 200, 'body' => ['desconto' => $desconto]];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criar(int $tenantId, int $matriculaId, ?int $adminId, array $data): array
    {
        $errors = self::validarCriacao($data);
        if ($errors !== []) {
            return [
                'status' => 422,
                'body' => ['type' => 'error', 'message' => implode(', ', $errors)],
            ];
        }

        $matriculaExiste = DB::selectOne(
            'SELECT id FROM matriculas WHERE id = ? AND tenant_id = ?',
            [$matriculaId, $tenantId]
        );
        if (! $matriculaExiste) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Matrícula não encontrada']];
        }

        DB::insert(
            'INSERT INTO matricula_descontos
                (tenant_id, matricula_id, tipo, valor, percentual, vigencia_inicio, vigencia_fim,
                 parcelas_restantes, motivo, ativo, criado_por, autorizado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)',
            [
                $tenantId,
                $matriculaId,
                $data['tipo'],
                $data['valor'] ?? null,
                $data['percentual'] ?? null,
                $data['vigencia_inicio'] ?? date('Y-m-d'),
                $data['vigencia_fim'] ?? null,
                $data['parcelas_restantes'] ?? null,
                $data['motivo'],
                $adminId,
                $data['autorizado_por'] ?? null,
            ]
        );

        $id = (int) DB::getPdo()->lastInsertId();

        return [
            'status' => 201,
            'body' => [
                'type' => 'success',
                'message' => 'Desconto criado com sucesso',
                'desconto' => $this->buscarPorId($tenantId, $id),
                'pagamentos_atualizados' => $this->recalcularDescontosPendentes($tenantId, $matriculaId),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizar(int $tenantId, int $id, array $data): array
    {
        $desconto = $this->buscarPorId($tenantId, $id);
        if (! $desconto) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Desconto não encontrado']];
        }

        $errors = self::validarAtualizacao($data);
        if ($errors !== []) {
            return [
                'status' => 422,
                'body' => ['type' => 'error', 'message' => implode(', ', $errors)],
            ];
        }

        // Valor e percentual são mutuamente exclusivos: informar um zera o outro.
        $updateData = $data;
        if (isset($data['valor']) && $data['valor']) {
            $updateData['percentual'] = null;
        } elseif (isset($data['percentual']) && $data['percentual']) {
            $updateData['valor'] = null;
        }

        $sets = [];
        $bindings = [];
        foreach (self::CAMPOS_ATUALIZAVEIS as $campo) {
            if (array_key_exists($campo, $updateData)) {
                $sets[] = "{$campo} = ?";
                $bindings[] = $updateData[$campo];
            }
        }

        if ($sets === []) {
            return [
                'status' => 422,
                'body' => ['type' => 'error', 'message' => 'Nenhum campo para atualizar'],
            ];
        }

        $sets[] = 'updated_at = NOW()';
        $bindings[] = $tenantId;
        $bindings[] = $id;

        DB::update(
            'UPDATE matricula_descontos SET '.implode(', ', $sets).' WHERE tenant_id = ? AND id = ?',
            $bindings
        );

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'Desconto atualizado',
                'desconto' => $this->buscarPorId($tenantId, $id),
                'pagamentos_atualizados' => $this->recalcularDescontosPendentes(
                    $tenantId,
                    (int) $desconto['matricula_id']
                ),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function desativar(int $tenantId, int $id): array
    {
        $desconto = $this->buscarPorId($tenantId, $id);
        if (! $desconto) {
            return ['status' => 404, 'body' => ['type' => 'error', 'message' => 'Desconto não encontrado']];
        }

        DB::update(
            'UPDATE matricula_descontos SET ativo = 0, updated_at = NOW() WHERE tenant_id = ? AND id = ?',
            [$tenantId, $id]
        );

        return [
            'status' => 200,
            'body' => [
                'type' => 'success',
                'message' => 'Desconto desativado com sucesso',
                'pagamentos_atualizados' => $this->recalcularDescontosPendentes(
                    $tenantId,
                    (int) $desconto['matricula_id']
                ),
            ],
        ];
    }

    /**
     * Descontos que valem para uma parcela: `primeira_mensalidade` só entra na 1ª,
     * `recorrente` entra sempre que a vigência cobrir o vencimento.
     *
     * @return list<array<string, mixed>>
     */
    public function buscarAplicaveis(
        int $tenantId,
        int $matriculaId,
        string $dataVencimento,
        bool $isPrimeiraParcela
    ): array {
        $tipos = $isPrimeiraParcela
            ? "('primeira_mensalidade', 'recorrente')"
            : "('recorrente')";

        return array_map(
            static fn ($row) => (array) $row,
            DB::select(
                "SELECT *
                 FROM matricula_descontos
                 WHERE tenant_id = ?
                   AND matricula_id = ?
                   AND ativo = 1
                   AND tipo IN {$tipos}
                   AND vigencia_inicio <= ?
                   AND (vigencia_fim IS NULL OR vigencia_fim >= ?)
                   AND (parcelas_restantes IS NULL OR parcelas_restantes > 0)
                 ORDER BY tipo ASC, created_at ASC",
                [$tenantId, $matriculaId, $dataVencimento, $dataVencimento]
            )
        );
    }

    /**
     * Soma os descontos aplicáveis sobre o valor cheio da parcela.
     * Se o total passar do valor base, os descontos são reduzidos proporcionalmente.
     *
     * @param  list<array<string, mixed>>  $descontos
     * @return array{desconto_total: float, motivos: string, ids: list<int>, detalhes: list<array{matricula_desconto_id: int, valor_desconto: float}>}
     */
    public static function calcularDesconto(float $valorBase, array $descontos): array
    {
        $descontoTotal = 0.0;
        $motivos = [];
        $ids = [];
        $detalhes = [];

        foreach ($descontos as $d) {
            $valorDesconto = 0.0;
            if (($d['valor'] ?? null) !== null) {
                $valorDesconto = (float) $d['valor'];
            } elseif (($d['percentual'] ?? null) !== null) {
                $valorDesconto = $valorBase * ((float) $d['percentual'] / 100);
            }

            $descontoTotal += $valorDesconto;
            $motivos[] = $d['motivo'];
            $ids[] = (int) $d['id'];
            $detalhes[] = [
                'matricula_desconto_id' => (int) $d['id'],
                'valor_desconto' => round($valorDesconto, 2),
            ];
        }

        if ($descontoTotal > $valorBase && $descontoTotal > 0) {
            $ratio = $valorBase / $descontoTotal;
            foreach ($detalhes as &$det) {
                $det['valor_desconto'] = round($det['valor_desconto'] * $ratio, 2);
            }
            unset($det);
            $descontoTotal = $valorBase;
        }

        return [
            'desconto_total' => round($descontoTotal, 2),
            'motivos' => implode(' + ', $motivos),
            'ids' => $ids,
            'detalhes' => $detalhes,
        ];
    }

    /**
     * @param  list<array{matricula_desconto_id: int, valor_desconto: float}>  $detalhes
     */
    public function salvarDescontosAplicados(int $pagamentoPlanoId, array $detalhes): void
    {
        foreach ($detalhes as $d) {
            if ($d['valor_desconto'] > 0) {
                DB::insert(
                    'INSERT INTO pagamento_desconto_aplicado
                        (pagamento_plano_id, matricula_desconto_id, valor_desconto)
                     VALUES (?, ?, ?)',
                    [$pagamentoPlanoId, $d['matricula_desconto_id'], $d['valor_desconto']]
                );
            }
        }
    }

    /**
     * @param  list<int>  $ids
     */
    public function decrementarParcelas(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        DB::update(
            "UPDATE matricula_descontos
             SET parcelas_restantes = parcelas_restantes - 1,
                 ativo = CASE WHEN parcelas_restantes - 1 <= 0 THEN 0 ELSE ativo END,
                 updated_at = NOW()
             WHERE id IN ({$placeholders})
               AND parcelas_restantes IS NOT NULL
               AND parcelas_restantes > 0",
            $ids
        );
    }

    /**
     * Reaplica os descontos em todas as parcelas Aguardando (1) da matrícula,
     * para manter os valores em dia após criar/editar/desativar um desconto.
     *
     * @return int Quantidade de pagamentos atualizados
     */
    public function recalcularDescontosPendentes(int $tenantId, int $matriculaId): int
    {
        $pendentes = DB::select(
            'SELECT id, valor, valor_original, desconto, data_vencimento
             FROM pagamentos_plano
             WHERE tenant_id = ? AND matricula_id = ? AND status_pagamento_id = 1
             ORDER BY data_vencimento ASC',
            [$tenantId, $matriculaId]
        );

        if ($pendentes === []) {
            return 0;
        }

        $minVenc = DB::selectOne(
            'SELECT MIN(data_vencimento) as min_venc
             FROM pagamentos_plano
             WHERE tenant_id = ? AND matricula_id = ? AND status_pagamento_id != 4',
            [$tenantId, $matriculaId]
        )?->min_venc;

        $atualizados = 0;

        foreach ($pendentes as $pag) {
            $pagId = (int) $pag->id;
            $valorBase = $pag->valor_original
                ? (float) $pag->valor_original
                : (float) $pag->valor + (float) ($pag->desconto ?? 0);

            $isPrimeira = ($pag->data_vencimento === $minVenc);

            $aplicaveis = $this->buscarAplicaveis($tenantId, $matriculaId, $pag->data_vencimento, $isPrimeira);
            $info = self::calcularDesconto($valorBase, $aplicaveis);

            DB::update(
                'UPDATE pagamentos_plano
                 SET valor = ?, valor_original = ?, desconto = ?, motivo_desconto = ?, updated_at = NOW()
                 WHERE id = ? AND tenant_id = ?',
                [
                    max(0, $valorBase - $info['desconto_total']),
                    $valorBase,
                    $info['desconto_total'],
                    $info['motivos'] ?: null,
                    $pagId,
                    $tenantId,
                ]
            );

            DB::delete('DELETE FROM pagamento_desconto_aplicado WHERE pagamento_plano_id = ?', [$pagId]);
            $this->salvarDescontosAplicados($pagId, $info['detalhes']);

            $atualizados++;
        }

        return $atualizados;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function validarCriacao(array $data): array
    {
        $errors = [];

        if (empty($data['tipo']) || ! in_array($data['tipo'], ['primeira_mensalidade', 'recorrente'], true)) {
            $errors[] = 'Tipo deve ser "primeira_mensalidade" ou "recorrente"';
        }
        if (empty($data['valor']) && empty($data['percentual'])) {
            $errors[] = 'Informe valor (R$) ou percentual (%)';
        }
        if (! empty($data['valor']) && ! empty($data['percentual'])) {
            $errors[] = 'Informe apenas valor OU percentual, não ambos';
        }
        if (! empty($data['valor']) && (! is_numeric($data['valor']) || (float) $data['valor'] <= 0)) {
            $errors[] = 'Valor deve ser numérico e positivo';
        }
        if (! empty($data['percentual'])
            && (! is_numeric($data['percentual'])
                || (float) $data['percentual'] <= 0
                || (float) $data['percentual'] > 100)
        ) {
            $errors[] = 'Percentual deve estar entre 0.01 e 100';
        }
        if (empty($data['motivo'])) {
            $errors[] = 'Motivo é obrigatório';
        }
        if (! empty($data['vigencia_fim']) && ! empty($data['vigencia_inicio'])
            && $data['vigencia_fim'] < $data['vigencia_inicio']
        ) {
            $errors[] = 'Vigência fim não pode ser anterior à vigência início';
        }

        return $errors;
    }

    /**
     * Valida campos opcionais enviados no UPDATE (valor/percentual só quando presentes no payload).
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function validarAtualizacao(array $data): array
    {
        $errors = [];

        $temValor = ! empty($data['valor']);
        $temPercentual = ! empty($data['percentual']);

        if ($temValor && $temPercentual) {
            $errors[] = 'Informe apenas valor OU percentual, não ambos';
        }

        if (array_key_exists('valor', $data) && $data['valor'] !== null && $data['valor'] !== '') {
            if (! is_numeric($data['valor']) || (float) $data['valor'] <= 0) {
                $errors[] = 'Valor deve ser numérico e positivo';
            }
        }

        if (array_key_exists('percentual', $data) && $data['percentual'] !== null && $data['percentual'] !== '') {
            if (! is_numeric($data['percentual'])
                || (float) $data['percentual'] <= 0
                || (float) $data['percentual'] > 100
            ) {
                $errors[] = 'Percentual deve estar entre 0.01 e 100';
            }
        }

        return $errors;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarPorMatricula(int $tenantId, int $matriculaId): array
    {
        return array_map(
            static fn ($row) => (array) $row,
            DB::select(
                'SELECT md.*, u.nome as criado_por_nome, ua.nome as autorizado_por_nome
                 FROM matricula_descontos md
                 LEFT JOIN usuarios u ON md.criado_por = u.id
                 LEFT JOIN usuarios ua ON md.autorizado_por = ua.id
                 WHERE md.tenant_id = ? AND md.matricula_id = ?
                 ORDER BY md.tipo ASC, md.created_at DESC',
                [$tenantId, $matriculaId]
            )
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buscarPorId(int $tenantId, int $id): ?array
    {
        $row = DB::selectOne(
            'SELECT md.*, u.nome as criado_por_nome, ua.nome as autorizado_por_nome
             FROM matricula_descontos md
             LEFT JOIN usuarios u ON md.criado_por = u.id
             LEFT JOIN usuarios ua ON md.autorizado_por = ua.id
             WHERE md.tenant_id = ? AND md.id = ?',
            [$tenantId, $id]
        );

        return $row ? (array) $row : null;
    }
}
