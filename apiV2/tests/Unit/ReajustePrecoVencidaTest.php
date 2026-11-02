<?php

namespace Tests\Unit;

use App\Services\ReajustePrecoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

/**
 * Caso real (matrícula 236): ciclo 1x/semana reajustado de R$ 70 para R$ 80; a matrícula já vencida seguia com a
 * parcela atrasada em R$ 70, porque o reajuste só mexia nas parcelas a vencer.
 */
class ReajustePrecoVencidaTest extends TestCase
{
    use RequiresSqlite;

    private const T = 3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();
        Schema::create('status_matricula', fn (Blueprint $t) => [$t->increments('id'), $t->string('codigo')]);
        Schema::create('alunos', fn (Blueprint $t) => [$t->increments('id'), $t->string('nome')->nullable()]);
        Schema::create('matriculas', function (Blueprint $t) {
            $t->increments('id');
            foreach (['tenant_id', 'aluno_id', 'plano_ciclo_id', 'pacote_contrato_id', 'status_id'] as $c) {
                $t->integer($c)->nullable();
            }
            $t->string('tipo_cobranca')->nullable();
            $t->decimal('valor', 10, 2);
            $t->decimal('valor_renovacao', 10, 2)->nullable();
            $t->date('data_vencimento')->nullable();
            $t->date('proxima_data_vencimento')->nullable();
            $t->timestamp('updated_at')->nullable();
        });
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            foreach (['tenant_id', 'matricula_id', 'status_pagamento_id'] as $c) {
                $t->integer($c)->nullable();
            }
            foreach (['valor', 'valor_original', 'desconto'] as $c) {
                $t->decimal($c, 10, 2)->nullable();
            }
            $t->date('data_vencimento')->nullable();
            $t->date('data_pagamento')->nullable();
            $t->text('observacoes')->nullable();
            $t->timestamp('updated_at')->nullable();
        });
        Schema::create('pagamentos_pix', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('matricula_id');
            $t->string('payment_id');
            $t->string('status')->nullable();
            $t->dateTime('expires_at')->nullable();
        });
        Schema::create('assinaturas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('matricula_id');
            $t->string('payment_url')->nullable();
            $t->string('status_gateway')->nullable();
            $t->decimal('valor', 10, 2)->nullable();
            $t->timestamp('atualizado_em')->nullable();
        });
        DB::table('status_matricula')->insert([['id' => 1, 'codigo' => 'ativa'], ['id' => 2, 'codigo' => 'vencida']]);
        DB::table('alunos')->insert(['id' => 1, 'nome' => 'VISSIA']);
    }

    private function dia(int $offset): string
    {
        return now()->addDays($offset)->toDateString();
    }

    private function matricula(int $id, int $statusId, string $vencimento): void
    {
        DB::table('matriculas')->insert(['id' => $id, 'tenant_id' => self::T, 'aluno_id' => 1, 'plano_ciclo_id' => 60, 'status_id' => $statusId,
            'tipo_cobranca' => 'avulso', 'valor' => 70, 'data_vencimento' => $vencimento, 'proxima_data_vencimento' => $vencimento]);
    }

    private function parcela(int $id, int $matriculaId, string $vencimento, string $obs = 'Pagamento gerado automaticamente após confirmação MP'): void
    {
        DB::table('pagamentos_plano')->insert(['id' => $id, 'tenant_id' => self::T, 'matricula_id' => $matriculaId, 'status_pagamento_id' => 3,
            'valor' => 70, 'valor_original' => 70, 'desconto' => 0, 'data_vencimento' => $vencimento, 'observacoes' => $obs]);
    }

    public function test_vencida_com_parcela_atrasada_passa_ao_preco_novo_no_reajuste(): void
    {
        $this->matricula(236, 2, $this->dia(-4));
        $this->parcela(1314, 236, $this->dia(-4));
        DB::table('pagamentos_pix')->insert(['tenant_id' => self::T, 'matricula_id' => 236, 'payment_id' => 'pay_velho', 'status' => 'pending', 'expires_at' => $this->dia(300)]);
        DB::table('assinaturas')->insert(['tenant_id' => self::T, 'matricula_id' => 236, 'payment_url' => 'https://asaas/i/velho', 'valor' => 70]);

        app(ReajustePrecoService::class)->reajustarCiclo(self::T, 60, 70.0, 80.0);

        $this->assertEqualsWithDelta(80.0, (float) DB::table('pagamentos_plano')->where('id', 1314)->value('valor'), 0.001);
        $m = DB::table('matriculas')->find(236);
        $this->assertEqualsWithDelta(80.0, (float) $m->valor, 0.001, 'o ciclo a pagar já é o do preço novo');
        $this->assertNull($m->valor_renovacao);
        $this->assertLessThanOrEqual(now()->timestamp, strtotime((string) DB::table('pagamentos_pix')->value('expires_at')), 'PIX no preço velho não é reaproveitado');
        $this->assertNull(DB::table('assinaturas')->value('payment_url'));
    }

    public function test_ativa_no_ciclo_pago_nao_muda_o_que_ja_esta_correndo(): void
    {
        $this->matricula(500, 1, $this->dia(20));
        $this->parcela(1500, 500, $this->dia(-2)); // atrasada, mas o ciclo pago ainda corre

        app(ReajustePrecoService::class)->reajustarCiclo(self::T, 60, 70.0, 80.0);

        $this->assertEqualsWithDelta(70.0, (float) DB::table('pagamentos_plano')->where('id', 1500)->value('valor'), 0.001);
        $this->assertEqualsWithDelta(80.0, (float) DB::table('matriculas')->where('id', 500)->value('valor_renovacao'), 0.001, 'vale na renovação');
    }

    public function test_simulacao_de_troca_nao_e_reajustada(): void
    {
        $this->matricula(236, 2, $this->dia(-4));
        $this->parcela(1531, 236, $this->dia(-4), 'Simulação de migração [MIGRACAO_SIMULADA plano_id=10 plano_ciclo_id=68]');
        DB::table('matriculas')->where('id', 236)->update(['valor_renovacao' => 80]);

        app(ReajustePrecoService::class)->aplicarNaRenovacaoEmAberto(self::T, 236);

        $this->assertEqualsWithDelta(70.0, (float) DB::table('pagamentos_plano')->where('id', 1531)->value('valor'), 0.001);
    }

    public function test_comando_corrige_quem_venceu_antes_do_reajuste_e_simular_nao_grava(): void
    {
        $this->matricula(236, 2, $this->dia(-4));
        $this->parcela(1314, 236, $this->dia(-4));
        DB::table('matriculas')->where('id', 236)->update(['valor_renovacao' => 80]);

        $this->artisan('reajuste:aplicar-renovacoes', ['--simular' => true])->assertSuccessful();
        $this->assertEqualsWithDelta(70.0, (float) DB::table('pagamentos_plano')->where('id', 1314)->value('valor'), 0.001);

        $this->artisan('reajuste:aplicar-renovacoes')->assertSuccessful();
        $this->assertEqualsWithDelta(80.0, (float) DB::table('pagamentos_plano')->where('id', 1314)->value('valor'), 0.001);
        $this->assertEqualsWithDelta(80.0, (float) DB::table('matriculas')->where('id', 236)->value('valor'), 0.001);
    }
}
