<?php

namespace Tests\Support;

use App\Services\PermissaoService;
use Illuminate\Support\Facades\Schema;

/**
 * Para os testes de rota que simulam o usuário com mocks e não criam tabelas: sem a tabela de papéis,
 * o papel vem do usuário do token (mock de findAuthContext). Com as tabelas (testes de permissão),
 * usa o serviço real.
 */
class PermissaoServiceSemBanco extends PermissaoService
{
    private function semBanco(): bool
    {
        return ! Schema::hasTable('tenant_usuario_papel');
    }

    public function papeisAtivos(int $tenantId, int $usuarioId): array
    {
        if (! $this->semBanco()) {
            return parent::papeisAtivos($tenantId, $usuarioId);
        }
        $papel = request()->attributes->get('usuario')['papel_id'] ?? null;

        return $papel !== null ? [(int) $papel] : [];
    }

    // Esquemas parciais de alguns testes: sem a coluna/tabela, comporta-se como "sem principal / sem ajustes"
    public function adminPrincipalId(int $tenantId): ?int
    {
        return $this->semBanco() || ! Schema::hasColumn('tenants', 'admin_principal_id') ? null : parent::adminPrincipalId($tenantId);
    }

    public function ajustes(int $tenantId, int $usuarioId): array
    {
        return $this->semBanco() || ! Schema::hasTable('tenant_usuario_permissoes')
            ? [self::CONCEDER => [], self::RETIRAR => []]
            : parent::ajustes($tenantId, $usuarioId);
    }
}
