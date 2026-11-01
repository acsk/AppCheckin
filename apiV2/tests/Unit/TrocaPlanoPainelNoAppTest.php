<?php

namespace Tests\Unit;

use App\Services\Admin\AdminMatriculaService;
use App\Services\Mobile\MobileAssinaturaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

/**
 * Caso real (matrícula 189): o admin trocou o plano no painel (parcela #1546 de R$ 134,19 em aberto) e o app
 * seguia mostrando o plano e a cobrança da simulação antiga, sem "Pagar agora".
 */
class TrocaPlanoPainelNoAppTest extends TestCase
{
    use RequiresSqlite;

    private function assinatura(array $pagamentos): array
    {
        return [
            'id' => 227, 'matricula_id' => 189, 'modalidade_id' => 3, 'status' => ['codigo' => 'pendente'],
            'cancelamento' => ['cancelado_por_codigo' => null], 'pode_pagar' => false, 'inicio_ciclo' => '2026-10-31',
            'pagamentos' => $pagamentos,
        ];
    }

    private function sanitizar(array $assinaturas): array
    {
        $m = new \ReflectionMethod(MobileAssinaturaService::class, 'sanitizarAssinaturasFinanceiroMobile');

        return $m->invoke((new \ReflectionClass(MobileAssinaturaService::class))->newInstanceWithoutConstructor(), $assinaturas);
    }

    public function test_pendente_com_parcela_nova_e_pagos_do_plano_anterior_pode_pagar(): void
    {
        $r = $this->sanitizar([$this->assinatura([
            ['id' => 1463, 'status_pagamento_id' => 2, 'status' => 'Pago', 'data_pagamento' => '2026-10-16', 'data_vencimento' => '2026-10-16'],
            ['id' => 1546, 'status_pagamento_id' => 1, 'status' => 'Aguardando', 'data_pagamento' => null, 'data_vencimento' => '2026-10-31'],
        ])]);

        $this->assertCount(1, $r);
        $this->assertTrue($r[0]['pode_pagar']);
        $this->assertSame([1546, 1463], array_column($r[0]['pagamentos'], 'id'), 'a parcela nova aparece primeiro');
    }

    public function test_pendente_com_mensalidade_vencida_e_pagamento_do_mes_anterior_pode_pagar(): void
    {
        $assinatura = $this->assinatura([
            ['id' => 1313, 'status_pagamento_id' => 2, 'status' => 'Pago', 'data_pagamento' => '2026-09-29', 'data_vencimento' => '2026-09-29'],
            ['id' => 1314, 'status_pagamento_id' => 3, 'status' => 'Atrasado', 'data_pagamento' => null, 'data_vencimento' => '2026-10-29'],
        ]);
        $assinatura['inicio_ciclo'] = '2026-09-29';

        $r = $this->sanitizar([$assinatura]);

        $this->assertCount(1, $r);
        $this->assertTrue($r[0]['pode_pagar']);
        $this->assertSame([1314, 1313], array_column($r[0]['pagamentos'], 'id'));
    }

    public function test_pendente_com_parcela_vencida_sem_historico_pode_pagar(): void
    {
        $r = $this->sanitizar([$this->assinatura([
            ['id' => 1701, 'status_pagamento_id' => 3, 'status' => 'Atrasado', 'data_pagamento' => null, 'data_vencimento' => '2026-10-29'],
        ])]);

        $this->assertCount(1, $r);
        $this->assertTrue($r[0]['pode_pagar']);
        $this->assertSame([1701], array_column($r[0]['pagamentos'], 'id'));
    }

    public function test_pendente_com_pago_do_proprio_ciclo_segue_aguardando_ativacao(): void
    {
        $r = $this->sanitizar([$this->assinatura([
            ['id' => 1600, 'status_pagamento_id' => 2, 'status' => 'Pago', 'data_pagamento' => '2026-10-31', 'data_vencimento' => '2026-10-31'],
            ['id' => 1601, 'status_pagamento_id' => 1, 'status' => 'Aguardando', 'data_pagamento' => null, 'data_vencimento' => '2026-10-31'],
        ])]);

        $this->assertFalse($r[0]['pode_pagar']);
        $this->assertSame([1600], array_column($r[0]['pagamentos'], 'id'));
    }

