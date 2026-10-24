<?php

namespace Tests\Feature;

use App\Services\AtualizacaoCadastralService;
use App\Support\PoliticaSenha;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class AtualizacaoCadastralTest extends TestCase
{
    use RequiresSqlite;

    private int $aluno;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();

        Schema::create('tenants', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
        });
        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
            $t->string('email');
            $t->string('email_global')->nullable();
            foreach (['cpf', 'telefone', 'whatsapp', 'sexo', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'foto_caminho'] as $c) {
                $t->string($c)->nullable();
            }
            $t->date('data_nascimento')->nullable();
            $t->string('senha_hash');
            $t->timestamps();
        });
        Schema::create('alunos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('usuario_id');
            $t->string('nome');
            $t->timestamps();
        });
        Schema::create('tenant_usuario_papel', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('usuario_id');
            $t->integer('papel_id');
            $t->boolean('ativo')->default(true);
        });
        (require database_path('migrations/2026_10_24_110000_atualizacao_cadastral.php'))->up();

        DB::table('tenants')->insert([['id' => 1, 'nome' => 'Aqua'], ['id' => 2, 'nome' => 'Outra']]);
        $this->aluno = (int) DB::table('usuarios')->insertGetId([
            'nome' => 'MARIA SILVA', 'email' => 'maria@x.com', 'email_global' => 'maria@x.com',
            'cpf' => '52998224725', 'senha_hash' => password_hash('52998224725', PASSWORD_BCRYPT),
        ]);
        DB::table('alunos')->insert(['usuario_id' => $this->aluno, 'nome' => 'MARIA SILVA']);
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => 1, 'usuario_id' => $this->aluno, 'papel_id' => 1, 'ativo' => 1]);
    }

    private function service(): AtualizacaoCadastralService
    {
        return app(AtualizacaoCadastralService::class);
    }

    private function campanha(array $extra = []): int
    {
        $r = $this->service()->salvar(null, $extra + [
            'titulo' => 'Atualize seu cadastro',
            'campos' => ['telefone', 'data_nascimento', 'cidade'],
            'campos_obrigatorios' => ['telefone'],
            'todas_academias' => true,
        ], 99);
        $this->assertSame(201, $r['status'], json_encode($r['body']));

        return $r['body']['campanha']['id'];
    }

    public function test_campanha_precisa_pedir_algo(): void
    {
        $r = $this->service()->salvar(null, ['titulo' => 'Vazia', 'campos' => []], 99);

        $this->assertSame(422, $r['status']);
    }

    public function test_aluno_da_academia_recebe_a_pendencia_com_os_campos(): void
    {
        $id = $this->campanha();

        $p = $this->service()->pendente($this->aluno)['body']['pendente'];

        $this->assertSame($id, $p['id']);
        $this->assertSame(['telefone', 'data_nascimento', 'cidade'], array_column($p['campos'], 'campo'));
        $this->assertTrue($p['campos'][0]['obrigatorio']);
        $this->assertFalse($p['campos'][1]['obrigatorio']);
    }

    public function test_campanha_de_outra_academia_nao_alcanca(): void
    {
        $this->campanha(['todas_academias' => false, 'academias' => [2]]);

        $this->assertNull($this->service()->pendente($this->aluno)['body']['pendente']);
    }

    public function test_campo_obrigatorio_vazio_e_recusado(): void
    {
        $id = $this->campanha();

        $r = $this->service()->responder($this->aluno, $id, ['dados' => ['telefone' => '', 'cidade' => 'Arapiraca']]);

        $this->assertSame(422, $r['status']);
        $this->assertArrayHasKey('telefone', $r['body']['campos']);
    }

    public function test_responder_grava_os_dados_e_some_a_pendencia(): void
    {
        $id = $this->campanha();

        $r = $this->service()->responder($this->aluno, $id, ['dados' => ['telefone' => '(82) 98837-6381', 'data_nascimento' => '1990-05-10', 'cidade' => 'Arapiraca']]);

        $this->assertSame(200, $r['status'], json_encode($r['body']));
        $u = DB::table('usuarios')->where('id', $this->aluno)->first();
        $this->assertSame('82988376381', $u->telefone);
        $this->assertSame('ARAPIRACA', $u->cidade);
        $this->assertNull($this->service()->pendente($this->aluno)['body']['pendente']);
        $this->assertSame(1, $this->service()->listar()['body']['campanhas'][0]['respostas']);
    }

    public function test_troca_de_senha_segue_a_politica_e_recusa_o_cpf(): void
    {
        $id = $this->campanha(['campos' => [], 'campos_obrigatorios' => [], 'trocar_senha' => true, 'senha_nivel' => 'forte', 'senha_min_caracteres' => 10]);

        $fraca = $this->service()->responder($this->aluno, $id, ['senha_atual' => '52998224725', 'nova_senha' => 'abc12345']);
        $this->assertSame(422, $fraca['status']);
        $this->assertArrayHasKey('nova_senha', $fraca['body']['campos']);

        $errada = $this->service()->responder($this->aluno, $id, ['senha_atual' => 'outra', 'nova_senha' => 'Forte#Senha2026']);
        $this->assertArrayHasKey('senha_atual', $errada['body']['campos']);

        $ok = $this->service()->responder($this->aluno, $id, ['senha_atual' => '52998224725', 'nova_senha' => 'Forte#Senha2026']);
        $this->assertSame(200, $ok['status'], json_encode($ok['body']));
        $this->assertTrue(password_verify('Forte#Senha2026', DB::table('usuarios')->where('id', $this->aluno)->value('senha_hash')));
    }

    public function test_validacao_de_email_exige_o_codigo(): void
    {
        $id = $this->campanha(['campos' => ['email'], 'campos_obrigatorios' => ['email'], 'validar_email' => true]);

        $sem = $this->service()->responder($this->aluno, $id, ['dados' => ['email' => 'nova@x.com']]);
        $this->assertSame(422, $sem['status']);
        $this->assertArrayHasKey('email', $sem['body']['campos']);

        DB::table('verificacoes_email')->insert([
            'usuario_id' => $this->aluno, 'email' => 'nova@x.com', 'codigo_hash' => password_hash('123456', PASSWORD_BCRYPT),
            'expira_em' => now()->addMinutes(15), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame(422, $this->service()->validarCodigoEmail($this->aluno, 'nova@x.com', '000000')['status']);
        $this->assertSame(200, $this->service()->validarCodigoEmail($this->aluno, 'nova@x.com', '123456')['status']);

        $ok = $this->service()->responder($this->aluno, $id, ['dados' => ['email' => 'nova@x.com']]);
        $this->assertSame(200, $ok['status'], json_encode($ok['body']));
        $u = DB::table('usuarios')->where('id', $this->aluno)->first();
        $this->assertSame('nova@x.com', $u->email);
        $this->assertSame('nova@x.com', $u->email_global);
        $this->assertNotNull($u->email_verificado_em);
    }

    public function test_politica_de_senha(): void
    {
        $this->assertSame([], PoliticaSenha::validar('abcdef', 'basica', 6));
        $this->assertNotSame([], PoliticaSenha::validar('abcdefgh', 'media', 8));
        $this->assertSame([], PoliticaSenha::validar('abcd1234', 'media', 8));
        $this->assertNotSame([], PoliticaSenha::validar('529.982.247-25', 'basica', 6, '52998224725'));
    }

    public function test_envio_do_codigo_respeita_o_intervalo_e_email_de_outro(): void
    {
        Mail::fake();
        DB::table('usuarios')->insert(['nome' => 'OUTRO', 'email' => 'outro@x.com', 'email_global' => 'outro@x.com', 'senha_hash' => 'x']);

        $this->assertSame(422, $this->service()->enviarCodigoEmail($this->aluno, 'outro@x.com')['status']);
        $this->assertSame(200, $this->service()->enviarCodigoEmail($this->aluno, 'nova@x.com')['status']);
        $this->assertSame(429, $this->service()->enviarCodigoEmail($this->aluno, 'nova@x.com')['status']);
        $this->assertSame(1, DB::table('verificacoes_email')->count());
    }
}
