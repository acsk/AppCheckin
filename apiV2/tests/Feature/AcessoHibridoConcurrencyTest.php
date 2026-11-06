<?php

namespace Tests\Feature;

use App\Support\AcademyDateTime;
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
            foreach (['turma_fixa_planos', 'turmas_fixas', 'checkins', 'turmas', 'dias', 'matriculas', 'alunos', 'planos', 'status_matricula', 'parametros', 'tipos_parametro'] as $table) {
                Schema::dropIfExists($table);
            }
        }
        parent::tearDown();
    }
}
