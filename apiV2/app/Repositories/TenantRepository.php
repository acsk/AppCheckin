<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TenantRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = DB::table('tenants')
            ->where('id', $id)
            ->where('ativo', 1)
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $row = DB::table('tenants')
            ->where('slug', $slug)
            ->where('ativo', 1)
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public function getAll(array $filtros = []): array
    {
        $query = DB::table('tenants')->where('id', '!=', 1);

        if (! empty($filtros['busca'])) {
            $busca = '%'.$filtros['busca'].'%';
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', $busca)
                    ->orWhere('email', 'like', $busca)
                    ->orWhere('cnpj', 'like', $busca);
            });
        }

        if (isset($filtros['ativo'])) {
            $query->where('ativo', $filtros['ativo'] ? 1 : 0);
        }

        return $query->orderBy('nome')->get()->map(fn ($row) => (array) $row)->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): int
    {
        $cnpj = isset($data['cnpj']) ? preg_replace('/[^0-9]/', '', (string) $data['cnpj']) : null;
        if ($cnpj === '') {
            $cnpj = null;
        }

        $tenantId = DB::table('tenants')->insertGetId([
            'nome' => $data['nome'],
            'slug' => $data['slug'],
            'email' => $data['email'],
            'cnpj' => $cnpj,
            'telefone' => $data['telefone'] ?? null,
            'responsavel_nome' => $data['responsavel_nome'] ?? null,
            'responsavel_cpf' => isset($data['responsavel_cpf']) ? preg_replace('/[^0-9]/', '', (string) $data['responsavel_cpf']) : null,
            'responsavel_telefone' => $data['responsavel_telefone'] ?? null,
            'responsavel_email' => $data['responsavel_email'] ?? null,
            'endereco' => $data['endereco'] ?? null,
            'cep' => $data['cep'] ?? null,
            'logradouro' => $data['logradouro'] ?? null,
            'numero' => $data['numero'] ?? null,
            'complemento' => $data['complemento'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'cidade' => $data['cidade'] ?? null,
            'estado' => $data['estado'] ?? null,
            'ativo' => $data['ativo'] ?? true,
        ]);

        $this->inicializarFormasPagamento($tenantId);

        return (int) $tenantId;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): bool
    {
        return DB::table('tenants')->where('id', $id)->update([
            'nome' => $data['nome'],
            'slug' => $data['slug'],
            'email' => $data['email'],
            'cnpj' => $data['cnpj'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'responsavel_nome' => $data['responsavel_nome'] ?? null,
            'responsavel_cpf' => $data['responsavel_cpf'] ?? null,
            'responsavel_telefone' => $data['responsavel_telefone'] ?? null,
            'responsavel_email' => $data['responsavel_email'] ?? null,
            'endereco' => $data['endereco'] ?? null,
            'cep' => $data['cep'] ?? null,
            'logradouro' => $data['logradouro'] ?? null,
            'numero' => $data['numero'] ?? null,
            'complemento' => $data['complemento'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'cidade' => $data['cidade'] ?? null,
            'estado' => $data['estado'] ?? null,
            'ativo' => $data['ativo'] ?? true,
        ]) >= 0;
    }

    public function delete(int $id): bool
    {
        return DB::table('tenants')->where('id', $id)->update(['ativo' => 0]) > 0;
    }

    public function inicializarFormasPagamento(int $tenantId): void
    {
        $total = DB::table('tenant_formas_pagamento')->where('tenant_id', $tenantId)->count();
        if ($total > 0) {
            return;
        }

        DB::insert(
            'INSERT INTO tenant_formas_pagamento
                (tenant_id, forma_pagamento_id, ativo, taxa_percentual, taxa_fixa,
                 aceita_parcelamento, parcelas_minimas, parcelas_maximas, juros_parcelamento,
                 parcelas_sem_juros, dias_compensacao, valor_minimo)
             SELECT ?, id, 0, percentual_desconto, 0.00, 0, 1, 12, 0.00, 1, 0, 0.00
             FROM formas_pagamento WHERE ativo = 1',
            [$tenantId],
        );
    }

    public function verificarUsuarioAdmin(int $tenantId): bool
    {
        return DB::table('usuarios as u')
            ->join('tenant_usuario_papel as tup', function ($join) {
                $join->on('tup.usuario_id', '=', 'u.id')->where('tup.ativo', 1);
            })
            ->where('tup.tenant_id', $tenantId)
            ->whereIn('tup.papel_id', [2, 3])
            ->where('u.ativo', 1)
            ->count() > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarAdmins(int $tenantId): array
    {
        $admins = DB::select(
            'SELECT DISTINCT u.id, u.nome, u.email, u.telefone, u.cpf,
                    MAX(tup.ativo) as ativo, MIN(tup.created_at) as vinculado_em
             FROM usuarios u
             INNER JOIN tenant_usuario_papel tup ON u.id = tup.usuario_id
             WHERE tup.tenant_id = ?
               AND tup.papel_id = 3
             GROUP BY u.id, u.nome, u.email, u.telefone, u.cpf
             ORDER BY u.nome ASC',
            [$tenantId],
        );

        return array_map(fn ($row) => (array) $row, $admins);
    }

    /**
     * @return list<int>
     */
    public function listarPapelIdsAtivos(int $tenantId, int $usuarioId): array
    {
        return DB::table('tenant_usuario_papel')
            ->where('tenant_id', $tenantId)
            ->where('usuario_id', $usuarioId)
            ->where('ativo', 1)
            ->orderByDesc('papel_id')
            ->pluck('papel_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function usuarioTemVinculoTenant(int $tenantId, int $usuarioId): bool
    {
        return DB::table('tenant_usuario_papel')
            ->where('tenant_id', $tenantId)
            ->where('usuario_id', $usuarioId)
            ->whereIn('papel_id', [1, 2, 3])
            ->count() > 0;
    }

    public function contarAdminsAtivos(int $tenantId): int
    {
        return DB::table('tenant_usuario_papel')
            ->where('tenant_id', $tenantId)
            ->where('papel_id', 3)
            ->where('ativo', 1)
            ->count();
    }

    public function vinculoAdminExiste(int $tenantId, int $usuarioId): bool
    {
        return DB::table('tenant_usuario_papel')
            ->where('tenant_id', $tenantId)
            ->where('usuario_id', $usuarioId)
            ->where('papel_id', 3)
            ->exists();
    }

    public function atribuirPapeis(int $tenantId, int $usuarioId, array $papeis): void
    {
        foreach ($papeis as $papelId) {
            DB::table('tenant_usuario_papel')->updateOrInsert(
                [
                    'tenant_id' => $tenantId,
                    'usuario_id' => $usuarioId,
                    'papel_id' => (int) $papelId,
                ],
                ['ativo' => 1],
            );
        }
    }

    public function substituirPapeis(int $tenantId, int $usuarioId, array $papeis): void
    {
        DB::table('tenant_usuario_papel')
            ->where('tenant_id', $tenantId)
            ->where('usuario_id', $usuarioId)
            ->delete();

        foreach ($papeis as $papelId) {
            if (! in_array((int) $papelId, [1, 2, 3], true)) {
                continue;
            }

            DB::table('tenant_usuario_papel')->insert([
                'tenant_id' => $tenantId,
                'usuario_id' => $usuarioId,
                'papel_id' => (int) $papelId,
                'ativo' => 1,
            ]);
        }
    }

    public function desativarPapeis(int $tenantId, int $usuarioId, array $papeis): int
    {
        $desativados = 0;

        foreach ($papeis as $papelId) {
            $desativados += DB::table('tenant_usuario_papel')
                ->where('tenant_id', $tenantId)
                ->where('usuario_id', $usuarioId)
                ->where('papel_id', (int) $papelId)
                ->where('ativo', 1)
                ->update(['ativo' => 0]);
        }

        return $desativados;
    }

    public function reativarAdmin(int $tenantId, int $usuarioId): bool
    {
        return DB::table('tenant_usuario_papel')
            ->where('tenant_id', $tenantId)
            ->where('usuario_id', $usuarioId)
            ->where('papel_id', 3)
            ->update(['ativo' => 1]) > 0;
    }
}
