<?php

namespace App\Repositories;

use App\Support\PoliticaCnpj;
use App\Support\VinculoCnpj;
use Illuminate\Support\Facades\DB;

/**
 * Entidades jurídicas (CNPJ), vínculos tenant ↔ CNPJ, exceções de duplicidade e auditoria.
 * Os métodos com $lock devem rodar dentro de DB::transaction (o EntidadeJuridicaService cuida disso).
 */
class EntidadeJuridicaRepository
{
    public function politica(): string
    {
        $valor = DB::table('configuracoes_sistema')->where('chave', PoliticaCnpj::CHAVE)->value('valor');

        return PoliticaCnpj::valida($valor) ? $valor : PoliticaCnpj::PADRAO;
    }

    public function salvarPolitica(string $politica, ?int $usuarioId): void
    {
        DB::table('configuracoes_sistema')->updateOrInsert(
            ['chave' => PoliticaCnpj::CHAVE],
            ['valor' => $politica, 'atualizado_por' => $usuarioId, 'updated_at' => now()],
        );
    }

    /** @return array<string, mixed>|null */
    public function buscarPorCnpj(string $cnpj, bool $lock = false): ?array
    {
        $query = DB::table('entidades_juridicas')->where('cnpj', $cnpj);
        if ($lock) {
            $query->lockForUpdate();
        }
        $row = $query->first();

        return $row ? (array) $row : null;
    }

