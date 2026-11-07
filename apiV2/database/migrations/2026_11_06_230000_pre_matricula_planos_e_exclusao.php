<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pre_matricula_planos')) {
            Schema::create('pre_matricula_planos', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('plano_free_id');
                $table->unsignedInteger('plano_pago_id');
                $table->timestamps();
                $table->unique(['tenant_id', 'plano_free_id'], 'pre_matricula_planos_tenant_free_unique');
            });
        }

        if (Schema::hasTable('pre_matriculas')) {
            Schema::table('pre_matriculas', function (Blueprint $table) {
                if (! Schema::hasColumn('pre_matriculas', 'plano_destino_id')) {
                    $table->unsignedInteger('plano_destino_id')->nullable();
                }
                if (! Schema::hasColumn('pre_matriculas', 'excluida_em')) {
                    $table->timestamp('excluida_em')->nullable();
                }
                if (! Schema::hasColumn('pre_matriculas', 'excluida_por')) {
                    $table->unsignedInteger('excluida_por')->nullable();
                }
            });
        }

        if (Schema::hasTable('matriculas') && ! Schema::hasColumn('matriculas', 'plano_destino_id')) {
            Schema::table('matriculas', function (Blueprint $table) {
                $table->unsignedInteger('plano_destino_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('matriculas') && Schema::hasColumn('matriculas', 'plano_destino_id')) {
            Schema::table('matriculas', fn (Blueprint $table) => $table->dropColumn('plano_destino_id'));
        }
        if (Schema::hasTable('pre_matriculas')) {
            foreach (['plano_destino_id', 'excluida_em', 'excluida_por'] as $coluna) {
                if (Schema::hasColumn('pre_matriculas', $coluna)) {
                    Schema::table('pre_matriculas', fn (Blueprint $table) => $table->dropColumn($coluna));
                }
            }
        }
        Schema::dropIfExists('pre_matricula_planos');
    }
};
