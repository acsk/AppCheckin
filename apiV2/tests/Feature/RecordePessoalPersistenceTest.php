<?php

namespace Tests\Feature;

use App\Repositories\RecordePessoalRepository;
use App\Services\Admin\AdminRecordeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecordePessoalPersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('recordes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('tenant_id');
            $table->integer('aluno_id')->nullable();
            $table->integer('definicao_id');
            $table->string('origem');
            $table->date('data_recorde');
            $table->text('observacoes')->nullable();
            $table->integer('registrado_por')->nullable();
            $table->boolean('valido');
            $table->timestamps();
        });

        Schema::create('recorde_valores', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('recorde_id');
            $table->integer('metrica_id');
            $table->bigInteger('valor_int')->nullable();
            $table->decimal('valor_decimal', 12, 3)->nullable();
            $table->bigInteger('valor_tempo_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['recorde_id', 'metrica_id']);
        });
    }

    public function test_criar_recorde_persiste_metricas_sem_updated_at(): void
    {
        $id = app(RecordePessoalRepository::class)->criar($this->dados());

        $this->assertDatabaseHas('recordes', ['id' => $id, 'tenant_id' => 3]);
        $this->assertDatabaseCount('recorde_valores', 3);
        $this->assertDatabaseHas('recorde_valores', [
            'recorde_id' => $id, 'metrica_id' => 21, 'valor_tempo_ms' => 30000,
            'valor_int' => null, 'valor_decimal' => null,
        ]);
        $this->assertDatabaseHas('recorde_valores', ['metrica_id' => 22, 'valor_int' => 10]);
        $this->assertDatabaseHas('recorde_valores', ['metrica_id' => 23, 'valor_decimal' => 42.5]);
        $this->assertNotNull(DB::table('recorde_valores')->where('metrica_id', 21)->value('created_at'));
    }

    public function test_atualizar_recorde_substitui_valores_sem_updated_at(): void
    {
        $repository = app(RecordePessoalRepository::class);
        $id = $repository->criar($this->dados());
        $dados = $this->dados();
        $dados['valores'] = [['metrica_id' => 21, 'valor_tempo_ms' => 25000]];

        $this->assertTrue($repository->atualizar($id, 3, $dados));

        $this->assertDatabaseCount('recorde_valores', 1);
        $this->assertDatabaseHas('recorde_valores', [
            'recorde_id' => $id, 'metrica_id' => 21, 'valor_tempo_ms' => 25000,
        ]);
    }

    public function test_listagem_inclui_ambas_origens_e_respeita_filtros_e_tenant(): void
    {
        Schema::create('recorde_definicoes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nome');
            $table->string('categoria');
            $table->integer('modalidade_id');
            $table->integer('ordem');
        });
        foreach (['modalidades', 'alunos', 'usuarios'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->increments('id');
                $table->string('nome');
            });
            DB::table($name)->insert(['id' => 1, 'nome' => 'Teste']);
        }
        Schema::create('recorde_definicao_metricas', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('codigo');
            $table->string('nome');
            $table->string('tipo_valor');
            $table->string('unidade')->nullable();
            $table->string('direcao');
            $table->integer('ordem_comparacao');
        });
        DB::table('recorde_definicoes')->insert([
            'id' => 1, 'nome' => 'Corrida', 'categoria' => 'tempo', 'modalidade_id' => 1, 'ordem' => 1,
        ]);
        DB::table('recorde_definicao_metricas')->insert([
            'id' => 21, 'codigo' => 'tempo', 'nome' => 'Tempo', 'tipo_valor' => 'tempo',
            'direcao' => 'menor_melhor', 'ordem_comparacao' => 1,
        ]);
        $repository = app(RecordePessoalRepository::class);
        $dados = $this->dados();
        $dados['valores'] = [['metrica_id' => 21, 'valor_tempo_ms' => 30000]];
        $dados['registrado_por'] = 1;
        $dados['origem'] = 'academia';
        $academiaId = $repository->criar($dados);
        $dados['origem'] = 'aluno';
        $dados['aluno_id'] = 1;
        $alunoId = $repository->criar($dados);
        $invalidId = $repository->criar($dados);
        DB::table('recordes')->where('id', $invalidId)->update(['valido' => 0]);
        $dados['tenant_id'] = 4;
        $repository->criar($dados);

        $service = app(AdminRecordeService::class);
        $todos = $service->listarRecordes(3, []);
        $this->assertEqualsCanonicalizing([$academiaId, $alunoId], array_column($todos['body']['recordes'], 'id'));
        $this->assertEquals(30000, $todos['body']['recordes'][0]['valores'][0]['valor_tempo_ms']);
        foreach (['academia' => $academiaId, 'aluno' => $alunoId] as $origem => $id) {
            $result = $service->listarRecordes(3, ['origem' => $origem]);
            $this->assertSame([$id], array_column($result['body']['recordes'], 'id'));
        }
        $porAluno = $service->listarRecordes(3, ['aluno_id' => 1, 'modalidade_id' => 1]);
        $this->assertSame([$alunoId], array_column($porAluno['body']['recordes'], 'id'));
        foreach (['modalidade_id', 'definicao_id'] as $filter) {
            $this->assertSame([], $service->listarRecordes(3, [$filter => 999])['body']['recordes']);
        }
        $this->assertSame(422, $service->listarRecordes(3, ['origem' => 'invalida'])['status']);
    }

    private function dados(): array
    {
        return [
            'tenant_id' => 3,
            'definicao_id' => 1,
            'data_recorde' => '2026-10-20',
            'valores' => [
                ['metrica_id' => 21, 'valor_tempo_ms' => 30000],
                ['metrica_id' => 22, 'valor_int' => 10],
                ['metrica_id' => 23, 'valor_decimal' => 42.5],
            ],
        ];
    }
}
