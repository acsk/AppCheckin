<?php

namespace Tests\Unit;

use App\Services\Asaas\AsaasNaoEncontradoException;
use App\Services\Asaas\AsaasPagamentoSync;
use App\Services\Recorrencia\RecorrenciaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class AsaasSincronizarPendentesTest extends TestCase
{
    use RequiresSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();

        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('tenant_id');
            $t->unsignedInteger('matricula_id');
            $t->unsignedInteger('status_pagamento_id');
            $t->date('data_pagamento')->nullable();
        });
        Schema::create('tenant_payment_credentials', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('tenant_id');
            $t->string('provider');
        });
        Schema::create('assinaturas', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('tenant_id');
            $t->unsignedInteger('matricula_id');
            $t->string('gateway_preference_id')->nullable();
            $t->string('status_gateway')->nullable();
            $t->timestamp('atualizado_em')->nullable();
        });
        Schema::create('pagamentos_pix', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('tenant_id');
            $t->unsignedInteger('matricula_id');
            $t->string('payment_id');
            $t->string('status')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        DB::table('tenant_payment_credentials')->insert(['tenant_id' => 3, 'provider' => 'asaas']);
        foreach ([10, 11] as $matricula) {
            DB::table('pagamentos_plano')->insert(['tenant_id' => 3, 'matricula_id' => $matricula, 'status_pagamento_id' => 1]);
        }
        DB::table('pagamentos_pix')->insert(['tenant_id' => 3, 'matricula_id' => 10, 'payment_id' => 'pay_apagado', 'status' => 'pending', 'created_at' => now()]);
        DB::table('assinaturas')->insert(['tenant_id' => 3, 'matricula_id' => 11, 'gateway_preference_id' => 'pay_removido', 'status_gateway' => 'pending', 'atualizado_em' => now()]);
    }

    public function test_cobranca_removida_no_asaas_nao_conta_como_erro_e_sai_da_conferencia(): void
    {
        $chamadas = [];
        $obter = function (int $tenant, string $id) use (&$chamadas): array {
            $chamadas[] = $id;
            if ($id === 'pay_apagado') {
                throw new AsaasNaoEncontradoException('Asaas: Cobrança não encontrada.');
            }

            return ['id' => $id, 'status' => 'PENDING', 'deleted' => true];
        };

        $res = (new AsaasPagamentoSync())->sincronizarPendentes(null, 200, 60, $obter);

        $this->assertSame(2, $res['conferidas']);
        $this->assertSame(2, $res['removidas']);
        $this->assertSame(0, $res['erros']);
        // Registros locais intactos
        $this->assertSame('pending', DB::table('pagamentos_pix')->value('status'));
        $this->assertSame('pending', DB::table('assinaturas')->value('status_gateway'));

        $segunda = (new AsaasPagamentoSync())->sincronizarPendentes(null, 200, 60, $obter);
        $this->assertSame(0, $segunda['conferidas']);
        $this->assertCount(2, $chamadas);
    }

    public function test_erro_de_verdade_continua_contando_e_traz_a_mensagem(): void
    {
        $obter = fn (int $tenant, string $id): array => throw new \RuntimeException('Asaas: A chave de API informada não pertence a este ambiente');

        $res = (new AsaasPagamentoSync())->sincronizarPendentes(null, 200, 60, $obter);

        $this->assertSame(2, $res['erros']);
        $this->assertSame(['Asaas: A chave de API informada não pertence a este ambiente'], $res['mensagens']);
    }

    public function test_mensalidade_de_assinatura_paga_no_asaas_e_aplicada_pela_recorrencia(): void
    {
        Schema::create('contratos_recorrencia', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('tenant_id');
            $t->unsignedInteger('matricula_id');
        });
        Schema::create('recorrencia_cobrancas', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('tenant_id');
            $t->unsignedInteger('contrato_id');
            $t->date('vencimento');
            $t->string('gateway_payment_id')->nullable();
            $t->string('status', 12);
        });
        DB::table('pagamentos_pix')->delete();
        DB::table('assinaturas')->delete();
        DB::table('contratos_recorrencia')->insert(['id' => 4, 'tenant_id' => 3, 'matricula_id' => 317]);
        DB::table('recorrencia_cobrancas')->insert([
            ['tenant_id' => 3, 'contrato_id' => 4, 'vencimento' => now()->toDateString(), 'gateway_payment_id' => 'pay_mensal', 'status' => 'pendente'],
            // Ainda não venceu: o cartão nem foi cobrado, não consulta
            ['tenant_id' => 3, 'contrato_id' => 4, 'vencimento' => now()->addMonth()->toDateString(), 'gateway_payment_id' => 'pay_futura', 'status' => 'pendente'],
            ['tenant_id' => 3, 'contrato_id' => 4, 'vencimento' => now()->subMonth()->toDateString(), 'gateway_payment_id' => 'pay_antiga', 'status' => 'paga'],
        ]);

        $recorrencia = \Mockery::mock(RecorrenciaService::class);
        $recorrencia->shouldReceive('processarPagamento')->once()
            ->with(3, 4, \Mockery::on(fn ($p) => $p['id'] === 'pay_mensal'), 'PAYMENT_CONFIRMED', true)
            ->andReturn('paga');
        $this->app->instance(RecorrenciaService::class, $recorrencia);

        $chamadas = [];
        $obter = function (int $tenant, string $id) use (&$chamadas): array {
            $chamadas[] = $id;

            return ['id' => $id, 'status' => 'CONFIRMED', 'billingType' => 'CREDIT_CARD', 'subscription' => 'sub_x'];
        };

        $res = (new AsaasPagamentoSync())->sincronizarPendentes(null, 200, 60, $obter);

        $this->assertSame(['pay_mensal'], $chamadas);
        $this->assertSame(1, $res['conferidas']);
        $this->assertSame(1, $res['pagas']);
    }
}
