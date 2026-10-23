<?php

namespace Tests\Feature;

use App\Repositories\AdminAlunoRepository;
use App\Repositories\AlunoRepository;
use App\Repositories\UsuarioRepository;
use App\Support\Papel;
use App\Support\Pessoa;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CnpjCompartilhadoSchema;
use Tests\TestCase;

/**
 * Fase 2: usuarios é a fonte dos dados pessoais. Leitura com fallback na cópia antiga de alunos;
 * gravação move o valor para usuarios e limpa a cópia.
 */
class DadosPessoaisTest extends TestCase
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
            foreach (['telefone', 'whatsapp', 'cpf', 'data_nascimento', 'sexo', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'foto_caminho', 'foto_url', 'foto_base64'] as $c) {
                $t->string($c)->nullable();
            }
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        $this->escola = $this->tenant('Escola A');
    }

    /** @return array{0: int, 1: int} usuario_id, aluno_id */
    private function aluno(array $copia = [], array $usuario = []): array
    {
        $u = $this->usuario($this->escola, Papel::ALUNO, 'Maria Souza');
        if ($usuario) {
            DB::table('usuarios')->where('id', $u)->update($usuario);
        }
        $a = (int) DB::table('alunos')->insertGetId(['usuario_id' => $u, 'nome' => 'MARIA SOUZA'] + $copia);

        return [$u, $a];
    }

    public function test_leitura_usa_usuarios_e_cai_na_copia_antiga(): void
    {
        [, $a] = $this->aluno(['whatsapp' => '82911112222', 'telefone' => '8233334444'], ['telefone' => '82955556666']);

        $aluno = app(AdminAlunoRepository::class)->findById($a, $this->escola);

        $this->assertSame('82955556666', $aluno['telefone'], 'usuarios vence');
        $this->assertSame('82911112222', $aluno['whatsapp'], 'cópia antiga enquanto o consolidar não roda');
    }

    public function test_gravar_move_para_usuarios_e_apagar_nao_ressuscita(): void
    {
        [$u, $a] = $this->aluno(['whatsapp' => '82911112222', 'cep' => '57035000']);
        $repo = app(AdminAlunoRepository::class);

        $repo->update($a, ['whatsapp' => '(82) 98888-7777', 'cep' => null, 'nome' => 'Maria Souza Lima']);

        $this->assertSame('82988887777', DB::table('usuarios')->where('id', $u)->value('whatsapp'));
        $this->assertNull(DB::table('alunos')->where('id', $a)->value('whatsapp'), 'cópia limpa');
        $this->assertNull($repo->findById($a)['cep'], 'apagado continua apagado');
        $this->assertSame('MARIA SOUZA LIMA', DB::table('alunos')->where('id', $a)->value('nome'), 'nome espelhado');
        $this->assertSame('MARIA SOUZA LIMA', DB::table('usuarios')->where('id', $u)->value('nome'));
    }

    public function test_foto_do_app_vai_para_usuarios_e_a_nova_vence_a_antiga(): void
    {
        [$u, $a] = $this->aluno(['foto_caminho' => '/uploads/fotos/aluno_novo.jpg'], ['foto_caminho' => '/uploads/fotos/usuario_slim.jpg']);
        $alunos = app(AlunoRepository::class);

        $this->assertSame('/uploads/fotos/aluno_novo.jpg', $alunos->findForTenant($u, $this->escola)['foto_caminho'], 'na transição a do aluno é a mais nova');

        $alunos->updateFotoCaminho($a, '/uploads/fotos/upload.jpg');

        $this->assertSame('/uploads/fotos/upload.jpg', DB::table('usuarios')->where('id', $u)->value('foto_caminho'));
        $this->assertNull(DB::table('alunos')->where('id', $a)->value('foto_caminho'));
        $this->assertSame('/uploads/fotos/upload.jpg', $alunos->findForTenant($u, $this->escola)['foto_caminho']);
    }

    public function test_cpf_de_outro_cadastro_nao_e_gravado(): void
    {
        $this->aluno([], ['cpf' => '52998224725']);
        [$u] = $this->aluno();

        $this->expectException(\DomainException::class);
        app(UsuarioRepository::class)->gravarDadosPessoais($u, ['cpf' => '529.982.247-25']);
    }

    public function test_cadastro_novo_nao_copia_dados_para_alunos(): void
    {
        $id = app(UsuarioRepository::class)->createUsuario([
            'nome' => 'Joana', 'email' => 'joana@x.com', 'senha' => 'segredo1', 'cpf' => '111.444.777-35', 'telefone' => '82999990000', 'cep' => '57035000',
        ], $this->escola, Papel::ALUNO);

        $this->assertSame('11144477735', DB::table('usuarios')->where('id', $id)->value('cpf'));
        $aluno = DB::table('alunos')->where('usuario_id', $id)->first();
        $this->assertNotNull($aluno);
        $this->assertNull($aluno->cpf);
        $this->assertNull($aluno->telefone);
    }

    public function test_coluna_ausente_vira_null_sem_quebrar_a_query(): void
    {
        Schema::table('alunos', fn (Blueprint $t) => $t->dropColumn('sexo'));
        Pessoa::limparCache();
        [, $a] = $this->aluno();

        $this->assertArrayHasKey('sexo', app(AdminAlunoRepository::class)->findById($a));
    }
}
