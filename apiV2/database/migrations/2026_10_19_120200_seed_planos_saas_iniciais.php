<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Planos iniciais do SaaS: Free (padrão de novos tenants) e AppCheckin Completo
 * (R$ 3,50 por aluno ativo, até 100 alunos). Planos antigos viram histórico.
 */
return new class extends Migration
{
    private const PLANOS_ANTIGOS = ['Starter', 'Professional', 'Enterprise'];

    public function up(): void
    {
        $agora = now();

        $free = [
            'codigo' => 'free',
            'nome' => 'Free',
            'descricao' => 'Plano gratuito por tempo indeterminado.',
            'tipo_cobranca' => 'gratuito',
            'valor' => 0,
            'valor_por_aluno' => null,
            'duracao_dias' => 30,
            'max_alunos' => null,
            'ativo' => 1,
            'atual' => 1,
            'ordem' => 0,
            'updated_at' => $agora,
        ];

        $freeExistente = DB::table('planos_sistema')->where('codigo', 'free')->first()
            ?? DB::table('planos_sistema')->where('nome', 'Livre')->where('valor', 0)->first();

        if ($freeExistente) {
            DB::table('planos_sistema')->where('id', $freeExistente->id)->update($free);
        } else {
            DB::table('planos_sistema')->insert($free + ['max_admins' => 1, 'created_at' => $agora]);
        }

        if (! DB::table('planos_sistema')->where('codigo', 'completo')->exists()) {
            DB::table('planos_sistema')->insert([
                'codigo' => 'completo',
                'nome' => 'AppCheckin Completo',
                'descricao' => 'App do aluno, modo professor e painel de gestão. Cobrança por aluno ativo.',
                'tipo_cobranca' => 'por_aluno',
                'valor' => 0,
                'valor_por_aluno' => 3.50,
                'duracao_dias' => 30,
                'max_alunos' => 100,
                'max_admins' => 1,
                'ativo' => 1,
                'atual' => 1,
                'ordem' => 1,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        }

        DB::table('planos_sistema')
            ->whereNull('codigo')
            ->whereIn('nome', self::PLANOS_ANTIGOS)
            ->update(['atual' => 0, 'updated_at' => $agora]);
    }

    public function down(): void
    {
        $completo = DB::table('planos_sistema')->where('codigo', 'completo')->first();
        if ($completo && ! DB::table('tenant_planos_sistema')->where('plano_sistema_id', $completo->id)->exists()) {
            DB::table('planos_sistema')->where('id', $completo->id)->delete();
        }

        DB::table('planos_sistema')
            ->whereNull('codigo')
            ->whereIn('nome', self::PLANOS_ANTIGOS)
            ->update(['atual' => 1]);
    }
};
