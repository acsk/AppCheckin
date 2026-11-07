<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pre_matriculas') && ! Schema::hasColumn('pre_matriculas', 'enviada_por_id')) {
            Schema::table('pre_matriculas', function (Blueprint $table) {
                // Quem enviou o pedido: o próprio aluno ou o responsável, no caso de dependente
                $table->unsignedInteger('enviada_por_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pre_matriculas') && Schema::hasColumn('pre_matriculas', 'enviada_por_id')) {
            Schema::table('pre_matriculas', fn (Blueprint $table) => $table->dropColumn('enviada_por_id'));
        }
    }
};
