<?php

namespace Tests\Unit;

use App\Services\AppConfigService;
use App\Services\Mobile\MobileCompraPlanoService;
use App\Services\PagamentoPlanoService;
use App\Support\AcademyDateTime;
use App\Support\TrocaPlanoApp;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

/**
 * Crédito gerado pela academia no cancelamento é abatido obrigatoriamente na compra do novo plano pelo app:
 * fica reservado na parcela e só é consumido quando ela é paga.
 */
class CreditoCompraAppTest extends TestCase
{
    use RequiresSqlite;

    private const T = 3;

    private const ALUNO = 6;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();

        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            foreach (['tenant_id', 'aluno_id', 'matricula_id', 'plano_id', 'status_pagamento_id', 'credito_id', 'criado_por'] as $c) {
                $t->integer($c)->nullable();
            }
            foreach (['valor', 'tarifa_cartao', 'sobretaxa_antecipacao', 'credito_aplicado', 'credito_reservado'] as $c) {
                $t->decimal($c, 10, 2)->nullable();
            }
            $t->date('data_vencimento')->nullable();
            $t->date('data_pagamento')->nullable();
            $t->text('observacoes')->nullable();
            $t->timestamps();
        });
        Schema::create('creditos_aluno', function (Blueprint $t) {
            $t->increments('id');
            foreach (['tenant_id', 'aluno_id', 'matricula_origem_id', 'pagamento_origem_id', 'criado_por'] as $c) {
                $t->integer($c)->nullable();
            }
            $t->decimal('valor', 10, 2);
            $t->decimal('valor_utilizado', 10, 2)->default(0);
            $t->integer('status_credito_id')->default(1);
            $t->text('motivo')->nullable();
            $t->timestamps();
        });
        // Crédito #28 (R$ 100, mais antigo) e #29 (R$ 50)
        DB::table('creditos_aluno')->insert([
            ['id' => 28, 'tenant_id' => self::T, 'aluno_id' => self::ALUNO, 'valor' => 100, 'valor_utilizado' => 0, 'status_credito_id' => 1],
            ['id' => 29, 'tenant_id' => self::T, 'aluno_id' => self::ALUNO, 'valor' => 50, 'valor_utilizado' => 0, 'status_credito_id' => 1],
            ['id' => 30, 'tenant_id' => self::T, 'aluno_id' => self::ALUNO, 'valor' => 999, 'valor_utilizado' => 0, 'status_credito_id' => 3], // cancelado
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function parcela(array $extra): int
    {
        return (int) DB::table('pagamentos_plano')->insertGetId($extra + [
            'tenant_id' => self::T, 'aluno_id' => self::ALUNO, 'matricula_id' => 55, 'plano_id' => 10,
            'data_vencimento' => AcademyDateTime::today(),
        ]);
    }

    private function credito(int $id): object
    {
        return DB::table('creditos_aluno')->find($id);
    }

    public function test_saldo_ignora_credito_cancelado(): void
    {
        $this->assertSame(150.0, (new PagamentoPlanoService)->saldoCreditoAluno(self::T, self::ALUNO));
    }

    public function test_parcela_paga_consome_o_reservado_do_mais_antigo_e_uma_vez_so(): void
    {
        $id = $this->parcela(['valor' => 440, 'credito_reservado' => 120, 'status_pagamento_id' => 2, 'data_pagamento' => AcademyDateTime::today()]);
        $svc = new PagamentoPlanoService;

        $svc->consumirCreditoReservado(self::T, 55);
        $svc->consumirCreditoReservado(self::T, 55);

        $this->assertEqualsWithDelta(100.0, (float) $this->credito(28)->valor_utilizado, 0.001);
        $this->assertSame(2, (int) $this->credito(28)->status_credito_id, 'usado por inteiro');
        $this->assertEqualsWithDelta(20.0, (float) $this->credito(29)->valor_utilizado, 0.001, 'e não 40: a segunda chamada não consome de novo');
        $this->assertSame(1, (int) $this->credito(29)->status_credito_id);
        $p = DB::table('pagamentos_plano')->find($id);
        $this->assertNull($p->credito_reservado);
        $this->assertEqualsWithDelta(120.0, (float) $p->credito_aplicado, 0.001);
        $this->assertSame(28, (int) $p->credito_id);
        $this->assertEqualsWithDelta(30.0, $svc->saldoCreditoAluno(self::T, self::ALUNO), 0.001);
    }

    public function test_parcela_nao_paga_nao_consome_credito(): void
    {
        $this->parcela(['valor' => 440, 'credito_reservado' => 120, 'status_pagamento_id' => 1]);
        $this->parcela(['valor' => 440, 'credito_reservado' => 120, 'status_pagamento_id' => 4]);

        (new PagamentoPlanoService)->consumirCreditoReservado(self::T, 55);

        $this->assertEqualsWithDelta(150.0, (new PagamentoPlanoService)->saldoCreditoAluno(self::T, self::ALUNO), 0.001);
    }

    public function test_reserva_maior_que_o_saldo_registra_so_o_que_existia(): void
    {
        $id = $this->parcela(['valor' => 10, 'credito_reservado' => 200, 'status_pagamento_id' => 2, 'data_pagamento' => AcademyDateTime::today()]);

        (new PagamentoPlanoService)->consumirCreditoReservado(self::T, 55);

        $this->assertEqualsWithDelta(150.0, (float) DB::table('pagamentos_plano')->where('id', $id)->value('credito_aplicado'), 0.001);
        $this->assertSame(0.0, (new PagamentoPlanoService)->saldoCreditoAluno(self::T, self::ALUNO));
    }

    public function test_credito_abatido_conta_como_pago_do_plano(): void
    {
        // Matrícula 55: R$ 320 no cartão (+ taxas) e R$ 240 de crédito pagam o plano de R$ 560
        $this->parcela(['valor' => 364.44, 'tarifa_cartao' => 12.08, 'sobretaxa_antecipacao' => 32.36, 'credito_aplicado' => 240, 'status_pagamento_id' => 2, 'data_pagamento' => AcademyDateTime::today()]);

        $this->assertEqualsWithDelta(560.0, (new PagamentoPlanoService)->somarPagamentosPagosNoCiclo(self::T, 55, AcademyDateTime::today()), 0.001);
    }

    public function test_credito_que_cobre_o_plano_ativa_sem_provedor(): void
    {
        $pagamentos = Mockery::mock(PagamentoPlanoService::class)->makePartial();
        $pagamentos->shouldReceive('cancelarParcelasAbertas')->once()->andReturn(0);
        // Recálculo real de status depende de MySQL; aqui basta o consumo que ele dispara primeiro
        $pagamentos->shouldReceive('atualizarStatusMatricula')->once()->andReturnUsing(fn (int $t, int $m) => $pagamentos->consumirCreditoReservado($t, $m));
        $this->app->instance(PagamentoPlanoService::class, $pagamentos);
        Schema::create('status_matricula', fn (Blueprint $t) => [$t->increments('id'), $t->string('codigo')]);
        Schema::create('matriculas', fn (Blueprint $t) => [$t->increments('id'), $t->integer('status_id')]);
        DB::table('status_matricula')->insert(['id' => 1, 'codigo' => 'ativa']);
        DB::table('matriculas')->insert(['id' => 55, 'status_id' => 1]);

        $ativar = new \ReflectionMethod(MobileCompraPlanoService::class, 'ativarComCredito');
        $r = $ativar->invoke(app(MobileCompraPlanoService::class), self::T, self::ALUNO, 55, 10, ['nome' => '4x por Semana', 'modalidade_nome' => 'Natação'],
            67, 'Semestral', 6, 120.0, AcademyDateTime::today(), new \DateTime('+6 months'), 5, 20);

        $this->assertTrue($r['body']['data']['pago_com_credito']);
        $this->assertNull($r['body']['data']['payment_url']);
        $p = DB::table('pagamentos_plano')->first();
        $this->assertSame(2, (int) $p->status_pagamento_id);
        $this->assertEqualsWithDelta(0.0, (float) $p->valor, 0.001);
        $this->assertEqualsWithDelta(120.0, (float) $p->credito_aplicado, 0.001);
        $this->assertEqualsWithDelta(30.0, $pagamentos->saldoCreditoAluno(self::T, self::ALUNO), 0.001);
    }

    public function test_troca_de_plano_pelo_app_fica_bloqueada_ate_a_academia_ligar(): void
    {
        $config = Mockery::mock(AppConfigService::class);
        $config->shouldReceive('parametro')->with('troca_plano_app', self::T)->andReturn(false, true);
        $this->app->instance(AppConfigService::class, $config);

        $this->assertFalse(TrocaPlanoApp::permitida(self::T));
        $this->assertSame(TrocaPlanoApp::CODIGO, TrocaPlanoApp::bloqueio()['body']['code']);
        $this->assertTrue(TrocaPlanoApp::permitida(self::T));
    }

    public function test_parametro_nasce_desligado(): void
    {
        $this->assertFalse(\App\Support\AppConfigCatalogo::definicao('parametro.troca_plano_app')['padrao']);
    }
}
