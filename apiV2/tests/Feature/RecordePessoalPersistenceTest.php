<?php

namespace Tests\Feature;

use App\Repositories\RecordePessoalRepository;
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
