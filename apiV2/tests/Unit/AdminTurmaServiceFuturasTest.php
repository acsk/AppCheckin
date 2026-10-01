<?php

namespace Tests\Unit;

use App\Models\Parametro;
use App\Repositories\DiaRepository;
use App\Repositories\TurmaRepository;
use App\Services\Admin\AdminTurmaService;
use App\Services\TurmaCheckinBloqueioService;
use App\Support\CheckinToleranciaAntes;
use Mockery;
use Tests\TestCase;

class AdminTurmaServiceFuturasTest extends TestCase
{

    private function turmaOriginal(): array
    {
        return [
            'id' => 10,
            'tenant_id' => 1,
            'dia_id' => 100,
            'dia_data' => '2026-09-02',
            'professor_id' => 5,
            'modalidade_id' => 3,
            'horario_inicio' => '18:00:00',
            'horario_fim' => '19:00:00',
            'nome' => 'Cross - 18:00 - Ana',
            'limite_alunos' => 20,
            'tolerancia_minutos' => 10,
            'tolerancia_antes_minutos' => 480,
            'tolerancia_antes_checkin_minutos' => null,
            'tolerancia_cancelamento_minutos' => null,
        ];
    }

    private function service(TurmaRepository $turmas): AdminTurmaService
    {
        $param = Mockery::mock(Parametro::class);
        $param->shouldReceive('getInt')->andReturn(0);

        return new AdminTurmaService(
            $turmas,
            Mockery::mock(DiaRepository::class),
            Mockery::mock(TurmaCheckinBloqueioService::class),
            new CheckinToleranciaAntes($param),
        );
    }

    /** Payload como o painel envia: todos os campos, só o limite mudou. */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'nome' => 'Cross - 18:00 - Ana',
            'modalidade_id' => 3,
            'professor_id' => 5,
            'horario_inicio' => '18:00:00',
            'horario_fim' => '19:00:00',
            'limite_alunos' => 10,
            'tolerancia_antes_minutos' => 480,
            'tolerancia_minutos' => 10,
            'tolerancia_antes_checkin_minutos' => null,
            'tolerancia_cancelamento_minutos' => null,
        ], $extra);
    }

    public function test_aplica_somente_campos_alterados_nas_futuras(): void
    {
        $original = $this->turmaOriginal();
        $turmas = Mockery::mock(TurmaRepository::class);
        $turmas->shouldReceive('findById')->andReturn($original);
        $turmas->shouldReceive('professorPertenceAoTenant')->andReturn(true);
        $turmas->shouldReceive('verificarHorarioOcupado')->andReturn([]);
        $turmas->shouldReceive('atualizar')->once()->with(10, Mockery::type('array'));
        $turmas->shouldReceive('listarEquivalentesFuturas')->once()->andReturn([
            ['id' => 11, 'dia_id' => 101, 'dia_data' => '2026-09-09'] + $original,
            ['id' => 12, 'dia_id' => 102, 'dia_data' => '2026-09-16'] + $original,
        ]);
        $turmas->shouldReceive('atualizar')->once()->with(11, ['limite_alunos' => 10]);
        $turmas->shouldReceive('atualizar')->once()->with(12, ['limite_alunos' => 10]);

        $result = $this->service($turmas)->update(10, 1, $this->payload(['aplicar_em_futuras' => true]));

        $this->assertSame(200, $result['status']);
        $this->assertSame(2, $result['body']['futuras']['atualizadas']);
        $this->assertSame(['limite_alunos'], $result['body']['futuras']['campos']);
    }

    public function test_pula_futura_com_conflito_ao_mudar_horario(): void
    {
        $original = $this->turmaOriginal();
        $turmas = Mockery::mock(TurmaRepository::class);
        $turmas->shouldReceive('findById')->andReturn($original);
        $turmas->shouldReceive('professorPertenceAoTenant')->andReturn(true);
        // Conflito só na aula de 16/09 (dia 102); a validação da própria turma passa.
        $turmas->shouldReceive('verificarHorarioOcupado')->andReturnUsing(
            fn ($tenant, $diaId) => $diaId === 102 ? [['id' => 99]] : [],
        );
        $turmas->shouldReceive('atualizar')->with(10, Mockery::type('array'))->once();
        $turmas->shouldReceive('listarEquivalentesFuturas')->andReturn([
            ['id' => 11, 'dia_id' => 101, 'dia_data' => '2026-09-09'] + $original,
            ['id' => 12, 'dia_id' => 102, 'dia_data' => '2026-09-16'] + $original,
        ]);
        $turmas->shouldReceive('atualizar')->with(11, Mockery::type('array'))->once();
        $turmas->shouldNotReceive('atualizar')->with(12, Mockery::any());

        $result = $this->service($turmas)->update(10, 1, $this->payload([
            'horario_inicio' => '19:00:00',
            'horario_fim' => '20:00:00',
            'aplicar_em_futuras' => true,
        ]));

        $this->assertSame(1, $result['body']['futuras']['atualizadas']);
        $this->assertSame([['turma_id' => 12, 'data' => '2026-09-16']], $result['body']['futuras']['puladas']);
    }

    public function test_sem_flag_nao_altera_futuras(): void
    {
        $turmas = Mockery::mock(TurmaRepository::class);
        $turmas->shouldReceive('findById')->andReturn($this->turmaOriginal());
        $turmas->shouldReceive('professorPertenceAoTenant')->andReturn(true);
        $turmas->shouldReceive('verificarHorarioOcupado')->andReturn([]);
        $turmas->shouldReceive('atualizar')->once()->with(10, Mockery::type('array'));
        $turmas->shouldNotReceive('listarEquivalentesFuturas');

        $result = $this->service($turmas)->update(10, 1, $this->payload());

        $this->assertSame(200, $result['status']);
        $this->assertNull($result['body']['futuras']);
    }
}
