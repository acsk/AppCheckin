<?php

namespace Tests\Unit;

use App\Services\AppConfigService;
use App\Services\AppVersionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppVersionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('app')->unique();
            $table->unsignedInteger('version');
            $table->unsignedInteger('release');
            $table->date('release_date');
            $table->boolean('obrigatoria')->default(true);
            $table->unsignedSmallInteger('ranking_checkins_limite')->default(15);
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_25_100000_app_config_remota.php'))->up();
    }

    public function test_store_and_show_formats_version(): void
    {
        $service = new AppVersionService();
        $stored = $service->store('mobile', 1, 5, '2026-10-15');

        $this->assertSame('v1.5', $stored['version']);
        $this->assertSame('v1.5', $service->show('mobile')['version']);
        $this->assertSame(5, $service->show('mobile')['release']);
    }

    public function test_super_admin_define_se_atualizacao_e_obrigatoria(): void
    {
        $service = new AppVersionService();
        $service->store('mobile', 1, 5, '2026-10-15');
        $this->assertTrue($service->show('mobile')['obrigatoria']);

        $this->assertFalse($service->definirObrigatoria('mobile', false)['obrigatoria']);
        // Publicar versão nova não muda a escolha do super admin
        $this->assertFalse($service->store('mobile', 1, 6, '2026-10-24')['obrigatoria']);
    }
    public function test_limite_do_ranking_tem_padrao_15_e_persiste_apos_publicar_versao(): void
    {
        $service = new AppVersionService();
        $this->assertSame(15, $service->limiteRankingCheckins());
        $this->assertSame(25, $service->definirLimiteRankingCheckins(25));
        $service->definirObrigatoria('mobile', false);
        $service->store('mobile', 2, 0, '2026-10-24');
        $this->assertSame(25, $service->limiteRankingCheckins());
        $this->assertFalse($service->show('mobile')['obrigatoria']);
    }

    public function test_apk_antigo_so_fica_bloqueado_quando_a_minima_alcanca_a_publicada(): void
    {
        $config = app(AppConfigService::class);
        $config->aplicarAgora(null, 'versao.android_versao_recomendada', '1.2.0', null, null);
        $config->aplicarAgora(null, 'versao.android_apk_url', 'https://exemplo.com/app.apk', null, null);
        $service = new AppVersionService();

        $this->assertFalse($service->androidRelease()['obrigatoria']);
        $config->aplicarAgora(null, 'versao.android_versao_minima', '1.1.0', null, null);
        $this->assertFalse($service->androidRelease()['obrigatoria']);
        $config->aplicarAgora(null, 'versao.android_versao_minima', '1.2.0', null, null);
        $this->assertTrue($service->androidRelease()['obrigatoria']);
    }

    public function test_apk_registrado_pelo_build_vira_a_versao_publicada_do_android(): void
    {
        (require database_path('migrations/2026_11_01_100000_app_versions_apk_android.php'))->up();
        config(['appcheckin.android_release.manifest' => '']);
        $service = new AppVersionService();
        $this->assertNull($service->androidRelease());

        $service->storeAndroid(['version' => '1.0.7', 'version_code' => 7, 'apk_url' => 'https://exemplo.com/7.apk']);
        $service->storeAndroid(['version' => '1.0.8', 'version_code' => 8, 'apk_url' => 'https://exemplo.com/8.apk', 'tamanho' => 10]);

        $android = $service->androidRelease();
        $this->assertSame('1.0.8', $android['version']);
        $this->assertSame('https://exemplo.com/8.apk', $android['apk_url']);
        $this->assertSame(8, $android['version_code']);
        $this->assertFalse($android['obrigatoria']);
        $at = app(AppConfigService::class)->contrato(['plataforma' => 'android', 'versao_app' => '1.0.7'])['atualizacao'];
        $this->assertTrue($at['disponivel']);
        $this->assertSame('1.0.8', $at['versao_recomendada']);
    }
}
