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

    public function test_perfil_inclui_campos_de_aniversario(): void
    {
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $alunos = Mockery::mock(AlunoRepository::class);
        $perfilRepo = Mockery::mock(MobilePerfilRepository::class);
        $checkins = Mockery::mock(CheckinRepository::class);

        $usuarios->shouldReceive('temAcessoTenant')->with(1, 3)->andReturn(true);
        $usuarios->shouldReceive('findById')->with(1, 3)->andReturn([
            'id' => 1,
            'nome' => 'MARIA TESTE',
            'email' => 'maria@test.com',
            'cpf' => null,
            'telefone' => null,
            'papel_id' => 1,
            'created_at' => '2026-01-01',
        ]);

        $alunos->shouldReceive('findPerfilByUsuario')->with(1, 3)->andReturn([
            'id' => 10,
            'foto_caminho' => null,
            'data_nascimento' => '1990-'.date('m-d'),
            'cep' => null,
            'logradouro' => null,
            'numero' => null,
            'complemento' => null,
            'bairro' => null,
            'cidade' => null,
            'estado' => null,
        ]);

        $perfilRepo->shouldReceive('getEstatisticasCheckin')->andReturn([]);
        $perfilRepo->shouldReceive('listarTenantsAtivosDoUsuario')->andReturn([]);
        $perfilRepo->shouldReceive('getPlanoUsuario')->andReturn(null);
        $checkins->shouldReceive('rankingUsuarioPorModalidade')->andReturn([]);

        $service = new MobilePerfilService($usuarios, $alunos, $perfilRepo, $checkins);
        $result = $service->perfil(1, 3);

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['data']['aniversario_hoje']);
        $this->assertNotNull($result['body']['data']['idade']);
        $this->assertArrayHasKey('data_nascimento', $result['body']['data']);
    }
}
