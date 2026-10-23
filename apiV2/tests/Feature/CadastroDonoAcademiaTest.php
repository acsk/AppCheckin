<?php

namespace Tests\Feature;

use App\Support\Papel;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CnpjCompartilhadoSchema;
use Tests\TestCase;

/**
 * Academia criada pelo site: o dono completa o cadastro em Minha conta, vê o responsável e o documento
 * da academia, e a tela de Usuários fica só com o suporte.
 */
class CadastroDonoAcademiaTest extends TestCase
{
    use CnpjCompartilhadoSchema;

    private int $escola;

    private int $dono;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaCnpj();
        $this->escola = $this->tenant('Escola Site');
        $this->rodarMigrationCnpj();
        (require database_path('migrations/2026_10_22_170000_create_tenant_usuario_permissoes.php'))->up();
        (require database_path('migrations/2026_10_23_100000_dados_pessoais_em_usuarios.php'))->up();
        $this->dono = $this->usuario($this->escola, Papel::ADMIN, 'Dono Site');
        (require database_path('migrations/2026_10_22_190000_estrutura_permissoes.php'))->up();
        // Como o checkout do site grava: só cidade/UF e o responsável com CPF (pessoa física)
        DB::table('tenants')->where('id', $this->escola)->update([
            'cidade' => 'Maceió', 'estado' => 'AL', 'responsavel_nome' => 'Dono Site', 'responsavel_cpf' => '52998224725',
            'responsavel_telefone' => '82988887777', 'responsavel_email' => 'dono@site.com',
        ]);
    }

    private function h(): array
    {
        return $this->auth($this->dono, $this->escola);
    }

    private function email(): string
    {
        return (string) DB::table('usuarios')->where('id', $this->dono)->value('email');
    }

    public function test_perfil_lista_o_que_falta_e_o_dono_completa(): void
    {
        $this->getJson('/v2/admin/minha-conta/perfil', $this->h())->assertOk()
            ->assertJsonPath('perfil.pendencias', ['CPF', 'telefone', 'data de nascimento', 'endereço']);

        $r = $this->putJson('/v2/admin/minha-conta/perfil', [
            'nome' => 'Dono Site', 'email' => $this->email(), 'telefone' => '(82) 98888-7777', 'whatsapp' => '(82) 98888-7777',
            'cpf' => '529.982.247-25', 'data_nascimento' => '1985-03-10',
            'cep' => '57035-000', 'logradouro' => 'Rua A', 'numero' => '10', 'bairro' => 'Ponta Verde', 'cidade' => 'Maceió', 'estado' => 'al',
        ], $this->h())->assertOk();

        $r->assertJsonPath('perfil.pendencias', [])->assertJsonPath('perfil.cpf', '52998224725')->assertJsonPath('perfil.estado', 'AL');
        $this->assertSame('1985-03-10', $r->json('perfil.data_nascimento'));
    }

    public function test_cpf_ja_cadastrado_so_o_suporte_troca(): void
    {
        DB::table('usuarios')->where('id', $this->dono)->update(['cpf' => '52998224725']);

        $this->putJson('/v2/admin/minha-conta/perfil', ['nome' => 'Dono Site', 'email' => $this->email(), 'cpf' => '111.444.777-35'], $this->h())
            ->assertStatus(422)->assertJsonPath('errors.0', 'Para trocar o CPF, fale com o suporte do Check-in');
    }

    public function test_valida_documento_e_datas(): void
    {
        $this->putJson('/v2/admin/minha-conta/perfil', [
            'nome' => 'Dono Site', 'email' => $this->email(), 'cpf' => '111.111.111-11', 'whatsapp' => '8233334444',
            'data_nascimento' => '2030-01-01', 'cep' => '570', 'estado' => 'ZZ',
        ], $this->h())->assertStatus(422)
            ->assertJsonPath('errors', ['WhatsApp deve ser um celular com DDD (11 dígitos)', 'CPF inválido', 'Data de nascimento inválida', 'CEP deve ter 8 dígitos', 'UF inválida']);
    }

    public function test_escola_mostra_responsavel_documento_e_pendencias(): void
    {
        $this->getJson('/v2/admin/minha-conta/escola', $this->h())->assertOk()
            ->assertJsonPath('escola.documento', ['tipo' => 'cpf', 'numero' => '52998224725'])
            ->assertJsonPath('escola.responsavel.nome', 'Dono Site')
            ->assertJsonPath('escola.pendencias', ['telefone', 'endereço da unidade']);
    }

    public function test_tela_de_usuarios_so_para_o_suporte(): void
    {
        $this->getJson('/v2/tenant/usuarios', $this->h())->assertOk();
        $this->postJson('/v2/tenant/usuarios', ['nome' => 'X', 'email' => 'x@x.com', 'senha' => 'segredo1'], $this->h())
            ->assertForbidden()->assertJsonPath('code', 'APENAS_SUPORTE');
        $this->putJson("/v2/tenant/usuarios/{$this->dono}", ['nome' => 'X'], $this->h())->assertForbidden();
        $this->deleteJson("/v2/tenant/usuarios/{$this->dono}", [], $this->h())->assertForbidden();
    }
}
