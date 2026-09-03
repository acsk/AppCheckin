<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class DiaRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getAtivos(): array
    {
        return $this->rows(
            'SELECT * FROM dias WHERE ativo = 1 AND data >= CURDATE() ORDER BY data ASC',
            [],
        );
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $query = DB::table('dias')->where('id', $id);

        if ($tenantId !== null && $this->diasTemTenantId()) {
            $query->where('tenant_id', $tenantId);
        }

        $row = $query->first();

        return $row ? (array) $row : null;
    }

    public function buscarPorId(int $id, ?int $tenantId = null): ?array
    {
        return $this->findById($id, $tenantId);
    }

    public function findByData(string $data, ?int $tenantId = null): ?array
    {
        $query = DB::table('dias')->where('data', $data);

        if ($tenantId !== null && $this->diasTemTenantId()) {
            $query->where('tenant_id', $tenantId);
        }

        $row = $query->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getDiasAoRedor(
        ?string $dataReferencia = null,
        int $diasAntes = 2,
        int $diasDepois = 2,
    ): array {
        $dataReferencia = $dataReferencia ?: date('Y-m-d');

        return $this->rows(
            'SELECT * FROM dias
             WHERE ativo = 1
             AND data >= DATE_SUB(?, INTERVAL '.(int) $diasAntes.' DAY)
             AND data <= DATE_ADD(?, INTERVAL '.(int) $diasDepois.' DAY)
             ORDER BY data ASC',
            [$dataReferencia, $dataReferencia],
        );
    }

    /**
     * Dias do mês cujo DAYOFWEEK está em $diasSemana (1=dom … 7=sab).
     *
     * @param  list<int>  $diasSemana
     * @return list<array<string, mixed>>
     */
    public function buscarDiasDoMes(string $mes, array $diasSemana, ?int $diaExcluir = null): array
    {
        if ($diasSemana === []) {
            return [];
        }

        $dataParts = explode('-', $mes);
        if (count($dataParts) !== 2) {
            return [];
        }

        $ano = (int) $dataParts[0];
        $mesNum = (int) $dataParts[1];
        $diasSemanaStr = implode(',', array_map('intval', $diasSemana));

        $sql = "SELECT * FROM dias
                WHERE YEAR(data) = ?
                AND MONTH(data) = ?
                AND DAYOFWEEK(data) IN ($diasSemanaStr)
                AND ativo = 1";
        $params = [$ano, $mesNum];

        if ($diaExcluir !== null) {
            $sql .= ' AND id != ?';
            $params[] = $diaExcluir;
        }

        $sql .= ' ORDER BY data ASC';

        return $this->rows($sql, $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buscarDiasDoMesPorDiaSemana(string $mes, int $diaSemana): array
    {
        $dataParts = explode('-', $mes);
        if (count($dataParts) !== 2) {
            return [];
        }

        return $this->rows(
            'SELECT * FROM dias
             WHERE YEAR(data) = ?
             AND MONTH(data) = ?
             AND DAYOFWEEK(data) = ?
             AND ativo = 1
             ORDER BY data ASC',
            [(int) $dataParts[0], (int) $dataParts[1], $diaSemana],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buscarDiasEntreDatas(string $dataInicio, string $dataFim): array
    {
        return $this->rows(
            'SELECT * FROM dias WHERE data BETWEEN ? AND ? AND ativo = 1 ORDER BY data ASC',
            [$dataInicio, $dataFim],
        );
    }

    /**
     * @param  list<int>  $diasSemana
     * @return list<array<string, mixed>>
     */
    public function buscarPorMesEDiasSemana(int $tenantId, string $mes, array $diasSemana): array
    {
        if ($diasSemana === []) {
            return [];
        }

        $dataParts = explode('-', $mes);
        if (count($dataParts) !== 2) {
            return [];
        }

        $diasSemanaStr = implode(',', array_map('intval', $diasSemana));

        $sql = "SELECT id, data FROM dias
                WHERE YEAR(data) = ?
                AND MONTH(data) = ?
                AND DAYOFWEEK(data) IN ($diasSemanaStr)
                ORDER BY data ASC";
        $params = [(int) substr($mes, 0, 4), (int) substr($mes, 5, 2)];

        if ($this->diasTemTenantId()) {
            $sql = "SELECT id, data FROM dias
                    WHERE tenant_id = ?
                    AND YEAR(data) = ?
                    AND MONTH(data) = ?
                    AND DAYOFWEEK(data) IN ($diasSemanaStr)
                    ORDER BY data ASC";
            $params = array_merge([$tenantId], $params);
        }

        return $this->rows($sql, $params);
    }

    public function desativar(int $id, int $tenantId): bool
    {
        $query = DB::table('dias')->where('id', $id);

        if ($this->diasTemTenantId()) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->update([
            'ativo' => 0,
            'updated_at' => DB::raw('CURRENT_TIMESTAMP'),
        ]) > 0;
    }

    /**
     * @param  list<int>  $ids
     */
    public function desativarVarios(array $ids, int $tenantId): int
    {
        if ($ids === []) {
            return 0;
        }

        $query = DB::table('dias')->whereIn('id', $ids);

        if ($this->diasTemTenantId()) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->update([
            'ativo' => 0,
            'updated_at' => DB::raw('CURRENT_TIMESTAMP'),
        ]);
    }

    private function diasTemTenantId(): bool
    {
        static $tem = null;

        if ($tem === null) {
            $tem = DB::getSchemaBuilder()->hasColumn('dias', 'tenant_id');
        }

        return $tem;
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $bindings): array
    {
        return array_map(
            static fn ($row) => (array) $row,
            DB::select($sql, $bindings),
        );
    }
}