    /** pay_ytve63…: PIX da simulação #1531, cancelada na troca. Reaproveitá-lo devolvia um PIX vencido de R$ 53,16. */
    public function test_pix_de_cobranca_cancelada_nao_e_reaproveitado_e_parcela_volta_ao_valor_do_plano(): void
    {
        $this->requireSqlite();
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            foreach (['tenant_id', 'matricula_id', 'status_pagamento_id'] as $c) {
                $t->integer($c)->nullable();
            }
            $t->text('observacoes')->nullable();
        });
        DB::table('pagamentos_plano')->insert(['tenant_id' => 3, 'matricula_id' => 189, 'status_pagamento_id' => 4,
            'observacoes' => '[MIGRACAO_SIMULADA plano_id=10 plano_ciclo_id=68] [Asaas pay_ytve63gaaviktizu] [Cancelado por alteração de plano]']);
        $svc = (new \ReflectionClass(\App\Services\Mobile\MobilePagamentoService::class))->newInstanceWithoutConstructor();
        $cancelada = new \ReflectionMethod($svc, 'pixDeCobrancaCancelada');
        $base = new \ReflectionMethod($svc, 'valorBaseParcela');

        $this->assertTrue($cancelada->invoke($svc, 3, 189, 'pay_ytve63gaaviktizu'));
        $this->assertFalse($cancelada->invoke($svc, 3, 189, 'pay_novo'));
        // Tentativa no cartão embutiu a tarifa: PIX e nova tentativa partem do valor do plano (sem taxa sobre taxa)
        $this->assertSame(134.19, $base->invoke($svc, (object) ['valor' => 151.33, 'tarifa_cartao' => 5.12, 'sobretaxa_antecipacao' => 12.02]));
    }

    public function test_alterar_plano_atualiza_a_assinatura_que_o_app_le(): void
    {
        $this->requireSqlite();
        Schema::create('assinatura_status', fn (Blueprint $t) => [$t->increments('id'), $t->string('codigo')]);
        Schema::create('plano_ciclos', fn (Blueprint $t) => [$t->increments('id'), $t->integer('assinatura_frequencia_id')->nullable()]);
        Schema::create('assinaturas', function (Blueprint $t) {
            $t->increments('id');
            foreach (['tenant_id', 'matricula_id', 'plano_id', 'status_id', 'frequencia_id'] as $c) {
                $t->integer($c)->nullable();
            }
            $t->decimal('valor', 10, 2)->nullable();
            foreach (['gateway_preference_id', 'payment_url', 'status_gateway', 'data_inicio', 'proxima_cobranca', 'atualizado_em'] as $c) {
                $t->string($c)->nullable();
            }
        });
        DB::table('assinatura_status')->insert([['id' => 1, 'codigo' => 'ativa'], ['id' => 2, 'codigo' => 'pendente']]);
        DB::table('plano_ciclos')->insert(['id' => 49, 'assinatura_frequencia_id' => 9]);
        DB::table('assinaturas')->insert(['id' => 227, 'tenant_id' => 3, 'matricula_id' => 189, 'plano_id' => 10, 'status_id' => 1, 'frequencia_id' => 4,
            'valor' => 53.16, 'gateway_preference_id' => 'pay_ytve63gaaviktizu', 'payment_url' => 'https://asaas/i/ytve63gaaviktizu', 'status_gateway' => 'OVERDUE']);

        $m = new \ReflectionMethod(AdminMatriculaService::class, 'sincronizarAssinaturaAposAlterarPlano');
        $m->invoke((new \ReflectionClass(AdminMatriculaService::class))->newInstanceWithoutConstructor(), 3, 189, 7, 49, 134.19, '2026-10-31', false);

        $a = DB::table('assinaturas')->find(227);
        $this->assertSame(7, (int) $a->plano_id);
        $this->assertEqualsWithDelta(134.19, (float) $a->valor, 0.001);
        $this->assertSame(2, (int) $a->status_id, 'pendente até pagar a parcela nova');
        $this->assertSame(9, (int) $a->frequencia_id);
        $this->assertNull($a->payment_url, 'a cobrança da simulação antiga sai; o app gera o PIX da parcela aberta');
        $this->assertNull($a->gateway_preference_id);
    }
}
