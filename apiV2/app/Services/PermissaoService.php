<?php

namespace App\Services;

use App\Support\Papel;
use App\Support\Permissao;
use Illuminate\Support\Facades\DB;

/**
 * Permissões efetivas de um usuário numa academia (App\Support\Permissao):
 * padrão dos papéis ativos + concedidas − retiradas (tenant_usuario_permissoes.efeito).
 *
 * - Só a equipe (Papel::EQUIPE) tem permissões; aluno não tem nenhuma no painel.
 * - O admin principal (tenants.admin_principal_id) tem todas as não sensíveis e não pode ter nada retirado.
 * - Sensíveis só existem por concessão do super admin.
 * - O super admin no "acessar como academia" tem todas (tratado no middleware/controller).
 */
class PermissaoService
{
    public const CONCEDER = 'conceder';

    public const RETIRAR = 'retirar';

    /** @return list<int> */
    public function papeisAtivos(int $tenantId, int $usuarioId): array
    {
        return DB::table('tenant_usuario_papel')
            ->where('tenant_id', $tenantId)->where('usuario_id', $usuarioId)->where('ativo', 1)
            ->pluck('papel_id')->map(fn ($p) => (int) $p)->unique()->values()->all();
    }

    public function adminPrincipalId(int $tenantId): ?int
    {
        $id = DB::table('tenants')->where('id', $tenantId)->value('admin_principal_id');

        return $id ? (int) $id : null;
    }

    public function ehAdminPrincipal(int $tenantId, int $usuarioId): bool
    {
        return $this->adminPrincipalId($tenantId) === $usuarioId
            && in_array(Papel::ADMIN, $this->papeisAtivos($tenantId, $usuarioId), true);
    }

    /** @return list<string> */
    public function efetivas(int $tenantId, int $usuarioId): array
    {
        $papeis = $this->papeisAtivos($tenantId, $usuarioId);
        if (array_intersect($papeis, Papel::EQUIPE) === []) {
            return [];
        }

        $principal = $this->ehAdminPrincipal($tenantId, $usuarioId);
        $codigos = Permissao::padraoDosPapeis($papeis);
        if ($principal) {
            $codigos = array_merge($codigos, Permissao::naoSensiveis());
        }

        $ajustes = $this->ajustes($tenantId, $usuarioId);
        $codigos = array_merge($codigos, $ajustes[self::CONCEDER]);
        if (! $principal) {
            $codigos = array_diff($codigos, $ajustes[self::RETIRAR]);
        }

        // Na ordem do catálogo
        return array_values(array_intersect(Permissao::todas(), $codigos));
    }

    public function tem(int $tenantId, int $usuarioId, string $permissao): bool
    {
        return in_array($permissao, $this->efetivas($tenantId, $usuarioId), true);
    }

    /**
     * Ajustes individuais válidos do usuário na academia.
     *
     * @return array{conceder: list<string>, retirar: list<string>}
     */
    public function ajustes(int $tenantId, int $usuarioId): array
    {
        $out = [self::CONCEDER => [], self::RETIRAR => []];
        $rows = DB::table('tenant_usuario_permissoes')->where('tenant_id', $tenantId)->where('usuario_id', $usuarioId)->get(['permissao', 'efeito']);
        foreach ($rows as $r) {
            $efeito = $r->efeito ?? self::CONCEDER;
            if (Permissao::valida((string) $r->permissao) && isset($out[$efeito])) {
                $out[$efeito][] = (string) $r->permissao;
            }
        }

        return $out;
    }

    /**
     * Concessões do usuário (usado pela ficha da academia no super admin para as sensíveis).
     *
     * @return list<string>
     */
    public function concedidas(int $tenantId, int $usuarioId, bool $somenteSensiveis = false): array
    {
        if (array_intersect($this->papeisAtivos($tenantId, $usuarioId), Papel::EQUIPE) === []) {
            return [];
        }
        $codigos = $this->ajustes($tenantId, $usuarioId)[self::CONCEDER];

        return array_values(array_filter($codigos, fn ($c) => ! $somenteSensiveis || Permissao::sensivel($c)));
    }

    /**
     * Substitui as concessões do usuário dentro de um escopo de códigos (o resto não é tocado).
     *
     * @param  list<string>  $permissoes
     * @param  list<string>  $escopo  códigos que esta tela administra
     * @return list<string>
     */
    public function definirConcessoes(int $tenantId, int $usuarioId, array $permissoes, array $escopo, ?int $concedidoPor): array
    {
        $validas = array_values(array_unique(array_filter($permissoes, fn ($p) => is_string($p) && in_array($p, $escopo, true))));

        DB::transaction(function () use ($tenantId, $usuarioId, $validas, $escopo, $concedidoPor) {
            DB::table('tenant_usuario_permissoes')->where('tenant_id', $tenantId)->where('usuario_id', $usuarioId)
                ->whereIn('permissao', $escopo)->whereNotIn('permissao', $validas ?: [''])->delete();
            foreach ($validas as $p) {
                DB::table('tenant_usuario_permissoes')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'usuario_id' => $usuarioId, 'permissao' => $p],
                    ['efeito' => self::CONCEDER, 'concedido_por' => $concedidoPor],
                );
            }
        });

        return $validas;
    }

    /**
     * @return list<array{codigo: string, modulo: string, nome: string, descricao: string, sensivel: bool}>
     */
    public function catalogo(bool $somenteSensiveis = false): array
    {
        $out = [];
        foreach (Permissao::CATALOGO as $codigo => $p) {
            if ($somenteSensiveis && ! Permissao::sensivel($codigo)) {
                continue;
            }
            $out[] = ['codigo' => $codigo, 'modulo' => $p['modulo'], 'nome' => $p['nome'], 'descricao' => $p['descricao'], 'sensivel' => Permissao::sensivel($codigo)];
        }

        return $out;
    }
}