    /** @return array<string, mixed>|null */
    public function buscarPorId(int $id, bool $lock = false): ?array
    {
        $query = DB::table('entidades_juridicas')->where('id', $id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $row = $query->first();

        return $row ? (array) $row : null;
    }

    /**
     * @param  array<string, mixed>  $dados  só campos de VinculoCnpj::CAMPOS_JURIDICOS
     *
     * @throws \Illuminate\Database\UniqueConstraintViolationException CNPJ criado por outra requisição
     */
    public function criar(string $cnpj, array $dados, ?int $usuarioId): int
    {
        return (int) DB::table('entidades_juridicas')->insertGetId(
            ['cnpj' => $cnpj, 'atualizado_por' => $usuarioId, 'created_at' => now(), 'updated_at' => now()]
            + array_intersect_key($dados, array_flip(VinculoCnpj::CAMPOS_JURIDICOS)),
        );
    }

    /** @param  array<string, mixed>  $dados */
    public function atualizarDados(int $id, array $dados, ?int $usuarioId): void
    {
        DB::table('entidades_juridicas')->where('id', $id)->update(
            array_intersect_key($dados, array_flip(VinculoCnpj::CAMPOS_JURIDICOS))
            + ['atualizado_por' => $usuarioId, 'updated_at' => now()],
        );
    }

    /**
     * Trava a linha do tenant: serializa vincular/desvincular do mesmo tenant.
     */
    public function travarTenant(int $tenantId): bool
    {
        return DB::table('tenants')->where('id', $tenantId)->lockForUpdate()->exists();
    }

    /** @return array<string, mixed>|null vínculo ativo + cnpj da entidade */
    public function vinculoAtivoDoTenant(int $tenantId, bool $lock = false): ?array
    {
        $query = DB::table('tenant_entidade_juridica as v')
            ->join('entidades_juridicas as e', 'e.id', '=', 'v.entidade_juridica_id')
            ->where('v.tenant_ativo', $tenantId)
            ->select('v.*', 'e.cnpj');
        if ($lock) {
            $query->lockForUpdate();
        }
        $row = $query->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function vinculos(int $entidadeId, bool $somenteAtivos = false): array
    {
        $query = DB::table('tenant_entidade_juridica as v')
            ->join('tenants as t', 't.id', '=', 'v.tenant_id')
            ->where('v.entidade_juridica_id', $entidadeId)
            ->orderByRaw("CASE WHEN v.status = 'ativo' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN v.papel = 'gestor' THEN 0 ELSE 1 END")
            ->orderBy('v.vinculado_em')
            ->select('v.id', 'v.tenant_id', 'v.papel', 'v.status', 'v.vinculado_em', 'v.encerrado_em', 't.nome as tenant_nome', 't.ativo as tenant_ativo_flag');
        if ($somenteAtivos) {
            $query->where('v.status', VinculoCnpj::ATIVO);
        }

        return $query->get()->map(fn ($r) => [
            'id' => (int) $r->id,
            'tenant_id' => (int) $r->tenant_id,
            'tenant_nome' => $r->tenant_nome,
            'tenant_ativo' => (bool) $r->tenant_ativo_flag,
            'papel' => $r->papel,
            'status' => $r->status,
            'vinculado_em' => $r->vinculado_em,
            'encerrado_em' => $r->encerrado_em,
        ])->all();
    }

    public function contarVinculosAtivos(int $entidadeId, ?int $excetoTenantId = null): int
    {
        $query = DB::table('tenant_entidade_juridica')
            ->where('entidade_juridica_id', $entidadeId)
            ->where('status', VinculoCnpj::ATIVO);
        if ($excetoTenantId) {
            $query->where('tenant_id', '<>', $excetoTenantId);
        }

        return $query->count();
    }

    /** @return array<string, mixed>|null */
    public function gestorAtivo(int $entidadeId): ?array
    {
        $row = DB::table('tenant_entidade_juridica')->where('gestor_ativo', $entidadeId)->first();

        return $row ? (array) $row : null;
    }

    public function criarVinculo(int $tenantId, int $entidadeId, string $papel): int
    {
        $gestor = $papel === VinculoCnpj::GESTOR;

        return (int) DB::table('tenant_entidade_juridica')->insertGetId([
            'tenant_id' => $tenantId,
            'entidade_juridica_id' => $entidadeId,
            'papel' => $papel,
            'status' => VinculoCnpj::ATIVO,
            'vinculado_em' => now(),
            'tenant_ativo' => $tenantId,
            'gestor_ativo' => $gestor ? $entidadeId : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function encerrarVinculo(int $vinculoId): void
    {
        DB::table('tenant_entidade_juridica')->where('id', $vinculoId)->update([
            'status' => VinculoCnpj::ENCERRADO,
            'encerrado_em' => now(),
            'tenant_ativo' => null,
            'gestor_ativo' => null,
            'updated_at' => now(),
        ]);
    }

    public function definirPapel(int $vinculoId, int $entidadeId, string $papel): void
    {
        DB::table('tenant_entidade_juridica')->where('id', $vinculoId)->update([
            'papel' => $papel,
            'gestor_ativo' => $papel === VinculoCnpj::GESTOR ? $entidadeId : null,
            'updated_at' => now(),
        ]);
    }

    /** Espelho de compatibilidade (Slim, cobrança Asaas, busca). Só o EntidadeJuridicaService escreve aqui. */
    public function atualizarEspelhoTenant(int $tenantId, ?string $cnpj): void
    {
        DB::table('tenants')->where('id', $tenantId)->update(['cnpj' => $cnpj, 'updated_at' => now()]);
    }

    /** @return array<string, mixed>|null */
    public function excecaoVigente(int $entidadeId): ?array
    {
        $row = DB::table('cnpj_excecoes')
            ->where('entidade_juridica_id', $entidadeId)
            ->whereNull('revogado_em')
            ->orderByDesc('id')
            ->first();

        return $row ? (array) $row : null;
    }

    /** @return array<string, mixed>|null */
    public function buscarExcecao(int $id, bool $lock = false): ?array
    {
        $query = DB::table('cnpj_excecoes')->where('id', $id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $row = $query->first();

        return $row ? (array) $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function excecoes(int $entidadeId): array
    {
        return DB::table('cnpj_excecoes as x')
            ->leftJoin('usuarios as a', 'a.id', '=', 'x.autorizado_por')
            ->leftJoin('usuarios as r', 'r.id', '=', 'x.revogado_por')
            ->where('x.entidade_juridica_id', $entidadeId)
            ->orderByDesc('x.id')
            ->select('x.id', 'x.justificativa', 'x.autorizado_em', 'x.revogado_em', 'x.motivo_revogacao',
                'a.nome as autorizado_por_nome', 'r.nome as revogado_por_nome')
            ->get()
            ->map(fn ($r) => (array) $r + ['vigente' => $r->revogado_em === null])
            ->all();
    }

    public function criarExcecao(int $entidadeId, string $justificativa, ?int $usuarioId): int
    {
        return (int) DB::table('cnpj_excecoes')->insertGetId([
            'entidade_juridica_id' => $entidadeId,
            'justificativa' => $justificativa,
            'autorizado_por' => $usuarioId,
            'autorizado_em' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function revogarExcecao(int $id, string $motivo, ?int $usuarioId): void
    {
        DB::table('cnpj_excecoes')->where('id', $id)->update([
            'revogado_por' => $usuarioId,
            'revogado_em' => now(),
            'motivo_revogacao' => $motivo,
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array{entidade_juridica_id?: ?int, cnpj?: ?string, tenant_id?: ?int, usuario_id?: ?int, acao: string, dados_anteriores?: mixed, dados_novos?: mixed, justificativa?: ?string, ip?: ?string}  $registro
     */
    public function registrarAuditoria(array $registro): void
    {
        DB::table('entidade_juridica_auditoria')->insert([
            'entidade_juridica_id' => $registro['entidade_juridica_id'] ?? null,
            'cnpj' => $registro['cnpj'] ?? null,
            'tenant_id' => $registro['tenant_id'] ?? null,
            'usuario_id' => $registro['usuario_id'] ?? null,
            'acao' => $registro['acao'],
            'dados_anteriores' => isset($registro['dados_anteriores']) ? json_encode($registro['dados_anteriores'], JSON_UNESCAPED_UNICODE) : null,
            'dados_novos' => isset($registro['dados_novos']) ? json_encode($registro['dados_novos'], JSON_UNESCAPED_UNICODE) : null,
            'justificativa' => $registro['justificativa'] ?? null,
            'ip' => $registro['ip'] ?? null,
            'created_at' => now(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function auditoria(int $entidadeId, int $limite = 50): array
    {
        return DB::table('entidade_juridica_auditoria as a')
            ->leftJoin('usuarios as u', 'u.id', '=', 'a.usuario_id')
            ->leftJoin('tenants as t', 't.id', '=', 'a.tenant_id')
            ->where('a.entidade_juridica_id', $entidadeId)
            ->orderByDesc('a.id')
            ->limit($limite)
            ->select('a.id', 'a.acao', 'a.tenant_id', 't.nome as tenant_nome', 'u.nome as usuario_nome',
                'a.dados_anteriores', 'a.dados_novos', 'a.justificativa', 'a.created_at')
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'acao' => $r->acao,
                'tenant_id' => $r->tenant_id !== null ? (int) $r->tenant_id : null,
                'tenant_nome' => $r->tenant_nome,
                'usuario_nome' => $r->usuario_nome,
                'dados_anteriores' => $r->dados_anteriores ? json_decode($r->dados_anteriores, true) : null,
                'dados_novos' => $r->dados_novos ? json_decode($r->dados_novos, true) : null,
                'justificativa' => $r->justificativa,
                'created_at' => $r->created_at,
            ])
            ->all();
    }

    /**
     * Lista para o super admin: busca por dígitos do CNPJ, razão social ou nome de tenant vinculado.
     *
     * @return list<array<string, mixed>>
     */
    public function listar(string $busca = '', bool $somenteCompartilhados = false, int $limite = 100): array
    {
        $ativos = DB::table('tenant_entidade_juridica')
            ->where('status', VinculoCnpj::ATIVO)
            ->groupBy('entidade_juridica_id')
            ->selectRaw('entidade_juridica_id, COUNT(*) as total');

        $query = DB::table('entidades_juridicas as e')
            ->leftJoinSub($ativos, 'va', 'va.entidade_juridica_id', '=', 'e.id')
            ->select('e.id', 'e.cnpj', 'e.razao_social', 'e.nome_fantasia', 'e.updated_at', DB::raw('COALESCE(va.total, 0) as tenants_ativos'))
            ->orderByDesc(DB::raw('COALESCE(va.total, 0)'))
            ->orderBy('e.razao_social')
            ->limit($limite);

        $busca = trim($busca);
        if ($busca !== '') {
            $digitos = preg_replace('/\D/', '', $busca);
            $like = '%'.$busca.'%';
            $query->where(function ($q) use ($digitos, $like) {
                if ($digitos !== '') {
                    $q->orWhere('e.cnpj', 'like', '%'.$digitos.'%');
                }
                $q->orWhere('e.razao_social', 'like', $like)
                    ->orWhere('e.nome_fantasia', 'like', $like)
                    ->orWhereExists(function ($s) use ($like) {
                        $s->from('tenant_entidade_juridica as v')
                            ->join('tenants as t', 't.id', '=', 'v.tenant_id')
                            ->whereColumn('v.entidade_juridica_id', 'e.id')
                            ->where('t.nome', 'like', $like);
                    });
            });
        }
        if ($somenteCompartilhados) {
            $query->where('va.total', '>', 1);
        }

        $vigentes = DB::table('cnpj_excecoes')->whereNull('revogado_em')->pluck('entidade_juridica_id')->map(fn ($id) => (int) $id)->all();

        return $query->get()->map(fn ($r) => [
            'id' => (int) $r->id,
            'cnpj' => $r->cnpj,
            'razao_social' => $r->razao_social,
            'nome_fantasia' => $r->nome_fantasia,
            'tenants_ativos' => (int) $r->tenants_ativos,
            'excecao_vigente' => in_array((int) $r->id, $vigentes, true),
            'updated_at' => $r->updated_at,
        ])->all();
    }
}
