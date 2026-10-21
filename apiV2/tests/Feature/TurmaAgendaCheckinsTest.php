<?php

namespace Tests\Feature;

use App\Repositories\TurmaRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TurmaAgendaCheckinsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('turmas', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('tenant_id');
            $table->integer('dia_id');
            $table->integer('professor_id');
            $table->integer('modalidade_id');
            $table->string('horario_inicio');
            $table->boolean('ativo');
        });
        Schema::create('dias', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->date('data');
        });
        Schema::create('professores', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('nome');
        });
        Schema::create('modalidades', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('nome');
            $table->string('icone')->nullable();
            $table->string('cor')->nullable();
        });
        Schema::create('checkins', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('tenant_id');
            $table->integer('turma_id');
            $table->integer('aluno_id');
        });
        Schema::create('inscricoes_turmas', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('turma_id');
            $table->boolean('ativo');
            $table->string('status');
        });
        DB::table('dias')->insert([['id' => 1, 'data' => '2026-10-20'], ['id' => 2, 'data' => '2026-10-21']]);
        DB::table('professores')->insert(['id' => 1, 'nome' => 'Professor']);
        DB::table('modalidades')->insert(['id' => 1, 'nome' => 'CrossFit']);
        foreach ([[1, 3, 1, 1], [2, 3, 1, 0], [3, 4, 1, 1], [4, 3, 2, 1]] as [$id, $tenant, $dia, $ativo]) {
            DB::table('turmas')->insert([
                'id' => $id, 'tenant_id' => $tenant, 'dia_id' => $dia,
                'professor_id' => 1, 'modalidade_id' => 1, 'horario_inicio' => '06:00:00', 'ativo' => $ativo,
            ]);
        }
    }

    public function test_agenda_conta_checkins_sem_inscricoes_e_isola_turma_dia_e_tenant(): void
    {
        DB::table('checkins')->insert([
            ['tenant_id' => 3, 'turma_id' => 1, 'aluno_id' => 10],
            ['tenant_id' => 3, 'turma_id' => 1, 'aluno_id' => 10],
            ['tenant_id' => 3, 'turma_id' => 1, 'aluno_id' => 11],
            ['tenant_id' => 4, 'turma_id' => 1, 'aluno_id' => 12],
            ['tenant_id' => 3, 'turma_id' => 2, 'aluno_id' => 13],
            ['tenant_id' => 4, 'turma_id' => 3, 'aluno_id' => 14],
            ['tenant_id' => 3, 'turma_id' => 4, 'aluno_id' => 15],
        ]);
        $repository = app(TurmaRepository::class);
        $rows = $repository->listarPorDia(3, 1, false);
        $byId = array_column($rows, null, 'id');

        $this->assertCount(2, $rows);
        $this->assertSame(0, (int) $byId[1]['alunos_count']);
        $this->assertSame(2, (int) $byId[1]['total_checkins']);
        $this->assertSame(1, (int) $byId[2]['total_checkins']);
        $this->assertCount(1, $repository->listarPorDia(3, 1, true));
        $this->assertSame(1, (int) $repository->listarPorDia(3, 2, false)[0]['total_checkins']);
        $this->assertSame(1, (int) $repository->listarPorDia(4, 1, false)[0]['total_checkins']);
    }

    public function test_inscricao_fixa_nao_e_contada_como_checkin(): void
    {
        DB::table('inscricoes_turmas')->insert(['turma_id' => 1, 'ativo' => 1, 'status' => 'ativa']);

        $rows = app(TurmaRepository::class)->listarPorDia(3, 1, true);

        $this->assertSame(1, (int) $rows[0]['alunos_count']);
        $this->assertSame(0, (int) $rows[0]['total_checkins']);
    }
}
