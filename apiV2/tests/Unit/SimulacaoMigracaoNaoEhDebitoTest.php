<?php

namespace Tests\Unit;

use App\Repositories\AdminMatriculaRepository;
use App\Services\MatriculaMigracaoAptidaoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

/**
 * Caso real (parcela #1531, matrícula 189): simulação de migração abandonada, vencida, bloqueava a troca de
 * plano no painel como "parcela em atraso". Ela não é dívida: o plano atual segue até a simulação ser paga.
 */
class SimulacaoMigracaoNaoEhDebitoTest extends TestCase
{
    use RequiresSqlite;

    private const T = 3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();
        $this->registrarFuncoesSqlite(['CURDATE' => [fn () => date('Y-m-d'), 0]]);
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            foreach (['tenant_id', 'matricula_id', 'status_pagamento_id'] as $c) {
                $t->integer($c)->nullable();
            }
            $t->decimal('valor', 10, 2)->nullable();
            $t->date('data_vencimento')->nullable();
            $t->date('data_pagamento')->nullable();
            $t->text('observacoes')->nullable();
        });
        DB::table('pagamentos_plano')->insert([
            'id' => 1531, 'tenant_id' => self::T, 'matricula_id' => 189, 'status_pagamento_id' => 3, 'valor' => 53.16,
            'data_vencimento' => date('Y-m-d', strtotime('-3 days')),
            'observacoes' => 'Simulação de migração — o plano atual continua ativo até o pagamento. [MIGRACAO_SIMULADA plano_id=10 plano_ciclo_id=68]',
        ]);
    }

    public function test_simulacao_vencida_nao_bloqueia_a_troca_no_painel_nem_a_migracao(): void
    {
        $this->assertFalse(app(AdminMatriculaRepository::class)->temParcelaAbertaAtrasada(189, self::T));
        $this->assertFalse(app(MatriculaMigracaoAptidaoService::class)->temParcelaAtrasada(189, self::T));
    }

    public function test_mensalidade_vencida_continua_bloqueando(): void
    {
        DB::table('pagamentos_plano')->insert([
            'tenant_id' => self::T, 'matricula_id' => 189, 'status_pagamento_id' => 3, 'valor' => 120,
            'data_vencimento' => date('Y-m-d', strtotime('-3 days')), 'observacoes' => 'Aguardando pagamento',
        ]);

        $this->assertTrue(app(AdminMatriculaRepository::class)->temParcelaAbertaAtrasada(189, self::T));
    }
}
