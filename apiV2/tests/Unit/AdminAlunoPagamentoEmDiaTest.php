<?php

namespace Tests\Unit;

use App\Repositories\AdminAlunoRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class AdminAlunoPagamentoEmDiaTest extends TestCase
{
    use RequiresSqlite;

    public function test_divida_anterior_a_matricula_atual_nao_marca_aluno_como_pendente(): void
    {
        $this->requireSqlite();
        $hoje = now()->toDateString();
        $this->registrarFuncoesSqlite(['CURDATE' => [static fn () => $hoje, 0]]);

        Schema::create('alunos', function (Blueprint $t) {
            $t->increments('id');
        });
        Schema::create('status_matricula', function (Blueprint $t) {
            $t->increments('id');
            $t->string('codigo');
        });
        Schema::create('matriculas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('aluno_id');
            $t->integer('tenant_id');
            $t->integer('status_id');
            $t->date('data_inicio');
            $t->date('data_vencimento')->nullable();
            $t->date('proxima_data_vencimento')->nullable();
        });
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('matricula_id');
            $t->integer('tenant_id');
            $t->integer('status_pagamento_id');
            $t->date('data_vencimento');
        });

        DB::table('alunos')->insert(['id' => 35]);
        DB::table('status_matricula')->insert(['id' => 1, 'codigo' => 'ativa']);
        DB::table('matriculas')->insert([
            'id' => 84,
            'aluno_id' => 35,
            'tenant_id' => 3,
            'status_id' => 1,
            'data_inicio' => now()->subDays(2)->toDateString(),
            'data_vencimento' => now()->addMonths(3)->toDateString(),
            'proxima_data_vencimento' => now()->addMonths(3)->toDateString(),
        ]);
        DB::table('pagamentos_plano')->insert([
            ['id' => 1246, 'matricula_id' => 84, 'tenant_id' => 3, 'status_pagamento_id' => 3, 'data_vencimento' => now()->subDays(5)->toDateString()],
            ['id' => 1481, 'matricula_id' => 84, 'tenant_id' => 3, 'status_pagamento_id' => 2, 'data_vencimento' => now()->subDays(2)->toDateString()],
        ]);

        $repository = new AdminAlunoRepository;
        $this->assertTrue($repository->temPagamentoAtivo(35, 3));

        DB::table('pagamentos_plano')->insert([
            'id' => 1482,
            'matricula_id' => 84,
            'tenant_id' => 3,
            'status_pagamento_id' => 3,
            'data_vencimento' => now()->subDay()->toDateString(),
        ]);
        $this->assertFalse($repository->temPagamentoAtivo(35, 3));
    }
}
