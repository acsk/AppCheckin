<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Acessar como academia": o super admin entra numa academia por tempo limitado, com motivo.
 * Cada sessão e cada ação que altera dados ficam registradas, e a academia vê os acessos
 * em Minha Conta → Segurança. Regras em App\Services\SuperAdmin\AcessoSuporteService.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('acessos_suporte')) {
            Schema::create('acessos_suporte', function (Blueprint $t) {
                $t->increments('id');
                $t->integer('tenant_id');
                $t->integer('usuario_id')->comment('Super admin que acessou');
                $t->text('motivo');
                $t->dateTime('iniciado_em');
                $t->dateTime('expira_em');
                $t->dateTime('encerrado_em')->nullable()->comment('NULL = ainda válido até expira_em');
                $t->string('ip', 45)->nullable();
                $t->string('user_agent', 255)->nullable();
                $t->timestamps();

                $t->index(['tenant_id', 'iniciado_em'], 'idx_acessos_suporte_tenant');
                $t->index(['usuario_id', 'iniciado_em'], 'idx_acessos_suporte_usuario');
                $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $t->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('acessos_suporte_acoes')) {
            Schema::create('acessos_suporte_acoes', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('acesso_suporte_id');
                $t->string('metodo', 10);
                $t->string('rota', 255);
                $t->unsignedSmallInteger('status');
                $t->timestamp('created_at')->useCurrent();

                $t->index(['acesso_suporte_id', 'created_at'], 'idx_acessos_suporte_acoes');
                $t->foreign('acesso_suporte_id')->references('id')->on('acessos_suporte')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('acessos_suporte_acoes');
        Schema::dropIfExists('acessos_suporte');
    }
};
