<?php

namespace Tests\Feature;

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
}
