<?php

use App\Services\AcessoHibridoService;
use App\Services\Admin\AdminTurmaFixaService;
use App\Support\AcademyDateTime;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\HybridFixture;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
HybridFixture::mysql();
$service = HybridFixture::checkinService(($argv[2] ?? '') === 'diario');
$modo = $argv[2] ?? '';
if ($modo === 'reposicao') {
    echo json_encode(HybridFixture::reposicaoService()->adicionarReposicao(90, 3, ['aluno_id' => (int) $argv[1], 'turma_id' => (int) ($argv[3] ?? 1)]));
    exit;
}
if ($modo === 'grade') {
    try {
        $app->make(AdminTurmaFixaService::class)->vincularMatricula(3, (int) $argv[1], [
            'tipo_acesso' => 'turma_fixa', 'grade' => [['turma_fixa_id' => 1, 'dia_semana' => (int) AcademyDateTime::now()->format('N')]],
        ]);
        echo json_encode(['status' => 200]);
    } catch (ValidationException $e) {
        echo json_encode(['status' => 422, 'errors' => $e->errors()]);
    }
    exit;
}
echo json_encode($modo === 'ativar'
    ? ['status' => $app->make(AcessoHibridoService::class)->aplicarStatus(3, (int) $argv[1], 1)]
    : $service->registrar((int) $argv[1], 3, ['turma_id' => (int) ($argv[3] ?? 1)]));
