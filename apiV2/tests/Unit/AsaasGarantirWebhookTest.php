<?php

namespace Tests\Unit;

use App\Services\Asaas\AsaasService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class AsaasGarantirWebhookTest extends TestCase
{
    use RequiresSqlite;

    private const URL = 'https://api.appcheckin.test/v2/webhooks/asaas';

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();

        config([
            'app.url' => 'https://api.appcheckin.test',
            'appcheckin.asaas.sandbox_url' => 'https://sandbox.asaas.test/v3',
        ]);
        Schema::create('tenant_payment_credentials', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('tenant_id');
            $t->string('provider');
            $t->string('environment')->nullable();
            $t->string('access_token_test')->nullable();
            $t->string('access_token_prod')->nullable();
            $t->string('webhook_secret')->nullable();
        });
        DB::table('tenant_payment_credentials')->insert([
            'tenant_id' => 3, 'provider' => 'asaas', 'environment' => 'sandbox', 'access_token_test' => '$aact_hmlg_teste',
        ]);
    }

    /** @param  list<string>  $eventos */
    private function listaWebhooks(array $eventos, bool $interrompido): array
    {
        return ['data' => [[
            'id' => 'wh_1', 'url' => self::URL, 'enabled' => true, 'interrupted' => $interrompido, 'events' => $eventos,
        ]]];
    }

    public function test_eventos_enviados_sao_todos_aceitos_pelo_asaas(): void
    {
        $eventos = (fn () => $this->eventosWebhook())->call(new AsaasService(3));

        // O Asaas respondeu 400 "evento inválido" para estes (log de produção de 30/10/2026)
        foreach (['PAYMENT_RECEIVED_IN_CASH', 'ANTICIPATION_PENDING', 'ANTICIPATION_SCHEDULED', 'ANTICIPATION_CREDITED', 'ANTICIPATION_DENIED', 'ANTICIPATION_CANCELLED'] as $invalido) {
            $this->assertNotContains($invalido, $eventos);
        }
        $this->assertContains('PAYMENT_CONFIRMED', $eventos);
        $this->assertContains('PAYMENT_RECEIVED', $eventos);
    }

    public function test_fila_interrompida_e_religada_mesmo_se_a_lista_de_eventos_for_recusada(): void
    {
        Http::fake(function (Request $r) {
            if ($r->method() === 'GET') {
                return Http::response($this->listaWebhooks(['PAYMENT_CONFIRMED'], true));
            }
            if (isset($r->data()['events'])) {
                return Http::response(['errors' => [['code' => 'invalid_object', 'description' => 'O evento [X] é inválido.']]], 400);
            }

            return Http::response(['id' => 'wh_1']);
        });

        (new AsaasService(3))->garantirWebhook();

        $puts = Http::recorded(fn (Request $r) => $r->method() === 'PUT')->map(fn ($par) => $par[0]->data())->values()->all();
        $this->assertCount(2, $puts);
        $this->assertSame(['enabled' => true, 'interrupted' => false], $puts[1]);
    }

    public function test_webhook_completo_e_ativo_nao_e_alterado(): void
    {
        $eventos = (fn () => $this->eventosWebhook())->call(new AsaasService(3));
        Http::fake(fn (Request $r) => Http::response($this->listaWebhooks($eventos, false)));

        (new AsaasService(3))->garantirWebhook();

        Http::assertSentCount(1);
    }
}
