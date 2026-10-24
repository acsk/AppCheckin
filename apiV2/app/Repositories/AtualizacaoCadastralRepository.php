<?php

namespace App\Repositories;

use App\Support\CamposCadastro;
use App\Support\Pessoa;
use Illuminate\Support\Facades\DB;

class AtualizacaoCadastralRepository
{
    /** Papéis que usam o app: aluno e professor. */
    private const PAPEIS_APP = [1, 2];

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        $rows = DB::table('atualizacoes_cadastrais')->orderByDesc('id')->get();
        $academias = $this->academiasPorCampanha($rows->pluck('id')->all());

        return $rows->map(fn ($r) => $this->mapear($r, $academias[(int) $r->id] ?? []))->all();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $r = DB::table('atualizacoes_cadastrais')->where('id', $id)->first();

        return $r ? $this->mapear($r, $this->academiasPorCampanha([$id])[$id] ?? []) : null;
    }

    /**
     * @param  array<string, mixed>  $dados
     * @param  list<int>  $tenantIds
     */
    public function salvar(?int $id, array $dados, array $tenantIds, ?int $userId): int
    {
        return DB::transaction(function () use ($id, $dados, $tenantIds, $userId) {
            $linha = [
                'titulo' => $dados['titulo'],
                'mensagem' => $dados['mensagem'],
                'campos' => json_encode($dados['campos']),
                'campos_obrigatorios' => json_encode($dados['campos_obrigatorios']),
                'validar_email' => $dados['validar_email'],
                'trocar_senha' => $dados['trocar_senha'],
                'senha_min_caracteres' => $dados['senha_min_caracteres'],
                'senha_nivel' => $dados['senha_nivel'],
                'bloquear_app' => $dados['bloquear_app'],
                'todas_academias' => $dados['todas_academias'],
                'updated_at' => now(),
            ];
            if ($id === null) {
                $id = (int) DB::table('atualizacoes_cadastrais')->insertGetId($linha + ['ativa' => 1, 'criado_por' => $userId, 'created_at' => now()]);
            } else {
                DB::table('atualizacoes_cadastrais')->where('id', $id)->update($linha);
            }

            DB::table('atualizacao_cadastral_academias')->where('atualizacao_id', $id)->delete();
            if (! $dados['todas_academias']) {
                DB::table('atualizacao_cadastral_academias')->insert(
                    array_map(fn ($t) => ['atualizacao_id' => $id, 'tenant_id' => $t], $tenantIds),
                );
            }

            return $id;
        });
    }

    public function encerrar(int $id): void
    {
        DB::table('atualizacoes_cadastrais')->where('id', $id)->update(['ativa' => 0, 'encerrada_em' => now(), 'updated_at' => now()]);
    }

    public function totalRespostas(int $id): int
    {
        return DB::table('atualizacao_cadastral_respostas')->where('atualizacao_id', $id)->count();
    }

    /** Quantos usuários do app a campanha alcança. */
    public function totalPublico(array $campanha): int
    {
        return (int) $this->publicoQuery($campanha)->distinct()->count('tup.usuario_id');
    }

    /**
     * Campanha ativa mais antiga que alcança o usuário e que ele ainda não respondeu.
     *
     * @return array<string, mixed>|null
     */
    public function pendenteDoUsuario(int $usuarioId): ?array
    {
        $ids = DB::table('atualizacoes_cadastrais as ac')
            ->where('ac.ativa', 1)
            ->whereNotExists(fn ($q) => $q->from('atualizacao_cadastral_respostas as r')
                ->whereColumn('r.atualizacao_id', 'ac.id')->where('r.usuario_id', $usuarioId))
            ->whereExists(fn ($q) => $q->from('tenant_usuario_papel as tup')
                ->where('tup.usuario_id', $usuarioId)->where('tup.ativo', 1)->whereIn('tup.papel_id', self::PAPEIS_APP)
                ->where(fn ($w) => $w->where('ac.todas_academias', 1)->orWhereExists(fn ($a) => $a->from('atualizacao_cadastral_academias as aca')
                    ->whereColumn('aca.atualizacao_id', 'ac.id')->whereColumn('aca.tenant_id', 'tup.tenant_id'))))
            ->orderBy('ac.id')
            ->limit(1)
            ->pluck('ac.id');

        return $ids->isEmpty() ? null : $this->find((int) $ids->first());
    }

    /**
     * Valores atuais do cadastro (usuarios, com a cópia antiga de alunos como reserva).
     *
     * @param  list<string>  $campos
     * @return array<string, mixed>
     */
    public function dadosDoUsuario(int $usuarioId, array $campos): array
    {
        $select = ['u.email', 'u.nome', 'u.email_verificado_em'];
        foreach (array_intersect($campos, Pessoa::CAMPOS) as $c) {
            $select[] = Pessoa::select($c, 'u', 'a');
        }
        $row = DB::table('usuarios as u')
            ->leftJoin('alunos as a', 'a.usuario_id', '=', 'u.id')
            ->where('u.id', $usuarioId)
            ->select($select)
            ->first();

        return $row ? (array) $row : [];
    }

    public function emailEmUsoPorOutro(string $email, int $usuarioId): bool
    {
        return DB::table('usuarios')->where('id', '<>', $usuarioId)
            ->where(fn ($q) => $q->where('email', $email)->orWhere('email_global', $email))
            ->exists();
    }

    /** @param  list<string>  $alterados */
    public function registrarResposta(int $atualizacaoId, int $usuarioId, array $alterados, bool $senhaTrocada): void
    {
        DB::table('atualizacao_cadastral_respostas')->insertOrIgnore([
            'atualizacao_id' => $atualizacaoId,
            'usuario_id' => $usuarioId,
            'campos_alterados' => json_encode(array_values($alterados)),
            'senha_trocada' => $senhaTrocada,
            'concluida_em' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function publicoQuery(array $campanha)
    {
        $q = DB::table('tenant_usuario_papel as tup')->where('tup.ativo', 1)->whereIn('tup.papel_id', self::PAPEIS_APP);
        if (! $campanha['todas_academias']) {
            $q->whereIn('tup.tenant_id', array_column($campanha['academias'], 'id') ?: [0]);
        }

        return $q;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, list<array{id: int, nome: string}>>
     */
    private function academiasPorCampanha(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $out = [];
        DB::table('atualizacao_cadastral_academias as aca')
            ->join('tenants as t', 't.id', '=', 'aca.tenant_id')
            ->whereIn('aca.atualizacao_id', $ids)
            ->orderBy('t.nome')
            ->get(['aca.atualizacao_id', 't.id', 't.nome'])
            ->each(function ($r) use (&$out) {
                $out[(int) $r->atualizacao_id][] = ['id' => (int) $r->id, 'nome' => (string) $r->nome];
            });

        return $out;
    }

    /** @return array<string, mixed> */
    private function mapear(object $r, array $academias): array
    {
        $decode = fn ($v) => CamposCadastro::filtrar(is_array($v) ? $v : (json_decode((string) $v, true) ?: []));

        return [
            'id' => (int) $r->id,
            'titulo' => (string) $r->titulo,
            'mensagem' => $r->mensagem,
            'campos' => $decode($r->campos),
            'campos_obrigatorios' => $decode($r->campos_obrigatorios),
            'validar_email' => (bool) $r->validar_email,
            'trocar_senha' => (bool) $r->trocar_senha,
            'senha_min_caracteres' => (int) $r->senha_min_caracteres,
            'senha_nivel' => (string) $r->senha_nivel,
            'bloquear_app' => (bool) $r->bloquear_app,
            'todas_academias' => (bool) $r->todas_academias,
            'academias' => $academias,
            'ativa' => (bool) $r->ativa,
            'encerrada_em' => $r->encerrada_em,
            'created_at' => $r->created_at,
        ];
    }
}
