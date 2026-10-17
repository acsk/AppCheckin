<?php

namespace Tests\Unit;

use App\Services\Asaas\AsaasPagamentoSync;
use App\Support\AsaasPagamentoStatus;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class AsaasPagamentoStatusTest extends TestCase
{
    use RequiresSqlite;

    public function test_cartao_confirmada_com_data(): void
    {
        $snap = AsaasPagamentoStatus::snapshot([
            'status' => 'CONFIRMED',
            'billingType' => 'CREDIT_CARD',
            'confirmedDate' => '2026-10-16',
        ]);

        $this->assertSame('CONFIRMED', $snap['status']);
        $this->assertSame('Confirmada', $snap['status_label']);
        $this->assertSame('2026-10-16', $snap['data']);
        $this->assertSame('16/10/2026', $snap['data_formatada']);
        $this->assertSame(
            'Pagamento Confirmada em 16/10/2026 no Asaas. Aguarde a ativação da matrícula.',
            AsaasPagamentoStatus::mensagem($snap, false),
        );
        $this->assertSame('PAYMENT_CONFIRMED', AsaasPagamentoStatus::eventoWebhook('CONFIRMED'));
    }

    public function test_tarifa_provedor_value_menos_net_value(): void
    {
        $tarifa = AsaasPagamentoStatus::tarifaProvedor([
            'value' => 20,
            'netValue' => 18.01,
        ]);

        $this->assertSame(20.0, $tarifa['valor']);
        $this->assertSame(18.01, $tarifa['valor_liquido']);
        $this->assertSame(1.99, $tarifa['desconto_provedor']);
    }

    public function test_antecipacao_em_analise_substitui_liquido_da_cobranca(): void
    {
        $payment = AsaasPagamentoStatus::aplicarAntecipacao(
            ['value' => 54.18, 'netValue' => 52.08],
            ['status' => 'PENDING', 'netValue' => 51.39, 'fee' => 0.69],
        );
        $tarifa = AsaasPagamentoStatus::tarifaProvedor($payment);

        $this->assertSame(51.39, $tarifa['valor_liquido']);
        $this->assertEqualsWithDelta(2.79, $tarifa['desconto_provedor'], 0.001);
    }

    public function test_antecipacao_negada_mantem_liquido_da_cobranca(): void
    {
        $payment = AsaasPagamentoStatus::aplicarAntecipacao(
            ['value' => 54.18, 'netValue' => 52.08],
            ['status' => 'DENIED', 'netValue' => 51.39],
        );
        $tarifa = AsaasPagamentoStatus::tarifaProvedor($payment);

        $this->assertSame(52.08, $tarifa['valor_liquido']);
        $this->assertSame(2.10, $tarifa['desconto_provedor']);
    }

    public function test_pix_recebido(): void
    {
        $snap = AsaasPagamentoStatus::snapshot([
            'status' => 'RECEIVED',
            'billingType' => 'PIX',
            'paymentDate' => '2026-10-16',
        ]);

        $this->assertSame('Recebido', $snap['status_label']);
        $this->assertTrue(AsaasPagamentoStatus::pago('RECEIVED'));
    }

    public function test_payment_id_usa_assinatura_quando_nao_ha_pix(): void
    {
        $this->requireSqlite();
        Schema::create('pagamentos_pix', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('matricula_id');
            $table->string('payment_id');
        });
        Schema::create('assinaturas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('matricula_id');
            $table->string('gateway_preference_id')->nullable();
        });
        DB::table('assinaturas')->insert([
            'tenant_id' => 3,
            'matricula_id' => 189,
            'gateway_preference_id' => 'pay_cartao_abc',
        ]);

        $id = (new AsaasPagamentoSync())->paymentIdDaMatricula(3, 189);

        $this->assertSame('pay_cartao_abc', $id);
    }

    public function test_payment_ids_priorizam_cartao_atual_sobre_pix_antigo(): void
    {
        $this->requireSqlite();
        Schema::create('pagamentos_pix', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('matricula_id');
            $table->string('payment_id');
        });
        Schema::create('assinaturas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('matricula_id');
            $table->string('gateway_preference_id')->nullable();
        });
        DB::table('pagamentos_pix')->insert([
            'tenant_id' => 3,
            'matricula_id' => 189,
            'payment_id' => 'pay_pix_antigo',
        ]);
        DB::table('assinaturas')->insert([
            'tenant_id' => 3,
            'matricula_id' => 189,
            'gateway_preference_id' => 'pay_mbavvt_cartao',
        ]);

        $ids = (new AsaasPagamentoSync())->paymentIdsDaMatricula(3, 189);

        $this->assertSame(['pay_mbavvt_cartao', 'pay_pix_antigo'], $ids);
        $this->assertSame('pay_mbavvt_cartao', (new AsaasPagamentoSync())->paymentIdDaMatricula(3, 189));
    }
}
