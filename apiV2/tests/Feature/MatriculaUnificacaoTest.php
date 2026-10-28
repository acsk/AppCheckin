<?php

namespace Tests\Feature;

use App\Services\MatriculaUnificacaoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class MatriculaUnificacaoTest extends TestCase
{
    use RequiresSqlite;

    private const T = 2;

    private const NATACAO = 3;

    private const MUSCULACAO = 4;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();

        Schema::create('planos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('modalidade_id')->nullable();
        });
        Schema::create('status_matricula', function (Blueprint $t) {
            $t->increments('id');
            $t->string('codigo');
        });
        Schema::create('matriculas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('aluno_id');
            $t->integer('plano_id');
            $t->integer('status_id');
            $t->integer('matricula_anterior_id')->nullable();
            $t->integer('pacote_contrato_id')->nullable();
            $t->date('data_matricula')->nullable();
            $t->date('data_inicio')->nullable();
            $t->timestamps();
        });
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('matricula_id');
            $t->decimal('valor', 10, 2);
        });
        Schema::create('assinaturas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('matricula_id')->nullable()->unique();
        });
        (require database_path('migrations/2026_10_31_120000_matriculas_unificadas.php'))->up();

        foreach (['ativa', 'vencida', 'cancelada', 'finalizada', 'pendente'] as $i => $c) {
            DB::table('status_matricula')->insert(['id' => $i + 1, 'codigo' => $c]);
        }
        DB::table('planos')->insert([['id' => 8, 'modalidade_id' => self::NATACAO], ['id' => 9, 'modalidade_id' => self::MUSCULACAO]]);
    }

    private function matricula(string $status, string $inicio, int $aluno = 158, int $plano = 8, ?int $anterior = null, ?int $pacote = null): int
    {
        return (int) DB::table('matriculas')->insertGetId([
            'tenant_id' => self::T, 'aluno_id' => $aluno, 'plano_id' => $plano,
            'status_id' => (int) DB::table('status_matricula')->where('codigo', $status)->value('id'),
            'matricula_anterior_id' => $anterior, 'pacote_contrato_id' => $pacote,
            'data_matricula' => $inicio, 'data_inicio' => $inicio, 'created_at' => $inicio, 'updated_at' => $inicio,
        ]);
    }

    private function servico(): MatriculaUnificacaoService
    {
        return app(MatriculaUnificacaoService::class);
    }

    // Caso do Daniel: #229 cancelada e paga, continuada pela #450 ativa
    public function test_ciclo_antigo_entra_na_matricula_atual_com_os_pagamentos(): void
    {
        $antiga = $this->matricula('cancelada', '2026-09-04');
        $atual = $this->matricula('ativa', '2026-10-22', anterior: $antiga);
        DB::table('pagamentos_plano')->insert([['matricula_id' => $antiga, 'valor' => 120], ['matricula_id' => $atual, 'valor' => 120]]);
        DB::table('assinaturas')->insert(['matricula_id' => $antiga]);

        $r = $this->servico()->unificar($this->servico()->grupos(self::T)[0], false);

        $this->assertSame($atual, $r['atual']);
        $this->assertSame([$antiga], $r['removidas']);
        $this->assertNull(DB::table('matriculas')->find($antiga));
        $this->assertSame(2, DB::table('pagamentos_plano')->where('matricula_id', $atual)->count());
        $this->assertSame($atual, (int) DB::table('assinaturas')->value('matricula_id'), 'assinatura do ciclo antigo passa para a atual');

        $linha = DB::table('matriculas')->find($atual);
        $this->assertNull($linha->matricula_anterior_id);
        $this->assertSame('2026-09-04', $linha->data_matricula, 'aluno desde a primeira matrícula');

        $registro = DB::table('matriculas_unificadas')->where('matricula_antiga_id', $antiga)->first();
        $this->assertSame($atual, (int) $registro->matricula_id);
        $this->assertSame('2026-09-04', json_decode($registro->dados, true)['data_inicio']);
        $this->assertSame($atual, $this->servico()->idAtual($antiga), 'MAT-{antiga} de cobrança antiga acha a atual');
        $this->assertSame([], $this->servico()->grupos(self::T));
    }

    public function test_cadeia_de_varios_ciclos_vira_uma_matricula(): void
    {
        $a = $this->matricula('finalizada', '2026-04-16');
        $b = $this->matricula('cancelada', '2026-06-16', anterior: $a);
        $c = $this->matricula('vencida', '2026-08-16', anterior: $b);
        $atual = $this->matricula('ativa', '2026-10-26', anterior: $c);
        foreach ([$a, $b, $c, $atual] as $id) {
            DB::table('pagamentos_plano')->insert(['matricula_id' => $id, 'valor' => 200]);
        }

        $this->servico()->unificar($this->servico()->grupos(self::T)[0], false);

        $this->assertSame(1, DB::table('matriculas')->count());
        $this->assertSame(4, DB::table('pagamentos_plano')->where('matricula_id', $atual)->count());
        $this->assertSame(3, DB::table('matriculas_unificadas')->where('matricula_id', $atual)->count());
    }

    public function test_todas_encerradas_fica_a_mais_recente(): void
    {
        $velha = $this->matricula('cancelada', '2026-03-01');
        $recente = $this->matricula('vencida', '2026-05-01');

        $r = $this->servico()->unificar($this->servico()->grupos(self::T)[0], false);

        $this->assertSame($recente, $r['atual']);
        $this->assertNull(DB::table('matriculas')->find($velha));
    }

    public function test_simular_nao_grava(): void
    {
        $antiga = $this->matricula('cancelada', '2026-09-04');
        $atual = $this->matricula('ativa', '2026-10-22');
        DB::table('pagamentos_plano')->insert(['matricula_id' => $antiga, 'valor' => 120]);

        $r = $this->servico()->unificar($this->servico()->grupos(self::T)[0]);

        $this->assertSame($atual, $r['atual']);
        $this->assertSame(1, $r['linhas_movidas']);
        $this->assertSame(2, DB::table('matriculas')->count());
        $this->assertSame($antiga, (int) DB::table('pagamentos_plano')->value('matricula_id'));
        $this->assertSame(0, DB::table('matriculas_unificadas')->count());
    }

    public function test_duas_em_aberto_ficam_para_decisao_manual(): void
    {
        $this->matricula('ativa', '2026-10-01');
        $this->matricula('pendente', '2026-10-20');

        $r = $this->servico()->unificar($this->servico()->grupos(self::T)[0], false);

        $this->assertNull($r['atual']);
        $this->assertNotNull($r['conflito']);
        $this->assertSame(2, DB::table('matriculas')->count());
    }

    public function test_outra_modalidade_aluno_e_pacote_nao_entram(): void
    {
        $this->matricula('ativa', '2026-10-01');
        $this->matricula('vencida', '2026-09-01', plano: 9);
        $this->matricula('vencida', '2026-09-01', aluno: 159);
        $this->matricula('ativa', '2026-09-01', pacote: 7);

        $this->assertSame([], $this->servico()->grupos(self::T));
    }

    public function test_assinatura_unica_da_atual_e_preservada(): void
    {
        $antiga = $this->matricula('cancelada', '2026-09-04');
        $atual = $this->matricula('ativa', '2026-10-22');
        $daAntiga = DB::table('assinaturas')->insertGetId(['matricula_id' => $antiga]);
        $daAtual = DB::table('assinaturas')->insertGetId(['matricula_id' => $atual]);

        $this->servico()->unificar($this->servico()->grupos(self::T)[0], false);

        $this->assertSame($atual, (int) DB::table('assinaturas')->where('id', $daAtual)->value('matricula_id'));
        $this->assertNotSame($atual, (int) DB::table('assinaturas')->where('id', $daAntiga)->value('matricula_id'));
    }

    public function test_comando_lista_sem_aplicar_e_grava_com_aplicar(): void
    {
        $antiga = $this->matricula('cancelada', '2026-09-04');
        $this->matricula('ativa', '2026-10-22');

        $this->assertSame(0, Artisan::call('matriculas:unificar', ['--tenant' => self::T]));
        $this->assertStringContainsString('Rode com --aplicar', Artisan::output());
        $this->assertNotNull(DB::table('matriculas')->find($antiga));

        Artisan::call('matriculas:unificar', ['--tenant' => self::T, '--aplicar' => true]);
        $this->assertNull(DB::table('matriculas')->find($antiga));
    }
}
