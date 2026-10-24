<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/** Configuração remota do app: rascunho (valores), publicações e auditoria. */
class AppConfigRepository
{
    /**
     * Rascunho no mesmo formato da publicação.
     *
     * @return array{global: array<string, mixed>, tenants: array<int, array<string, mixed>>}
     */
    public function rascunho(): array
    {
        $out = ['global' => [], 'tenants' => []];
        foreach (DB::table('app_config_valores')->orderBy('chave')->get(['tenant_id', 'chave', 'valor']) as $r) {
            $valor = json_decode((string) $r->valor, true);
            if ($r->tenant_id === null) {
                $out['global'][$r->chave] = $valor;
            } else {
                $out['tenants'][(int) $r->tenant_id][$r->chave] = $valor;
            }
        }

        return $out;
    }

    /** null remove o valor (volta a herdar). */
    public function gravar(?int $tenantId, string $chave, mixed $valor, ?int $userId): void
    {
        $q = DB::table('app_config_valores')->where('chave', $chave)
            ->when($tenantId === null, fn ($q) => $q->whereNull('tenant_id'), fn ($q) => $q->where('tenant_id', $tenantId));
        if ($valor === null) {
            $q->delete();

            return;
        }
        $linha = ['valor' => json_encode($valor, JSON_UNESCAPED_UNICODE), 'atualizado_por' => $userId, 'updated_at' => now()];
        if ((clone $q)->exists()) {
            $q->update($linha);
        } else {
            DB::table('app_config_valores')->insert($linha + ['tenant_id' => $tenantId, 'chave' => $chave, 'created_at' => now()]);
        }
    }

    /** @param  array{global: array<string, mixed>, tenants: array<int|string, array<string, mixed>>}  $conteudo */
    public function substituirRascunho(array $conteudo, ?int $userId): void
    {
        DB::table('app_config_valores')->delete();
        foreach ($conteudo['global'] as $chave => $valor) {
            $this->gravar(null, $chave, $valor, $userId);
        }
        foreach ($conteudo['tenants'] as $tenantId => $valores) {
            foreach ($valores as $chave => $valor) {
                $this->gravar((int) $tenantId, $chave, $valor, $userId);
            }
        }
    }

    /** @return array{versao: int, global: array<string, mixed>, tenants: array<int, array<string, mixed>>}|null */
    public function ultimaPublicacao(): ?array
    {
        $r = DB::table('app_config_publicacoes')->orderByDesc('id')->first(['id', 'conteudo']);

        return $r ? $this->decodificar($r) : null;
    }

    /** @return array{versao: int, global: array<string, mixed>, tenants: array<int, array<string, mixed>>}|null */
    public function publicacao(int $versao): ?array
    {
        $r = DB::table('app_config_publicacoes')->where('id', $versao)->first(['id', 'conteudo']);

        return $r ? $this->decodificar($r) : null;
    }

    /** @param  array{global: array<string, mixed>, tenants: array<int|string, array<string, mixed>>}  $conteudo */
    public function publicar(array $conteudo, ?string $nota, string $origem, ?int $rollbackDe, ?int $userId): int
    {
        return (int) DB::table('app_config_publicacoes')->insertGetId([
            'conteudo' => json_encode(['global' => (object) $conteudo['global'], 'tenants' => (object) array_map(fn ($v) => (object) $v, $conteudo['tenants'])], JSON_UNESCAPED_UNICODE),
            'nota' => $nota,
            'origem' => $origem,
            'rollback_de' => $rollbackDe,
            'publicado_por' => $userId,
            'created_at' => now(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function publicacoes(int $limite = 50): array
    {
        return DB::table('app_config_publicacoes as p')
            ->leftJoin('usuarios as u', 'u.id', '=', 'p.publicado_por')
            ->orderByDesc('p.id')
            ->limit($limite)
            ->get(['p.id', 'p.nota', 'p.origem', 'p.rollback_de', 'p.created_at', 'u.nome as publicado_por'])
            ->map(fn ($r) => (array) $r + ['versao' => (int) $r->id])
            ->all();
    }

    public function auditar(string $acao, ?int $tenantId, ?string $chave, mixed $anterior, mixed $novo, ?int $versao, ?string $nota, ?int $userId): void
    {
        DB::table('app_config_auditoria')->insert([
            'acao' => $acao,
            'tenant_id' => $tenantId,
            'chave' => $chave,
            'valor_anterior' => $anterior === null ? null : json_encode($anterior, JSON_UNESCAPED_UNICODE),
            'valor_novo' => $novo === null ? null : json_encode($novo, JSON_UNESCAPED_UNICODE),
            'versao' => $versao,
            'nota' => $nota,
            'usuario_id' => $userId,
            'created_at' => now(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function auditoria(int $limite = 100): array
    {
        return DB::table('app_config_auditoria as a')
            ->leftJoin('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->leftJoin('tenants as t', 't.id', '=', 'a.tenant_id')
            ->orderByDesc('a.id')
            ->limit($limite)
            ->get(['a.id', 'a.acao', 'a.tenant_id', 't.nome as academia', 'a.chave', 'a.valor_anterior', 'a.valor_novo', 'a.versao', 'a.nota', 'a.created_at', 'u.nome as usuario'])
            ->map(fn ($r) => array_merge((array) $r, [
                'valor_anterior' => $r->valor_anterior === null ? null : json_decode($r->valor_anterior, true),
                'valor_novo' => $r->valor_novo === null ? null : json_decode($r->valor_novo, true),
            ]))
            ->all();
    }

    public function tenantExiste(int $tenantId): bool
    {
        return DB::table('tenants')->where('id', $tenantId)->exists();
    }

    /** @return array<int, string> */
    public function nomesTenants(array $ids): array
    {
        return $ids === [] ? [] : DB::table('tenants')->whereIn('id', $ids)->pluck('nome', 'id')->map(fn ($n) => (string) $n)->all();
    }

    /** @return list<int> */
    public function papeisDoUsuario(int $usuarioId, int $tenantId): array
    {
        return DB::table('tenant_usuario_papel')->where('usuario_id', $usuarioId)->where('tenant_id', $tenantId)->where('ativo', 1)
            ->pluck('papel_id')->map(fn ($p) => (int) $p)->unique()->values()->all();
    }

    /** @return array{versao: int, global: array<string, mixed>, tenants: array<int, array<string, mixed>>} */
    private function decodificar(object $r): array
    {
        $c = json_decode((string) $r->conteudo, true) ?: [];
        $tenants = [];
        foreach ((array) ($c['tenants'] ?? []) as $id => $valores) {
            $tenants[(int) $id] = (array) $valores;
        }

        return ['versao' => (int) $r->id, 'global' => (array) ($c['global'] ?? []), 'tenants' => $tenants];
    }
}
