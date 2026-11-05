<?php

namespace Tests\Feature;

use App\Support\ParcelasPeriodo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

/** Caso da matrícula #358: renovação (#1229) e "Primeiro pagamento" (#1588) no mesmo vencimento. */
class ParcelasPeriodoTest extends TestCase
{
    use RequiresSqlite;

    private const T = 3;

    private const M = 358;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();

        Schema::create('status_pagamento', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
        });
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('matricula_id');
            $t->decimal('valor', 10, 2);
            $t->date('data_vencimento');
            $t->integer('status_pagamento_id');
        });
        foreach ([1 => 'Aguardando', 2 => 'Pago', 3 => 'Atrasado', 4 => 'Cancelado'] as $id => $nome) {
            DB::table('status_pagamento')->insert(['id' => $id, 'nome' => $nome]);
        }
    }

    private function parcela(int $id, float $valor, string $venc, int $status): void
    {
        DB::table('pagamentos_plano')->insert([
            'id' => $id, 'tenant_id' => self::T, 'matricula_id' => self::M,
            'valor' => $valor, 'data_vencimento' => $venc, 'status_pagamento_id' => $status,
        ]);
    }

    public function test_matricula_refeita_encontra_a_renovacao_em_aberto_do_mesmo_dia(): void
    {
        $this->parcela(1118, 120, '2026-09-25', 2);
        $this->parcela(1229, 120, '2026-11-05', 1);
        $this->parcela(1000, 120, '2026-11-05', 4); // cancelada não conta

        $existentes = ParcelasPeriodo::existentesAPartirDe(self::T, self::M, '2026-11-05');

        $this->assertSame([1229], array_column($existentes, 'id'));
        $this->assertSame('Aguardando', $existentes[0]['status']);
        $this->assertStringContainsString('#1229 de R$ 120,00 com vencimento em 05/11/2026', ParcelasPeriodo::descrever($existentes));
    }

    public function test_baixa_nao_gera_proxima_quando_outra_parcela_cobre_o_periodo(): void
    {
        $this->parcela(1229, 120, '2026-11-05', 2);
        $this->parcela(1588, 130, '2026-11-05', 1);

        $this->assertTrue(ParcelasPeriodo::periodoSeguinteJaCobrado(self::T, self::M, 1229, '2026-11-05', '2026-12-05'));
    }

    public function test_baixa_normal_gera_a_proxima(): void
    {
        $this->parcela(1118, 120, '2026-09-25', 2);
        $this->parcela(1229, 120, '2026-11-05', 2);

        $this->assertFalse(ParcelasPeriodo::periodoSeguinteJaCobrado(self::T, self::M, 1229, '2026-11-05', '2026-12-05'));
    }

    public function test_alerta_lista_vencimento_duplicado(): void
    {
        $pagamentos = [
            ['id' => 1229, 'valor' => '120.00', 'data_vencimento' => '2026-11-05', 'status_pagamento_id' => 2, 'status' => 'Pago'],
            ['id' => 1588, 'valor' => '130.00', 'data_vencimento' => '2026-11-05', 'status_pagamento_id' => 1, 'status' => 'Aguardando'],
            ['id' => 1589, 'valor' => '130.00', 'data_vencimento' => '2026-12-05', 'status_pagamento_id' => 1, 'status' => 'Aguardando'],
            ['id' => 999, 'valor' => '130.00', 'data_vencimento' => '2026-12-05', 'status_pagamento_id' => 4, 'status' => 'Cancelado'],
        ];

        $alertas = ParcelasPeriodo::duplicadas($pagamentos);

        $this->assertCount(1, $alertas);
        $this->assertSame('2026-11-05', $alertas[0]['data_vencimento']);
        $this->assertSame([1229, 1588], array_column($alertas[0]['parcelas'], 'id'));
        $this->assertStringContainsString('2 cobranças com vencimento em 05/11/2026', $alertas[0]['mensagem']);
    }
}
