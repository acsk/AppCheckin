<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuração remota do app (catálogo em App\Support\AppConfigCatalogo).
 * - app_config_valores: rascunho editado pelo super admin (tenant_id null = global). Só o que difere do padrão.
 * - app_config_publicacoes: cada publicação guarda o retrato completo; o app lê a última. Rollback = republicar um retrato.
 * - app_config_auditoria: quem mudou o quê.
 * O estado atual (app_versions.obrigatoria, ranking_checkins_limite e o APK do .env) vira a publicação 1.
 * As colunas antigas de app_versions ficam, mas deixam de ser lidas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_config_valores')) {
            Schema::create('app_config_valores', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('tenant_id')->nullable()->index();
                $t->string('chave', 80);
                $t->text('valor');
                $t->unsignedInteger('atualizado_por')->nullable();
                $t->timestamps();
                $t->unique(['tenant_id', 'chave']);
            });
        }

        if (! Schema::hasTable('app_config_publicacoes')) {
            Schema::create('app_config_publicacoes', function (Blueprint $t) {
                $t->id();
                $t->longText('conteudo');
                $t->string('nota', 255)->nullable();
                $t->string('origem', 20)->default('publicacao')->comment('publicacao | rollback | imediata | migracao');
                $t->unsignedBigInteger('rollback_de')->nullable();
                $t->unsignedInteger('publicado_por')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('app_config_auditoria')) {
            Schema::create('app_config_auditoria', function (Blueprint $t) {
                $t->id();
                $t->string('acao', 20);
                $t->unsignedInteger('tenant_id')->nullable();
                $t->string('chave', 80)->nullable();
                $t->text('valor_anterior')->nullable();
                $t->text('valor_novo')->nullable();
                $t->unsignedBigInteger('versao')->nullable();
                $t->string('nota', 255)->nullable();
                $t->unsignedInteger('usuario_id')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index('created_at');
            });
        }

        if (DB::table('app_config_publicacoes')->exists()) {
            return;
        }

        $global = [];
        $mobile = Schema::hasTable('app_versions') ? DB::table('app_versions')->where('app', 'mobile')->first() : null;
        if ($mobile && property_exists($mobile, 'ranking_checkins_limite') && (int) $mobile->ranking_checkins_limite !== 15) {
            $global['parametro.ranking_limite_home'] = min(100, max(1, (int) $mobile->ranking_checkins_limite));
        }
        $obrigatoria = ! $mobile || ! property_exists($mobile, 'obrigatoria') || (bool) $mobile->obrigatoria;
        if (! $obrigatoria) {
            $global['versao.web_obrigatoria'] = false;
        }
        // Antes a obrigatoriedade valia também para o APK: quem estava abaixo do publicado ficava bloqueado
        $apk = (string) config('appcheckin.android_release.version', '');
        if ($obrigatoria && $apk !== '') {
            $global['versao.android_versao_minima'] = ltrim($apk, 'vV');
        }

        $now = now();
        foreach ($global as $chave => $valor) {
            DB::table('app_config_valores')->insert(['tenant_id' => null, 'chave' => $chave, 'valor' => json_encode($valor), 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('app_config_publicacoes')->insert([
            'conteudo' => json_encode(['global' => (object) $global, 'tenants' => (object) []]),
            'nota' => 'Estado inicial (app_versions e .env)',
            'origem' => 'migracao',
            'created_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_config_auditoria');
        Schema::dropIfExists('app_config_publicacoes');
        Schema::dropIfExists('app_config_valores');
    }
};
