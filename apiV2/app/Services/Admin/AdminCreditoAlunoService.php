<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

/**
 * Créditos do aluno (paridade com Slim CreditoAlunoController + Models\CreditoAluno).
 *
 * `creditos_aluno` não tem coluna `saldo`: o saldo é sempre `valor - valor_utilizado`.
 */
class AdminCreditoAlunoService
{
    /** IDs da tabela status_creditos_aluno. */
    public const STATUS_ATIVO = 1;

    public const STATUS_UTILIZADO = 2;

    public const STATUS_CANCELADO = 3;

    /**
     * @return array{status: int, body: mixed}
     */
    public function listarPorAluno(int $tenantId, int $alunoId): array
    {
        $creditos = $this->rows(
            'SELECT ca.*, sca.codigo as status, sca.nome as status_nome,
                    (ca.valor - ca.valor_utilizado) as saldo
             FROM creditos_aluno ca
             INNER JOIN status_creditos_aluno sca ON sca.id = ca.status_credito_id
             WHERE ca.tenant_id = ? AND ca.aluno_id = ?
             ORDER BY ca.created_at DESC',
            [$tenantId, $alunoId]
        );

        // A Slim serializa a lista crua (array JSON), sem envelope.
        return ['status' => 200, 'body' => $creditos];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function saldo(int $tenantId, int $alunoId): array
    {
        $ativos = $this->listarAtivos($tenantId, $alunoId);

        return [
            'status' => 200,
            'body' => [
                'saldo_total' => self::somarSaldos($ativos),
                'creditos_ativos' => $ativos,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criar(int $tenantId, int $alunoId, ?int $adminId, array $data): array
    {
        if (empty($data['valor']) || (float) $data['valor'] <= 0) {
            return [
                'status' => 422,
                'body' => ['error' => 'valor é obrigatório e deve ser maior que zero'],
            ];
        }

        DB::insert(
            'INSERT INTO creditos_aluno
                (tenant_id, aluno_id, matricula_origem_id, pagamento_origem_id, valor, motivo, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $tenantId,
                $alunoId,
                $data['matricula_origem_id'] ?? null,
                $data['pagamento_origem_id'] ?? null,
                (float) $data['valor'],
                $data['motivo'] ?? 'Crédito manual',
                $adminId,
            ]
        );

        $id = (int) DB::getPdo()->lastInsertId();

        return [
            'status' => 201,
            'body' => [
                'message' => 'Crédito criado com sucesso',
                'credito' => $this->buscarPorId($tenantId, $id),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function cancelar(int $tenantId, int $creditoId): array
    {
        $afetados = DB::update(
            'UPDATE creditos_aluno
             SET status_credito_id = '.self::STATUS_CANCELADO.', updated_at = NOW()
             WHERE tenant_id = ? AND id = ? AND status_credito_id = '.self::STATUS_ATIVO,
            [$tenantId, $creditoId]
        );

        if ($afetados < 1) {
            return [
                'status' => 404,
                'body' => ['error' => 'Crédito não encontrado ou já utilizado/cancelado'],
            ];
        }

        return ['status' => 200, 'body' => ['message' => 'Crédito cancelado com sucesso']];
    }

    /**
     * Saldo de um crédito individual: valor total menos o já consumido.
     */
    public static function calcularSaldo(mixed $valor, mixed $valorUtilizado): float
    {
        return round((float) $valor - (float) $valorUtilizado, 2);
    }

    /**
     * Soma dos saldos de uma lista de créditos (mesma semântica do SUM da Slim).
     *
     * @param  list<array<string, mixed>>  $creditos
     */
    public static function somarSaldos(array $creditos): float
    {
        $total = 0.0;
        foreach ($creditos as $credito) {
            $total += array_key_exists('saldo', $credito)
                ? (float) $credito['saldo']
                : self::calcularSaldo($credito['valor'] ?? 0, $credito['valor_utilizado'] ?? 0);
        }

        return round($total, 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarAtivos(int $tenantId, int $alunoId): array
    {
        return $this->rows(
            'SELECT ca.*, sca.codigo as status, sca.nome as status_nome,
                    (ca.valor - ca.valor_utilizado) as saldo
             FROM creditos_aluno ca
             INNER JOIN status_creditos_aluno sca ON sca.id = ca.status_credito_id
             WHERE ca.tenant_id = ? AND ca.aluno_id = ?
               AND ca.status_credito_id = '.self::STATUS_ATIVO.'
               AND (ca.valor - ca.valor_utilizado) > 0
             ORDER BY ca.created_at ASC',
            [$tenantId, $alunoId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buscarPorId(int $tenantId, int $id): ?array
    {
        $row = DB::selectOne(
            'SELECT ca.*, sca.codigo as status, sca.nome as status_nome,
                    (ca.valor - ca.valor_utilizado) as saldo
             FROM creditos_aluno ca
             INNER JOIN status_creditos_aluno sca ON sca.id = ca.status_credito_id
             WHERE ca.tenant_id = ? AND ca.id = ? LIMIT 1',
            [$tenantId, $id]
        );

        return $row ? (array) $row : null;
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $bindings): array
    {
        return array_map(
            static fn ($row) => (array) $row,
            DB::select($sql, $bindings)
        );
    }
}
