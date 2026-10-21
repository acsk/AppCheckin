<?php

namespace Tests\Feature;

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
        });
        Schema::create('recordes', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('aluno_id')->nullable();
            $t->integer('definicao_id');
            $t->string('origem');
            $t->date('data_recorde');
            $t->boolean('valido')->default(1);
        });
        Schema::create('recorde_valores', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('recorde_id');
            $t->integer('metrica_id');
            $t->bigInteger('valor_int')->nullable();
            $t->decimal('valor_decimal', 12, 3)->nullable();
            $t->bigInteger('valor_tempo_ms')->nullable();
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

    public function test_aluno_fora_da_lista_do_limite_continua_aparecendo(): void
    {
        $rank = app(MobileRecordeService::class)->ranking(100, 3, 1, null, 1)['body'];

        $this->assertSame(['Dalvan', 'André'], array_column($rank['ranking'], 'nome'));
        $this->assertSame(3, $rank['ranking'][1]['posicao']);
    }
}
