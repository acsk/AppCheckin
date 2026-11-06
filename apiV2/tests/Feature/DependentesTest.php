<?php

namespace Tests\Feature;

use App\Http\Middleware\JwtAuthenticate;
use App\Services\DependenteLoginService;
use App\Services\JwtService;
use App\Services\Mobile\MobileDependenteService;
use App\Support\Pagador;
use App\Support\Pessoa;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

/** Pai com conta cadastra o filho sem e-mail nem CPF e usa o app no perfil dele. */
class DependentesTest extends TestCase
{
    use RequiresSqlite;

    private const TENANT = 5;

    private const PAI = 10;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();
        Pessoa::limparCache();

        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
            $t->string('email')->nullable();
            $t->string('email_global')->nullable()->unique();
            $t->string('senha_hash')->default('');
            $t->string('cpf', 14)->nullable()->unique();
            $t->string('telefone')->nullable();
            $t->string('whatsapp')->nullable();
            $t->date('data_nascimento')->nullable();
            $t->string('sexo')->nullable();
            foreach (['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'foto_caminho'] as $c) {
                $t->string($c)->nullable();
            }
            foreach (['responsavel_nome', 'responsavel_cpf', 'responsavel_email', 'responsavel_telefone', 'responsavel_parentesco'] as $c) {
                $t->string($c)->nullable();
            }
            $t->integer('responsavel_usuario_id')->nullable();
            $t->string('dependente_pin_hash')->nullable();
            $t->unsignedTinyInteger('dependente_pin_tentativas')->default(0);
            $t->dateTime('dependente_pin_bloqueado_ate')->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('alunos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('usuario_id');
            $t->string('nome')->default('');
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('tenant_usuario_papel', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('usuario_id');
            $t->integer('papel_id');
            $t->boolean('ativo')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'usuario_id', 'papel_id']);
        });
        Schema::create('tenants', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
            foreach (['slug', 'email', 'telefone', 'whatsapp_links', 'instagram_url'] as $c) {
                $t->string($c)->nullable();
            }
            $t->boolean('ativo')->default(true);
        });
        Schema::create('notificacoes', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('usuario_id');
            $t->string('tipo');
            $t->string('titulo');
            $t->text('mensagem')->nullable();
            $t->text('dados')->nullable();
            $t->boolean('lida')->default(false);
            $t->timestamps();
        });
        DB::statement('CREATE TABLE IF NOT EXISTS rate_limits (id INTEGER PRIMARY KEY, ip TEXT, action TEXT, attempts INTEGER, expires_at TEXT, created_at TEXT, updated_at TEXT)');
        Schema::create('papeis', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
        });

        DB::table('tenants')->insert(['id' => self::TENANT, 'nome' => 'Aqua', 'ativo' => 1]);
        DB::table('papeis')->insert(['id' => 1, 'nome' => 'Aluno']);
        DB::table('usuarios')->insert([
            'id' => self::PAI, 'nome' => 'ANDRE CABRAL', 'email' => 'andre@exemplo.com', 'email_global' => 'andre@exemplo.com',
            'cpf' => '52998224725', 'telefone' => '+5582999990000', 'data_nascimento' => '1985-01-01',
        ]);
        DB::table('alunos')->insert(['usuario_id' => self::PAI, 'nome' => 'ANDRE CABRAL']);
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => self::TENANT, 'usuario_id' => self::PAI, 'papel_id' => 1, 'ativo' => 1]);
    }

    private function service(): MobileDependenteService
    {
        return app(MobileDependenteService::class);
    }

    private function filho(array $extra = []): array
    {
        return $extra + ['nome' => 'Joao Cabral', 'data_nascimento' => now()->subYears(11)->format('Y-m-d'), 'sexo' => 'masculino', 'parentesco' => 'pai'];
    }

    public function test_cadastra_filho_sem_email_nem_cpf_com_dados_do_pai(): void
    {
        $r = $this->service()->criar(self::PAI, self::TENANT, $this->filho());

        $this->assertSame(201, $r['status'], json_encode($r['body']));
        $id = $r['body']['data']['dependente']['usuario_id'];
        $u = DB::table('usuarios')->find($id);
        $this->assertNull($u->email);
        $this->assertNull($u->cpf);
        $this->assertSame(self::PAI, (int) $u->responsavel_usuario_id);
        $this->assertSame('52998224725', $u->responsavel_cpf);
        $this->assertSame('ANDRE CABRAL', $u->responsavel_nome);
        $this->assertSame('pai', $u->responsavel_parentesco);
        $this->assertNotNull($r['body']['data']['dependente']['aluno_id'], 'vira aluno da academia');

        // Segundo filho sem e-mail/CPF não colide nos índices únicos
        $r2 = $this->service()->criar(self::PAI, self::TENANT, $this->filho(['nome' => 'Ana Cabral']));
        $this->assertSame(201, $r2['status']);
        $this->assertCount(2, $this->service()->listar(self::PAI, self::TENANT)['body']['data']['dependentes']);
    }

    public function test_recusa_o_email_ou_cpf_do_proprio_responsavel_e_maior_de_idade(): void
    {
        $r = $this->service()->criar(self::PAI, self::TENANT, $this->filho(['cpf' => '529.982.247-25']));
        $this->assertSame(422, $r['status']);
        $this->assertStringContainsString('seu CPF', $r['body']['error']);

        $r = $this->service()->criar(self::PAI, self::TENANT, $this->filho(['email' => 'ANDRE@exemplo.com']));
        $this->assertStringContainsString('seu e-mail', $r['body']['error']);

        $r = $this->service()->criar(self::PAI, self::TENANT, $this->filho(['data_nascimento' => '2000-01-01']));
        $this->assertStringContainsString('menos de 18', $r['body']['error']);
    }

    public function test_cpf_ja_cadastrado_pela_recepcao_com_este_responsavel_e_vinculado(): void
    {
        DB::table('usuarios')->insert(['id' => 30, 'nome' => 'JOAO CABRAL', 'cpf' => '11144477735', 'responsavel_cpf' => '52998224725']);
        DB::table('usuarios')->insert(['id' => 31, 'nome' => 'OUTRA CRIANCA', 'cpf' => '39053344705', 'responsavel_cpf' => '11144477735']);

        $r = $this->service()->criar(self::PAI, self::TENANT, $this->filho(['cpf' => '11144477735']));
        $this->assertSame(200, $r['status']);
        $this->assertSame(self::PAI, (int) DB::table('usuarios')->where('id', 30)->value('responsavel_usuario_id'));

        $r = $this->service()->criar(self::PAI, self::TENANT, $this->filho(['cpf' => '39053344705']));
        $this->assertSame(409, $r['status'], 'criança de outro responsável não é sequestrada');
    }

    public function test_entrar_no_perfil_e_voltar(): void
    {
        $id = $this->service()->criar(self::PAI, self::TENANT, $this->filho())['body']['data']['dependente']['usuario_id'];
        $jwt = app(JwtService::class);

        $entrar = $this->service()->entrar(self::PAI, $id, self::TENANT);
        $this->assertSame(200, $entrar['status']);
        $claims = (array) $jwt->decode($entrar['body']['token']);
        $this->assertSame($id, (int) $claims['user_id']);
        $this->assertSame(self::PAI, (int) $claims['responsavel_id']);
        $this->assertNotNull($claims['aluno_id']);
        $this->assertSame(self::TENANT, $entrar['body']['tenants'][0]['tenant']['id']);

        $voltar = $this->service()->voltar($id, self::PAI, self::TENANT);
        $claims = (array) $jwt->decode($voltar['body']['token']);
        $this->assertSame(self::PAI, (int) $claims['user_id']);
        $this->assertArrayNotHasKey('responsavel_id', $claims);

        // Outra pessoa não entra no perfil
        $this->assertSame(404, $this->service()->entrar(99, $id, self::TENANT)['status']);
    }

    public function test_cobranca_do_menor_sai_no_nome_do_responsavel(): void
    {
        $id = $this->service()->criar(self::PAI, self::TENANT, $this->filho())['body']['data']['dependente']['usuario_id'];

        $dados = Pagador::aplicar(['usuario_id' => $id, 'aluno_nome' => 'JOAO CABRAL', 'aluno_cpf' => null, 'aluno_email' => null]);
        $this->assertSame('52998224725', $dados['aluno_cpf']);
        $this->assertSame('ANDRE CABRAL', $dados['aluno_nome']);
        $this->assertSame('andre@exemplo.com', $dados['aluno_email']);

        // Adulto sem responsável continua pagando como ele mesmo
        $this->assertSame('X', Pagador::aplicar(['usuario_id' => self::PAI, 'aluno_nome' => 'X'])['aluno_nome']);
    }

    public function test_pin_recusa_sequencia_repeticao_e_data_de_nascimento(): void
    {
        $nasc = '2015-08-31';
        $this->assertNotNull(MobileDependenteService::pinFraco('123', $nasc));
        $this->assertNotNull(MobileDependenteService::pinFraco('1234', $nasc));
        $this->assertNotNull(MobileDependenteService::pinFraco('7777', $nasc));
        $this->assertNotNull(MobileDependenteService::pinFraco('3108', $nasc));
        $this->assertNotNull(MobileDependenteService::pinFraco('2015', $nasc));
        $this->assertNotNull(MobileDependenteService::pinFraco('310815', $nasc));
        $this->assertNull(MobileDependenteService::pinFraco('4826', $nasc));
    }

    public function test_login_proprio_com_email_do_responsavel_e_pin(): void
    {
        $id = $this->service()->criar(self::PAI, self::TENANT, $this->filho())['body']['data']['dependente']['usuario_id'];
        $login = app(DependenteLoginService::class);

        // Sem PIN o perfil não aparece
        $this->assertSame([], $login->perfis('andre@exemplo.com', '1.1.1.1')['body']['perfis']);

        $this->assertSame(200, $this->service()->definirPin(self::PAI, $id, '4826')['status']);
        $this->assertSame(404, $this->service()->definirPin(99, $id, '4826')['status'], 'só o responsável define');

        $perfis = $login->perfis('ANDRE@exemplo.com', '1.1.1.1')['body']['perfis'];
        $this->assertSame([['id' => $id, 'nome' => 'Joao']], $perfis);
        $this->assertSame([], $login->perfis('outro@exemplo.com', '1.1.1.1')['body']['perfis']);

        $errado = $login->entrar('andre@exemplo.com', $id, '1111', null, '1.1.1.1');
        $this->assertSame(401, $errado['status']);
        $this->assertStringContainsString('Restam 4', $errado['body']['message']);

        $ok = $login->entrar('andre@exemplo.com', $id, '4826', null, '1.1.1.1');
        $this->assertSame(200, $ok['status'], json_encode($ok['body']));
        $claims = (array) app(JwtService::class)->decode($ok['body']['token']);
        $this->assertSame($id, (int) $claims['user_id']);
        $this->assertSame(self::PAI, (int) $claims['dependente_login']);
        $this->assertArrayNotHasKey('responsavel_id', $claims, 'não vira o pai');
        $this->assertSame(1, DB::table('notificacoes')->where('usuario_id', self::PAI)->where('tipo', 'acesso_dependente')->count());

        // O e-mail do pai não abre o perfil de outro dependente nem do próprio pai
        $this->assertSame(404, $login->entrar('andre@exemplo.com', self::PAI, '4826', null, '1.1.1.1')['status']);
    }

    public function test_cinco_pins_errados_travam_e_avisam(): void
    {
        $id = $this->service()->criar(self::PAI, self::TENANT, $this->filho())['body']['data']['dependente']['usuario_id'];
        $this->service()->definirPin(self::PAI, $id, '4826');
        $login = app(DependenteLoginService::class);

        for ($i = 0; $i < 5; $i++) {
            $r = $login->entrar('andre@exemplo.com', $id, '0001', null, '2.2.2.'.$i);
        }
        $this->assertSame('PIN_BLOQUEADO', $r['body']['code']);
        // Travado: nem o PIN certo entra
        $this->assertSame('PIN_BLOQUEADO', $login->entrar('andre@exemplo.com', $id, '4826', null, '3.3.3.3')['body']['code']);
        $this->assertSame(1, DB::table('notificacoes')->where('titulo', 'PIN errado várias vezes')->count());

        // Trocar o PIN destrava
        $this->service()->definirPin(self::PAI, $id, '5937');
        $this->assertSame(200, $login->entrar('andre@exemplo.com', $id, '5937', null, '3.3.3.3')['status']);
    }

    public function test_rotas_de_compra_bloqueadas_no_login_do_dependente(): void
    {
        foreach (['v2/mobile/comprar-plano', 'v2/mobile/pagamento/pix', 'v2/mobile/pacotes/3/contratar', 'v2/mobile/pacotes/contratos/9/pagar',
            'v2/mobile/recorrencia/contratar', 'v2/mobile/recorrencia/contratos/2/cancelar', 'v2/mobile/migrar-plano', 'v2/mobile/assinatura/4/cancelar'] as $p) {
            $this->assertTrue(JwtAuthenticate::ehCompraOuPagamento($p), $p);
        }
        foreach (['v2/mobile/checkin', 'v2/mobile/perfil', 'v2/mobile/simular-migracao', 'v2/mobile/verificar-pagamento'] as $p) {
            $this->assertFalse(JwtAuthenticate::ehCompraOuPagamento($p), $p);
        }
    }

    public function test_responsavel_edita_o_dependente(): void
    {
        $id = $this->service()->criar(self::PAI, self::TENANT, $this->filho())['body']['data']['dependente']['usuario_id'];

        $r = $this->service()->atualizar(self::PAI, $id, $this->filho(['nome' => 'Joao Pedro Cabral', 'parentesco' => 'tutor', 'email' => 'joao@exemplo.com']));
        $this->assertSame(200, $r['status'], json_encode($r['body']));
        $u = DB::table('usuarios')->find($id);
        $this->assertSame('JOAO PEDRO CABRAL', $u->nome);
        $this->assertSame('tutor', $u->responsavel_parentesco);
        $this->assertSame('joao@exemplo.com', $u->email);
        $this->assertSame('JOAO PEDRO CABRAL', DB::table('alunos')->where('usuario_id', $id)->value('nome'));

        // Apagar o e-mail volta a NULL; outra pessoa não edita; o e-mail do pai é recusado
        $this->assertSame(200, $this->service()->atualizar(self::PAI, $id, $this->filho(['email' => '']))['status']);
        $this->assertNull(DB::table('usuarios')->where('id', $id)->value('email'));
        $this->assertSame(404, $this->service()->atualizar(99, $id, $this->filho())['status']);
        $this->assertSame(422, $this->service()->atualizar(self::PAI, $id, $this->filho(['email' => 'andre@exemplo.com']))['status']);
    }

    public function test_menor_cadastrado_no_painel_sem_vinculo_e_ligado_ao_abrir_dependentes(): void
    {
        // Cadastro antigo (antes da migration): tem o CPF do responsável, mas não o vínculo
        DB::table('usuarios')->insert(['id' => 459, 'nome' => 'FILHO DO ANDRE', 'responsavel_cpf' => '52998224725', 'data_nascimento' => '2015-08-31']);
        DB::table('alunos')->insert(['usuario_id' => 459, 'nome' => 'FILHO DO ANDRE']);
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => self::TENANT, 'usuario_id' => 459, 'papel_id' => 1, 'ativo' => 1]);

        $lista = $this->service()->listar(self::PAI, self::TENANT)['body']['data']['dependentes'];
        $this->assertSame([459], array_column($lista, 'usuario_id'));
        $this->assertSame(self::PAI, (int) DB::table('usuarios')->where('id', 459)->value('responsavel_usuario_id'));

        // O login "Sou dependente" também liga; com PIN o perfil aparece
        DB::table('usuarios')->where('id', 459)->update(['responsavel_usuario_id' => null]);
        $this->service()->definirPin(self::PAI, 459, '4826'); // sem vínculo: 404
        $this->assertSame([], app(DependenteLoginService::class)->perfis('andre@exemplo.com', '9.9.9.9')['body']['perfis']);
        $this->service()->listar(self::PAI, self::TENANT);
        $this->service()->definirPin(self::PAI, 459, '4826');
        $this->assertSame([['id' => 459, 'nome' => 'Filho']], app(DependenteLoginService::class)->perfis('andre@exemplo.com', '9.9.9.9')['body']['perfis']);
    }

    public function test_admin_define_e_remove_o_pin_pelo_painel(): void
    {
        $dep = $this->service()->criar(self::PAI, self::TENANT, $this->filho())['body']['data']['dependente'];
        $admin = app(\App\Services\Admin\AdminAlunoService::class);

        $this->assertSame(422, $admin->definirPinDependente($dep['aluno_id'], self::TENANT, '1234')['status'], 'PIN fraco');
        $ok = $admin->definirPinDependente($dep['aluno_id'], self::TENANT, '4826');
        $this->assertSame(200, $ok['status'], json_encode($ok['body']));
        $this->assertSame(1, DB::table('notificacoes')->where('usuario_id', self::PAI)->where('titulo', 'like', 'PIN de Joao%')->count());
        $this->assertSame([['id' => $dep['usuario_id'], 'nome' => 'Joao']], app(DependenteLoginService::class)->perfis('andre@exemplo.com', '8.8.8.8')['body']['perfis']);

        // Outra academia não mexe; o pai (adulto) não recebe PIN
        $this->assertSame(404, $admin->definirPinDependente($dep['aluno_id'], 999, '4826')['status']);
        $alunoPai = (int) DB::table('alunos')->where('usuario_id', self::PAI)->value('id');
        $this->assertSame(422, $admin->definirPinDependente($alunoPai, self::TENANT, '4826')['status']);

        $this->assertSame(200, $admin->removerPinDependente($dep['aluno_id'], self::TENANT)['status']);
        $this->assertSame([], app(DependenteLoginService::class)->perfis('andre@exemplo.com', '8.8.8.8')['body']['perfis']);
    }
}
