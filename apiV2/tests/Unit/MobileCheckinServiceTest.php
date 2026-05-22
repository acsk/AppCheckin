<?php

namespace Tests\Unit;

use App\Repositories\AlunoRepository;
use App\Repositories\CheckinRepository;
use App\Repositories\MatriculaRepository;
use App\Repositories\TurmaRepository;
use App\Repositories\UsuarioRepository;
use App\Services\Mobile\MobileCheckinService;
use App\Services\TurmaCheckinBloqueioService;
use Mockery;
use Tests\TestCase;

class MobileCheckinServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_registrar_requires_turma_id_when_access_ok(): void
    {
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $alunos = Mockery::mock(AlunoRepository::class);
        $matriculas = Mockery::mock(MatriculaRepository::class);
        $turmas = Mockery::mock(TurmaRepository::class);
        $checkins = Mockery::mock(CheckinRepository::class);
        $bloqueios = Mockery::mock(TurmaCheckinBloqueioService::class);

        $usuarios->shouldReceive('findById')->andReturn(['nome' => 'Test', 'email' => 'a@b.c']);
        $alunos->shouldReceive('findForTenant')->andReturn(['id' => 10, 'foto_caminho' => null]);
        $alunos->shouldReceive('usuarioTemAcessoTenant')->andReturn(true);
        $matriculas->shouldReceive('atualizarStatusMatriculasVencidas');
        $matriculas->shouldReceive('findElegivelParaCheckin')->andReturn([
            'id' => 1,
            'proxima_data_vencimento' => date('Y-m-d', strtotime('+30 days')),
            'data_vencimento' => null,
        ]);

        $service = new MobileCheckinService(
            $usuarios,
            $alunos,
            $matriculas,
            $turmas,
            $checkins,
            $bloqueios,
        );

        $result = $service->registrar(1, 1, []);

        $this->assertSame(400, $result['status']);
        $this->assertSame('turma_id é obrigatório', $result['body']['error']);
    }
}
