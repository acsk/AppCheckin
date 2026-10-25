<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atualizacoes_cadastrais', function (Blueprint $table) {
            $table->boolean('dispensar_revalidados')->default(false);
            $table->unsignedSmallInteger('intervalo_meses')->nullable();
        });
        Schema::table('usuarios', function (Blueprint $table) {
            $table->timestamp('cadastro_revalidado_em')->nullable();
        });

        // Aproveita conclusões anteriores e preserva a marca ao excluir campanhas.
        DB::table('usuarios')->whereExists(fn ($q) => $q->from('atualizacao_cadastral_respostas as r')
            ->whereColumn('r.usuario_id', 'usuarios.id'))
            ->update(['cadastro_revalidado_em' => DB::raw('(SELECT MAX(r.concluida_em) FROM atualizacao_cadastral_respostas r WHERE r.usuario_id = usuarios.id)')]);
    }

    public function down(): void
    {
        Schema::table('atualizacoes_cadastrais', fn (Blueprint $table) => $table->dropColumn(['dispensar_revalidados', 'intervalo_meses']));
        Schema::table('usuarios', fn (Blueprint $table) => $table->dropColumn('cadastro_revalidado_em'));
    }
};
