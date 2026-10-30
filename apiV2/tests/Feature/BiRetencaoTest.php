<?php

namespace Tests\Feature;

use App\Services\Bi\BiExportacaoService;
use App\Services\Bi\BiRetencaoService;
use App\Services\Bi\BiVisaoGeralService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BiSchema;
use Tests\TestCase;

/**
 * Retenção e evasão do BI (SQLite). Ativos em 30/09: ANA, CARLA, DIEGO (BiSchema) + HELENA (acesso até 05/10,
 * não renovou) + IRIS (acesso até 15/10, ainda na tolerância de 7 dias em 20/10).
 */
class BiRetencaoTest extends TestCase
{
    use BiSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarCenarioBi();
        DB::table('alunos')->insert([['id' => 8, 'nome' => 'HELENA'], ['id' => 10, 'nome' => 'IRIS']]);
        $this->matricula(8, '2026-09-05', '2026-09-05', '2026-10-05', 2);
        $this->matricula(10, '2026-09-15', '2026-09-15', '2026-10-15', 2);
        DB::table('matriculas')->where('aluno_id', 3)->update(['motivo_cancelamento_codigo' => 'horario']);

        Schema::create('prospeccao_oportunidades', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('aluno_id');
            $t->string('status');
            $t->string('motivo_categoria')->nullable();
            $t->integer('matricula_conversao_id')->nullable();
            $t->dateTime('convertida_em')->nullable();
            $t->dateTime('ultimo_contato_em')->nullable();
            $t->timestamps();
        });
        // G voltou pela Prospecção (matrícula trimestral de 06/10); outra criada e não contatada; uma da outra academia
        $gustavo = (int) DB::table('matriculas')->where('aluno_id', 7)->max('id');
        DB::table('prospeccao_oportunidades')->insert([
            ['tenant_id' => self::T1, 'aluno_id' => 7, 'status' => 'recuperado', 'motivo_categoria' => 'financeiro', 'matricula_conversao_id' => $gustavo, 'convertida_em' => '2026-10-06 10:00:00', 'ultimo_contato_em' => '2026-10-02 09:00:00', 'created_at' => '2026-10-01 08:00:00', 'updated_at' => null],
            ['tenant_id' => self::T1, 'aluno_id' => 3, 'status' => 'nao_contatado', 'motivo_categoria' => 'horario', 'matricula_conversao_id' => null, 'convertida_em' => null, 'ultimo_contato_em' => null, 'created_at' => '2026-10-15 08:00:00', 'updated_at' => null],
            ['tenant_id' => self::T2, 'aluno_id' => 9, 'status' => 'recuperado', 'motivo_categoria' => null, 'matricula_conversao_id' => 1, 'convertida_em' => '2026-10-06 10:00:00', 'ultimo_contato_em' => null, 'created_at' => '2026-10-01 08:00:00', 'updated_at' => null],
        ]);
    }

    private function dados(): array
    {
        $r = app(BiRetencaoService::class)->retencao(self::T1, 'mes', self::HOJE);
        $this->assertSame(200, $r['status']);

        return $r['body']['data'];
    }

    public function test_composicao_dos_ativos_do_inicio_fecha_a_conta(): void
    {
        $d = $this->dados();

        $this->assertSame(['ativos_inicio' => 5, 'continuam' => 2, 'cancelaram' => 1, 'nao_renovaram' => 1, 'renovacao_atrasada' => 1], $d['composicao']);
        $this->assertSame(40.0, $d['indicadores']['retencao']['atual']);
        $this->assertSame(40.0, $d['indicadores']['evasao']['atual'], 'cancelou + não renovou; a renovação atrasada fica de fora');
    }

    public function test_motivos_recuperados_e_funil_da_prospeccao(): void
    {
        $d = $this->dados();

        $this->assertSame([['codigo' => 'horario', 'nome' => 'Horário incompatível', 'alunos' => 1]], $d['motivos']);
        $this->assertSame(1, $d['indicadores']['recuperados']['atual']);
        $this->assertSame(['criadas' => 2, 'contatadas' => 1, 'recuperadas' => 1, 'sem_interesse' => 0, 'abertas' => 1, 'taxa_recuperacao' => 50.0], $d['funil']);
        $this->assertCount(12, $d['mensal']);
        $this->assertSame(['mes' => '2026-10', 'ativos_inicio' => 5, 'cancelaram' => 1, 'nao_renovaram' => 1, 'recuperados' => 1, 'evasao' => 40.0], end($d['mensal']));
    }

    public function test_detalhes_batem_com_os_numeros(): void
    {
        $detalhe = fn ($k) => app(BiVisaoGeralService::class)->detalhe(self::T1, $k, 'mes', self::HOJE)['body']['data']['itens'];

        $this->assertSame([['HELENA', '2026-10-05']], array_map(fn ($x) => [$x['aluno_nome'], $x['data']], $detalhe('nao_renovaram')));
        $this->assertSame(['IRIS'], array_column($detalhe('renovacao_atrasada'), 'aluno_nome'));
        $this->assertSame([['GUSTAVO', '2026-10-06', 'Financeiro']], array_map(fn ($x) => [$x['aluno_nome'], $x['data'], $x['motivo']], $detalhe('recuperados')));
        $this->assertSame('Horário incompatível', $detalhe('saidas')[0]['motivo']);
    }

    public function test_exporta_csv_para_excel(): void
    {
        $r = app(BiExportacaoService::class)->exportar(self::T1, 'receita_recebida', null, 'mes', self::HOJE);
        $this->assertSame(200, $r['status']);
        $this->assertSame('bi-receita_recebida-2026-10-01-a-2026-10-20.csv', $r['arquivo']);
        $linhas = explode("\n", trim(substr($r['csv'], 3)));
        $this->assertSame('Parcela;Aluno;Plano;Matrícula;"Valor (R$)";Data;Situação', $linhas[0]);
        $this->assertCount(3, $linhas, 'cabeçalho + 2 parcelas, sem a outra academia');
        $this->assertStringContainsString(';100,00;05/10/2026;"Pago em"', implode("\n", $linhas));

        $m = app(BiExportacaoService::class)->exportar(self::T1, null, 'retencao', 'mes', self::HOJE);
        $this->assertStringStartsWith("\xEF\xBB\xBFMês;\"Ativos no início\";Cancelaram;\"Não renovaram\";Recuperados;\"Evasão (%)\"", $m['csv']);
        $this->assertStringContainsString('10/2026;5;1;1;1;40,00', $m['csv']);

        $this->assertSame(422, app(BiExportacaoService::class)->exportar(self::T1, 'senha', null, 'mes', self::HOJE)['status']);
    }

    public function test_csv_neutraliza_formula(): void
    {
        $this->assertStringContainsString("'=HYPERLINK(1)", BiExportacaoService::csv([['aluno_nome' => '=HYPERLINK(1)']]));
    }
}
