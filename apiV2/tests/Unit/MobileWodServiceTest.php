<?php

namespace Tests\Unit;

use App\Repositories\UsuarioRepository;
use App\Repositories\WodRepository;
use App\Services\Mobile\MobileWodService;
use Mockery;
use Tests\TestCase;

class MobileWodServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_wod_do_dia_explicit_modalidade_does_not_fallback_to_generic(): void
    {
        $wods = Mockery::mock(WodRepository::class);
        $wods->shouldReceive('findPublishedForDate')
            ->once()
            ->with(1, Mockery::type('string'), 99, false)
            ->andReturn(null);

        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findById')
            ->once()
            ->andReturn(['id' => 1]);

        $service = new MobileWodService($wods, $usuarios);
        $result = $service->wodDoDia(1, 1, null, 99, true);

        $this->assertTrue($result['body']['success']);
        $this->assertNull($result['body']['data']);
        $this->assertSame('Nenhum WOD agendado para esta data', $result['body']['message']);
    }

    public function test_find_published_without_modalidade_and_no_fallback_returns_null(): void
    {
        $wods = new WodRepository;

        $this->assertNull($wods->findPublishedForDate(1, '2026-05-22', null, false));
    }

    public function test_wod_do_dia_without_explicit_modalidade_allows_generic_fallback(): void
    {
        $wods = Mockery::mock(WodRepository::class);
        $wods->shouldReceive('findPublishedForDate')
            ->once()
            ->with(1, Mockery::type('string'), null, true)
            ->andReturn(['id' => 1, 'titulo' => 'WOD', 'descricao' => '', 'data' => '2026-05-22', 'status' => 'published', 'modalidade_id' => null]);

        $wods->shouldReceive('listBlocosByWod')->andReturn([]);
        $wods->shouldReceive('listVariacoesByWod')->andReturn([]);

        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findById')
            ->once()
            ->andReturn(['id' => 1, 'modalidade_id' => null]);

        $service = new MobileWodService($wods, $usuarios);
        $result = $service->wodDoDia(1, 1, null, null, false);

        $this->assertSame(1, $result['body']['data']['id']);
    }
}
