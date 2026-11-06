<?php

namespace Tests\Feature;

use App\Repositories\TurmaRepository;
use App\Services\AcessoHibridoService;
use App\Services\Admin\AdminTurmaFixaGestaoService;
use App\Support\AcademyDateTime;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\Fixtures\HybridFixture;
use Tests\TestCase;

class AcessoHibridoConcurrencyTest extends TestCase
{
    public function test_mysql_apenas_um_aluno_ocupa_a_ultima_vaga_e_repeticoes_sao_consistentes(): void
    {
        if (getenv('HYBRID_MYSQL_TEST') !== '1') {
            $this->markTestSkipped('Execute no container MySQL isolado, conforme README.');
        }
        HybridFixture::mysql();
        HybridFixture::create();
        HybridFixture::migrate();
        HybridFixture::seed();
        DB::table('turmas')->update(['horario_inicio' => AcademyDateTime::now()->format('H:i:s')]);
        DB::table('checkins')->insert(['tenant_id' => 3, 'turma_id' => 1, 'aluno_id' => 11]);
        $start = static fn ($id) => new Process([PHP_BINARY, base_path('tests/Fixtures/hybrid-checkin-worker.php'), (string) $id],
            base_path(), ['DB_CONNECTION' => 'mysql'], null, 30);
        $workers = [$start(12), $start(13)];
        foreach ($workers as $worker) {
            $worker->start();
        }
        $statuses = [];
        foreach ($workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            $statuses[] = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR)['status'];
        }
        sort($statuses);
        $this->assertSame([201, 403], $statuses);
        $this->assertSame(2, DB::table('checkins')->count());
        $winner = (int) DB::table('checkins')->where('aluno_id', '<>', 11)->value('aluno_id');
        $repeated = [$start($winner), $start($winner)];
        foreach ($repeated as $worker) {
            $worker->start();
        }
        foreach ($repeated as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            $this->assertNotSame(201, json_decode($worker->getOutput(), true)['status']);
        }
        $this->assertSame(2, DB::table('checkins')->count());

