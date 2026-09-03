<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class FormaPagamentoCatalogRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listarTodas(?int $tenantId): array
    {
        if ($tenantId) {
            return array_map(
                fn ($row) => (array) $row,
                DB::select(
                    'SELECT fp.id, fp.nome, fp.descricao, fp.percentual_desconto
                     FROM formas_pagamento fp
                     INNER JOIN tenant_formas_pagamento tfp ON fp.id = tfp.forma_pagamento_id
                     WHERE tfp.tenant_id = ?
                       AND fp.ativo = 1
                       AND tfp.ativo = 1
                     ORDER BY fp.nome',
                    [$tenantId]
                )
            );
        }

        return array_map(
            fn ($row) => (array) $row,
            DB::select(
                'SELECT id, nome, descricao, percentual_desconto
                 FROM formas_pagamento
                 WHERE ativo = 1
                 ORDER BY nome'
            )
        );
    }
}
