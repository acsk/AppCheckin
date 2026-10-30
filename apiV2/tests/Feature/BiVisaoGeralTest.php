<?php

namespace Tests\Feature;

use App\Services\Bi\BiVisaoGeralService;
use App\Support\Bi\Periodo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

/**
 * Fórmulas da Visão geral do BI (SQLite). Hoje = 20/10/2026, período "mes": 01 a 20/10 contra 01 a 20/09.
 */
class BiVisaoGeralTest extends TestCase
{
    use RequiresSqlite;

    private const HOJE = '2026-10-20';

    private const T1 = 1;

    private const T2 = 2;

    private int $mensal;

    private int $diaria;

    protected function setUp(): void
    {
        parent::setUp();
        // Nunca no banco do ambiente: o container define DB_CONNECTION=mysql
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        $this->requireSqlite();

        Schema::create('planos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
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
        });
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('matricula_id');
            $t->integer('aluno_id')->nullable();
            $t->decimal('valor', 10, 2);
            $t->date('data_vencimento');
            $t->date('data_pagamento')->nullable();
            $t->integer('status_pagamento_id');
        });
        Schema::create('alunos', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
        });
        foreach ([1 => 'ANA', 2 => 'BRUNO', 3 => 'CARLA', 4 => 'DIEGO', 5 => 'EVA', 6 => 'FABIO', 9 => 'OUTRA ACADEMIA'] as $id => $nome) {
            DB::table('alunos')->insert(['id' => $id, 'nome' => $nome]);
        }
        Schema::create('dias', function (Blueprint $t) {
            $t->increments('id');
            $t->date('data');
        });
        Schema::create('turmas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('dia_id');
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

        $this->mensal = (int) DB::table('planos')->insertGetId(['tenant_id' => self::T1, 'nome' => 'Mensal', 'duracao_dias' => 30]);
        $this->diaria = (int) DB::table('planos')->insertGetId(['tenant_id' => self::T1, 'nome' => 'Diária', 'duracao_dias' => 1]);
        $this->cenario();
    }

    private function matricula(int $aluno, string $criada, string $inicio, string $fim, int $status, ?string $cancelada = null, ?int $plano = null, int $tenant = self::T1): int
    {
        return (int) DB::table('matriculas')->insertGetId([
            'tenant_id' => $tenant, 'aluno_id' => $aluno, 'plano_id' => $plano ?? $this->mensal, 'data_matricula' => $criada,
            'data_inicio' => $inicio, 'data_vencimento' => $fim, 'status_id' => $status, 'data_cancelamento' => $cancelada,
        ]);
    }

    private function parcela(int $matricula, ?int $aluno, float $valor, string $vence, int $status, ?string $pago = null, int $tenant = self::T1): void
    {
        DB::table('pagamentos_plano')->insert([
            'tenant_id' => $tenant, 'matricula_id' => $matricula, 'aluno_id' => $aluno, 'valor' => $valor,
            'data_vencimento' => $vence, 'data_pagamento' => $pago, 'status_pagamento_id' => $status,
        ]);
    }

    private function checkin(string $quando, ?int $turma = null, ?bool $presente = null, int $tenant = self::T1): void
    {
        DB::table('checkins')->insert(['tenant_id' => $tenant, 'aluno_id' => 1, 'turma_id' => $turma, 'data_checkin' => $quando.' 07:00:00', 'presente' => $presente]);
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
        // Outra academia: nada disso pode aparecer
        $outra = $this->matricula(1, '2026-10-01', '2026-10-01', '2026-10-31', 1, null, null, self::T2);
        $this->matricula(9, '2026-10-02', '2026-10-02', '2026-10-31', 3, '2026-10-04', null, self::T2);

        $this->parcela($a, 1, 100, '2026-10-05', 2, '2026-10-05');
        $this->parcela($b, null, 150, '2026-09-28', 2, '2026-10-02'); // aluno pela matrícula
        $this->parcela($a, 1, 120, '2026-10-20', 1);                  // vence hoje: a vencer
        $this->parcela($b, 2, 80, '2026-10-15', 3);                   // atrasada
        $this->parcela($b, 2, 50, '2026-10-18', 1);                   // aguardando, já vencida
        $this->parcela($b, 2, 999, '2026-10-10', 4);                  // cancelada: fora de tudo
        $this->parcela($a, 1, 200, '2026-09-10', 2, '2026-09-10');
        $this->parcela($outra, 1, 5000, '2026-10-05', 2, '2026-10-05', self::T2);

        $dia = (int) DB::table('dias')->insertGetId(['data' => '2026-10-06']);
        $turma = (int) DB::table('turmas')->insertGetId(['dia_id' => $dia]);
        $this->checkin('2026-10-07', $turma);       // conta no dia da aula (06/10)
        $this->checkin('2026-10-08', null, true);
        $this->checkin('2026-10-09');
        $this->checkin('2026-10-10', null, false);  // marcado ausente
        $this->checkin('2026-09-15');
        for ($i = 0; $i < 10; $i++) {
            $this->checkin('2026-10-05', null, null, self::T2);
        }
    }

    private function dados(?string $preset = 'mes'): array
    {
        $r = app(BiVisaoGeralService::class)->visaoGeral(self::T1, $preset, self::HOJE);
        $this->assertSame(200, $r['status']);

        return $r['body']['data'];
    }

    public function test_alunos_novos_e_saidas(): void
    {
        $i = $this->dados()['indicadores'];

        $this->assertSame(['atual' => 3, 'anterior' => 3], $i['alunos_ativos']); // A, B, D hoje; A, C, D em 20/09
        $this->assertSame(['atual' => 2, 'anterior' => 0], $i['novos_alunos']);  // B e E (diária também é aluno novo)
        $this->assertSame(['atual' => 1, 'anterior' => 0], $i['saidas']);        // só C
    }

    public function test_financeiro_separa_recebido_previsto_e_vencido(): void
    {
        $d = $this->dados();
        $i = $d['indicadores'];

        $this->assertSame(250.0, $i['receita_recebida']['atual']);
        $this->assertSame(200.0, $i['receita_recebida']['anterior']);
        $this->assertSame(125.0, $i['ticket_medio']['atual']);   // 250 / 2 pagantes
        $this->assertSame(350.0, $i['receita_prevista']['atual']); // 100 + 120 + 80 + 50
        $this->assertSame(130.0, $i['valor_vencido']['atual']);
        $this->assertSame(37.1, $i['inadimplencia']['atual']);
        $this->assertSame(['previsto' => 350.0, 'pago' => 100.0, 'a_vencer' => 120.0, 'vencido' => 130.0, 'vencido_qtd' => 2], $d['parcelas_periodo']);

        $out = end($d['receita_mensal']);
        $this->assertSame(['mes' => '2026-10', 'previsto' => 350.0, 'recebido' => 250.0], $out);
        $this->assertCount(12, $d['receita_mensal']);
    }

    public function test_checkins_pelo_dia_da_aula_sem_ausentes_e_sem_outra_academia(): void
    {
        $d = $this->dados();

        $this->assertSame(['atual' => 3, 'anterior' => 1], $d['indicadores']['checkins']);
        $this->assertSame('dia', $d['checkins_serie']['agrupamento']);
        $this->assertCount(20, $d['checkins_serie']['pontos']);
        $porDia = array_column($d['checkins_serie']['pontos'], 'total', 'data');
        $this->assertSame(1, $porDia['2026-10-06']);
        $this->assertSame(0, $porDia['2026-10-07']);
    }

    public function test_doze_meses_agrupa_checkins_por_mes(): void
    {
        $d = $this->dados('12m');

        $this->assertSame('mes', $d['checkins_serie']['agrupamento']);
        $this->assertSame(4, $d['indicadores']['checkins']['atual']);
    }

    private function detalhe(string $indicador, string $preset = 'mes'): array
    {
        $r = app(BiVisaoGeralService::class)->detalhe(self::T1, $indicador, $preset, self::HOJE);
        $this->assertSame(200, $r['status']);

        return $r['body']['data'];
    }

    public function test_detalhamento_bate_com_os_cards(): void
    {
        $i = $this->dados()['indicadores'];

        foreach (['alunos_ativos', 'novos_alunos', 'saidas'] as $k) {
            $this->assertSame($i[$k]['atual'], $this->detalhe($k)['quantidade'], $k);
        }
        $this->assertSame($i['receita_recebida']['atual'], $this->detalhe('receita_recebida')['valor_total']);
        $this->assertSame($i['valor_vencido']['atual'], $this->detalhe('valor_vencido')['valor_total']);
    }

    public function test_detalhamento_mostra_quem_compoe_e_nada_de_outra_academia(): void
    {
        $this->assertSame(['ANA', 'BRUNO', 'DIEGO'], array_column($this->detalhe('alunos_ativos')['itens'], 'aluno_nome'));
        $this->assertSame(['BRUNO', 'EVA'], array_column($this->detalhe('novos_alunos')['itens'], 'aluno_nome'));

        $saida = $this->detalhe('saidas')['itens'];
        $this->assertSame([['CARLA', '2026-10-10']], array_map(fn ($x) => [$x['aluno_nome'], $x['data']], $saida));

        $recebido = $this->detalhe('receita_recebida')['itens'];
        $this->assertSame(['2026-10-05', '2026-10-02'], array_column($recebido, 'data'));
        $this->assertSame('BRUNO', $recebido[1]['aluno_nome'], 'aluno pela matrícula quando a parcela não tem aluno');

        $this->assertSame([80.0, 50.0], array_column($this->detalhe('valor_vencido')['itens'], 'valor'));
        $this->assertNotContains('OUTRA ACADEMIA', array_column($this->detalhe('saidas')['itens'], 'aluno_nome'));
    }

    public function test_detalhamento_recusa_indicador_desconhecido(): void
    {
        $this->assertSame(422, app(BiVisaoGeralService::class)->detalhe(self::T1, 'tenants', 'mes', self::HOJE)['status']);
    }

    public function test_periodo_compara_intervalos_equivalentes(): void
    {
        $p = Periodo::resolver('mes', '2026-03-31');
        $this->assertSame(['2026-03-01', '2026-03-31', '2026-02-01', '2026-02-28'], [$p->inicio, $p->fim, $p->anteriorInicio, $p->anteriorFim]);

        $p = Periodo::resolver('mes_anterior', '2026-03-15');
        $this->assertSame(['2026-02-01', '2026-02-28', '2026-01-01', '2026-01-31'], [$p->inicio, $p->fim, $p->anteriorInicio, $p->anteriorFim]);

        $p = Periodo::resolver('3m', self::HOJE);
        $this->assertSame(90, $p->dias());
        $this->assertSame('2026-07-23', $p->inicio);
        $this->assertSame('2026-07-22', $p->anteriorFim);
        $this->assertSame(89, (int) (new \DateTime($p->anteriorInicio))->diff(new \DateTime($p->anteriorFim))->days);

        $this->assertSame('mes', Periodo::resolver('qualquer', self::HOJE)->preset);
    }
}
