<?php

namespace Tests\Feature;

use App\Services\AtualizacaoCadastralService;
use App\Support\PoliticaSenha;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $t->boolean('ativo')->default(true);
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
        Schema::create('status_matricula', function (Blueprint $t) {
            $t->increments('id');
            $t->string('codigo');
        });
        Schema::create('matriculas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('aluno_id');
            $t->integer('status_id');
        });
        DB::table('status_matricula')->insert([
            ['id' => 1, 'codigo' => 'ativa'], ['id' => 2, 'codigo' => 'vencida'],
            ['id' => 3, 'codigo' => 'cancelada'], ['id' => 4, 'codigo' => 'pendente'], ['id' => 5, 'codigo' => 'bloqueada'],
        ]);
        (require database_path('migrations/2026_10_24_110000_atualizacao_cadastral.php'))->up();
        (require database_path('migrations/2026_10_25_110000_forcar_validacao_email_campanha.php'))->up();

        DB::table('tenants')->insert([['id' => 1, 'nome' => 'Aqua'], ['id' => 2, 'nome' => 'Outra']]);
        $this->aluno = (int) DB::table('usuarios')->insertGetId([
            'nome' => 'MARIA SILVA', 'email' => 'maria@x.com', 'email_global' => 'maria@x.com',
            'cpf' => '52998224725', 'senha_hash' => password_hash('52998224725', PASSWORD_BCRYPT),
        ]);
        DB::table('alunos')->insert(['usuario_id' => $this->aluno, 'nome' => 'MARIA SILVA']);
        DB::table('matriculas')->insert(['tenant_id' => 1, 'aluno_id' => DB::table('alunos')->where('usuario_id', $this->aluno)->value('id'), 'status_id' => 1]);
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

    public function test_cadastro_unico_conta_e_responde_uma_vez_em_varias_academias(): void
    {
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => 2, 'usuario_id' => $this->aluno, 'papel_id' => 1, 'ativo' => 1]);
        $alunoId = DB::table('alunos')->where('usuario_id', $this->aluno)->value('id');
        DB::table('matriculas')->insert([
            ['tenant_id' => 1, 'aluno_id' => $alunoId, 'status_id' => 1],
            ['tenant_id' => 2, 'aluno_id' => $alunoId, 'status_id' => 1],
        ]);
        $inativo = (int) DB::table('usuarios')->insertGetId(['nome' => 'INATIVO', 'email' => 'i@x.com', 'senha_hash' => 'x', 'ativo' => 0]);
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => 1, 'usuario_id' => $inativo, 'papel_id' => 1, 'ativo' => 1]);
        $id = $this->campanha();

        $this->assertSame(1, $this->service()->listar()['body']['campanhas'][0]['publico']);
        $pendentes = $this->service()->pendentes($id)['body']['pendentes'];
        $this->assertSame([$this->aluno], array_column($pendentes, 'id'));
        $this->assertSame(['Aqua', 'Outra'], $pendentes[0]['academias']);

        $this->service()->responder($this->aluno, $id, ['dados' => ['telefone' => '(82) 98837-6381']]);

        $this->assertNull($this->service()->pendente($this->aluno)['body']['pendente']);
        $this->assertSame([], $this->service()->pendentes($id)['body']['pendentes']);
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

    public function test_apenas_aluno_com_matricula_ativa_na_academia_recebe_campanha(): void
    {
        $id = $this->campanha();
        foreach ([2, 3, 4, 5] as $status) {
            DB::table('matriculas')->update(['status_id' => $status]);
            $this->assertSame(0, $this->service()->listar()['body']['campanhas'][0]['publico']);
            $this->assertSame([], $this->service()->pendentes($id)['body']['pendentes']);
            $this->assertNull($this->service()->pendente($this->aluno)['body']['pendente']);
        }
        DB::table('matriculas')->update(['status_id' => 1, 'tenant_id' => 2]);
        $this->assertNull($this->service()->pendente($this->aluno)['body']['pendente']);
        DB::table('matriculas')->update(['tenant_id' => 1]);
        DB::table('tenant_usuario_papel')->update(['papel_id' => 2]);
        $this->assertNull($this->service()->pendente($this->aluno)['body']['pendente']);
        $this->assertSame(0, $this->service()->listar()['body']['campanhas'][0]['publico']);
        DB::table('tenant_usuario_papel')->update(['papel_id' => 1]);
        $this->assertNotNull($this->service()->pendente($this->aluno)['body']['pendente']);
        DB::table('matriculas')->delete();
        $this->assertNull($this->service()->pendente($this->aluno)['body']['pendente']);
    }

    public function test_matricula_ativa_em_outra_academia_nao_atende_campanha_restrita(): void
    {
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => 2, 'usuario_id' => $this->aluno, 'papel_id' => 1, 'ativo' => 1]);
        $id = $this->campanha(['todas_academias' => false, 'academias' => [2]]);
        $this->assertSame(0, $this->service()->listar()['body']['campanhas'][0]['publico']);
        $this->assertSame([], $this->service()->pendentes($id)['body']['pendentes']);
        $this->assertNull($this->service()->pendente($this->aluno)['body']['pendente']);
    }

    public function test_inativo_nao_recebe_campanha_nem_conta_no_progresso(): void
    {
        $id = $this->campanha();
        $this->assertSame(200, $this->service()->responder($this->aluno, $id, ['dados' => ['telefone' => '82999999999']])['status']);
        DB::table('usuarios')->where('id', $this->aluno)->update(['ativo' => 0]);
        $c = $this->service()->listar()['body']['campanhas'][0];
        $this->assertSame(0, $c['publico']);
        $this->assertSame(0, $c['respostas']);
        $this->assertSame([], $this->service()->pendentes($id)['body']['pendentes']);
        $novo = $this->campanha();
        $this->assertNull($this->service()->pendente($this->aluno)['body']['pendente']);
        $this->assertSame(409, $this->service()->responder($this->aluno, $novo, ['dados' => ['telefone' => '82999999999']])['status']);

        DB::table('usuarios')->where('id', $this->aluno)->update(['ativo' => 1]);
        DB::table('tenant_usuario_papel')->where('usuario_id', $this->aluno)->update(['ativo' => 0]);
        $this->assertNull($this->service()->pendente($this->aluno)['body']['pendente']);
        foreach ($this->service()->listar()['body']['campanhas'] as $campanha) {
            $this->assertSame(0, $campanha['publico']);
            $this->assertSame(0, $campanha['respostas']);
        }
    }

    public function test_exclusao_exige_autenticacao(): void
    {
        $id = $this->campanha();
        $this->deleteJson("/v2/superadmin/atualizacoes-cadastrais/{$id}")->assertUnauthorized();
        $this->assertTrue(DB::table('atualizacoes_cadastrais')->where('id', $id)->exists());
    }

    public function test_excluir_apaga_so_campanha_e_dependencias_sem_desfazer_cadastro(): void
    {
        $id = $this->campanha(['todas_academias' => false, 'academias' => [1]]);
        $outra = $this->campanha();
        $this->assertSame(200, $this->service()->responder($this->aluno, $id, ['dados' => ['telefone' => '82999999999']])['status']);
        DB::table('usuarios')->where('id', $this->aluno)->update(['email_verificado_em' => now()]);
        foreach ([$id, $outra, null] as $campanhaId) {
            DB::table('verificacoes_email')->insert([
                'usuario_id' => $this->aluno, 'email' => 'maria@x.com', 'codigo_hash' => 'hash',
                'atualizacao_id' => $campanhaId, 'expira_em' => now()->addMinutes(15), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->service()->encerrar($id);
        $this->assertSame(200, $this->service()->excluir($id)['status']);
        $this->assertFalse(DB::table('atualizacoes_cadastrais')->where('id', $id)->exists());
        $this->assertSame(0, DB::table('atualizacao_cadastral_academias')->where('atualizacao_id', $id)->count());
        $this->assertSame(0, DB::table('atualizacao_cadastral_respostas')->where('atualizacao_id', $id)->count());
        $this->assertSame(0, DB::table('verificacoes_email')->where('atualizacao_id', $id)->count());
        $this->assertSame(2, DB::table('verificacoes_email')->count());
        $u = DB::table('usuarios')->where('id', $this->aluno)->first();
        $this->assertSame('82999999999', $u->telefone);
        $this->assertNotNull($u->email_verificado_em);
        $this->assertSame('maria@x.com', $u->email);
        $this->assertTrue(password_verify('52998224725', $u->senha_hash));
        $this->assertSame(404, $this->service()->excluir($id)['status']);
        $this->assertSame(404, $this->service()->pendentes($id)['status']);
        $this->assertSame(200, $this->service()->excluir($outra)['status']);
        $this->assertNull($this->service()->pendente($this->aluno)['body']['pendente']);
    }

    public function test_email_ja_verificado_continua_dispensado_sem_forcar(): void
    {
        DB::table('usuarios')->where('id', $this->aluno)->update(['email_verificado_em' => now()->subHour()]);
        $id = $this->campanha(['campos' => [], 'validar_email' => true]);
        $p = $this->service()->pendente($this->aluno)['body']['pendente'];
        $this->assertFalse($p['forcar_validacao_email']);
        $this->assertTrue($p['email_verificado']);
        $this->assertSame(200, $this->service()->responder($this->aluno, $id, [])['status']);
    }

    public function test_forcar_validacao_exige_codigo_novo_da_propria_campanha(): void
    {
        DB::table('usuarios')->where('id', $this->aluno)->update(['email_verificado_em' => now()->subHour()]);
        $anterior = [
            'usuario_id' => $this->aluno, 'email' => 'maria@x.com', 'codigo_hash' => password_hash('123456', PASSWORD_BCRYPT),
            'expira_em' => now()->addMinutes(15), 'validado_em' => now()->subMinutes(5), 'created_at' => now()->subMinutes(10), 'updated_at' => now(),
        ];
        DB::table('verificacoes_email')->insert($anterior);
        $id = $this->campanha(['campos' => [], 'validar_email' => false, 'forcar_validacao_email' => true]);
        $outra = $this->campanha(['campos' => [], 'forcar_validacao_email' => true]);
        DB::table('verificacoes_email')->insert($anterior + ['atualizacao_id' => $outra]);

        $p = $this->service()->pendente($this->aluno)['body']['pendente'];
        $this->assertTrue($p['validar_email']);
        $this->assertTrue($p['forcar_validacao_email']);
        $this->assertFalse($p['email_verificado']);
        $this->assertSame(422, $this->service()->responder($this->aluno, $id, [])['status']);
        $this->assertNotNull(DB::table('usuarios')->where('id', $this->aluno)->value('email_verificado_em'));

        // Nem código pendente de outra campanha pode ser confirmado na campanha atual.
        DB::table('verificacoes_email')->insert(array_replace($anterior, ['atualizacao_id' => $outra, 'validado_em' => null]));
        $this->assertSame(422, $this->service()->validarCodigoEmail($this->aluno, 'maria@x.com', '123456')['status']);
        DB::table('verificacoes_email')->insert(array_replace($anterior, ['atualizacao_id' => $id, 'validado_em' => null]));
        $this->assertSame(200, $this->service()->validarCodigoEmail($this->aluno, 'maria@x.com', '123456')['status']);
        $this->assertSame(200, $this->service()->responder($this->aluno, $id, [])['status']);
        // Uma campanha nova não aceita a confirmação feita na anterior.
        DB::table('verificacoes_email')->where('atualizacao_id', $outra)->delete();
        $this->assertSame(422, $this->service()->responder($this->aluno, $outra, [])['status']);
    }

    public function test_envio_forcado_vincula_codigo_a_campanha_pendente(): void
    {
        config(['mail.default' => 'array', 'appcheckin.mail_guard_enabled' => true, 'appcheckin.mail_from_address' => '', 'mail.from.address' => 'mail@appcheckin.com.br']);
        $id = $this->campanha(['campos' => [], 'forcar_validacao_email' => true]);
        $this->assertSame(200, $this->service()->enviarCodigoEmail($this->aluno, 'maria@x.com')['status']);
        $this->assertSame($id, (int) DB::table('verificacoes_email')->value('atualizacao_id'));
    }

    public function test_ativar_forca_em_campanha_concluida_nao_reabre_resposta(): void
    {
        DB::table('usuarios')->where('id', $this->aluno)->update(['email_verificado_em' => now()]);
        $id = $this->campanha(['campos' => [], 'validar_email' => true]);
        $this->assertSame(200, $this->service()->responder($this->aluno, $id, [])['status']);
        $r = $this->service()->salvar($id, ['titulo' => 'Validar novamente', 'campos' => [], 'forcar_validacao_email' => true], 99);
        $this->assertSame(200, $r['status']);
        $this->assertTrue($r['body']['campanha']['forcar_validacao_email']);
        $this->assertNull($this->service()->pendente($this->aluno)['body']['pendente']);
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
        // Mailer real em memória, com o mail guard ligado: o assunto precisa estar liberado
        config(['mail.default' => 'array', 'appcheckin.mail_guard_enabled' => true, 'appcheckin.mail_from_address' => '', 'mail.from.address' => 'mail@appcheckin.com.br']);
        DB::table('usuarios')->insert(['nome' => 'OUTRO', 'email' => 'outro@x.com', 'email_global' => 'outro@x.com', 'senha_hash' => 'x']);

        $this->assertSame(422, $this->service()->enviarCodigoEmail($this->aluno, 'outro@x.com')['status']);
        $this->assertSame(200, $this->service()->enviarCodigoEmail($this->aluno, 'nova@x.com')['status']);
        $this->assertSame(429, $this->service()->enviarCodigoEmail($this->aluno, 'nova@x.com')['status']);
        $this->assertSame(1, DB::table('verificacoes_email')->count());
        $enviados = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $enviados);
        $this->assertStringContainsString('nova@x.com', $enviados[0]->getEnvelope()->getRecipients()[0]->getAddress());
    }

    public function test_envio_bloqueado_pelo_mail_guard_nao_finge_que_enviou(): void
    {
        config(['mail.default' => 'array', 'appcheckin.mail_guard_enabled' => true, 'appcheckin.mail_allowed_subjects' => ['Outro assunto']]);

        $r = $this->service()->enviarCodigoEmail($this->aluno, 'nova@x.com');

        $this->assertSame(503, $r['status']);
        $this->assertSame(0, DB::table('verificacoes_email')->count());
    }
}
