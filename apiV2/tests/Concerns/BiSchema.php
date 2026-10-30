<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema mínimo (SQLite) das tabelas que o BI da academia lê, e um cenário com os casos de borda.
 * Hoje = 20/10/2026; período "mes": 01 a 20/10 contra 01 a 20/09. Academia 1 é a analisada; 2 é ruído.
 */
trait BiSchema
{
    use RequiresSqlite;

    protected const HOJE = '2026-10-20';

    protected const T1 = 1;

    protected const T2 = 2;

    protected const PIX = 2;

    protected const DINHEIRO = 1;

    private int $mensal;

    private int $diaria;

    private int $trimestral;

    protected function criarCenarioBi(): void
    {
        // Nunca no banco do ambiente: o container define DB_CONNECTION=mysql
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        $this->requireSqlite();

        Schema::create('modalidades', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->string('nome');
        });
        DB::table('modalidades')->insert([['id' => 1, 'tenant_id' => self::T1, 'nome' => 'Natação'], ['id' => 2, 'tenant_id' => self::T1, 'nome' => 'Hidroginástica']]);
        Schema::create('planos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('modalidade_id')->nullable();
            $t->string('nome');
            $t->integer('duracao_dias')->default(30);
        });
        Schema::create('matriculas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('aluno_id')->nullable();
            $t->integer('plano_id');
            $t->date('data_matricula');
            $t->date('data_inicio');
            $t->date('data_vencimento');
            $t->integer('status_id');
            $t->date('data_cancelamento')->nullable();
            $t->string('motivo_cancelamento_codigo', 30)->nullable();
        });
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('matricula_id');
            $t->integer('aluno_id')->nullable();
            $t->integer('plano_id')->nullable();
            $t->decimal('valor', 10, 2);
            $t->decimal('desconto', 10, 2)->default(0);
            $t->date('data_vencimento');
            $t->date('data_pagamento')->nullable();
            $t->decimal('valor_estornado', 10, 2)->nullable();
            $t->date('data_estorno')->nullable();
            $t->string('motivo_estorno')->nullable();
            $t->integer('estornado_por')->nullable();
            $t->integer('status_pagamento_id');
            $t->integer('forma_pagamento_id')->nullable();
            $t->timestamp('updated_at')->nullable();
        });
        Schema::create('formas_pagamento', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
        });
        DB::table('formas_pagamento')->insert([['id' => self::DINHEIRO, 'nome' => 'Dinheiro'], ['id' => self::PIX, 'nome' => 'Pix']]);
        Schema::create('alunos', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
        });
        foreach ([1 => 'ANA', 2 => 'BRUNO', 3 => 'CARLA', 4 => 'DIEGO', 5 => 'EVA', 6 => 'FABIO', 7 => 'GUSTAVO', 9 => 'OUTRA ACADEMIA'] as $id => $nome) {
            DB::table('alunos')->insert(['id' => $id, 'nome' => $nome]);
        }
        Schema::create('dias', function (Blueprint $t) {
            $t->increments('id');
            $t->date('data');
        });
        Schema::create('professores', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
        });
        DB::table('professores')->insert([['id' => 1, 'nome' => 'Rafael Lima'], ['id' => 2, 'nome' => 'Carla Souza']]);
        Schema::create('turmas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id')->default(self::T1);
            $t->integer('dia_id');
            $t->integer('modalidade_id')->nullable();
            $t->integer('professor_id')->nullable();
            $t->time('horario_inicio')->nullable();
            $t->integer('limite_alunos')->nullable();
            $t->boolean('ativo')->default(true);
        });
        Schema::create('checkins', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('aluno_id');
            $t->integer('turma_id')->nullable();
            $t->dateTime('data_checkin');
            $t->date('data_checkin_date')->nullable();
            $t->boolean('presente')->nullable();
        });

        $this->mensal = (int) DB::table('planos')->insertGetId(['tenant_id' => self::T1, 'modalidade_id' => 1, 'nome' => 'Mensal', 'duracao_dias' => 30]);
        $this->diaria = (int) DB::table('planos')->insertGetId(['tenant_id' => self::T1, 'modalidade_id' => 1, 'nome' => 'Diária', 'duracao_dias' => 1]);
        $this->trimestral = (int) DB::table('planos')->insertGetId(['tenant_id' => self::T1, 'modalidade_id' => 2, 'nome' => 'Trimestral', 'duracao_dias' => 90]);
        $this->cenario();
    }

    private function matricula(int $aluno, string $criada, string $inicio, string $fim, int $status, ?string $cancelada = null, ?int $plano = null, int $tenant = self::T1): int
    {
        return (int) DB::table('matriculas')->insertGetId([
            'tenant_id' => $tenant, 'aluno_id' => $aluno, 'plano_id' => $plano ?? $this->mensal, 'data_matricula' => $criada,
            'data_inicio' => $inicio, 'data_vencimento' => $fim, 'status_id' => $status, 'data_cancelamento' => $cancelada,
        ]);
    }

    /** @param  array<string, mixed>  $extra  forma_pagamento_id, desconto, plano_id */
    private function parcela(int $matricula, ?int $aluno, float $valor, string $vence, int $status, ?string $pago = null, int $tenant = self::T1, array $extra = []): void
    {
        DB::table('pagamentos_plano')->insert($extra + [
            'tenant_id' => $tenant, 'matricula_id' => $matricula, 'aluno_id' => $aluno, 'valor' => $valor,
            'data_vencimento' => $vence, 'data_pagamento' => $pago, 'status_pagamento_id' => $status,
        ]);
    }

    private function checkin(string $quando, ?int $turma = null, ?bool $presente = null, int $tenant = self::T1, int $aluno = 1): void
    {
        DB::table('checkins')->insert(['tenant_id' => $tenant, 'aluno_id' => $aluno, 'turma_id' => $turma, 'data_checkin' => $quando.' 07:00:00', 'presente' => $presente]);
    }

    /** Uma aula: turma num dia (como no sistema, cada linha de turmas é uma aula) */
    private function aula(string $data, string $hora, ?int $limite, int $modalidade = 1, int $professor = 1, bool $ativo = true, int $tenant = self::T1): int
    {
        $dia = DB::table('dias')->where('data', $data)->value('id') ?? DB::table('dias')->insertGetId(['data' => $data]);

        return (int) DB::table('turmas')->insertGetId(['tenant_id' => $tenant, 'dia_id' => $dia, 'modalidade_id' => $modalidade,
            'professor_id' => $professor, 'horario_inicio' => $hora, 'limite_alunos' => $limite, 'ativo' => $ativo]);
    }

    private function cenario(): void
    {
        // A: aluno antigo, renovou em outubro
        $this->matricula(1, '2026-06-01', '2026-06-01', '2026-09-30', 4);
        $a = $this->matricula(1, '2026-09-30', '2026-10-01', '2026-10-31', 1);
        // B: primeira matrícula em outubro
        $b = $this->matricula(2, '2026-10-05', '2026-10-05', '2026-11-04', 1);
        // C: cancelou em 10/10 e não voltou
        $this->matricula(3, '2026-07-01', '2026-07-01', '2026-10-31', 3, '2026-10-10');
        // D: trocou de plano em 12/10 (cancelada + nova no mesmo dia): não é saída
        $this->matricula(4, '2026-08-01', '2026-08-01', '2026-10-31', 3, '2026-10-12');
        $this->matricula(4, '2026-10-12', '2026-10-12', '2026-11-11', 1);
        // E: diária cancelada — não entra em saídas
        $this->matricula(5, '2026-10-03', '2026-10-03', '2026-10-03', 3, '2026-10-03', $this->diaria);
        // F: só pendente — não é aluno ativo nem novo
        $this->matricula(6, '2026-10-08', '2026-10-08', '2026-11-07', 5);
        // G: parou em abril e voltou em 06/10, no plano trimestral (Hidroginástica)
        $this->matricula(7, '2026-03-01', '2026-03-01', '2026-04-30', 4);
        $this->matricula(7, '2026-10-06', '2026-10-06', '2027-01-05', 1, null, $this->trimestral);
        // Outra academia: nada disso pode aparecer
        $outra = $this->matricula(1, '2026-10-01', '2026-10-01', '2026-10-31', 1, null, null, self::T2);
        $this->matricula(9, '2026-10-02', '2026-10-02', '2026-10-31', 3, '2026-10-04', null, self::T2);

        $this->parcela($a, 1, 100, '2026-10-05', 2, '2026-10-05', self::T1, ['forma_pagamento_id' => self::PIX, 'desconto' => 20]);
        // Aluno pela matrícula; plano da própria parcela vale sobre o da matrícula; sem forma informada
        $this->parcela($b, null, 150, '2026-09-28', 2, '2026-10-02', self::T1, ['plano_id' => $this->trimestral]);
        $this->parcela($a, 1, 120, '2026-10-20', 1);                  // vence hoje: a vencer
        $this->parcela($b, 2, 80, '2026-10-15', 3);                   // atrasada
        $this->parcela($b, 2, 50, '2026-10-18', 1);                   // aguardando, já vencida
        $this->parcela($b, 2, 999, '2026-10-10', 4);                  // cancelada: fora de tudo
        $this->parcela($a, 1, 200, '2026-09-10', 2, '2026-09-10', self::T1, ['forma_pagamento_id' => self::DINHEIRO, 'desconto' => 5]);
        $this->parcela($outra, 1, 5000, '2026-10-05', 2, '2026-10-05', self::T2, ['forma_pagamento_id' => self::PIX, 'desconto' => 500]);

        // Aulas: natação 06:00 com Rafael (limite 10) em 06/10 e 08/10; hidro 19:00 com Carla sem limite em 09/10;
        // uma aula desativada sem check-in (fora de tudo) e uma da outra academia
        $natacao = $this->aula('2026-10-06', '06:00:00', 10);
        $this->aula('2026-10-08', '06:00:00', 10);
        $hidro = $this->aula('2026-10-09', '19:00:00', null, 2, 2);
        $this->aula('2026-10-12', '06:00:00', 10, 1, 1, false);
        $this->aula('2026-10-06', '06:00:00', 1, 1, 1, true, self::T2);

        $this->checkin('2026-10-07', $natacao);     // conta no dia da aula (06/10)
        $this->checkin('2026-10-08', null, true);   // sem aula: hora do check-in (07h)
        $this->checkin('2026-10-09', $hidro);
        $this->checkin('2026-10-10', null, false);  // marcado ausente
        $this->checkin('2026-09-15');
        // D: 8 check-ins em agosto e nenhum nas últimas 4 semanas — frequência em queda
        foreach (['08-02', '08-05', '08-09', '08-12', '08-16', '08-19', '08-23', '08-26'] as $md) {
            $this->checkin('2026-'.$md, null, null, self::T1, 4);
        }
        for ($i = 0; $i < 10; $i++) {
            $this->checkin('2026-10-05', null, null, self::T2);
        }
    }
}
