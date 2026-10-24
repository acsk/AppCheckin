<?php

namespace Tests\Feature;

use App\Repositories\UsuarioRepository;
use App\Services\AppConfigService;
use App\Services\JwtService;
use App\Support\AppConfigCatalogo;
use App\Support\AppConfigResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class AppConfigTest extends TestCase
{
    use RequiresSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();
        config(['cache.default' => 'array']);
        \Illuminate\Support\Facades\Cache::forget('app_config:publicada');

        Schema::create('tenants', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
        });
        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
        });
        Schema::create('tenant_usuario_papel', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('usuario_id');
            $t->integer('papel_id');
            $t->boolean('ativo')->default(true);
        });
        (require database_path('migrations/2026_10_25_100000_app_config_remota.php'))->up();
        DB::table('tenants')->insert([['id' => 1, 'nome' => 'Aqua'], ['id' => 2, 'nome' => 'Cia']]);
        DB::table('usuarios')->insert(['id' => 5, 'nome' => 'SUPER']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function service(): AppConfigService
    {
        return app(AppConfigService::class);
    }

    private function contrato(?int $tenantId = null, string $plataforma = 'web', ?string $versao = null, ?int $usuarioId = null): array
    {
        return $this->service()->contrato(['tenant_id' => $tenantId, 'usuario_id' => $usuarioId, 'plataforma' => $plataforma, 'versao_app' => $versao]);
    }

    public function test_exigencia_de_matricula_publicada_aceita_excecao_por_academia(): void
    {
        $s = $this->service();
        $this->assertTrue($s->parametro('checkin_exigir_matricula_ativa', 1));
        $s->definir(null, 'parametro.checkin_exigir_matricula_ativa', false, 5);
        $this->assertTrue($s->parametro('checkin_exigir_matricula_ativa', 1));
        $s->definir(1, 'parametro.checkin_exigir_matricula_ativa', true, 5);
        $s->publicar('Controle de acesso ao Check-in', 5);
        $this->assertTrue($s->parametro('checkin_exigir_matricula_ativa', 1));
        $this->assertFalse($s->parametro('checkin_exigir_matricula_ativa', 2));
    }

    public function test_sem_nada_publicado_vale_o_comportamento_atual(): void
    {
        $c = $this->contrato(1);

        $this->assertSame(AppConfigCatalogo::VERSAO_CONTRATO, $c['versao_contrato']);
        $this->assertSame(1, $c['versao_config']); // publicação inicial da migration
        $this->assertTrue($c['funcionalidades']['checkin']);
        $this->assertTrue($c['funcionalidades']['ranking']);
        $this->assertSame(15, $c['parametros']['ranking_limite_home']);
        $this->assertSame(300, $c['cache_ttl_segundos']);
        $this->assertArrayNotHasKey('cache_ttl_segundos', $c['parametros']);
    }

    public function test_rascunho_so_vale_depois_de_publicar(): void
    {
        $this->service()->definir(null, 'funcionalidade.ranking', false, 5);
        $this->assertTrue($this->contrato(1)['funcionalidades']['ranking']);
        $this->assertCount(1, $this->service()->estado()['body']['pendencias']);

        $this->assertSame(200, $this->service()->publicar('Desliga ranking', 5)['status']);

        $this->assertFalse($this->contrato(1)['funcionalidades']['ranking']);
        $this->assertSame([], $this->service()->estado()['body']['pendencias']);
        $this->assertSame(422, $this->service()->publicar(null, 5)['status']);
    }

    public function test_sobrescrita_da_academia_nao_vaza_para_outra(): void
    {
        $this->service()->definir(1, 'funcionalidade.recordes', false, 5);
        $this->service()->definir(1, 'parametro.ranking_limite_home', 30, 5);
        $this->service()->definir(null, 'parametro.ranking_limite_home', 20, 5);
        $this->service()->publicar(null, 5);

        $this->assertFalse($this->contrato(1)['funcionalidades']['recordes']);
        $this->assertSame(30, $this->contrato(1)['parametros']['ranking_limite_home']);
        $this->assertTrue($this->contrato(2)['funcionalidades']['recordes']);
        $this->assertSame(20, $this->contrato(2)['parametros']['ranking_limite_home']);
        // Público (antes do login) só vê o global
        $this->assertSame(20, $this->contrato()['parametros']['ranking_limite_home']);
    }

    public function test_validacao_e_escopo(): void
    {
        $s = $this->service();
        $this->assertSame(422, $s->definir(null, 'parametro.ranking_limite_home', 101, 5)['status']);
        $this->assertSame(422, $s->definir(null, 'parametro.ranking_limite_home', '1.5', 5)['status']);
        $this->assertSame(422, $s->definir(null, 'parametro.nao_existe', 1, 5)['status']);
        $this->assertSame(422, $s->definir(null, 'bloqueio.checkin', true, 5)['status']);
        $this->assertSame(422, $s->definir(1, 'parametro.cache_ttl_segundos', 600, 5)['status']);
        $this->assertSame(422, $s->definir(null, 'versao.android_apk_url', 'http://inseguro.com/app.apk', 5)['status']);
        $this->assertSame(422, $s->definir(null, 'versao.android_versao_minima', 'abc', 5)['status']);
        $this->assertSame(404, $s->definir(99, 'funcionalidade.checkin', false, 5)['status']);
        $this->assertSame(200, $s->definir(null, 'versao.android_versao_minima', 'v1.2.0', 5)['status']);
        $this->assertSame('1.2.0', $s->estado()['body']['rascunho']['global']['versao.android_versao_minima']);
    }

    public function test_global_igual_ao_padrao_nao_e_guardado(): void
    {
        $this->service()->definir(null, 'parametro.ranking_limite_home', 20, 5);
        $this->service()->definir(null, 'parametro.ranking_limite_home', 15, 5);

        $this->assertSame(0, DB::table('app_config_valores')->where('chave', 'parametro.ranking_limite_home')->count());
    }

    public function test_bloqueio_publica_na_hora_sem_levar_o_rascunho_e_vence_a_academia(): void
    {
        $s = $this->service();
        $s->definir(1, 'funcionalidade.checkin', true, 5);
        $s->publicar(null, 5);
        $s->definir(null, 'funcionalidade.ranking', false, 5); // pendente

        $s->bloquear('checkin', true, null, 5);

        $this->assertFalse($this->contrato(1)['funcionalidades']['checkin']);
        $this->assertTrue($this->contrato(1)['funcionalidades']['ranking'], 'o rascunho pendente não pode ir junto');
        $this->assertCount(1, $s->estado()['body']['pendencias']);
        $this->assertSame(['bloqueio.checkin'], $s->estado()['body']['bloqueios']);

        $s->bloquear('checkin', false, null, 5);
        $this->assertTrue($this->contrato(1)['funcionalidades']['checkin']);
    }

    public function test_rollback_restaura_versao_anterior_como_nova_publicacao(): void
    {
        $s = $this->service();
        $s->definir(null, 'parametro.aviso_home', 'Feriado: sem aulas', 5);
        $s->publicar(null, 5); // versão 2
        $s->definir(null, 'parametro.aviso_home', '', 5);
        $s->definir(null, 'funcionalidade.ranking', false, 5);
        $s->publicar(null, 5); // versão 3

        $this->assertSame(200, $s->restaurar(2, 5)['status']);

        $c = $this->contrato(1);
        $this->assertSame(4, $c['versao_config']);
        $this->assertSame('Feriado: sem aulas', $c['parametros']['aviso_home']);
        $this->assertTrue($c['funcionalidades']['ranking']);
        $this->assertSame([], $s->estado()['body']['pendencias']);
        $hist = $s->historico()['body'];
        $this->assertSame('rollback', $hist['publicacoes'][0]['origem']);
        $this->assertSame(2, (int) $hist['publicacoes'][0]['rollback_de']);
        $this->assertSame(404, $s->restaurar(99, 5)['status']);
    }

    public function test_auditoria_registra_alteracoes(): void
    {
        $this->service()->definir(1, 'funcionalidade.recordes', false, 5);
        $this->service()->publicar('Teste', 5);

        $a = $this->service()->historico()['body']['auditoria'];
        $this->assertSame(['publicar', 'alterar'], array_column($a, 'acao'));
        $this->assertSame('Aqua', $a[1]['academia']);
        $this->assertSame('SUPER', $a[1]['usuario']);
        $this->assertFalse($a[1]['valor_novo']);
    }

    public function test_restricoes_de_versao_papel_e_rollout(): void
    {
        $defs = AppConfigCatalogo::definicoes();
        $defs['funcionalidade.checkin']['versao_minima'] = ['android' => '1.3.0', 'ios' => null];
        $defs['funcionalidade.ranking']['papeis'] = [2];
        $pub = ['versao' => 1, 'global' => ['rollout.recordes' => 30], 'tenants' => [1 => ['funcionalidade.checkin' => true]]];
        $r = fn (array $ctx) => AppConfigResolver::resolver($pub, $ctx, $defs)['funcionalidades'];

        $this->assertSame('versao', $r(['tenant_id' => 1, 'plataforma' => 'android', 'versao_app' => '1.2.9'])['checkin']['origem']);
        $this->assertTrue($r(['tenant_id' => 1, 'plataforma' => 'android', 'versao_app' => '1.3.0'])['checkin']['ativo']);
        $this->assertTrue($r(['tenant_id' => 1, 'plataforma' => 'web'])['checkin']['ativo'], 'web sempre está na última versão');
        $this->assertFalse($r(['papeis' => [1]])['ranking']['ativo']);
        $this->assertTrue($r(['papeis' => [1, 2]])['ranking']['ativo']);

        // Rollout determinístico: mesma resposta a cada consulta, perto do percentual
        $ativos = 0;
        for ($u = 1; $u <= 2000; $u++) {
            $a = $r(['usuario_id' => $u])['recordes']['ativo'];
            $this->assertSame($a, $r(['usuario_id' => $u])['recordes']['ativo']);
            $ativos += $a ? 1 : 0;
        }
        $this->assertGreaterThan(500, $ativos);
        $this->assertLessThan(700, $ativos);
        $this->assertFalse($r([])['recordes']['ativo'], 'sem usuário não entra no rollout parcial');
    }

    public function test_atualizacao_nativa_por_versao_minima_e_publicada(): void
    {
        $s = $this->service();
        $s->definir(null, 'versao.android_versao_recomendada', '1.5.0', 5);
        $s->definir(null, 'versao.android_versao_minima', '1.2.0', 5);
        $s->definir(null, 'versao.android_apk_url', 'https://exemplo.com/app.apk', 5);
        $s->publicar(null, 5);

        $antigo = $this->contrato(1, 'android', '1.1.9')['atualizacao'];
        $this->assertTrue($antigo['obrigatoria']);
        $meio = $this->contrato(1, 'android', '1.3.0')['atualizacao'];
        $this->assertFalse($meio['obrigatoria']);
        $this->assertTrue($meio['disponivel']);
        $this->assertSame('https://exemplo.com/app.apk', $meio['url']);
        $this->assertFalse($this->contrato(1, 'android', '1.5.0')['atualizacao']['disponivel']);
        $this->assertSame(['obrigatoria' => true, 'mensagem' => ''], $this->contrato(1)['atualizacao']);
    }

    public function test_endpoint_publico_com_etag(): void
    {
        $r = $this->getJson('/v2/app-config', ['X-App-Platform' => 'android', 'X-App-Version' => '1.0.1'])->assertOk();
        $etag = $r->headers->get('ETag');
        $this->assertNotEmpty($etag);
        $r->assertJsonPath('versao_contrato', 1)->assertJsonPath('funcionalidades.checkin', true);

        $this->get('/v2/app-config', ['X-App-Platform' => 'android', 'X-App-Version' => '1.0.1', 'If-None-Match' => $etag])->assertStatus(304);

        $this->service()->bloquear('checkin', true, null, 5);
        $this->get('/v2/app-config', ['X-App-Platform' => 'android', 'X-App-Version' => '1.0.1', 'If-None-Match' => $etag])
            ->assertOk()->assertJsonPath('funcionalidades.checkin', false);
    }

    public function test_painel_exclusivo_do_super_admin(): void
    {
        $this->getJson('/v2/superadmin/app-config', $this->auth(3))->assertForbidden();
        $this->postJson('/v2/superadmin/app-config/bloqueio/checkin', [], $this->auth(3))->assertForbidden();
        $this->postJson('/v2/superadmin/app-config/publicacoes/1/restaurar', [], $this->auth(3))->assertForbidden();

        $this->getJson('/v2/superadmin/app-config', $this->auth(4))->assertOk()->assertJsonPath('versao_publicada', 1);
        $this->putJson('/v2/superadmin/app-config/valor', ['chave' => 'funcionalidade.ranking', 'valor' => false], $this->auth(4))
            ->assertOk()->assertJsonCount(1, 'pendencias');
    }

    public function test_backend_bloqueia_rota_com_funcionalidade_desligada(): void
    {
        $this->service()->definir(1, 'funcionalidade.checkin', false, 5);
        $this->service()->publicar(null, 5);

        $this->postJson('/v2/mobile/checkin', ['turma_id' => 1], $this->auth(1))
            ->assertForbidden()
            ->assertJsonPath('code', 'FUNCIONALIDADE_DESATIVADA')
            ->assertJsonPath('funcionalidade', 'checkin');
    }

    /** @return array<string, string> */
    private function auth(int $papelId): array
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')->with(5)
            ->andReturn(['id' => 5, 'nome' => 'S', 'email' => 's@example.com', 'tenant_id' => 1, 'papel_id' => $papelId]);
        $this->app->instance(UsuarioRepository::class, $usuarios);

        $token = app(JwtService::class)->encode(['user_id' => 5, 'email' => 's@example.com', 'tenant_id' => 1, 'is_super_admin' => $papelId === 4]);

        return ['Authorization' => 'Bearer '.$token];
    }
}
