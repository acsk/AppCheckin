<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Parâmetro por tenant: teto de minutos antes do início da aula para abrir o check-in.
 * valor_padrao 0 = sem teto (usa apenas tolerancia_antes_minutos da turma).
 *
 * INSERT ... SELECT único: idempotente e compatível com `migrate --pretend`
 * (no pretend os SELECTs não executam, então nada de consultas prévias em PHP).
 */
return new class extends Migration
{
    private const CODIGO = 'max_tolerancia_checkin_antes_minutos';

    public function up(): void
    {
        DB::statement(
            "INSERT INTO parametros (
                tipo_parametro_id, codigo, nome, descricao, tipo_valor, valor_padrao,
                validacao, ordem, ativo, visivel_tenant, created_at, updated_at
            )
            SELECT tp.id, ?, ?, ?, 'integer', '0', ?, 11, 1, 1, NOW(), NOW()
            FROM tipos_parametro tp
            WHERE tp.codigo = 'checkin'
              AND NOT EXISTS (SELECT 1 FROM parametros p WHERE p.codigo = ?)",
            [
                self::CODIGO,
                'Máximo de minutos antes da aula (check-in)',
                'Limita o quanto antes do horário de início o aluno pode fazer check-in. Ex.: 30 = no máximo 30 minutos antes. 0 = desligado (usa apenas a tolerância de cada turma).',
                json_encode(['min' => 0, 'max' => 1440]),
                self::CODIGO,
            ],
        );
    }

    public function down(): void
    {
        DB::table('parametros')->where('codigo', self::CODIGO)->delete();
    }
};