        DB::table('checkins')->delete();
        DB::table('turmas')->insert(['id' => 2, 'tenant_id' => 3, 'dia_id' => 1, 'horario_inicio' => AcademyDateTime::now()->format('H:i:s')]);
        $diarios = array_map(fn ($aula) => new Process([PHP_BINARY, base_path('tests/Fixtures/hybrid-checkin-worker.php'), '11', 'diario', (string) $aula], base_path(), [], null, 30), [1, 2]);
        foreach ($diarios as $worker) {
            $worker->start();
        }
        $statuses = [];
        foreach ($diarios as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            $statuses[] = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR)['status'];
        }
        sort($statuses);
        $this->assertSame([201, 400], $statuses);
        $this->assertSame(1, DB::table('checkins')->where('aluno_id', 11)->count());

        DB::table('checkins')->delete();
        DB::table('turmas_fixas')->where('id', 1)->update(['capacidade' => 11]);
        DB::table('matriculas')->whereIn('id', [13, 14])->update(['tipo_acesso' => 'turma_fixa', 'turma_fixa_id' => 1, 'status_id' => 2]);
        $ativacoes = array_map(fn ($id) => new Process([PHP_BINARY, base_path('tests/Fixtures/hybrid-checkin-worker.php'), (string) $id, 'ativar'], base_path(), [], null, 30), [13, 14]);
        foreach ($ativacoes as $worker) {
            $worker->start();
        }
        foreach ($ativacoes as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
        }
        $this->assertSame(1, DB::table('matriculas')->whereIn('id', [13, 14])->where('status_id', 1)->count());
        $esperando = DB::table('status_matricula')->where('codigo', 'aguardando_vaga')->value('id');
        $this->assertSame(1, DB::table('matriculas')->whereIn('id', [13, 14])->where('status_id', $esperando)->count());
    }

    protected function tearDown(): void
    {
        if (getenv('HYBRID_MYSQL_TEST') === '1' && config('database.connections.mysql.database') === 'appcheckin_hybrid_test') {
            foreach (['turma_fixa_eventos', 'turma_fixa_pausas', 'aula_reposicoes', 'matricula_grade', 'tenant_usuario_papel', 'professores', 'turma_fixa_planos', 'turmas_fixas', 'checkins', 'turmas', 'dias', 'matriculas', 'alunos', 'planos', 'status_matricula', 'parametros', 'tipos_parametro'] as $table) {
                Schema::dropIfExists($table);
            }
        }
        parent::tearDown();
    }

    public function test_mysql_reposicao_e_checkin_livre_disputam_a_mesma_ultima_vaga(): void
    {
        if (getenv('HYBRID_MYSQL_TEST') !== '1') {
            $this->markTestSkipped('Execute no MySQL isolado.');
        }
        HybridFixture::mysql();
        HybridFixture::create();
        HybridFixture::migrate();
        HybridFixture::seed();
        DB::table('turmas_fixas')->update(['dias_semana' => json_encode([(int) AcademyDateTime::now()->format('N')])]);
        (require database_path('migrations/2026_11_06_140000_grade_individual_reposicoes.php'))->up();
        (require database_path('migrations/2026_11_06_160000_ciclo_vida_turmas_fixas.php'))->up();
        Schema::create('tenant_usuario_papel', function (Blueprint $t) {
            $t->integer('usuario_id');
            $t->integer('tenant_id');
            $t->integer('papel_id');
            $t->boolean('ativo');
        });
        Schema::create('professores', function (Blueprint $t) {
            $t->integer('id');
            $t->integer('usuario_id');
        });
        DB::table('tenant_usuario_papel')->insert(['usuario_id' => 90, 'tenant_id' => 3, 'papel_id' => 2, 'ativo' => true]);
        DB::table('professores')->insert(['id' => 1, 'usuario_id' => 90]);
        DB::table('turmas')->update(['limite_alunos' => 11, 'horario_inicio' => AcademyDateTime::now()->format('H:i:s'), 'horario_fim' => '23:59:59']);
        $workers = [
            new Process([PHP_BINARY, base_path('tests/Fixtures/hybrid-checkin-worker.php'), '12', 'reposicao'], base_path(), [], null, 30),
            new Process([PHP_BINARY, base_path('tests/Fixtures/hybrid-checkin-worker.php'), '13'], base_path(), [], null, 30),
        ];
        foreach ($workers as $worker) {
            $worker->start();
        }
        $statuses = [];
        foreach ($workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            $statuses[] = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR)['status'];
        }
        sort($statuses);
        $this->assertSame([201, 403], $statuses);
        $this->assertSame(1, DB::table('aula_reposicoes')->count() + DB::table('checkins')->count());
        $this->assertSame(0, app(AcessoHibridoService::class)->vagas(HybridFixture::aula(), 3)['vagas_livres']);
        DB::table('checkins')->delete();
        DB::table('aula_reposicoes')->delete();
        $workers = array_map(fn ($id) => new Process([PHP_BINARY, base_path('tests/Fixtures/hybrid-checkin-worker.php'), (string) $id, 'reposicao'], base_path(), [], null, 30), [12, 13]);
        foreach ($workers as $worker) {
            $worker->start();
        }
        $statuses = [];
        foreach ($workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            $statuses[] = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR)['status'];
        }
        sort($statuses);
        $this->assertSame([201, 403], $statuses);
        $this->assertSame(1, DB::table('aula_reposicoes')->count());
        DB::table('aula_reposicoes')->delete();
        DB::table('turmas_fixas')->where('id', 1)->update(['capacidade' => 11]);
        $workers = array_map(fn ($id) => new Process([PHP_BINARY, base_path('tests/Fixtures/hybrid-checkin-worker.php'), (string) $id, 'grade'], base_path(), [], null, 30), [12, 13]);
        foreach ($workers as $worker) {
            $worker->start();
        }
        $statuses = [];
        foreach ($workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            $statuses[] = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR)['status'];
        }
        sort($statuses);
        $this->assertSame([200, 422], $statuses);
        $this->assertSame(11, DB::table('matricula_grade')->count());
    }

    public function test_mysql_pausas_simultaneas_nao_duplicam_periodo_nem_evento(): void
    {
        if (getenv('HYBRID_MYSQL_TEST') !== '1') {
            $this->markTestSkipped('Execute no MySQL isolado.');
        }
        HybridFixture::mysql();
        HybridFixture::create();
        HybridFixture::migrate();
        HybridFixture::seed();
        (require database_path('migrations/2026_11_06_140000_grade_individual_reposicoes.php'))->up();
        (require database_path('migrations/2026_11_06_160000_ciclo_vida_turmas_fixas.php'))->up();
        $workers = array_map(fn ($id) => new Process([PHP_BINARY, base_path('tests/Fixtures/hybrid-checkin-worker.php'), (string) $id, 'pausar'], base_path(), [], null, 30), [1, 2]);
        foreach ($workers as $worker) {
            $worker->start();
        }
        $statuses = [];
        foreach ($workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            $statuses[] = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR)['status'];
        }
        sort($statuses);
        $this->assertSame([200, 422], $statuses);
        $this->assertSame(1, DB::table('turma_fixa_pausas')->count());
        $this->assertSame(1, DB::table('turma_fixa_eventos')->count());
        $this->assertEquals(0, DB::table('turmas')->where('id', 1)->value('ativo'));

        DB::table('turma_fixa_pausas')->delete();
        DB::table('turma_fixa_eventos')->delete();
        DB::table('turmas')->where('id', 1)->update(['ativo' => 1, 'suspensao_turma_id' => null,
            'horario_inicio' => AcademyDateTime::now()->format('H:i:s'), 'horario_fim' => '23:59:59']);
        $workers = [
            new Process([PHP_BINARY, base_path('tests/Fixtures/hybrid-checkin-worker.php'), '1', 'pausar'], base_path(), [], null, 30),
            new Process([PHP_BINARY, base_path('tests/Fixtures/hybrid-checkin-worker.php'), '13'], base_path(), [], null, 30),
        ];
        foreach ($workers as $worker) {
            $worker->start();
        }
        $statuses = [];
        foreach ($workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            $statuses[] = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR)['status'];
        }
        $this->assertTrue(in_array($statuses, [[200, 403], [422, 201]], true), json_encode($statuses));
        $this->assertSame(1, DB::table('turma_fixa_pausas')->count() + DB::table('checkins')->count());
        Schema::create('professores', fn (Blueprint $t) => $t->integer('id')->primary());
        DB::table('professores')->insert([['id' => 1], ['id' => 2]]);
        $repositorio = \Mockery::mock(TurmaRepository::class)->makePartial();
        $repositorio->shouldReceive('professorPertenceAoTenant')->andReturn(true);
        $this->app->instance(TurmaRepository::class, $repositorio);
        $futuro = AcademyDateTime::now()->modify('next Monday')->format('Y-m-d');
        $diaId = DB::table('dias')->insertGetId(['data' => $futuro]);
        DB::table('turmas')->insert(['id' => 2, 'tenant_id' => 3, 'dia_id' => $diaId, 'turma_fixa_id' => 1,
            'ativo' => 0, 'checkin_livre' => false, 'limite_alunos' => 9, 'tolerancia_antes_checkin_minutos' => 30]);
        $resultado = app(AdminTurmaFixaGestaoService::class)->executar(3, 90, [
            'acao' => 'professor', 'ids' => [1], 'dados' => ['from' => AcademyDateTime::now()->modify('+1 day')->format('Y-m-d'), 'professor_id' => 2],
        ]);
        $nova = DB::table('turmas')->where('turma_fixa_id', $resultado['resultados'][0]['nova_turma_id'])->where('dia_id', $diaId)->first();
        $this->assertEquals(0, $nova->ativo);
        $this->assertEquals(9, $nova->limite_alunos);
        $this->assertEquals(30, $nova->tolerancia_antes_checkin_minutos);
        $this->assertNull($nova->suspensao_turma_id);
    }
}
