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
}
