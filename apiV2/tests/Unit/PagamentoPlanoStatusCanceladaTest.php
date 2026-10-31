<?php

namespace Tests\Unit;

use App\Services\PagamentoPlanoService;
use App\Support\AcademyDateTime;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

/**
 * Caso real (matrícula 189): cancelada com crédito, o recálculo de status a reativava porque o período pago ainda
 * corria — o aluno ficava com o plano e com o crédito do cancelamento.
 */
class PagamentoPlanoStatusCanceladaTest extends TestCase
{
    use RequiresSqlite;

    private const T = 3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();
        $this->registrarFuncoesSqlite(['NOW' => [fn () => date('Y-m-d H:i:s'), 0], 'CURDATE' => [fn () => date('Y-m-d'), 0],
            'DATEDIFF' => [fn ($a, $b) => (int) ((strtotime((string) $a) - strtotime((string) $b)) / 86400), 2]]);

        Schema::create('status_matricula', fn (Blueprint $t) => [$t->increments('id'), $t->string('codigo')]);
        Schema::create('planos', fn (Blueprint $t) => [$t->increments('id'), $t->integer('duracao_dias')->nullable()]);
        Schema::create('plano_ciclos', fn (Blueprint $t) => [$t->increments('id'), $t->integer('meses')->nullable()]);
        Schema::create('matriculas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('plano_id');
            $t->integer('plano_ciclo_id')->nullable();
            $t->integer('status_id');
            $t->string('tipo_cobranca')->nullable();
            $t->decimal('valor', 10, 2);
            $t->date('data_inicio')->nullable();
            $t->date('data_vencimento')->nullable();
            $t->date('proxima_data_vencimento')->nullable();
            $t->date('data_cancelamento')->nullable();
            $t->timestamp('updated_at')->nullable();
        });
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('matricula_id');
            $t->integer('status_pagamento_id');
            $t->decimal('valor', 10, 2)->nullable();
            $t->date('data_vencimento')->nullable();
            $t->date('data_pagamento')->nullable();
            $t->text('observacoes')->nullable();
            $t->timestamp('updated_at')->nullable();
        });

        DB::table('status_matricula')->insert([['id' => 1, 'codigo' => 'ativa'], ['id' => 2, 'codigo' => 'vencida'], ['id' => 3, 'codigo' => 'cancelada']]);
        DB::table('planos')->insert(['id' => 8, 'duracao_dias' => 30]);
    }

    private function matricula(?string $dataCancelamento): void
    {
        $hoje = AcademyDateTime::todayStart();
        DB::table('matriculas')->insert([
            'id' => 189, 'tenant_id' => self::T, 'plano_id' => 8, 'status_id' => 3, 'tipo_cobranca' => 'recorrente', 'valor' => 120,
            'data_inicio' => (clone $hoje)->modify('-15 days')->format('Y-m-d'),
            'data_vencimento' => (clone $hoje)->modify('+15 days')->format('Y-m-d'),
            'data_cancelamento' => $dataCancelamento,
        ]);
        DB::table('pagamentos_plano')->insert([
            'id' => 1463, 'tenant_id' => self::T, 'matricula_id' => 189, 'status_pagamento_id' => 2, 'valor' => 120,
            'data_vencimento' => (clone $hoje)->modify('-15 days')->format('Y-m-d'),
            'data_pagamento' => (clone $hoje)->modify('-15 days')->format('Y-m-d'),
        ]);
    }

    private function recalcularStatus(): int
    {
        (new PagamentoPlanoService)->atualizarStatusMatricula(self::T, 189);

        return (int) DB::table('matriculas')->where('id', 189)->value('status_id');
    }

    public function test_cancelada_no_ciclo_atual_nao_e_reativada_pelo_recalculo(): void
    {
        $this->matricula(AcademyDateTime::todayStart()->modify('-2 days')->format('Y-m-d'));

        $this->assertSame(3, $this->recalcularStatus(), 'período pago correndo não desfaz o cancelamento');
    }

    public function test_cancelamento_de_ciclo_anterior_nao_trava_o_recalculo(): void
    {
        // Matrícula reaproveitada numa nova compra: data_cancelamento antiga, ciclo novo pago
        $this->matricula(AcademyDateTime::todayStart()->modify('-40 days')->format('Y-m-d'));

        $this->assertSame(1, $this->recalcularStatus());
    }
}
