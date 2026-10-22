<?php

use App\Services\Admin\Prospeccao\ProspeccaoSincronizacaoService as Sinc;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Janela da Recuperação de Alunos por academia (antes fixa em 24 meses no código), na tela de Parâmetros:
 * entra quem cancelou nos últimos N meses e há pelo menos M meses.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tipoId = DB::table('tipos_parametro')->where('codigo', 'prospeccao')->value('id');
        if (! $tipoId) {
            $tipoId = DB::table('tipos_parametro')->insertGetId([
                'codigo' => 'prospeccao', 'nome' => 'Prospecção', 'descricao' => 'Recuperação de alunos e funil comercial',
                'icone' => 'fa-bullhorn', 'ordem' => 7, 'ativo' => 1,
            ]);
        }

        $parametros = [
            [Sinc::PARAM_JANELA, 'Recuperação: cancelados nos últimos (meses)', 'Só entram na Recuperação de Alunos os alunos que cancelaram nos últimos N meses. Ex.: 12 = cancelamentos do último ano. Padrão: 24.', (string) Sinc::JANELA_MESES, 2],
            [Sinc::PARAM_CARENCIA, 'Recuperação: cancelados há pelo menos (meses)', 'Espera N meses após o cancelamento antes de o aluno entrar na Recuperação. 0 = entra assim que cancela.', '0', 3],
        ];
        foreach ($parametros as [$codigo, $nome, $descricao, $padrao, $ordem]) {
            if (DB::table('parametros')->where('codigo', $codigo)->exists()) {
                continue;
            }
            DB::table('parametros')->insert([
                'tipo_parametro_id' => $tipoId, 'codigo' => $codigo, 'nome' => $nome, 'descricao' => $descricao,
                'tipo_valor' => 'integer', 'valor_padrao' => $padrao, 'ordem' => $ordem,
                'visivel_admin' => 1, 'visivel_tenant' => 1, 'ativo' => 1,
            ]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('parametros')->whereIn('codigo', [Sinc::PARAM_JANELA, Sinc::PARAM_CARENCIA])->pluck('id');
        DB::table('parametros_tenant')->whereIn('parametro_id', $ids)->delete();
        DB::table('parametros')->whereIn('id', $ids)->delete();
    }
};
