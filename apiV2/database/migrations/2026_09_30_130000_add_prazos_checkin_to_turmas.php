<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prazos do aluno por turma (NULL = comportamento anterior):
 * - tolerancia_antes_checkin_minutos: check-in fecha X min ANTES do início (depois só inclusão manual).
 * - tolerancia_cancelamento_minutos: aluno pode desfazer o check-in até X min antes do início.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turmas', function (Blueprint $table) {
            if (! Schema::hasColumn('turmas', 'tolerancia_antes_checkin_minutos')) {
                $table->unsignedInteger('tolerancia_antes_checkin_minutos')
                    ->nullable()
                    ->after('tolerancia_antes_minutos')
                    ->comment('Check-in fecha X min antes do início; NULL = fecha em início + tolerancia_minutos');
            }

            if (! Schema::hasColumn('turmas', 'tolerancia_cancelamento_minutos')) {
                $table->unsignedInteger('tolerancia_cancelamento_minutos')
                    ->nullable()
                    ->after('tolerancia_antes_checkin_minutos')
                    ->comment('Aluno pode desfazer até X min antes do início; NULL = até o início');
            }
        });
    }

    public function down(): void
    {
        Schema::table('turmas', function (Blueprint $table) {
            foreach (['tolerancia_cancelamento_minutos', 'tolerancia_antes_checkin_minutos'] as $coluna) {
                if (Schema::hasColumn('turmas', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
