<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atualização cadastral pedida pelo super admin: uma campanha diz quais campos o usuário do app
 * revisa (e quais são obrigatórios), se valida o e-mail por código e se troca a senha (com política).
 * Cada usuário responde uma vez por campanha.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('atualizacoes_cadastrais')) {
            Schema::create('atualizacoes_cadastrais', function (Blueprint $t) {
                $t->increments('id');
                $t->string('titulo', 120);
                $t->text('mensagem')->nullable()->comment('Texto mostrado ao usuário no app');
                $t->json('campos')->comment('Campos do cadastro que o usuário revisa');
                $t->json('campos_obrigatorios')->comment('Subconjunto de campos que não podem ficar vazios');
                $t->boolean('validar_email')->default(false);
                $t->boolean('trocar_senha')->default(false);
                $t->unsignedTinyInteger('senha_min_caracteres')->default(8);
                $t->string('senha_nivel', 10)->default('media')->comment('basica, media ou forte');
                $t->boolean('bloquear_app')->default(true)->comment('false: o usuário pode responder depois');
                $t->boolean('todas_academias')->default(true);
                $t->boolean('ativa')->default(true);
                $t->unsignedInteger('criado_por')->nullable();
                $t->timestamp('encerrada_em')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('atualizacao_cadastral_academias')) {
            Schema::create('atualizacao_cadastral_academias', function (Blueprint $t) {
                $t->unsignedInteger('atualizacao_id');
                $t->unsignedInteger('tenant_id');
                $t->primary(['atualizacao_id', 'tenant_id']);
                $t->foreign('atualizacao_id')->references('id')->on('atualizacoes_cadastrais')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('atualizacao_cadastral_respostas')) {
            Schema::create('atualizacao_cadastral_respostas', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('atualizacao_id');
                $t->unsignedInteger('usuario_id');
                $t->json('campos_alterados')->nullable();
                $t->boolean('senha_trocada')->default(false);
                $t->timestamp('concluida_em');
                $t->timestamps();
                $t->unique(['atualizacao_id', 'usuario_id']);
                $t->foreign('atualizacao_id')->references('id')->on('atualizacoes_cadastrais')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('verificacoes_email')) {
            Schema::create('verificacoes_email', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('usuario_id')->index();
                $t->string('email', 255);
                $t->string('codigo_hash', 255);
                $t->unsignedTinyInteger('tentativas')->default(0);
                $t->timestamp('expira_em');
                $t->timestamp('validado_em')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasColumn('usuarios', 'email_verificado_em')) {
            Schema::table('usuarios', function (Blueprint $t) {
                $t->timestamp('email_verificado_em')->nullable()->comment('Último e-mail confirmado por código');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('usuarios', 'email_verificado_em')) {
            Schema::table('usuarios', fn (Blueprint $t) => $t->dropColumn('email_verificado_em'));
        }
        Schema::dropIfExists('verificacoes_email');
        Schema::dropIfExists('atualizacao_cadastral_respostas');
        Schema::dropIfExists('atualizacao_cadastral_academias');
        Schema::dropIfExists('atualizacoes_cadastrais');
    }
};
