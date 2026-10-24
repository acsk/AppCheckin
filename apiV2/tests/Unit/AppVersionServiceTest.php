<?php

namespace Tests\Unit;

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

}
