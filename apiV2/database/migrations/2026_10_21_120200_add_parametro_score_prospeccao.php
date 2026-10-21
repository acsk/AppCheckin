<?php

use App\Services\Admin\Prospeccao\ProspeccaoScoreService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pesos do score de recuperação por tenant (parametros + parametros_tenant).
 * Fica fora da tela genérica de parâmetros (visivel_tenant = 0): é editado no módulo Prospecção.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tipoId = DB::table('tipos_parametro')->where('codigo', 'prospeccao')->value('id');
        if (! $tipoId) {
            $tipoId = DB::table('tipos_parametro')->insertGetId([
                'codigo' => 'prospeccao',
                'nome' => 'Prospecção',
                'descricao' => 'Recuperação de alunos e funil comercial',
                'icone' => 'fa-bullhorn',
                'ordem' => 7,
                'ativo' => 1,
            ]);
        }

        if (DB::table('parametros')->where('codigo', ProspeccaoScoreService::PARAMETRO)->exists()) {
            return;
        }

        DB::table('parametros')->insert([
            'tipo_parametro_id' => $tipoId,
            'codigo' => ProspeccaoScoreService::PARAMETRO,
            'nome' => 'Score de recuperação',
            'descricao' => 'Pesos dos critérios e motivos de cancelamento considerados reversíveis',
            'tipo_valor' => 'json',
            'valor_padrao' => json_encode(ProspeccaoScoreService::CONFIG_PADRAO),
            'ordem' => 1,
            'visivel_admin' => 1,
            'visivel_tenant' => 0,
            'ativo' => 1,
        ]);
    }

    public function down(): void
    {
        $param = DB::table('parametros')->where('codigo', ProspeccaoScoreService::PARAMETRO)->first();
        if ($param) {
            DB::table('parametros_tenant')->where('parametro_id', $param->id)->delete();
            DB::table('parametros')->where('id', $param->id)->delete();
        }
        DB::table('tipos_parametro')->where('codigo', 'prospeccao')->delete();
    }
};
