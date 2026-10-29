<?php

namespace Tests\Feature;

use App\Services\AppVersionService;
use Mockery;
use Tests\TestCase;

class V2AppVersionRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_mobile_version_is_public(): void
    {
        $service = Mockery::mock(AppVersionService::class);
        $service->shouldReceive('show')
            ->once()
            ->with('mobile')
            ->andReturn([
                'app' => 'mobile',
                'version' => 'v1.3',
                'version_number' => 1,
                'release' => 3,
                'release_date' => '2026-10-15',
            ]);
        $service->shouldReceive('androidRelease')->andReturn(null);
        $this->app->instance(AppVersionService::class, $service);

        $this->getJson('/v2/version/mobile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.version', 'v1.3');
    }

    public function test_get_mobile_version_inclui_apk_android_quando_configurado(): void
    {
        $service = Mockery::mock(AppVersionService::class);
        $service->shouldReceive('show')->with('mobile')->andReturn(['app' => 'mobile', 'version' => 'v1.3', 'obrigatoria' => false]);
        $service->shouldReceive('androidRelease')->andReturn(['version' => '1.0.2', 'apk_url' => 'https://exemplo.com/app.apk', 'obrigatoria' => false]);
        $this->app->instance(AppVersionService::class, $service);

        $this->getJson('/v2/version/mobile')
            ->assertOk()
            ->assertJsonPath('data.android.version', '1.0.2')
            ->assertJsonPath('data.android.apk_url', 'https://exemplo.com/app.apk')
            ->assertJsonPath('data.android.obrigatoria', false);
    }

    public function test_post_mobile_version_requires_token_in_production(): void
    {
        config([
            'app.env' => 'production',
            'appcheckin.app_version_update_token' => 'secret-token',
        ]);

        $this->postJson('/v2/version/mobile', [
            'version' => 1,
            'release' => 4,
            'release_date' => '2026-10-15',
        ])->assertUnauthorized();
    }

    public function test_post_mobile_version_updates_with_token(): void
    {
        config(['appcheckin.app_version_update_token' => 'secret-token']);

        $service = Mockery::mock(AppVersionService::class);
        $service->shouldReceive('store')
            ->once()
            ->with('mobile', 1, 4, '2026-10-15')
            ->andReturn([
                'app' => 'mobile',
                'version' => 'v1.4',
                'version_number' => 1,
                'release' => 4,
                'release_date' => '2026-10-15',
            ]);
        $this->app->instance(AppVersionService::class, $service);

        $this->postJson('/v2/version/mobile', [
            'version' => 1,
            'release' => 4,
            'release_date' => '2026-10-15',
        ], [
            'X-App-Version-Token' => 'secret-token',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.version', 'v1.4');
    }

    public function test_post_android_version_requires_token_in_production(): void
    {
        config(['app.env' => 'production', 'appcheckin.app_version_update_token' => 'secret-token']);

        $this->postJson('/v2/version/android', ['version' => '1.0.8', 'version_code' => 8, 'apk_url' => 'https://exemplo.com/a.apk'])
            ->assertUnauthorized();
    }

    public function test_post_android_version_registra_apk_com_token(): void
    {
        config(['appcheckin.app_version_update_token' => 'secret-token']);

        $service = Mockery::mock(AppVersionService::class);
        $service->shouldReceive('storeAndroid')->once()
            ->with(['version' => '1.0.8', 'version_code' => 8, 'apk_url' => 'https://exemplo.com/a.apk'])
            ->andReturn(['version' => '1.0.8', 'version_code' => 8, 'url' => 'https://exemplo.com/a.apk']);
        $this->app->instance(AppVersionService::class, $service);

        $this->postJson('/v2/version/android', ['version' => '1.0.8', 'version_code' => 8, 'apk_url' => 'https://exemplo.com/a.apk'], ['X-App-Version-Token' => 'secret-token'])
            ->assertOk()
            ->assertJsonPath('data.version', '1.0.8');
        $this->postJson('/v2/version/android', ['version' => 'v1.0', 'version_code' => 8, 'apk_url' => 'https://exemplo.com/a.apk'], ['X-App-Version-Token' => 'secret-token'])
            ->assertUnprocessable();
    }
}
