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
        Schema::create('matriculas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->date('data_inicio')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('pagamentos_plano', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('matricula_id');
            $table->unsignedInteger('status_pagamento_id');
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
        ]);
        DB::table('tipos_baixa')->insert(['id' => 4, 'nome' => 'Integração']);
        DB::table('assinatura_status')->insert(['id' => 1, 'codigo' => 'paga']);
        DB::table('matriculas')->insert(['id' => 189, 'tenant_id' => 3, 'data_inicio' => null]);
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
