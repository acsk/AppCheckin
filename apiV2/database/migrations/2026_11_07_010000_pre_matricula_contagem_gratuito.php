<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pre_matricula_configuracoes') && ! Schema::hasColumn('pre_matricula_configuracoes', 'contagem_gratuito')) {
            Schema::table('pre_matricula_configuracoes', function (Blueprint $table) {
                // dia_vencimento: o período gratuito vai até o próximo vencimento escolhido + o ciclo do plano
                // aprovacao: o período gratuito conta a partir do dia da aprovação
                $table->string('contagem_gratuito', 20)->default('dia_vencimento');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pre_matricula_configuracoes') && Schema::hasColumn('pre_matricula_configuracoes', 'contagem_gratuito')) {
            Schema::table('pre_matricula_configuracoes', fn (Blueprint $table) => $table->dropColumn('contagem_gratuito'));
        }
    }
};
