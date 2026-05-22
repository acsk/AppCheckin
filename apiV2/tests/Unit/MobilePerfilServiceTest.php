<?php

namespace Tests\Unit;

use App\Repositories\AlunoRepository;
use App\Repositories\CheckinRepository;
use App\Repositories\MobilePerfilRepository;
use App\Repositories\UsuarioRepository;
use App\Services\Mobile\MobilePerfilService;
use Mockery;
use Tests\TestCase;

class MobilePerfilServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_verificar_acesso_returns_permitido_when_no_bloqueio(): void
    {
        $service = new MobilePerfilService(
            Mockery::mock(UsuarioRepository::class),
            Mockery::mock(AlunoRepository::class),
            Mockery::mock(MobilePerfilRepository::class),
            Mockery::mock(CheckinRepository::class),
        );

        $result = $service->verificarAcesso(1, 1);

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['success']);
        $this->assertTrue($result['body']['acesso']['permitido']);
        $this->assertFalse($result['body']['acesso']['bloqueado']);
    }

    public function test_perfil_requires_tenant(): void
    {
        $service = new MobilePerfilService(
            Mockery::mock(UsuarioRepository::class),
            Mockery::mock(AlunoRepository::class),
            Mockery::mock(MobilePerfilRepository::class),
            Mockery::mock(CheckinRepository::class),
        );

        $result = $service->perfil(1, null);

        $this->assertSame(400, $result['status']);
        $this->assertSame('MISSING_TENANT', $result['body']['code']);
    }
}
