<?php

namespace Tests\Unit;

use App\Repositories\ApplicationErrorLogRepository;
use App\Services\ApplicationErrorLogService;
use Mockery;
use Tests\TestCase;

class ApplicationErrorLogServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_record_persists_error_and_builds_fingerprint(): void
    {
        $repo = Mockery::mock(ApplicationErrorLogRepository::class);
        $repo->shouldReceive('tableExists')->once()->andReturn(true);
        $repo->shouldReceive('insert')->once()->with(Mockery::on(function (array $data): bool {
            $this->assertSame('error', $data['level']);
            $this->assertNotEmpty($data['fingerprint']);
            $this->assertSame(64, strlen($data['fingerprint']));
            $this->assertStringContainsString('Falha ao processar pagamento', $data['description']);

            return true;
        }))->andReturn(42);
        $repo->shouldReceive('countSince')->once()->andReturn(1);

        config([
            'appcheckin.error_alert_email' => '',
            'appcheckin.error_alert_throttle_minutes' => 15,
        ]);

        $service = new ApplicationErrorLogService($repo);
        $id = $service->record('Falha ao processar pagamento #123', 'error');

        $this->assertSame(42, $id);
    }

    public function test_record_ignores_info_level(): void
    {
        $repo = Mockery::mock(ApplicationErrorLogRepository::class);
        $repo->shouldNotReceive('insert');

        $service = new ApplicationErrorLogService($repo);
        $this->assertNull($service->record('debug msg', 'info'));
    }
}
