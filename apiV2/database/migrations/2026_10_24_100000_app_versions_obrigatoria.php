<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atualização obrigatória do app mobile passa a ser decidida pelo super admin no painel
 * (antes toda versão nova bloqueava o app). A tabela vinha da API Slim (068_create_app_versions.sql).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_versions')) {
            Schema::create('app_versions', function (Blueprint $t) {
                $t->id();
                $t->string('app', 32)->unique();
                $t->unsignedInteger('version')->default(1);
                $t->unsignedInteger('release')->default(0);
                $t->date('release_date');
                $t->timestamps();
            });
        }

        if (! Schema::hasColumn('app_versions', 'obrigatoria')) {
            Schema::table('app_versions', function (Blueprint $t) {
                // true mantém o comportamento de antes: versão nova bloqueia até atualizar
                $t->boolean('obrigatoria')->default(true)->after('release_date')
                    ->comment('Versão nova bloqueia o app até atualizar; false só avisa');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('app_versions', 'obrigatoria')) {
            Schema::table('app_versions', fn (Blueprint $t) => $t->dropColumn('obrigatoria'));
        }
    }
};
