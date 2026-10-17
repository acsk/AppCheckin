<?php

namespace Tests\Unit;

use App\Services\PagamentoPlanoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class PagamentoPlanoCobrancaDuplicadaTest extends TestCase
{
    use RequiresSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();
        $this->registrarFuncoesSqlite([
            'NOW' => [fn () => date('Y-m-d H:i:s'), 0],
        ]);

        Schema::create('planos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('duracao_dias')->nullable();
        });
        Schema::create('plano_ciclos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('meses')->nullable();
        });
        Schema::create('matriculas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('plano_id')->nullable();
            $table->unsignedInteger('plano_ciclo_id')->nullable();
            $table->string('tipo_cobranca')->nullable();
        });
        Schema::create('pagamentos_plano', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('matricula_id');
            $table->unsignedInteger('status_pagamento_id');
            $table->decimal('valor', 10, 2)->nullable();
            $table->date('data_vencimento')->nullable();
            $table->date('data_pagamento')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        DB::table('planos')->insert(['id' => 8, 'duracao_dias' => 30]);
        DB::table('plano_ciclos')->insert(['id' => 51, 'meses' => 2]);
        DB::table('matriculas')->insert([
            'id' => 350, 'tenant_id' => 3, 'plano_id' => 8, 'plano_ciclo_id' => 51, 'tipo_cobranca' => 'avulso',
        ]);
        DB::table('pagamentos_plano')->insert([
            [
                'id' => 1333, 'tenant_id' => 3, 'matricula_id' => 350, 'status_pagamento_id' => 2,
                'valor' => 197.05, 'data_vencimento' => '2026-10-02', 'data_pagamento' => '2026-10-02',
                'observacoes' => 'Pago via Mercado Pago',
            ],
            [
                'id' => 1334, 'tenant_id' => 3, 'matricula_id' => 350, 'status_pagamento_id' => 3,
                'valor' => 200, 'data_vencimento' => '2026-10-02', 'data_pagamento' => null,
                'observacoes' => 'Pagamento gerado automaticamente após confirmação MP',
            ],
        ]);
    }

    public function test_avanca_parcela_gerada_no_mesmo_dia_da_paga(): void
    {
        $n = (new PagamentoPlanoService())->corrigirCobrancaDuplicadaNoCicloPago(3, 350);

        $this->assertSame(1, $n);
        $row = (array) DB::table('pagamentos_plano')->where('id', 1334)->first();
        $this->assertSame('2026-12-02', $row['data_vencimento']);
        $this->assertSame(1, (int) $row['status_pagamento_id']);
        $this->assertStringContainsString('[Corrigido: renovação 2026-12-02]', (string) $row['observacoes']);
    }
}
