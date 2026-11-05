<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Usuários com o papel Aluno ativo e sem registro em `alunos` (ex.: admin da Cia da Natação com o papel
 * Aluno marcado pelo SuperAdmin) não viam pacotes nem planos no app. Cria o registro que falta.
 * Só insere: quem já tem registro não muda. O down não apaga (o registro pode ter ganhado matrícula).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alunos') || ! Schema::hasTable('tenant_usuario_papel')) {
            return;
        }

        $semRegistro = DB::table('tenant_usuario_papel as tup')
            ->join('usuarios as u', 'u.id', '=', 'tup.usuario_id')
            ->leftJoin('alunos as a', 'a.usuario_id', '=', 'tup.usuario_id')
            ->where('tup.papel_id', 1)
            ->where('tup.ativo', 1)
            ->whereNull('a.id')
            ->distinct()
            ->get(['u.id', 'u.nome']);

        foreach ($semRegistro as $u) {
            DB::table('alunos')->insert([
                'usuario_id' => (int) $u->id,
                'nome' => (string) ($u->nome ?? ''),
                'ativo' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Sem volta: o registro de aluno pode ter recebido matrícula, check-in ou pagamento.
    }
};
