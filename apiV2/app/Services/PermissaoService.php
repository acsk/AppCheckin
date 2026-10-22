<?php

namespace App\Services;

use App\Support\Papel;
use App\Support\Permissao;
use Illuminate\Support\Facades\DB;

/**
 * Permissões pontuais por usuário em cada academia (App\Support\Permissao).
 * Só valem para quem é admin ativo da academia; o super admin no "acessar como academia" tem todas.
 */
class PermissaoService
{
    /** @return list<string> */
    public function doUsuario(int $tenantId, int $usuarioId): array
    {
        $admin = DB::table('tenant_usuario_papel')
            ->where('tenant_id', $tenantId)->where('usuario_id', $usuarioId)->where('papel_id', Papel::ADMIN)->where('ativo', 1)->exists();
        if (! $admin) {
            return [];
        }

        return DB::table('tenant_usuario_permissoes')
            ->where('tenant_id', $tenantId)->where('usuario_id', $usuarioId)
            ->pluck('permissao')->filter(fn ($p) => Permissao::valida((string) $p))->values()->all();
    }

    public function tem(int $tenantId, int $usuarioId, string $permissao): bool
    {
        return in_array($permissao, $this->doUsuario($tenantId, $usuarioId), true);
    }

    /**
     * Substitui as permissões do usuário na academia (códigos desconhecidos são ignorados).
     *
     * @param  list<string>  $permissoes
     * @return list<string>
     */
    public function definir(int $tenantId, int $usuarioId, array $permissoes, ?int $concedidoPor): array
    {
        $validas = array_values(array_unique(array_filter($permissoes, fn ($p) => is_string($p) && Permissao::valida($p))));

        DB::transaction(function () use ($tenantId, $usuarioId, $validas, $concedidoPor) {
            DB::table('tenant_usuario_permissoes')->where('tenant_id', $tenantId)->where('usuario_id', $usuarioId)
                ->whereNotIn('permissao', $validas ?: [''])->delete();
            foreach ($validas as $p) {
                DB::table('tenant_usuario_permissoes')->insertOrIgnore([
                    'tenant_id' => $tenantId, 'usuario_id' => $usuarioId, 'permissao' => $p, 'concedido_por' => $concedidoPor, 'created_at' => now(),
                ]);
            }
        });

        return $validas;
    }

    /** @return list<array{codigo: string, nome: string, descricao: string}> */
    public function catalogo(): array
    {
        return array_map(fn ($codigo, $r) => ['codigo' => $codigo] + $r, array_keys(Permissao::ROTULOS), Permissao::ROTULOS);
    }
}
