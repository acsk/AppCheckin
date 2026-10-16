<?php

namespace Tests\Unit;

use App\Services\Asaas\AsaasWebhookService;
use App\Services\EncryptionService;
use App\Services\PagamentoPlanoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class AsaasWebhookServiceTest extends TestCase
{
    use RequiresSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();

        Schema::create('tenant_payment_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->string('provider');
            $table->string('webhook_secret')->nullable();
        });
        Schema::create('pagamentos_pix', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('matricula_id');
            $table->string('payment_id');
            $table->string('status')->nullable();
        });
        Schema::create('assinaturas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->nullable();
            $table->unsignedInteger('matricula_id')->nullable();
            $table->string('gateway_preference_id')->nullable();
            $table->string('status_gateway')->nullable();
            $table->unsignedInteger('status_id')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });
        Schema::create('status_matricula', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
        });
        Schema::create('matriculas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('status_id')->nullable();
            $table->date('data_inicio')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('pagamentos_plano', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('matricula_id');
            $table->unsignedInteger('status_pagamento_id');
            $table->decimal('valor', 10, 2)->nullable();
            $table->decimal('valor_liquido', 10, 2)->nullable();
            $table->decimal('desconto_provedor', 10, 2)->nullable();
            $table->date('data_pagamento')->nullable();
            $table->date('data_vencimento')->nullable();
            $table->unsignedInteger('forma_pagamento_id')->nullable();
            $table->unsignedInteger('tipo_baixa_id')->nullable();
            $table->string('observacoes')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('formas_pagamento', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
        });
        Schema::create('tipos_baixa', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
        });
        Schema::create('assinatura_status', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
        });

        DB::table('formas_pagamento')->insert([
            ['id' => 1, 'nome' => 'pix'],
            ['id' => 2, 'nome' => 'dinheiro'],
            ['id' => 3, 'nome' => 'credito a vista'],
        ]);
        DB::table('tipos_baixa')->insert(['id' => 4, 'nome' => 'Integração']);
        DB::table('assinatura_status')->insert(['id' => 1, 'codigo' => 'paga']);
        DB::table('status_matricula')->insert([
            ['id' => 1, 'codigo' => 'pendente'],
            ['id' => 2, 'codigo' => 'ativa'],
        ]);
        DB::table('matriculas')->insert(['id' => 189, 'tenant_id' => 3, 'status_id' => 1, 'data_inicio' => null]);
        DB::table('pagamentos_pix')->insert([
            'tenant_id' => 3,
            'matricula_id' => 189,
            'payment_id' => 'pay_xlm9exz869gjx520',
            'status' => 'pending',
        ]);
        DB::table('pagamentos_plano')->insert([
            'tenant_id' => 3,
            'matricula_id' => 189,
            'status_pagamento_id' => 1,
            'valor' => 70,
            'data_pagamento' => null,
            'data_vencimento' => '2026-10-16',
            'observacoes' => 'PIX',
        ]);
        DB::table('assinaturas')->insert([
            'tenant_id' => 3,
            'matricula_id' => 189,
            'gateway_preference_id' => 'pay_xlm9exz869gjx520',
            'status_gateway' => 'pending',
        ]);
        DB::table('tenant_payment_credentials')->insert([
            'tenant_id' => 3,
            'provider' => 'asaas',
            'webhook_secret' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_baixa_manual_received_in_cash_atualiza_parcela_e_matricula(): void
    {
        $pagamentos = Mockery::mock(PagamentoPlanoService::class);
        $pagamentos->shouldReceive('atualizarStatusMatricula')->once()->with(3, 189);

        $service = new AsaasWebhookService($pagamentos, new EncryptionService());
        $result = $service->processar([
            'id' => 'evt_1',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_xlm9exz869gjx520',
                'status' => 'RECEIVED_IN_CASH',
                'billingType' => 'RECEIVED_IN_CASH',
                'externalReference' => 'MAT-189-17921701877f63f5e5',
                'paymentDate' => '2026-10-16',
                'value' => 20,
                'netValue' => 18.01,
            ],
        ], null);

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['body']['success']);
        $this->assertSame(189, $result['body']['matricula_id']);
        $this->assertSame('approved', DB::table('pagamentos_pix')->value('status'));
        $parcela = DB::table('pagamentos_plano')->first();
        $this->assertSame(2, (int) $parcela->status_pagamento_id);
        $this->assertSame('2026-10-16', $parcela->data_pagamento);
        $this->assertSame(2, (int) $parcela->forma_pagamento_id);
        $this->assertEquals(18.01, (float) $parcela->valor_liquido);
        $this->assertEquals(1.99, (float) $parcela->desconto_provedor);
        $this->assertStringContainsString('desconto_provedor R$ 1,99', (string) $parcela->observacoes);
        $this->assertSame('approved', DB::table('assinaturas')->value('status_gateway'));
        $this->assertSame('2026-10-16', DB::table('matriculas')->value('data_inicio'));
    }

    public function test_payment_updated_com_status_received_e_pago(): void
    {
        $pagamentos = Mockery::mock(PagamentoPlanoService::class);
        $pagamentos->shouldReceive('atualizarStatusMatricula')->once()->with(3, 189);

        $service = new AsaasWebhookService($pagamentos, new EncryptionService());
        $result = $service->processar([
            'event' => 'PAYMENT_UPDATED',
            'payment' => [
                'id' => 'pay_xlm9exz869gjx520',
                'status' => 'RECEIVED',
                'billingType' => 'PIX',
                'externalReference' => 'MAT-189-abc',
                'paymentDate' => '2026-10-16',
            ],
        ], null);

        $this->assertSame(200, $result['status']);
        $this->assertSame(189, $result['body']['matricula_id']);
        $this->assertSame(1, (int) DB::table('pagamentos_plano')->value('forma_pagamento_id'));
    }

    public function test_pending_updated_e_ignorado(): void
    {
        $pagamentos = Mockery::mock(PagamentoPlanoService::class);
        $pagamentos->shouldNotReceive('atualizarStatusMatricula');

        $service = new AsaasWebhookService($pagamentos, new EncryptionService());
        $result = $service->processar([
            'event' => 'PAYMENT_UPDATED',
            'payment' => [
                'id' => 'pay_xlm9exz869gjx520',
                'status' => 'PENDING',
                'billingType' => 'PIX',
                'externalReference' => 'MAT-189-abc',
            ],
        ], null);

        $this->assertSame(200, $result['status']);
        $this->assertSame('PAYMENT_UPDATED', $result['body']['ignored']);
        $this->assertSame('pending', DB::table('pagamentos_pix')->value('status'));
    }

    public function test_pix_ja_approved_ainda_baixa_parcela_aberta(): void
    {
        DB::table('pagamentos_pix')->update(['status' => 'approved']);
        DB::table('pagamentos_plano')->where('matricula_id', 189)->update([
            'status_pagamento_id' => 1,
            'valor' => 70,
            'data_pagamento' => null,
            'observacoes' => 'Aguardando pagamento via Asaas',
        ]);

        $pagamentos = Mockery::mock(PagamentoPlanoService::class);
        $pagamentos->shouldReceive('atualizarStatusMatricula')->once()->with(3, 189);

        $service = new AsaasWebhookService($pagamentos, new EncryptionService());
        $result = $service->processar([
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_xlm9exz869gjx520',
                'status' => 'RECEIVED',
                'billingType' => 'PIX',
                'externalReference' => 'MAT-189-abc',
                'paymentDate' => '2026-10-16',
                'value' => 70,
            ],
        ], null);

        $this->assertSame(200, $result['status']);
        $this->assertArrayNotHasKey('ignored', $result['body']);
        $parcela = DB::table('pagamentos_plano')->first();
        $this->assertSame(2, (int) $parcela->status_pagamento_id);
        $this->assertSame('2026-10-16', $parcela->data_pagamento);
        $this->assertStringContainsString('Asaas pay_xlm9exz869gjx520', (string) $parcela->observacoes);
        $this->assertSame(2, (int) DB::table('matriculas')->value('status_id'));
    }

    public function test_pix_antigo_nao_sobrescreve_preference_id_do_cartao(): void
    {
        DB::table('assinaturas')->where('matricula_id', 189)->update([
            'gateway_preference_id' => 'pay_cartao_novo',
            'status_gateway' => 'pending',
        ]);
        DB::table('pagamentos_plano')->insert([
            'tenant_id' => 3,
            'matricula_id' => 189,
            'status_pagamento_id' => 1,
            'valor' => 50,
            'data_pagamento' => null,
            'data_vencimento' => '2026-10-16',
            'observacoes' => 'Aguardando pagamento via Asaas',
        ]);
        DB::table('pagamentos_plano')->where('valor', 70)->update([
            'status_pagamento_id' => 2,
            'data_pagamento' => '2026-10-16',
            'observacoes' => 'Aguardando pagamento via Asaas [Asaas pay_xlm9exz869gjx520]',
        ]);

        $pagamentos = Mockery::mock(PagamentoPlanoService::class);
        $pagamentos->shouldReceive('atualizarStatusMatricula')->once()->with(3, 189);

        $service = new AsaasWebhookService($pagamentos, new EncryptionService());
        $service->processar([
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_xlm9exz869gjx520',
                'status' => 'RECEIVED',
                'billingType' => 'PIX',
                'externalReference' => 'MAT-189-abc',
                'paymentDate' => '2026-10-16',
                'value' => 70,
            ],
        ], null);

        $this->assertSame('pay_cartao_novo', DB::table('assinaturas')->value('gateway_preference_id'));
        $this->assertSame(1, (int) DB::table('pagamentos_plano')->where('valor', 50)->value('status_pagamento_id'));
    }

    public function test_cartao_confirmed_baixa_sem_pix_e_sem_receber_cobranca(): void
    {
        DB::table('pagamentos_pix')->delete();
        DB::table('assinaturas')->where('matricula_id', 189)->update([
            'gateway_preference_id' => 'pay_cartao_confirmed',
            'status_gateway' => 'pending',
        ]);
        DB::table('pagamentos_plano')->where('matricula_id', 189)->update([
            'status_pagamento_id' => 1,
            'data_pagamento' => null,
            'forma_pagamento_id' => null,
        ]);

        $pagamentos = Mockery::mock(PagamentoPlanoService::class);
        $pagamentos->shouldReceive('atualizarStatusMatricula')->once()->with(3, 189);

        $service = new AsaasWebhookService($pagamentos, new EncryptionService());
        $result = $service->processar([
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'pay_cartao_confirmed',
                'status' => 'CONFIRMED',
                'billingType' => 'CREDIT_CARD',
                'externalReference' => 'MAT-189-abc',
                'confirmedDate' => '2026-10-16',
            ],
        ], null);

        $this->assertSame(200, $result['status']);
        $this->assertSame(189, $result['body']['matricula_id']);
        $parcela = DB::table('pagamentos_plano')->first();
        $this->assertSame(2, (int) $parcela->status_pagamento_id);
        $this->assertSame('2026-10-16', $parcela->data_pagamento);
        $this->assertSame(3, (int) $parcela->forma_pagamento_id);
        $this->assertSame('approved', DB::table('assinaturas')->value('status_gateway'));
    }

    public function test_payload_invalido_retorna_400(): void
    {
        $service = new AsaasWebhookService(
            Mockery::mock(PagamentoPlanoService::class),
            new EncryptionService(),
        );

        $result = $service->processar(['event' => 'PAYMENT_RECEIVED'], null);

        $this->assertSame(400, $result['status']);
    }
}
