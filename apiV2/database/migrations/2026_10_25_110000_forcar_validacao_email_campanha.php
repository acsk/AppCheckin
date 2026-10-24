<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atualizacoes_cadastrais', function (Blueprint $t) {
            $t->boolean('forcar_validacao_email')->default(false);
        });
        Schema::table('verificacoes_email', function (Blueprint $t) {
            $t->unsignedInteger('atualizacao_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('verificacoes_email', function (Blueprint $t) {
            $t->dropIndex(['atualizacao_id']);
            $t->dropColumn('atualizacao_id');
        });
        Schema::table('atualizacoes_cadastrais', fn (Blueprint $t) => $t->dropColumn('forcar_validacao_email'));
    }
};
