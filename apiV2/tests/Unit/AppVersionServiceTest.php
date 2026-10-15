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
}
