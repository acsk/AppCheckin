<?php

namespace Tests\Feature;

use App\Repositories\RecordePessoalRepository;
use App\Repositories\UsuarioRepository;
use App\Services\JwtService;
use App\Services\Mobile\MobileRecordeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MobileRecordeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('modalidades', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
        });
        Schema::create('alunos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('usuario_id')->nullable();
            $t->string('foto_caminho')->nullable();
            $t->string('nome');
            $t->string('sexo')->nullable();
        });
        Schema::create('recorde_definicoes', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('modalidade_id')->nullable();
            $t->string('nome');
            $t->string('categoria')->default('prova');
            $t->text('descricao')->nullable();
            $t->boolean('ativo')->default(1);
            $t->integer('ordem')->default(0);
        });
        Schema::create('recorde_definicao_metricas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('definicao_id');
            $t->string('codigo');
            $t->string('nome');
            $t->string('tipo_valor');
            $t->string('unidade')->nullable();
            $t->integer('ordem_comparacao');
            $t->string('direcao');
            $t->boolean('obrigatoria')->default(1);
        });
        Schema::create('recordes', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('aluno_id')->nullable();
            $t->integer('definicao_id');
            $t->string('origem');
            $t->date('data_recorde');
            $t->text('observacoes')->nullable();
            $t->integer('registrado_por')->nullable();
            $t->boolean('valido')->default(1);
            $t->timestamps();
        });
        Schema::create('recorde_valores', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('recorde_id');
            $t->integer('metrica_id');
            $t->bigInteger('valor_int')->nullable();
            $t->decimal('valor_decimal', 12, 3)->nullable();
            $t->bigInteger('valor_tempo_ms')->nullable();
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
        });

        DB::table('modalidades')->insert(['id' => 1, 'nome' => 'Natação']);
        DB::table('alunos')->insert([
            ['id' => 10, 'usuario_id' => 100, 'nome' => 'André', 'sexo' => 'masculino'],
            ['id' => 11, 'usuario_id' => 101, 'nome' => 'Dalvan', 'sexo' => 'masculino'],
            ['id' => 12, 'usuario_id' => 102, 'nome' => 'Helloá', 'sexo' => 'feminino'],
        ]);
        DB::table('recorde_definicoes')->insert([
            ['id' => 1, 'tenant_id' => 3, 'modalidade_id' => 1, 'nome' => '50m Livre', 'ordem' => 1, 'ativo' => 1],
            ['id' => 2, 'tenant_id' => 3, 'modalidade_id' => 1, 'nome' => '100m Medley', 'ordem' => 2, 'ativo' => 1],
            ['id' => 3, 'tenant_id' => 9, 'modalidade_id' => 1, 'nome' => 'Outra academia', 'ordem' => 1, 'ativo' => 1],
        ]);
        DB::table('recorde_definicao_metricas')->insert([
            ['id' => 1, 'definicao_id' => 1, 'codigo' => 'tempo', 'nome' => 'Tempo', 'tipo_valor' => 'tempo_ms', 'ordem_comparacao' => 1, 'direcao' => 'menor_melhor'],
            ['id' => 2, 'definicao_id' => 2, 'codigo' => 'tempo', 'nome' => 'Tempo', 'tipo_valor' => 'tempo_ms', 'ordem_comparacao' => 1, 'direcao' => 'menor_melhor'],
            ['id' => 3, 'definicao_id' => 3, 'codigo' => 'tempo', 'nome' => 'Tempo', 'tipo_valor' => 'tempo_ms', 'ordem_comparacao' => 1, 'direcao' => 'menor_melhor'],
        ]);

        $this->marca(10, 1, '2026-09-01', 41200);
        $this->marca(10, 1, '2026-10-12', 38420);
        $this->marca(10, 1, '2026-10-15', 39900);
        $this->marca(11, 1, '2026-08-22', 29870);
        $this->marca(12, 1, '2026-09-12', 31200);
        $this->marca(null, 1, '2026-01-01', 30500, 'academia');
        $this->marca(11, 1, '2026-10-01', 20000, 'aluno', 0); // inválido: não conta
        $this->marca(11, 3, '2026-10-01', 1000); // outro tenant
    }

    private function marca(?int $aluno, int $def, string $data, int $ms, string $origem = 'aluno', int $valido = 1): void
    {
        $id = DB::table('recordes')->insertGetId(['tenant_id' => $def === 3 ? 9 : 3, 'aluno_id' => $aluno, 'definicao_id' => $def, 'origem' => $origem, 'data_recorde' => $data, 'valido' => $valido]);
        DB::table('recorde_valores')->insert(['recorde_id' => $id, 'metrica_id' => $def, 'valor_tempo_ms' => $ms]);
    }

    public function test_resumo_traz_melhor_marca_anterior_posicao_e_recordistas_por_sexo(): void
    {
        $r = app(MobileRecordeService::class)->resumo(100, 3);

        $this->assertSame(200, $r['status']);
        $body = $r['body'];
        $this->assertCount(2, $body['definicoes']);

        $livre = collect($body['meus'])->firstWhere('definicao_id', 1);
        $this->assertEquals(38420, $livre['valor']);
        $this->assertSame('2026-10-12', $livre['data']);
        $this->assertEquals(41200, $livre['anterior']);
        $this->assertEquals([41200, 38420, 39900], $livre['historico']);
        $this->assertSame(3, $livre['posicao']);
        $this->assertSame(3, $livre['total']);

        $medley = collect($body['meus'])->firstWhere('definicao_id', 2);
        $this->assertNull($medley['valor']);

        $geral = collect($body['escola']['geral'])->firstWhere('definicao_id', 1);
        $this->assertSame('Dalvan', $geral['nome']);
        $this->assertFalse($geral['self']);
        $this->assertSame('Helloá', collect($body['escola']['feminino'])->firstWhere('definicao_id', 1)['nome']);
    }

    public function test_marca_da_academia_vence_o_geral_mas_nao_entra_no_ranking(): void
    {
        DB::table('recorde_valores')->where('valor_tempo_ms', 30500)->update(['valor_tempo_ms' => 29000]);

        $r = app(MobileRecordeService::class)->resumo(100, 3)['body'];
        $this->assertSame('Marca da academia', collect($r['escola']['geral'])->firstWhere('definicao_id', 1)['nome']);
        $this->assertSame('Dalvan', collect($r['escola']['masculino'])->firstWhere('definicao_id', 1)['nome']);

        $rank = app(MobileRecordeService::class)->ranking(100, 3, 1, null)['body'];
        $this->assertSame(['Dalvan', 'Helloá', 'André'], array_column($rank['ranking'], 'nome'));
        $this->assertTrue($rank['ranking'][2]['self']);
    }

    public function test_ranking_filtra_sexo_e_nao_ve_outro_tenant(): void
    {
        $svc = app(MobileRecordeService::class);

        $fem = $svc->ranking(100, 3, 1, 'feminino')['body'];
        $this->assertSame(['Helloá'], array_column($fem['ranking'], 'nome'));
        $this->assertSame(1, $fem['ranking'][0]['posicao']);

        $this->assertSame(404, $svc->ranking(100, 3, 3, null)['status']);
    }

    public function test_ranking_traz_foto_do_aluno_ou_null_sem_foto(): void
    {
        DB::table('alunos')->where('id', 11)->update(['foto_caminho' => '/uploads/fotos/dalvan.jpg']);
        $headers = $this->headersAluno();
        $this->getJson('/v2/mobile/recordes/ranking/1', $headers)->assertOk()
            ->assertJsonPath('ranking.0.foto_caminho', '/uploads/fotos/dalvan.jpg')
            ->assertJsonPath('ranking.1.foto_caminho', null)
            ->assertJsonPath('ranking.2.foto_caminho', null);
    }

    public function test_aluno_fora_da_lista_do_limite_continua_aparecendo(): void
    {
        $rank = app(MobileRecordeService::class)->ranking(100, 3, 1, null, 1)['body'];

        $this->assertSame(['Dalvan', 'André'], array_column($rank['ranking'], 'nome'));
        $this->assertSame(3, $rank['ranking'][1]['posicao']);
    }

    public function test_prova_lista_marcas_do_aluno_e_marca_a_melhor(): void
    {
        $this->marca(10, 1, '2026-10-16', 40000, 'academia');

        $r = app(MobileRecordeService::class)->prova(100, 3, 1);
        $this->assertSame(200, $r['status']);
        $body = $r['body'];
        $this->assertSame(['2026-10-16', '2026-10-15', '2026-10-12', '2026-09-01'], array_column($body['marcas'], 'data'));
        $this->assertSame([false, true, true, true], array_column($body['marcas'], 'editavel'));
        $this->assertSame($body['marcas'][2]['id'], $body['melhor_id']);
        $this->assertSame('tempo_ms', $body['metricas'][0]['tipo_valor']);

        $this->assertSame(404, app(MobileRecordeService::class)->prova(100, 3, 3)['status']);
    }

    public function test_criar_diz_se_bateu_o_recorde_e_valida_entrada(): void
    {
        $svc = app(MobileRecordeService::class);
        $base = ['definicao_id' => 1, 'data_recorde' => '2026-10-20', 'valores' => [['metrica_id' => 1, 'valor' => 37860]]];

        $r = $svc->criar(100, 3, $base);
        $this->assertSame(201, $r['status']);
        $this->assertTrue($r['body']['novo_recorde']);
        $this->assertEquals(38420, $r['body']['melhor_anterior']);
        $this->assertSame('aluno', DB::table('recordes')->where('id', $r['body']['id'])->value('origem'));
        $this->assertSame(100, (int) DB::table('recordes')->where('id', $r['body']['id'])->value('registrado_por'));

        $pior = $svc->criar(100, 3, ['valores' => [['metrica_id' => 1, 'valor' => 45000]]] + $base);
        $this->assertFalse($pior['body']['novo_recorde']);

        $this->assertSame(422, $svc->criar(100, 3, ['valores' => []] + $base)['status']);
        $this->assertSame(422, $svc->criar(100, 3, ['data_recorde' => '2999-01-01'] + $base)['status']);
        $this->assertSame(422, $svc->criar(100, 3, ['data_recorde' => '21/10/2026'] + $base)['status']);
        $this->assertSame(404, $svc->criar(100, 3, ['definicao_id' => 3] + $base)['status']);
        $this->assertSame(404, $svc->criar(999, 3, $base)['status']);
    }

    public function test_aluno_so_edita_e_exclui_as_proprias_marcas(): void
    {
        $svc = app(MobileRecordeService::class);
        $minha = (int) DB::table('recordes')->where('aluno_id', 10)->where('data_recorde', '2026-10-15')->value('id');
        $alheia = (int) DB::table('recordes')->where('aluno_id', 11)->where('data_recorde', '2026-08-22')->value('id');
        $this->marca(10, 1, '2026-10-16', 40000, 'academia');
        $daEscola = (int) DB::table('recordes')->where('origem', 'academia')->where('aluno_id', 10)->value('id');
        $edit = ['data_recorde' => '2026-10-14', 'observacoes' => ' Festival ', 'valores' => [['metrica_id' => 1, 'valor' => 39000]]];

        $this->assertSame(200, $svc->atualizar(100, 3, $minha, $edit)['status']);
        $row = DB::table('recordes')->where('id', $minha)->first();
        $this->assertSame('2026-10-14', substr((string) $row->data_recorde, 0, 10));
        $this->assertSame('Festival', $row->observacoes);
        $this->assertEquals(39000, DB::table('recorde_valores')->where('recorde_id', $minha)->value('valor_tempo_ms'));

        $this->assertSame(404, $svc->atualizar(100, 3, $alheia, $edit)['status']);
        $this->assertSame(403, $svc->atualizar(100, 3, $daEscola, $edit)['status']);
        $this->assertSame(404, $svc->excluir(100, 3, $alheia)['status']);
        $this->assertSame(403, $svc->excluir(100, 3, $daEscola)['status']);

        $this->assertSame(200, $svc->excluir(100, 3, $minha)['status']);
        $this->assertFalse(DB::table('recordes')->where('id', $minha)->exists());
    }

    public function test_entradas_malformadas_nao_gravam_nem_substituem_valores(): void
    {
        $svc = app(MobileRecordeService::class);
        $base = ['definicao_id' => 1, 'data_recorde' => '2020-01-01', 'valores' => [['metrica_id' => 1, 'valor' => 37860]]];
        $invalidos = [
            ['definicao_id' => []], ['data_recorde' => []], ['data_recorde' => '2025-02-29'],
            ['observacoes' => ['texto']], ['observacoes' => str_repeat('x', 501)],
            ['valores' => 'inválido'], ['valores' => ['metrica_id' => 1, 'valor' => 42]],
            ['valores' => [true]], ['valores' => [['metrica_id' => [], 'valor' => 42]]],
            ['valores' => [['metrica_id' => 2, 'valor' => 42]]],
            ['valores' => [['metrica_id' => 1, 'valor' => 42], ['metrica_id' => 1, 'valor' => 50]]],
        ];
        foreach ([0, -1, 1.2, 'NaN', INF, [], 9007199254740992] as $valor) {
            $invalidos[] = ['valores' => [['metrica_id' => 1, 'valor' => $valor]]];
        }
        $id = (int) DB::table('recordes')->where('aluno_id', 10)->value('id');
        $antes = DB::table('recordes')->count();
        $valorAntes = DB::table('recorde_valores')->where('recorde_id', $id)->value('valor_tempo_ms');
        foreach ($invalidos as $entrada) {
            $this->assertSame(422, $svc->criar(100, 3, $entrada + $base)['status']);
            if (! array_key_exists('definicao_id', $entrada)) {
                $this->assertSame(422, $svc->atualizar(100, 3, $id, $entrada + $base)['status']);
            }
        }
        $this->assertSame($antes, DB::table('recordes')->count());
        $this->assertEquals($valorAntes, DB::table('recorde_valores')->where('recorde_id', $id)->value('valor_tempo_ms'));
    }

    public function test_multiplas_metricas_e_principal_opcional_continuam_visiveis(): void
    {
        DB::table('recorde_definicao_metricas')->where('id', 2)->update(['obrigatoria' => 0, 'tipo_valor' => 'inteiro', 'direcao' => 'maior_melhor']);
        DB::table('recorde_definicao_metricas')->insert(['id' => 4, 'definicao_id' => 2, 'codigo' => 'carga', 'nome' => 'Carga', 'tipo_valor' => 'decimal', 'ordem_comparacao' => 2, 'direcao' => 'maior_melhor', 'obrigatoria' => 0]);
        $svc = app(MobileRecordeService::class);
        $base = ['definicao_id' => 2, 'data_recorde' => '2020-01-01'];
        $this->assertSame(422, $svc->criar(100, 3, $base + ['valores' => [['metrica_id' => 4, 'valor' => 12]]])['status']);
        foreach ([0.0001, 12.3456, 1000000000] as $decimal) {
            $this->assertSame(422, $svc->criar(100, 3, $base + ['valores' => [['metrica_id' => 2, 'valor' => 12], ['metrica_id' => 4, 'valor' => $decimal]]])['status']);
        }
        $result = $svc->criar(100, 3, $base + ['valores' => [['metrica_id' => 2, 'valor' => 12], ['metrica_id' => 4, 'valor' => 0.001]]]);
        $this->assertSame(201, $result['status']);
        $this->assertEquals(0.001, $svc->prova(100, 3, 2)['body']['marcas'][0]['valores'][4]);
        $melhor = $svc->criar(100, 3, $base + ['valores' => [['metrica_id' => 2, 'valor' => 15]]]);
        $this->assertTrue($melhor['body']['novo_recorde']);
        $this->assertSame($melhor['body']['id'], $svc->prova(100, 3, 2)['body']['melhor_id']);
        $this->assertSame(200, $svc->excluir(100, 3, $melhor['body']['id'])['status']);
        $this->assertSame($result['body']['id'], $svc->prova(100, 3, 2)['body']['melhor_id']);
    }

    public function test_ciclo_http_persiste_rele_e_recalcula_recorde_e_ranking(): void
    {
        $headers = $this->headersAluno();
        $payload = ['definicao_id' => 1, 'data_recorde' => '2020-01-01', 'valores' => [['metrica_id' => 1, 'valor' => 25001]], 'aluno_id' => 11, 'tenant_id' => 9, 'origem' => 'academia'];
        $id = $this->postJson('/v2/mobile/recordes', $payload, $headers)
            ->assertCreated()->assertJsonPath('novo_recorde', true)->json('id');
        $this->assertSame(10, (int) DB::table('recordes')->where('id', $id)->value('aluno_id'));
        $this->getJson('/v2/mobile/recordes/prova/1', $headers)->assertOk()->assertJsonPath('melhor_id', $id);
        $this->getJson('/v2/mobile/recordes/ranking/1', $headers)->assertOk()->assertJsonPath('ranking.0.self', true);
        $this->putJson('/v2/mobile/recordes/'.$id, ['valores' => [['metrica_id' => 1, 'valor' => 45000]]] + $payload, $headers)->assertOk();
        $this->assertEquals(45000, DB::table('recorde_valores')->where('recorde_id', $id)->value('valor_tempo_ms'));
        $this->getJson('/v2/mobile/recordes/ranking/1', $headers)->assertOk()->assertJsonPath('ranking.0.self', false);
        $this->deleteJson('/v2/mobile/recordes/'.$id, [], $headers)->assertOk();
        $marcas = $this->getJson('/v2/mobile/recordes/prova/1', $headers)->assertOk()->json('marcas');
        $this->assertNotContains($id, array_column($marcas, 'id'));
        $this->getJson('/v2/mobile/recordes', $headers)->assertOk()->assertJsonPath('meus.0.valor', 38420);
    }

    public function test_http_bloqueia_sem_token_e_retorna_validacoes_e_isolamento(): void
    {
        foreach (['/v2/mobile/recordes', '/v2/mobile/recordes/prova/1', '/v2/mobile/recordes/ranking/1'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        $this->postJson('/v2/mobile/recordes')->assertUnauthorized();
        $this->putJson('/v2/mobile/recordes/1')->assertUnauthorized();
        $this->deleteJson('/v2/mobile/recordes/1')->assertUnauthorized();
        $headers = $this->headersAluno();
        $this->getJson('/v2/mobile/recordes/prova/3', $headers)->assertNotFound();
        $this->getJson('/v2/mobile/recordes/ranking/1?sexo[]=masculino', $headers)->assertUnprocessable();
        $this->getJson('/v2/mobile/recordes/ranking/1?limit[]=10', $headers)->assertUnprocessable();
        $this->postJson('/v2/mobile/recordes', ['definicao_id' => 1, 'data_recorde' => []], $headers)->assertUnprocessable();
        $outro = (int) DB::table('recordes')->where('aluno_id', 11)->value('id');
        $this->deleteJson('/v2/mobile/recordes/'.$outro, [], $headers)->assertNotFound();
    }

    public function test_exclusao_sem_linha_removida_nao_retorna_sucesso(): void
    {
        $repo = app(RecordePessoalRepository::class);
        $id = (int) DB::table('recordes')->where('aluno_id', 10)->value('id');
        $marca = $repo->buscar($id, 3);
        $mock = \Mockery::mock(RecordePessoalRepository::class);
        $mock->shouldReceive('buscar')->once()->with($id, 3)->andReturn($marca);
        $mock->shouldReceive('excluir')->once()->with($id, 3)->andReturn(false);
        $this->assertSame(404, (new MobileRecordeService($mock))->excluir(100, 3, $id)['status']);
    }

    private function headersAluno(): array
    {
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);
        $usuarios = \Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('findAuthContext')->with(100)->andReturn(['id' => 100, 'nome' => 'André', 'email' => 'aluno@example.com', 'tenant_id' => 3, 'papel_id' => 1]);
        $this->app->instance(UsuarioRepository::class, $usuarios);

        return ['Authorization' => 'Bearer '.app(JwtService::class)->encode(['user_id' => 100, 'email' => 'aluno@example.com', 'tenant_id' => 3])];
    }
}
