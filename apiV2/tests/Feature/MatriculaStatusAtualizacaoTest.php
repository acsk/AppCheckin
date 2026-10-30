<?php

namespace Tests\Feature;

use App\Repositories\MatriculaRepository;
use App\Services\MatriculaStatusAtualizacaoService;
use App\Services\PagamentoPlanoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

/** Recálculo em lote: só ativa/pendente/vencida, simulação não grava, falha desfaz a academia. */
class MatriculaStatusAtualizacaoTest extends TestCase
{
    use RequiresSqlite;

    private const T = 3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();

        Schema::create('tenants', fn (Blueprint $t) => [$t->increments('id'), $t->boolean('ativo')]);
        Schema::create('status_matricula', fn (Blueprint $t) => [$t->increments('id'), $t->string('codigo')]);
        Schema::create('alunos', fn (Blueprint $t) => [$t->increments('id'), $t->string('nome')]);
        Schema::create('matriculas', fn (Blueprint $t) => [$t->increments('id'), $t->integer('tenant_id'), $t->integer('aluno_id'), $t->integer('status_id'), $t->timestamps()]);

        DB::table('tenants')->insert([['id' => self::T, 'ativo' => 1], ['id' => 9, 'ativo' => 0]]);
        DB::table('status_matricula')->insert([['id' => 1, 'codigo' => 'ativa'], ['id' => 2, 'codigo' => 'vencida'], ['id' => 3, 'codigo' => 'cancelada'], ['id' => 5, 'codigo' => 'pendente'], ['id' => 6, 'codigo' => 'bloqueado']]);
        DB::table('alunos')->insert([['id' => 1, 'nome' => 'Ana'], ['id' => 2, 'nome' => 'Bia'], ['id' => 3, 'nome' => 'Caio']]);
        DB::table('matriculas')->insert([
            ['id' => 10, 'tenant_id' => self::T, 'aluno_id' => 1, 'status_id' => 5], // pendente já paga
            ['id' => 11, 'tenant_id' => self::T, 'aluno_id' => 2, 'status_id' => 6], // bloqueada
            ['id' => 12, 'tenant_id' => self::T, 'aluno_id' => 3, 'status_id' => 1], // ativa sem mudança
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** Recálculo falso: a pendente #10 vira ativa. */
    private function servico(?\Throwable $falha = null): array
    {
        $pag = Mockery::mock(PagamentoPlanoService::class);
        $pag->shouldReceive('marcarAtrasados')->with(self::T)->andReturn(2);
        $chamadas = new \ArrayObject;
        $pag->shouldReceive('atualizarStatusMatricula')->andReturnUsing(function (int $t, int $id) use ($chamadas, $falha) {
            $chamadas[] = $id;
            if ($id === 10) {
                DB::table('matriculas')->where('id', 10)->update(['status_id' => 1]);
            }
            if ($falha && $id === 12) {
                throw $falha;
            }
        });
        $rep = Mockery::mock(MatriculaRepository::class);
        $rep->shouldReceive('marcarPendenteSeLimiteCicloEsgotado')->andReturn(false);

        return [new MatriculaStatusAtualizacaoService($pag, $rep), $chamadas];
    }

    public function test_recalcula_so_ativa_pendente_vencida_e_lista_mudancas(): void
    {
        [$s, $chamadas] = $this->servico();
        $r = $s->processarTenant(self::T);

        $this->assertSame([10, 12], $chamadas->getArrayCopy());
        $this->assertSame([['matricula_id' => 10, 'aluno' => 'Ana', 'de' => 'pendente', 'para' => 'ativa']], $r['mudancas']);
        $this->assertSame(2, $r['parcelas_atrasadas']);
        $this->assertSame(1, (int) DB::table('matriculas')->where('id', 10)->value('status_id'));
        $this->assertSame(6, (int) DB::table('matriculas')->where('id', 11)->value('status_id'));
    }

    public function test_simulacao_lista_sem_gravar(): void
    {
        [$s] = $this->servico();
        $r = $s->processarTenant(self::T, true);

        $this->assertCount(1, $r['mudancas']);
        $this->assertSame(5, (int) DB::table('matriculas')->where('id', 10)->value('status_id'));
    }

    public function test_falha_desfaz_a_academia(): void
    {
        [$s] = $this->servico(new \RuntimeException('boom'));
        $r = $s->processarTenant(self::T);

        $this->assertSame('boom', $r['erro']);
        $this->assertSame([], $r['mudancas']);
        $this->assertSame(5, (int) DB::table('matriculas')->where('id', 10)->value('status_id'));
    }

    public function test_so_academias_ativas(): void
    {
        [$s] = $this->servico();
        $this->assertSame([self::T], $s->tenantsAtivos());
    }
}
