<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Parâmetro por tenant: teto de minutos antes do início da aula para abrir o check-in.
 * valor_padrao 0 = sem teto (usa apenas tolerancia_antes_minutos da turma).
 */
return new class extends Migration
{
    private const CODIGO = 'max_tolerancia_checkin_antes_minutos';

    public function up(): void
    {
        if (DB::table('parametros')->where('codigo', self::CODIGO)->exists()) {
            return;
        }

        $tipoId = DB::table('tipos_parametro')->where('codigo', 'checkin')->value('id');
        if (! $tipoId) {
            throw new RuntimeException("tipos_parametro 'checkin' não encontrado.");
        }

        DB::table('parametros')->insert([
            'tipo_parametro_id' => $tipoId,
            'codigo' => self::CODIGO,
            'nome' => 'Máximo de minutos antes da aula (check-in)',
            'descricao' => 'Limita o quanto antes do horário de início o aluno pode fazer check-in. Ex.: 30 = no máximo 30 minutos antes. 0 = desligado (usa apenas a tolerância de cada turma).',
            'tipo_valor' => 'integer',
            'valor_padrao' => '0',
            'validacao' => json_encode(['min' => 0, 'max' => 1440]),
            'ordem' => 11,
            'ativo' => 1,
            'visivel_tenant' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('parametros')->where('codigo', self::CODIGO)->delete();
    }
};
