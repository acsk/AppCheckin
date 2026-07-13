<?php

namespace Tests\Unit;

use App\Repositories\ModalidadeRepository;
use App\Services\Admin\AdminModalidadeService;
use Mockery;
use Tests\TestCase;

class AdminModalidadeServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_modalidades(): void
    {
        $repo = Mockery::mock(ModalidadeRepository::class);
        $repo->shouldReceive('listarPorTenant')
            ->once()
            ->with(3, false)
            ->andReturn([['id' => 1, 'nome' => 'Natação']]);

        $service = new AdminModalidadeService($repo);
        $result = $service->index(3, false);

        $this->assertSame(200, $result['status']);
        $this->assertSame('Natação', $result['body']['modalidades'][0]['nome']);
    }

    public function test_create_requires_nome(): void
    {
        $service = new AdminModalidadeService(Mockery::mock(ModalidadeRepository::class));
        $result = $service->create(3, []);

        $this->assertSame(422, $result['status']);
        $this->assertSame('error', $result['body']['type']);
        $this->assertSame('Nome é obrigatório', $result['body']['message']);
    }

    public function test_show_not_found(): void
    {
        $repo = Mockery::mock(ModalidadeRepository::class);
        $repo->shouldReceive('buscarPorId')->once()->with(99, 3)->andReturn(null);

        $service = new AdminModalidadeService($repo);
        $result = $service->show(99, 3);

        $this->assertSame(404, $result['status']);
        $this->assertSame('Modalidade não encontrada', $result['body']['message']);
    }

    public function test_delete_toggles_and_messages(): void
    {
        $repo = Mockery::mock(ModalidadeRepository::class);
        $repo->shouldReceive('buscarPorId')->once()->with(1, 3)->andReturn([
            'id' => 1,
            'tenant_id' => 3,
            'ativo' => 1,
            'nome' => 'Boxe',
        ]);
        $repo->shouldReceive('alternarAtivo')->once()->with(1)->andReturn(true);

        $service = new AdminModalidadeService($repo);
        $result = $service->delete(1, 3);

        $this->assertSame(200, $result['status']);
        $this->assertSame('Modalidade desativada com sucesso', $result['body']['message']);
    }
}
