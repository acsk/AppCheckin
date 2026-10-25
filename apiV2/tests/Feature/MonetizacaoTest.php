<?php

namespace Tests\Feature;

use App\Repositories\UsuarioRepository;
use App\Services\JwtService;
use App\Services\MonetizacaoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class MonetizacaoTest extends TestCase
{
    use RequiresSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();
        (require database_path('migrations/2026_10_25_120000_monetizacao.php'))->up();
    }

    private function service(): MonetizacaoService
    {
        return app(MonetizacaoService::class);
    }

    private function formulario(string $provedor = 'adsense', string $ambiente = 'development', int $index = 0): array
    {
        $r = $this->service()->listar($provedor, $ambiente);
        $c = $r['configuracoes'][$index];
        $c['global_habilitado'] = $r['global']['habilitado'];
        $c['versao_global'] = $r['global']['versao'];

        return $c;
    }

    public function test_inicia_desativado_com_plataformas_e_ambientes_separados(): void
    {
        $this->assertSame(4, DB::table('monetizacao_aplicacoes')->count());
        $this->assertSame(0, DB::table('monetizacao_provedores')->where('habilitado', true)->count());
        $this->assertSame(0, DB::table('monetizacao_posicionamentos')->where('habilitado', true)->count());
        $adsense = $this->service()->listar('adsense', 'development');
        $admob = $this->service()->listar('admob', 'development');
        $this->assertFalse($adsense['global']['habilitado']);
        $this->assertSame(['web', 'web'], array_column($adsense['configuracoes'], 'plataforma'));
        $this->assertSame(['android', 'ios'], array_column($admob['configuracoes'], 'plataforma'));
        $this->assertNotSame($this->formulario()['id'], $this->formulario('adsense', 'production')['id']);
        $this->assertFalse($admob['configuracoes'][0]['integracao_disponivel']);
    }

    public function test_grava_configuracao_posicionamentos_e_auditoria_sem_vazar_contexto(): void
    {
        $f = $this->formulario();
        $f['habilitado'] = $f['global_habilitado'] = true;
        $f['publisher_id'] = 'ca-pub-1234567890123456';
        $f['dominio'] = 'example.com';
        $f['posicionamentos'][0]['habilitado'] = true;
        $f['posicionamentos'][0]['unidade_id'] = '1234567890';
        $r = $this->service()->salvar('adsense', $f['id'], $f, 7);
        $this->assertTrue($r['global']['habilitado']);
        $this->assertSame(2, $r['configuracoes'][0]['versao']);
        $this->assertSame('ca-pub-1234567890123456', $r['configuracoes'][0]['publisher_id']);
        $this->assertTrue($r['configuracoes'][0]['posicionamentos'][0]['habilitado']);
        $this->assertFalse($r['configuracoes'][1]['habilitado']);
        $this->assertFalse($this->formulario('adsense', 'production')['global_habilitado']);
        $this->assertFalse($this->formulario('admob')['habilitado']);
        $this->assertCount(1, $r['auditoria']);
        $this->assertSame(7, (int) $r['auditoria'][0]['usuario_id']);
        $this->assertFalse($r['auditoria'][0]['antes']['habilitado']);
        $this->assertTrue($r['auditoria'][0]['depois']['habilitado']);
    }

    public function test_ids_do_admob_nao_sao_intercambiaveis(): void
    {
        $f = $this->formulario('admob');
        $f['app_id'] = 'ca-app-pub-3940256099942544/6300978111';
        try {
            $this->service()->salvar('admob', $f['id'], $f, 7);
            $this->fail('Ad Unit ID não pode ser salvo como App ID.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('app_id', $e->errors());
        }
        $f['app_id'] = 'ca-app-pub-3940256099942544~3347511713';
        $f['posicionamentos'][0]['unidade_id'] = $f['app_id'];
        try {
            $this->service()->salvar('admob', $f['id'], $f, 7);
            $this->fail('App ID não pode ser salvo como Ad Unit ID.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('posicionamentos.0.unidade_id', $e->errors());
        }
        $this->assertSame(0, DB::table('monetizacao_auditoria')->count());
    }

    public function test_ativacao_incompleta_e_scripts_arbitrarios_sao_recusados(): void
    {
        $f = $this->formulario();
        $f['habilitado'] = true;
        try {
            $this->service()->salvar('adsense', $f['id'], $f, 7);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('habilitado', $e->errors());
        }
        $f['habilitado'] = false;
        $f['configuracoes']['script'] = '<script>alert(1)</script>';
        $this->expectException(ValidationException::class);
        $this->service()->salvar('adsense', $f['id'], $f, 7);
    }

    public function test_posicionamentos_duplicados_e_formato_invalido_sao_recusados(): void
    {
        $f = $this->formulario();
        $f['posicionamentos'][] = $f['posicionamentos'][0];
        $f['posicionamentos'][0]['formato'] = 'interstitial';
        $this->expectException(ValidationException::class);
        $this->service()->salvar('adsense', $f['id'], $f, 7);
    }

    public function test_atualizacao_antiga_nao_sobrescreve_dados(): void
    {
        $f = $this->formulario();
        $this->service()->salvar('adsense', $f['id'], $f, 7);
        try {
            $this->service()->salvar('adsense', $f['id'], $f, 8);
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame(1, DB::table('monetizacao_auditoria')->count());
    }

    public function test_remocao_de_posicao_nao_apaga_outro_provedor(): void
    {
        $f = $this->formulario();
        $quantidade = DB::table('monetizacao_posicionamentos')->count();
        $f['posicionamentos'] = [];
        $this->service()->salvar('adsense', $f['id'], $f, 7);
        $this->assertSame($quantidade - 1, DB::table('monetizacao_posicionamentos')->count());
        $this->assertNotEmpty($this->formulario('admob')['posicionamentos']);
    }

    public function test_api_somente_super_admin(): void
    {
        $this->getJson('/v2/superadmin/monetizacao/adsense')->assertUnauthorized();
        $this->putJson('/v2/superadmin/monetizacao/adsense/1', [])->assertUnauthorized();
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);
        foreach ([3 => 403, 4 => 200] as $papel => $status) {
            $repo = Mockery::mock(UsuarioRepository::class);
            $repo->shouldReceive('findAuthContext')->with(7)->andReturn(['id' => 7, 'nome' => 'TESTE', 'email' => 'teste@example.com', 'papel_id' => $papel, 'tenant_id' => 1]);
            $this->app->instance(UsuarioRepository::class, $repo);
            $token = app(JwtService::class)->encode(['user_id' => 7, 'tenant_id' => 1, 'is_super_admin' => $papel === 4]);
            $this->getJson('/v2/superadmin/monetizacao/adsense', ['Authorization' => 'Bearer '.$token])->assertStatus($status);
            $f = $this->formulario();
            $this->putJson('/v2/superadmin/monetizacao/adsense/'.$f['id'], $f, ['Authorization' => 'Bearer '.$token])->assertStatus($status);
        }
    }

    public function test_id_de_outro_provedor_e_recusado(): void
    {
        $f = $this->formulario('admob');
        try {
            $this->service()->salvar('adsense', $f['id'], $f, 7);
            $this->fail('Não deve permitir editar uma configuração de outro provedor.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_controle_global_compartilhado_nao_e_sobrescrito_por_formulario_antigo(): void
    {
        $web = $this->formulario();
        $mobile = $this->formulario('admob');
        $web['global_habilitado'] = true;
        $this->service()->salvar('adsense', $web['id'], $web, 7);
        try {
            $this->service()->salvar('admob', $mobile['id'], $mobile, 7);
            $this->fail('Versão global antiga deve ser recusada.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertTrue($this->formulario('admob')['global_habilitado']);
        $this->assertSame(1, DB::table('monetizacao_auditoria')->count());
    }
}
