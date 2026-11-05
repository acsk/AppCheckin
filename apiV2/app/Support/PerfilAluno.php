<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Papel Aluno sem registro em `alunos` deixa a pessoa de fora de tudo que é do aluno no app
 * (pacotes à venda, matrícula, check-in): as consultas partem de `alunos`. Ex.: admin da academia
 * com o papel Aluno marcado pelo painel (Tiago, usuário 69) não via os pacotes.
 */
final class PerfilAluno
{
    /** Garante o registro de aluno do usuário (reativa se inativo). Devolve o id, ou null sem usuário. */
    public static function garantir(int $usuarioId): ?int
    {
        $existente = DB::table('alunos')->where('usuario_id', $usuarioId)->orderBy('id')->first(['id', 'ativo']);
        if ($existente) {
            if (! (int) ($existente->ativo ?? 1)) {
                DB::table('alunos')->where('id', $existente->id)->update(['ativo' => 1, 'updated_at' => now()]);
            }

            return (int) $existente->id;
        }

        $nome = DB::table('usuarios')->where('id', $usuarioId)->value('nome');
        if ($nome === null) {
            return null;
        }

        return (int) DB::table('alunos')->insertGetId([
            'usuario_id' => $usuarioId,
            'nome' => (string) $nome,
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param  array<int, int|string>  $papeis */
    public static function garantirSeAluno(int $usuarioId, array $papeis): void
    {
        if (in_array(Papel::ALUNO, array_map('intval', $papeis), true)) {
            self::garantir($usuarioId);
        }
    }
}
