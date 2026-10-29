<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * APK Android publicado pelo build (npm run android:apk) na mesma tabela da versão web,
 * linha app = 'android': version = versionCode, versao_nome = expo.version (1.0.8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_versions', function (Blueprint $t) {
            if (! Schema::hasColumn('app_versions', 'versao_nome')) {
                $t->string('versao_nome', 20)->nullable()->after('release_date')->comment('APK: expo.version (ex.: 1.0.8)');
            }
            if (! Schema::hasColumn('app_versions', 'url')) {
                $t->string('url', 500)->nullable()->after('versao_nome')->comment('APK: link de download');
            }
            if (! Schema::hasColumn('app_versions', 'sha256')) {
                $t->char('sha256', 64)->nullable()->after('url');
            }
            if (! Schema::hasColumn('app_versions', 'tamanho')) {
                $t->unsignedBigInteger('tamanho')->nullable()->after('sha256')->comment('APK: bytes');
            }
        });
    }

    public function down(): void
    {
        foreach (['tamanho', 'sha256', 'url', 'versao_nome'] as $coluna) {
            if (Schema::hasColumn('app_versions', $coluna)) {
                Schema::table('app_versions', fn (Blueprint $t) => $t->dropColumn($coluna));
            }
        }
    }
};
