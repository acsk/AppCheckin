<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pre_matriculas') && ! Schema::hasColumn('pre_matriculas', 'matricula_id')) {
            Schema::table('pre_matriculas', function (Blueprint $table) {
                $table->unsignedInteger('matricula_id')->nullable()->index('pre_matriculas_matricula_id_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pre_matriculas') && Schema::hasColumn('pre_matriculas', 'matricula_id')) {
            Schema::table('pre_matriculas', function (Blueprint $table) {
                $table->dropIndex('pre_matriculas_matricula_id_index');
                $table->dropColumn('matricula_id');
            });
        }
    }
};
