<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Ver app como aluno": admin da academia (ou super admin no "acessar como academia") abre o app
 * web logado como o aluno, só para visualizar. Cada acesso fica registrado com o motivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('acessos_aluno')) {
            return;
        }
        Schema::create('acessos_aluno', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('aluno_id');
            $t->integer('aluno_usuario_id');
            $t->integer('operador_usuario_id')->comment('Admin ou super admin que abriu');
            $t->unsignedInteger('acesso_suporte_id')->nullable()->comment('Super admin: acesso à academia em que foi aberto');
            $t->string('motivo', 500);
            $t->dateTime('iniciado_em');
            $t->dateTime('expira_em');
            $t->dateTime('encerrado_em')->nullable()->comment('NULL = ainda válido até expira_em');
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamps();

            $t->index(['tenant_id', 'iniciado_em'], 'idx_acessos_aluno_tenant');
            $t->index(['operador_usuario_id', 'encerrado_em'], 'idx_acessos_aluno_operador');
            $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acessos_aluno');
    }
};
