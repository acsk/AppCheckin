<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Usuário de suporte do Check-in (usuarios.suporte = 1): vinculado a toda academia como Admin + Aluno,
 * invisível para o tenant e editável só pelo super admin. Vínculo em App\Services\UsuarioSuporteService.
 *
 * Toda listagem/edição de usuários e alunos feita pelo tenant passa por ocultar()/sqlOcultar()/eh().
 */
final class UsuarioSuporte
{
    private const CACHE = 'appcheckin.usuario_suporte_id';

    /** Id do usuário de suporte (cache por requisição), ou null se não houver. */
    public static function id(): ?int
    {
        if (app()->bound(self::CACHE)) {
            return app(self::CACHE);
        }
        $id = Schema::hasColumn('usuarios', 'suporte')
            ? DB::table('usuarios')->where('suporte', 1)->value('id')
            : null;
        $id = $id ? (int) $id : null;
        app()->instance(self::CACHE, $id);

        return $id;
    }

    public static function eh(?int $usuarioId): bool
    {
        return $usuarioId !== null && $usuarioId > 0 && self::id() === $usuarioId;
    }

    /** Remove o usuário de suporte da query (coluna com o id do usuário, ex.: 'u.id', 'a.usuario_id'). */
    public static function ocultar(Builder $query, string $colunaUsuarioId): Builder
    {
        if (($id = self::id()) !== null) {
            $query->where($colunaUsuarioId, '<>', $id);
        }

        return $query;
    }

    /**
     * Para SQL cru: [" AND coluna <> ?", [id]] ou ['', []].
     *
     * @return array{0: string, 1: list<int>}
     */
    public static function sqlOcultar(string $colunaUsuarioId): array
    {
        $id = self::id();

        return $id === null ? ['', []] : [" AND {$colunaUsuarioId} <> ?", [$id]];
    }

    /** Limpa o cache (após marcar/desmarcar o suporte). */
    public static function esquecer(): void
    {
        app()->forgetInstance(self::CACHE);
    }
}
