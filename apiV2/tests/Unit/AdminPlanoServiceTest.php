<?php

namespace Tests\Unit;

use App\Repositories\AdminPlanoCicloRepository;
use App\Repositories\AdminPlanoRepository;
use App\Services\Admin\AdminPlanoCicloService;
use App\Services\Admin\AdminPlanoService;
use Mockery;
use Tests\TestCase;

class AdminPlanoServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_requires_fields(): void
    {
        $service = new AdminPlanoService(Mockery::mock(AdminPlanoRepository::class));
        $result = $service->create(3, []);

        $this->assertSame(422, $result['status']);
        $this->assertContains('Modalidade é obrigatória', $result['body']['errors']);
        $this->assertContains('Nome é obrigatório', $result['body']['errors']);
    }

    public function test_show_not_found(): void
    {
        $repo = Mockery::mock(AdminPlanoRepository::class);
        $repo->shouldReceive('findById')->once()->with(9, 3)->andReturn(null);

        $service = new AdminPlanoService($repo);
        $result = $service->show(9, 3);

        $this->assertSame(404, $result['status']);
        $this->assertSame('Plano não encontrado', $result['body']['error']);
    }

    public function test_ciclo_criar_requires_frequencia(): void
    {
        $service = new AdminPlanoCicloService(
            Mockery::mock(AdminPlanoCicloRepository::class),
            Mockery::mock(AdminPlanoRepository::class),
        );

        $result = $service->criar(1, 3, ['valor' => 100]);

        $this->assertSame(422, $result['status']);
        $this->assertContains('Frequência de assinatura é obrigatória', $result['body']['errors']);
    }

    public function test_listar_frequencias(): void
    {
        $repo = Mockery::mock(AdminPlanoCicloRepository::class);
        $repo->shouldReceive('listarFrequencias')->once()->andReturn([
            ['id' => 1, 'nome' => 'Mensal', 'codigo' => 'mensal', 'meses' => 1, 'ordem' => 1],
        ]);

        $service = new AdminPlanoCicloService($repo, Mockery::mock(AdminPlanoRepository::class));
        $result = $service->listarFrequencias();

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['success']);
        $this->assertSame('Mensal', $result['body']['data'][0]['nome']);
    }
}
