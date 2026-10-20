<?php

namespace Tests\Unit;

use App\Services\CobrancaAnteriorService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class CobrancaAnteriorServiceTest extends TestCase
{
    use RequiresSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();
        Schema::create('pagamentos_pix', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('tenant_id');
            $t->unsignedInteger('matricula_id');
            $t->string('payment_id');
            $t->string('status')->nullable();
            $t->timestamp('updated_at')->nullable();
        });
        Schema::create('assinaturas', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('tenant_id');
            $t->unsignedInteger('matricula_id');
            $t->string('gateway_preference_id')->nullable();
            $t->string('status_gateway')->nullable();
        });
        // Matrícula 385: Pix de R$ 70 do mensal ficou pendente; o de R$ 120 foi pago
        DB::table('pagamentos_pix')->insert([
            ['tenant_id' => 3, 'matricula_id' => 385, 'payment_id' => 'pay_70', 'status' => 'pending'],
            ['tenant_id' => 3, 'matricula_id' => 385, 'payment_id' => 'pay_120', 'status' => 'approved'],
        ]);
    }

    private function asaasFake(array &$cancelados, array $status = []): CobrancaAnteriorService
    {
        $gw = new class($cancelados, $status)
        {
            public function __construct(private array &$cancelados, private array $status) {}

            public function obterPagamento(string $id): array
            {
                return ['id' => $id, 'status' => $this->status[$id] ?? 'OVERDUE', 'value' => 70, 'billingType' => 'PIX'];
            }

            public function cancelarCobranca(string $id): bool
            {
                if ($id === 'pay_falha') {
                    throw new \RuntimeException('Asaas: indisponível');
                }
                $this->cancelados[] = $id;

                return true;
            }
        };

        return new CobrancaAnteriorService(fn () => $gw);
    }

    public function test_lista_so_as_cobrancas_ainda_abertas(): void
    {
        $this->assertSame(['pay_70'], (new CobrancaAnteriorService)->idsEmAberto(3, 385));
    }

    public function test_cancela_a_anterior_e_mantem_a_nova(): void
    {
        $cancelados = [];
        $res = $this->asaasFake($cancelados)->cancelar(3, ['pay_70', 'pay_novo'], ['pay_novo']);

        $this->assertSame(['pay_70'], $cancelados);
        $this->assertSame('cancelled', DB::table('pagamentos_pix')->where('payment_id', 'pay_70')->value('status'));
        $this->assertSame('Sua cobrança anterior de R$ 70,00 (Pix) foi cancelada e substituída por esta.', CobrancaAnteriorService::aviso($res['canceladas']));
    }

    public function test_nao_cancela_cobranca_ja_paga_no_provedor(): void
    {
        $cancelados = [];
        $res = $this->asaasFake($cancelados, ['pay_70' => 'RECEIVED'])->cancelar(3, ['pay_70']);

        $this->assertSame([], $cancelados);
        $this->assertSame([], $res['canceladas']);
    }

    public function test_falha_no_provedor_nao_quebra_e_e_reportada(): void
    {
        $cancelados = [];
        $res = $this->asaasFake($cancelados)->cancelar(3, ['pay_falha']);

        $this->assertSame('pay_falha', $res['falhas'][0]['payment_id']);
        $this->assertNull(CobrancaAnteriorService::aviso($res['canceladas']));
    }
}
