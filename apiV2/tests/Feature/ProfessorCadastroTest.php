<?php

namespace Tests\Feature;

use App\Repositories\AdminProfessorRepository;
use App\Support\Papel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CnpjCompartilhadoSchema;
use Tests\TestCase;

/**
 * Cadastro completo do professor: dados pessoais/profissionais (professores), endereço (usuarios),
 * dados da academia (professor_tenant, professor_modalidades) e vínculo com seed.
 */
class ProfessorCadastroTest extends TestCase
{
    use CnpjCompartilhadoSchema;

    private int $escola;

    private int $dono;

    private int $professor;

    private int $usuarioProfessor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaCnpj();
        $this->rodarMigrationCnpj();
        (require database_path('migrations/2026_10_22_170000_create_tenant_usuario_permissoes.php'))->up();
        Schema::create('professores', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('usuario_id')->nullable()->unique();
            $t->string('nome');
            $t->string('cpf')->nullable()->unique();
            $t->string('email')->nullable();
            $t->string('telefone')->nullable();
            $t->string('foto_url')->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('modalidades', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->string('nome');
        });
        Schema::create('dias', function (Blueprint $t) {
            $t->increments('id');
            $t->date('data');
        });
        Schema::create('turmas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('professor_id')->nullable();
            $t->integer('modalidade_id')->nullable();
            $t->integer('dia_id')->nullable();
            $t->string('nome')->nullable();
            $t->time('horario_inicio')->nullable();
            $t->time('horario_fim')->nullable();
            $t->boolean('ativo')->default(true);
        });
        (require database_path('migrations/2026_10_22_220000_dados_profissionais_do_professor.php'))->up();

        $this->escola = $this->tenant('Escola A');
        $this->dono = $this->usuario($this->escola, Papel::ADMIN, 'Dono');
        $this->usuarioProfessor = $this->usuario($this->escola, Papel::PROFESSOR, 'Carla Souza');
        (require database_path('migrations/2026_10_22_190000_estrutura_permissoes.php'))->up();
        $this->professor = (int) app(AdminProfessorRepository::class)->garantirPerfil($this->usuarioProfessor);
    }

    private function h(): array
    {
        return $this->auth($this->dono, $this->escola);
    }

    private function modalidade(string $nome, ?int $tenantId = null): int
    {
        return (int) DB::table('modalidades')->insertGetId(['tenant_id' => $tenantId ?? $this->escola, 'nome' => $nome]);
    }

    public function test_vinculos_vem_do_seed(): void
    {
        $this->getJson('/v2/admin/professores/vinculos', $this->h())->assertOk()
            ->assertJsonCount(5, 'vinculos')
            ->assertJsonPath('vinculos.0.codigo', 'clt')
            ->assertJsonPath('vinculos.1.nome', 'Prestador (PJ)');
    }

    public function test_salva_e_devolve_cadastro_completo(): void
    {
        $muay = $this->modalidade('Muay Thai');
        $jiu = $this->modalidade('Jiu-Jitsu');
        $pj = (int) DB::table('vinculos_professor')->where('codigo', 'pj')->value('id');

        $this->putJson("/v2/admin/professores/{$this->professor}", [
            'nome' => 'Carla Souza',
            'cpf' => '529.982.247-25',
            'data_nascimento' => '1990-05-20',
            'telefone' => '(82) 99999-1234',
            'whatsapp' => '(82) 98888-1234',
            'cref' => '012345-g/al',
            'cref_validade' => '2027-12-31',
            'vinculo_id' => $pj,
            'admitido_em' => '2024-02-01',
            'modalidade_ids' => [$muay, $jiu],
            'emergencia_nome' => 'João Souza',
            'emergencia_parentesco' => 'Irmão',
            'emergencia_telefone' => '82 3333-4444',
            'cep' => '57035-000',
            'logradouro' => 'Rua A',
            'numero' => '10',
            'bairro' => 'Ponta Verde',
            'cidade' => 'Maceió',
            'estado' => 'al',
            'observacoes' => 'Prefere turmas da manhã',
        ], $this->h())->assertOk();

        $p = $this->getJson("/v2/admin/professores/{$this->professor}", $this->h())->assertOk()->json('professor');
        $this->assertSame('52998224725', $p['cpf']);
        $this->assertSame('012345-G/AL', $p['cref']);
        $this->assertSame('82988881234', $p['whatsapp']);
        $this->assertSame('8233334444', $p['emergencia_telefone']);
        $this->assertSame('pj', $p['vinculo_codigo']);
        $this->assertSame('2024-02-01', $p['admitido_em']);
        $this->assertSame('57035000', $p['cep']);
        $this->assertSame('AL', $p['estado']);
        $this->assertSame('82999991234', $p['telefone']);
        $this->assertEqualsCanonicalizing([$muay, $jiu], $p['modalidade_ids']);

        // Trocar modalidades substitui; limpar campo opcional grava null
        $this->putJson("/v2/admin/professores/{$this->professor}", ['modalidade_ids' => [$jiu], 'cref' => ''], $this->h())->assertOk();
        $p = $this->getJson("/v2/admin/professores/{$this->professor}", $this->h())->json('professor');
        $this->assertSame([$jiu], $p['modalidade_ids']);
        $this->assertNull($p['cref']);
    }

    public function test_recusa_dados_invalidos(): void
    {
        $outra = $this->modalidade('Boxe', $this->tenant('Escola B'));

        $r = $this->putJson("/v2/admin/professores/{$this->professor}", [
            'cpf' => '111.111.111-11',
            'cref' => '12-G/ZZ',
            'modalidade_ids' => [$outra],
            'vinculo_id' => 999,
        ], $this->h())->assertStatus(422);

        $this->assertSame(['cpf', 'cref', 'vinculo_id', 'modalidade_ids'], array_keys($r->json('campos')));
        $this->assertSame(0, DB::table('professor_modalidades')->count());
    }

    public function test_lista_traz_modalidades_e_proxima_aula(): void
    {
        $muay = $this->modalidade('Muay Thai');
        DB::table('professor_modalidades')->insert(['professor_id' => $this->professor, 'modalidade_id' => $muay]);
        $amanha = DB::table('dias')->insertGetId(['data' => now()->addDay()->toDateString()]);
        $depois = DB::table('dias')->insertGetId(['data' => now()->addDays(3)->toDateString()]);
        $longe = DB::table('dias')->insertGetId(['data' => now()->addDays(10)->toDateString()]);
        foreach ([[$depois, '07:00:00'], [$amanha, '19:00:00'], [$longe, '08:00:00']] as [$dia, $hora]) {
            DB::table('turmas')->insert(['tenant_id' => $this->escola, 'professor_id' => $this->professor, 'modalidade_id' => $muay,
                'dia_id' => $dia, 'horario_inicio' => $hora, 'horario_fim' => '20:00:00', 'ativo' => 1]);
        }

        $p = $this->getJson('/v2/admin/professores', $this->h())->assertOk()->json('professores.0');
        $this->assertSame([['id' => $muay, 'nome' => 'Muay Thai']], $p['modalidades']);
        $this->assertSame(2, $p['aulas_semana']);
        $this->assertSame(now()->addDay()->toDateString(), $p['proxima_aula']['data']);
        $this->assertSame('19:00', $p['proxima_aula']['horario_inicio']);
        $this->assertSame('Muay Thai', $p['proxima_aula']['nome']);
    }

    public function test_desativar_vale_so_nesta_academia(): void
    {
        $this->putJson("/v2/admin/professores/{$this->professor}", ['ativo' => false], $this->h())->assertOk()
            ->assertJsonPath('professor.vinculo_ativo', 0);

        $this->assertSame(1, (int) DB::table('professores')->where('id', $this->professor)->value('ativo'));
    }

    public function test_excluir_tira_da_academia_e_recusa_com_aulas(): void
    {
        $turma = DB::table('turmas')->insertGetId(['tenant_id' => $this->escola, 'professor_id' => $this->professor, 'ativo' => 1]);
        $this->deleteJson("/v2/admin/professores/{$this->professor}", [], $this->h())->assertStatus(409);

        DB::table('turmas')->where('id', $turma)->update(['ativo' => 0]);
        $this->deleteJson("/v2/admin/professores/{$this->professor}", [], $this->h())->assertOk();

        $this->assertFalse(DB::table('tenant_usuario_papel')->where(['usuario_id' => $this->usuarioProfessor, 'papel_id' => Papel::PROFESSOR])->exists());
        $this->assertTrue(DB::table('professores')->where('id', $this->professor)->exists(), 'o perfil global fica');
        $this->getJson("/v2/admin/professores/{$this->professor}", $this->h())->assertNotFound();
    }

    public function test_email_disponivel(): void
    {
        $email = DB::table('usuarios')->where('id', $this->usuarioProfessor)->value('email');

        $this->getJson('/v2/admin/professores/email/disponivel?email='.urlencode($email), $this->h())->assertOk()
            ->assertJsonPath('disponivel', false)->assertJsonPath('nome', 'Carla Souza');
        $this->getJson('/v2/admin/professores/email/disponivel?email='.urlencode($email).'&usuario_id='.$this->usuarioProfessor, $this->h())
            ->assertJsonPath('disponivel', true);
        $this->getJson('/v2/admin/professores/email/disponivel?email=livre@x.com', $this->h())->assertJsonPath('disponivel', true);
    }
}
