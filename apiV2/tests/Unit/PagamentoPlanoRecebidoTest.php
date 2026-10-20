<?php

namespace Tests\Unit;

use App\Services\Admin\AdminPagamentoPlanoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

/** "Recebido" igual em Dashboard, Relatório e Assinaturas: pagas pela data do pagamento. */
class PagamentoPlanoRecebidoTest extends TestCase
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
            $t->unsignedInteger('aluno_id')->nullable();
            $t->unsignedInteger('plano_id')->nullable();
            $t->decimal('valor', 10, 2);
            $t->unsignedInteger('status_pagamento_id');
            $t->unsignedInteger('forma_pagamento_id')->nullable();
            $t->date('data_vencimento');
            $t->date('data_pagamento')->nullable();
        });
        foreach (['status_pagamento', 'formas_pagamento', 'alunos', 'planos'] as $tabela) {
            Schema::create($tabela, function (Blueprint $t) {
                $t->id();
                $t->string('nome');
            });
        }
        DB::table('status_pagamento')->insert([['id' => 1, 'nome' => 'Aguardando'], ['id' => 2, 'nome' => 'Pago']]);
        DB::table('alunos')->insert(['id' => 1, 'nome' => 'Aluno']);
        DB::table('planos')->insert(['id' => 1, 'nome' => 'Mensal']);
        $p = fn ($id, $st, $venc, $pago, $plano = 1) => ['id' => $id, 'tenant_id' => 3, 'matricula_id' => 1, 'aluno_id' => 1, 'plano_id' => $plano, 'valor' => 100,
            'status_pagamento_id' => $st, 'data_vencimento' => $venc, 'data_pagamento' => $pago];
        DB::table('pagamentos_plano')->insert([
            $p(1, 2, '2026-10-05', '2026-10-05'),      // vence e paga em outubro
            $p(2, 2, '2026-11-10', '2026-10-20'),      // paga adiantada em outubro
            $p(3, 2, '2026-10-12', '2026-09-30'),      // vence em outubro, paga em setembro
            $p(4, 1, '2026-10-15', null),              // em aberto
            $p(5, 2, '2026-10-08', '2026-10-08', 99),  // plano removido: continua contando
        ]);
    }

    private function ids(array $query): array
    {
        $service = (new \ReflectionClass(AdminPagamentoPlanoService::class))->newInstanceWithoutConstructor();
        $rows = $service->index(3, $query)['body']['pagamentos'];
        $ids = array_map(fn ($r) => (int) ((array) $r)['id'], $rows);
        sort($ids);

        return $ids;
    }

    public function test_por_pagamento_soma_o_que_entrou_no_mes(): void
    {
        $this->assertSame([1, 2, 5], $this->ids(['periodo_por' => 'pagamento', 'data_inicio' => '2026-10-01', 'data_fim' => '2026-10-31']));
    }

    public function test_padrao_continua_pela_data_de_vencimento(): void
    {
        $this->assertSame([1, 3, 4, 5], $this->ids(['data_inicio' => '2026-10-01', 'data_fim' => '2026-10-31']));
    }
}
