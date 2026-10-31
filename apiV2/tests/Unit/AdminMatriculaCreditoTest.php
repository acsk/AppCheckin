<?php

namespace Tests\Unit;

use App\Repositories\AdminAssinaturaRepository;
use App\Repositories\AdminMatriculaRepository;
use App\Repositories\MatriculaRepository;
use App\Services\Admin\AdminMatriculaService;
use App\Services\Admin\AdminPacoteService;
use App\Services\Admin\AdminPagamentoPlanoService;
use App\Services\MatriculaMigracaoAptidaoService;
use App\Services\MatriculaMigracaoService;
use App\Services\PagamentoPlanoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\MockInterface;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

/**
 * O painel calcula crédito pela mesma régua da migração do app (só plano vigente, proporcional ao uso).
 * Caso real (matrícula 55): bimestral R$ 240 com 7 de 61 dias restantes e 15 check-ins → R$ 27,54, não R$ 240.
 */
class AdminMatriculaCreditoTest extends TestCase
{
    use RequiresSqlite;

    private const T = 3;

    /** @var AdminMatriculaRepository&MockInterface */
    private MockInterface $repo;

    private float $credito = 27.54;

    protected function setUp(): void
    {
        parent::setUp();

        $migracao = Mockery::mock(MatriculaMigracaoService::class);
        $migracao->shouldReceive('calcularCreditoMigracao')->andReturnUsing(fn () => [
            'tipo_credito' => 'proporcional',
            'credito' => $this->credito,
            'dias_totais' => 61,
            'dias_restantes' => 7,
            'dias_usados' => 54,
            'checkins_usados' => 15,
            'checkins_direito' => 26,
            'motivo' => 'Crédito proporcional (7 de 61 dias restantes; 15 de 26 check-ins usados de R$240,00)',
        ]);
        $this->app->instance(MatriculaMigracaoService::class, $migracao);

        $this->repo = Mockery::mock(AdminMatriculaRepository::class);
        $this->repo->shouldReceive('creditosAluno')->andReturn(['saldo_total' => 0.0, 'creditos_ativos' => []]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function service(): AdminMatriculaService
    {
        $pagamentos = Mockery::mock(PagamentoPlanoService::class);
        $pagamentos->shouldReceive('marcarAtrasados', 'atualizarStatusMatricula');
        $aptidao = Mockery::mock(MatriculaMigracaoAptidaoService::class);
        $aptidao->shouldReceive('avaliarAptidaoMigracao')->andReturn(['apto' => true, 'gera_credito' => true, 'code' => 'OK', 'message' => '', 'motivo' => null]);

        return new AdminMatriculaService(
            $this->repo,
            $pagamentos,
            Mockery::mock(MatriculaRepository::class),
            Mockery::mock(AdminPacoteService::class),
            Mockery::mock(AdminPagamentoPlanoService::class),
            Mockery::mock(AdminAssinaturaRepository::class),
            $aptidao,
        );
    }

    public function test_alterar_plano_com_valor_cheio_credita_so_o_proporcional_ao_uso(): void
    {
        $this->repo->shouldReceive('findParaAlterarPlano')->with(55, self::T)->andReturn([
            'id' => 55, 'aluno_id' => 6, 'plano_id' => 9, 'plano_ciclo_id' => 54, 'plano_nome' => '3x por Semana', 'valor' => 240.0,
            'status_codigo' => 'ativa', 'dia_vencimento' => 5, 'pacote_contrato_id' => null,
            'data_inicio' => '2026-09-07', 'data_vencimento' => '2026-11-07', 'proxima_data_vencimento' => '2026-11-07',
        ]);
        $this->repo->shouldReceive('temParcelaAbertaAtrasada')->andReturn(false);
        $this->repo->shouldReceive('findPlano')->with(10, self::T)->andReturn(['id' => 10, 'nome' => '4x por Semana', 'valor' => 560.0, 'duracao_dias' => 180]);
        $this->repo->shouldIgnoreMissing();

        $r = $this->service()->simularAlterarPlano(55, self::T, ['plano_id' => 10, 'abater_plano_anterior' => true]);

        $this->assertSame(200, $r['status'], json_encode($r['body']));
        $this->assertSame('proporcional', $r['body']['credito']['tipo']);
        $this->assertEqualsWithDelta(27.54, (float) $r['body']['credito']['gerado'], 0.001, 'e não os 240 do plano inteiro');
        $this->assertEqualsWithDelta(532.46, (float) $r['body']['valor_parcela'], 0.001);
    }

    public function test_cancelar_com_credito_gera_so_o_proporcional_ao_uso(): void
    {
        // Check-ins pesando mais que o tempo: só pelo tempo (cálculo antigo) dariam 240 × 7/61 = 27,54
        $this->credito = 10.0;
        $this->requireSqlite();
        $this->registrarFuncoesSqlite(['CONCAT' => [fn (...$a) => implode('', $a), -1], 'NOW' => [fn () => date('Y-m-d H:i:s'), 0], 'CURDATE' => [fn () => date('Y-m-d'), 0]]);
        Schema::create('status_matricula', fn (Blueprint $t) => [$t->increments('id'), $t->string('codigo'), $t->string('nome')->nullable()]);
        Schema::create('planos', fn (Blueprint $t) => [$t->increments('id'), $t->string('nome'), $t->decimal('valor', 10, 2), $t->integer('duracao_dias')]);
        Schema::create('plano_ciclos', fn (Blueprint $t) => [$t->increments('id'), $t->decimal('valor', 10, 2), $t->integer('assinatura_frequencia_id')->nullable()]);
        Schema::create('assinatura_frequencias', fn (Blueprint $t) => [$t->increments('id'), $t->integer('meses')]);
        Schema::create('matriculas', function (Blueprint $t) {
            $t->increments('id');
            foreach (['tenant_id', 'aluno_id', 'plano_id', 'status_id', 'cancelado_por'] as $c) {
                $t->integer($c)->nullable();
            }
            $t->integer('plano_ciclo_id')->nullable();
            $t->decimal('valor', 10, 2);
            foreach (['data_inicio', 'data_vencimento', 'proxima_data_vencimento', 'data_cancelamento'] as $c) {
                $t->date($c)->nullable();
            }
            $t->string('motivo_cancelamento')->nullable();
            $t->string('motivo_cancelamento_codigo')->nullable();
            $t->timestamp('updated_at')->nullable();
        });
        Schema::create('pagamentos_plano', fn (Blueprint $t) => [$t->increments('id'), $t->integer('tenant_id'), $t->integer('matricula_id'), $t->integer('status_pagamento_id'), $t->text('observacoes')->nullable(), $t->timestamp('updated_at')->nullable()]);
        DB::table('status_matricula')->insert([['id' => 1, 'codigo' => 'ativa'], ['id' => 4, 'codigo' => 'cancelada']]);
        DB::table('planos')->insert(['id' => 9, 'nome' => '3x por Semana', 'valor' => 150, 'duracao_dias' => 30]);
        DB::table('matriculas')->insert([
            'id' => 55, 'tenant_id' => self::T, 'aluno_id' => 6, 'plano_id' => 9, 'status_id' => 1, 'valor' => 240,
            'data_inicio' => '2026-09-07', 'data_vencimento' => '2026-11-07', 'proxima_data_vencimento' => '2026-11-07',
        ]);

        $this->repo->shouldReceive('desativarDescontosSeTabelaExiste')->once();
        $this->repo->shouldReceive('findUsuarioIdPorAluno')->andReturn(null);
        $this->repo->shouldReceive('inserirCredito')->once()
            ->with(Mockery::on(fn (array $c) => abs($c['valor'] - 10.0) < 0.001 && $c['matricula_origem_id'] === 55))
            ->andReturn(28);

        $r = $this->service()->cancelarComCredito(55, self::T, 1, ['gerar_credito' => true, 'motivo_codigo' => 'troca_plano']);

        $this->assertSame(200, $r['status'], json_encode($r['body']));
        $this->assertEqualsWithDelta(10.0, (float) $r['body']['credito_gerado']['valor'], 0.001);
        $this->assertSame(4, (int) DB::table('matriculas')->where('id', 55)->value('status_id'));
    }
}
