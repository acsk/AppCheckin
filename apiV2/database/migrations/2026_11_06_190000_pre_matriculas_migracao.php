<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pre_matricula_configuracoes')) {
            Schema::create('pre_matricula_configuracoes', function (Blueprint $table) {
                $table->unsignedInteger('tenant_id')->primary();
                $table->boolean('ativa')->default(false);
                $table->boolean('obrigatoria')->default(false);
                $table->string('publico', 20)->default('sem_matricula');
                $table->unsignedInteger('atualizada_por')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pre_matriculas')) {
            Schema::create('pre_matriculas', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('usuario_id');
                $table->unsignedInteger('plano_id');
                $table->text('plano_descricao')->nullable();
                $table->unsignedTinyInteger('checkins_semanais');
                $table->json('grade');
                $table->unsignedTinyInteger('dia_vencimento')->nullable();
                $table->string('status', 20)->default('pendente');
                $table->unsignedInteger('avaliada_por')->nullable();
                $table->timestamp('avaliada_em')->nullable();
                $table->text('observacao')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'status', 'created_at'], 'pre_matriculas_tenant_status_created');
                $table->index(['tenant_id', 'usuario_id', 'status'], 'pre_matriculas_tenant_usuario_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_matriculas');
        Schema::dropIfExists('pre_matricula_configuracoes');
    }
};
