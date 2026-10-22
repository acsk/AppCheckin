<?php

namespace Tests\Feature;

use App\Services\SuperAdmin\ConsolidacaoPessoasService as C;
use App\Support\Papel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CnpjCompartilhadoSchema;
use Tests\TestCase;

/**
 * Fase 1: alunos/professores → usuarios. Preenche só o que está vazio; conflito e CPF problemático ficam no relatório.
 */
class ConsolidacaoPessoasTest extends TestCase
{
    use CnpjCompartilhadoSchema;

    private int $escola;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaCnpj();
        (require database_path('migrations/2026_10_23_100000_dados_pessoais_em_usuarios.php'))->up();
        Schema::create('alunos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('usuario_id')->unique();
            $t->string('nome');
            foreach (['telefone', 'whatsapp', 'cpf', 'data_nascimento', 'sexo', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'foto_caminho'] as $c) {
                $t->string($c)->nullable();
            }
            $t->timestamps();
        });
        Schema::create('professores', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('usuario_id')->nullable()->unique();
            $t->string('nome');
            foreach (['cpf', 'email', 'telefone', 'whatsapp', 'data_nascimento'] as $c) {
                $t->string($c)->nullable();
            }
            $t->timestamps();
        });
        $this->escola = $this->tenant('Escola A');
    }

    private function pessoa(array $usuario = [], ?array $aluno = null, ?array $professor = null): int
    {
        $id = $this->usuario($this->escola, Papel::ALUNO, $usuario['nome'] ?? 'Fulano de Tal');
        if ($usuario) {
            DB::table('usuarios')->where('id', $id)->update($usuario);
        }
        $nome = DB::table('usuarios')->where('id', $id)->value('nome');
        if ($aluno !== null) {
            DB::table('alunos')->insert(['usuario_id' => $id, 'nome' => $nome] + $aluno);
        }
        if ($professor !== null) {
            DB::table('professores')->insert(['usuario_id' => $id, 'nome' => $nome] + $professor);
        }

        return $id;
    }

    private function item(array $itens, int $id, string $campo): ?array
    {
        foreach ($itens as $i) {
            if ($i['usuario_id'] === $id && $i['campo'] === $campo) {
                return $i;
            }
        }

        return null;
    }

    public function test_preenche_so_o_vazio_e_nunca_sobrescreve(): void
    {
        $id = $this->pessoa(['telefone' => '82999990000'], [
            'telefone' => '(82) 98888-1111', 'whatsapp' => '(82) 98888-1111', 'data_nascimento' => '1990-05-20', 'sexo' => 'Feminino',
            'cpf' => '529.982.247-25', 'foto_caminho' => '/uploads/fotos/aluno_1.jpg',
        ]);

        $service = app(C::class);
        $itens = $service->analisar();
        $this->assertSame(C::CONFLITO, $this->item($itens, $id, 'telefone')['situacao']);
        $this->assertSame(C::PREENCHER, $this->item($itens, $id, 'cpf')['situacao']);

        $this->assertSame(1, $service->aplicar($itens));
        $u = DB::table('usuarios')->find($id);
        $this->assertSame('82999990000', $u->telefone, 'conflito não sobrescreve');
        $this->assertSame('82988881111', $u->whatsapp);
        $this->assertSame('1990-05-20', substr($u->data_nascimento, 0, 10));
        $this->assertSame('feminino', $u->sexo);
        $this->assertSame('52998224725', $u->cpf);
        $this->assertSame('/uploads/fotos/aluno_1.jpg', $u->foto_caminho);

        // Rodar de novo: o que foi preenchido não aparece mais
        $this->assertNull($this->item($service->analisar(), $id, 'cpf'));
    }

    public function test_cpf_invalido_ou_de_outro_usuario_fica_bloqueado(): void
    {
        $this->pessoa(['nome' => 'Dono do CPF', 'cpf' => '52998224725']);
        $outro = $this->pessoa([], ['cpf' => '529.982.247-25']);
        $invalido = $this->pessoa([], ['cpf' => '111.111.111-01']);

        $itens = app(C::class)->analisar();

        $this->assertSame(C::BLOQUEADO, $this->item($itens, $outro, 'cpf')['situacao']);
        $this->assertStringContainsString('caso para unificação', $this->item($itens, $outro, 'cpf')['motivo']);
        $this->assertSame('CPF inválido', $this->item($itens, $invalido, 'cpf')['motivo']);

        app(C::class)->aplicar($itens);
        $this->assertNull(DB::table('usuarios')->where('id', $outro)->value('cpf'));
    }

    public function test_aluno_e_professor_discordando_e_conflito(): void
    {
        $id = $this->pessoa([], ['data_nascimento' => '1990-05-20'], ['data_nascimento' => '1991-05-20', 'whatsapp' => '82977776666']);

        $itens = app(C::class)->analisar();

        $this->assertSame(C::CONFLITO, $this->item($itens, $id, 'data_nascimento')['situacao']);
        $this->assertSame('alunos e professores discordam', $this->item($itens, $id, 'data_nascimento')['motivo']);
        $this->assertSame(C::PREENCHER, $this->item($itens, $id, 'whatsapp')['situacao']);
        $this->assertSame('professores', $this->item($itens, $id, 'whatsapp')['origem']);
    }

    public function test_endereco_vai_em_bloco(): void
    {
        $vazio = $this->pessoa([], ['cep' => '57035-000', 'logradouro' => 'Rua A', 'numero' => '10', 'cidade' => 'Maceió', 'estado' => 'al']);
        $parcial = $this->pessoa(['cidade' => 'RECIFE'], ['cep' => '57035-000', 'logradouro' => 'Rua A', 'cidade' => 'Maceió']);

        $service = app(C::class);
        $itens = $service->analisar();
        $this->assertSame(C::PREENCHER, $this->item($itens, $vazio, 'endereco')['situacao']);
        $this->assertSame(C::CONFLITO, $this->item($itens, $parcial, 'endereco')['situacao'], 'não mistura rua de um com cidade de outro');

        $service->aplicar($itens);
        $u = DB::table('usuarios')->find($vazio);
        $this->assertSame(['57035000', 'RUA A', '10', 'MACEIÓ', 'AL'], [$u->cep, $u->logradouro, $u->numero, $u->cidade, $u->estado]);
        $this->assertNull(DB::table('usuarios')->where('id', $parcial)->value('logradouro'));
    }

    public function test_nome_e_email_so_aparecem_no_relatorio(): void
    {
        $id = $this->pessoa(['nome' => 'ANDRE SILVA'], ['nome' => 'x'], ['email' => 'outro@x.com']);
        DB::table('alunos')->where('usuario_id', $id)->update(['nome' => 'André Silva']);

        $service = app(C::class);
        $itens = $service->analisar();
        $this->assertSame(C::CONFLITO, $this->item($itens, $id, 'nome')['situacao']);
        $this->assertSame(C::CONFLITO, $this->item($itens, $id, 'email')['situacao']);

        $service->aplicar($itens);
        $this->assertSame('ANDRE SILVA', DB::table('usuarios')->where('id', $id)->value('nome'));
    }

    public function test_comando_sem_aplicar_nao_grava(): void
    {
        $id = $this->pessoa([], ['whatsapp' => '82988881111']);

        $this->artisan('pessoas:consolidar')->expectsOutputToContain('Nada foi gravado')->assertSuccessful();
        $this->assertNull(DB::table('usuarios')->where('id', $id)->value('whatsapp'));

        $this->artisan('pessoas:consolidar', ['--aplicar' => true, '--force' => true])->assertSuccessful();
        $this->assertSame('82988881111', DB::table('usuarios')->where('id', $id)->value('whatsapp'));
    }
}
