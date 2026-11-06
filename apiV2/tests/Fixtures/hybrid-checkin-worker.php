<?php

use App\Services\AcessoHibridoService;
use Illuminate\Contracts\Console\Kernel;
use Tests\Fixtures\HybridFixture;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
HybridFixture::mysql();
$service = HybridFixture::checkinService(($argv[2] ?? '') === 'diario');
echo json_encode(($argv[2] ?? '') === 'ativar'
    ? ['status' => $app->make(AcessoHibridoService::class)->aplicarStatus(3, (int) $argv[1], 1)]
    : $service->registrar((int) $argv[1], 3, ['turma_id' => (int) ($argv[3] ?? 1)]));
