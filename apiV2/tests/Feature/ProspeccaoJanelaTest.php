<?php

namespace Tests\Feature;

use App\Repositories\ProspeccaoRepository;
use App\Services\Admin\Prospeccao\ProspeccaoSincronizacaoService as Sinc;
use App\Support\Papel;
use App\Support\ProspeccaoStatus;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ProspeccaoSchema;
use Tests\TestCase;

/**
 * Janela da Recuperação de Alunos configurável em Parâmetros (últimos N meses / há pelo menos M meses).
 */
class ProspeccaoJanelaTest extends TestCase
{
    use ProspeccaoSchema;

    private int $t1;

    private int $admin;

    private int $plano;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaProspeccao();
        (require database_path('migrations/2026_10_22_180000_add_parametros_janela_prospeccao.php'))->up();
        $this->t1 = $this->tenant('Academia Um');
        $this->admin = $this->usuario($this->t1, Papel::ADMIN, 'Admin');
        $this->plano = $this->plano($this->t1);
    }

    private function parametro(string $codigo, int $valor): void
    {
        app(ProspeccaoRepository::class)->salvarParametro($this->t1, $codigo, (string) $valor, null);
    }

    private function sincronizar(): array
    {
        return app(Sinc::class)->sincronizar($this->t1);
    }

    private function cancelada(string $nome, int $diasAtras): int
    {
        $aluno = $this->aluno($this->t1, $nome);
        $this->matricula($this->t1, $aluno, $this->plano, 'cancelada', $this->dia(-$diasAtras));

        return $aluno;
    }

    private function temOportunidade(int $aluno): bool
    {
        return DB::table('prospeccao_oportunidades')->where('aluno_id', $aluno)->exists();
    }

    public function test_padrao_continua_24_meses_sem_carencia(): void
    {
        $this->assertSame(['meses' => 24, 'carencia_meses' => 0], array_intersect_key(app(Sinc::class)->janela($this->t1), ['meses' => 0, 'carencia_meses' => 0]));
        $ontem = $this->cancelada('ONTEM', 1);
        $this->sincronizar();
        $this->assertTrue($this->temOportunidade($ontem));
    }

    public function test_janela_em_meses_limita_quem_entra(): void
    {
        $this->parametro(Sinc::PARAM_JANELA, 6);
        $recente = $this->cancelada('RECENTE', 90);
        $antigo = $this->cancelada('ANTIGO', 240);

        $this->sincronizar();

        $this->assertTrue($this->temOportunidade($recente));
        $this->assertFalse($this->temOportunidade($antigo));
        $this->assertSame(1, app(Sinc::class)->diagnostico($this->t1)['fora_da_janela']);
    }

    public function test_carencia_espera_e_usa_sempre_o_cancelamento_mais_recente(): void
    {
        $this->parametro(Sinc::PARAM_CARENCIA, 2);
        $novo = $this->cancelada('NOVO', 20);
        $maduro = $this->cancelada('MADURO', 90);
        // Cancelou de novo há 10 dias: não pode entrar pela cancelada antiga
        $voltou = $this->cancelada('VOLTOU', 150);
        $this->matricula($this->t1, $voltou, $this->plano, 'cancelada', $this->dia(-10));

        $this->sincronizar();

        $this->assertFalse($this->temOportunidade($novo));
        $this->assertTrue($this->temOportunidade($maduro));
        $this->assertFalse($this->temOportunidade($voltou));
        $this->assertSame(2, app(Sinc::class)->diagnostico($this->t1)['aguardando_carencia']);
    }

    public function test_carencia_nunca_fica_maior_que_a_janela(): void
    {
        $this->parametro(Sinc::PARAM_JANELA, 3);
        $this->parametro(Sinc::PARAM_CARENCIA, 12);

        $this->assertSame(2, app(Sinc::class)->janela($this->t1)['carencia_meses']);
    }

    public function test_reduzir_a_janela_esconde_so_quem_ainda_nao_foi_trabalhado(): void
    {
        $intocado = $this->cancelada('INTOCADO', 200);
        $trabalhado = $this->cancelada('TRABALHADO', 210);
        $this->sincronizar();
        DB::table('prospeccao_oportunidades')->where('aluno_id', $trabalhado)
            ->update(['status' => ProspeccaoStatus::CONTATADO, 'ultimo_contato_em' => now()]);

        $this->parametro(Sinc::PARAM_JANELA, 3);

        $this->getJson('/v2/admin/prospeccao/oportunidades?status=todas', $this->auth($this->admin, $this->t1))
            ->assertOk()
            ->assertJsonPath('janela.meses', 3)
            ->assertJsonPath('paginacao.total', 1)
            ->assertJsonPath('oportunidades.0.aluno_id', $trabalhado);
        $this->assertTrue($this->temOportunidade($intocado), 'nada é apagado; só sai da lista');
    }
}
