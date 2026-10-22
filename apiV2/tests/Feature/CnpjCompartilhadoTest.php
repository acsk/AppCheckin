<?php

namespace Tests\Feature;

use App\Repositories\PlanoSistemaRepository;
use App\Repositories\TenantPlanoRepository;
use App\Repositories\UsuarioRepository;
use App\Services\EntidadeJuridica\EntidadeJuridicaException;
use App\Services\EntidadeJuridica\EntidadeJuridicaService;
use App\Services\SuperAdmin\SuperAdminAcademiaService;
use App\Support\Papel;
use App\Support\PoliticaCnpj;
use App\Support\VinculoCnpj;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\CnpjCompartilhadoSchema;
use Tests\TestCase;

/**
 * CNPJ compartilhado entre tenants + Minha Conta (SQLite).
 */
class CnpjCompartilhadoTest extends TestCase
{
    use CnpjCompartilhadoSchema;

    private const CNPJ_A = '11222333000181';

    private const CNPJ_B = '80791773000172';

    private int $escolaA;

    private int $escolaB;

    private int $adminA;

    private int $adminB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaCnpj();
        // Escola A já existia com CNPJ antes da migration (backfill)
        $this->escolaA = $this->tenant('Escola A', '11.222.333/0001-81');
        $this->rodarMigrationCnpj();
        $this->escolaB = $this->tenant('Escola B');
        $this->adminA = $this->usuario($this->escolaA, Papel::ADMIN, 'Admin A');
        $this->adminB = $this->usuario($this->escolaB, Papel::ADMIN, 'Admin B');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function service(): EntidadeJuridicaService
    {
        return app(EntidadeJuridicaService::class);
    }

    private function politica(string $p): void
    {
        $this->service()->definirPolitica($p, null);
    }

    private function vinculo(int $tenantId): ?object
    {
        return DB::table('tenant_entidade_juridica')->where('tenant_ativo', $tenantId)->first();
    }

    private function autorizar(string $cnpj = self::CNPJ_A): void
    {
        $this->service()->autorizarExcecao($cnpj, 'Mesma empresa, duas unidades', null);
    }

    /** Super admin cria a academia pelo serviço real (contrato/admin mockados). */
    private function criarAcademia(string $nome, string $cnpj): array
    {
        $contratos = Mockery::mock(TenantPlanoRepository::class);
        $contratos->shouldReceive('criar')->andReturn(1);
        $contratos->shouldReceive('buscarPorId')->andReturn(['id' => 1]);
        $planos = Mockery::mock(PlanoSistemaRepository::class);
        $planos->shouldReceive('buscarPorCodigo')->andReturn(['id' => 1, 'tipo_cobranca' => 'gratuito', 'valor' => 0]);
        $usuarios = Mockery::mock(UsuarioRepository::class);
        $usuarios->shouldReceive('emailExists')->andReturn(false);
        $usuarios->shouldReceive('criarUsuarioCompleto')->andReturn(99);

        $service = new SuperAdminAcademiaService(
            app(\App\Repositories\TenantRepository::class), $contratos, $usuarios,
            Mockery::mock(\App\Services\SuperAdmin\SuperAdminPapelService::class), $planos,
        );

        return $service->criarAcademia(['nome' => $nome, 'email' => str()->slug($nome).'@x.com', 'senha_admin' => '123456', 'cnpj' => $cnpj]);
    }

    // ------------------------------------------------------------------ migração

    public function test_migracao_cria_entidade_e_gestor_e_libera_unique_de_tenants(): void
    {
        $v = $this->vinculo($this->escolaA);
        $this->assertSame(VinculoCnpj::GESTOR, $v->papel);
        $this->assertSame(self::CNPJ_A, DB::table('tenants')->where('id', $this->escolaA)->value('cnpj'), 'espelho normalizado');
        $this->assertSame('Escola A', DB::table('entidades_juridicas')->value('razao_social'));
        $this->assertSame(PoliticaCnpj::EXCECAO, $this->service()->politica(), 'padrão = mediante exceção');
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasIndex('tenants', 'unique_tenant_cnpj'));
    }

    // ------------------------------------------------------------------ cadastro / política

    public function test_cadastro_com_cnpj_novo_vira_gestor(): void
    {
        $r = $this->criarAcademia('Escola Nova', self::CNPJ_B);

        $this->assertSame(201, $r['status']);
        $id = (int) $r['body']['academia']['id'];
        $this->assertSame(VinculoCnpj::GESTOR, $this->vinculo($id)->papel);
        $this->assertSame(self::CNPJ_B, $r['body']['academia']['cnpj']);
    }

    public function test_cadastro_duplicado_bloqueado_nao_cria_academia(): void
    {
        $this->politica(PoliticaCnpj::BLOQUEAR);
        $this->autorizar(); // nem com exceção: bloquear é absoluto

        $r = $this->criarAcademia('Escola Dup', self::CNPJ_A);

        $this->assertSame(409, $r['status']);
        $this->assertSame('CNPJ_DUPLICADO_BLOQUEADO', $r['body']['code']);
        $this->assertFalse(DB::table('tenants')->where('nome', 'Escola Dup')->exists());
    }

    public function test_cadastro_duplicado_sem_excecao_e_recusado_no_modo_excecao(): void
    {
        $r = $this->criarAcademia('Escola Dup', self::CNPJ_A);

        $this->assertSame(409, $r['status']);
        $this->assertSame('CNPJ_DUPLICADO_SEM_EXCECAO', $r['body']['code']);
    }

    public function test_cadastro_duplicado_autorizado_por_excecao_entra_como_vinculado(): void
    {
        $this->autorizar();

        $r = $this->criarAcademia('Escola Filial', self::CNPJ_A);

        $this->assertSame(201, $r['status']);
        $id = (int) $r['body']['academia']['id'];
        $this->assertSame(VinculoCnpj::VINCULADO, $this->vinculo($id)->papel);
        $this->assertSame(1, DB::table('entidades_juridicas')->where('cnpj', self::CNPJ_A)->count(), 'dados jurídicos não duplicam');
    }

    public function test_politica_permitir_compartilha_sem_excecao(): void
    {
        $this->politica(PoliticaCnpj::PERMITIR);

        $this->service()->vincular($this->escolaB, self::CNPJ_A, null, 'teste');

        $this->assertSame(VinculoCnpj::VINCULADO, $this->vinculo($this->escolaB)->papel);
    }

    public function test_cnpj_com_digito_verificador_errado_e_recusado(): void
    {
        $this->expectException(EntidadeJuridicaException::class);
        $this->service()->vincular($this->escolaB, '11222333000182', null, 'teste');
    }

    public function test_tenant_nao_troca_cnpj_pelo_meu_contrato(): void
    {
        $this->expectExceptionMessage('A academia já tem CNPJ cadastrado');
        $this->service()->vincular($this->escolaA, self::CNPJ_B, $this->adminA, 'meu_contrato', null, true);
    }

    // ------------------------------------------------------------------ gestor x vinculado

    private function compartilhar(): void
    {
        $this->autorizar();
        $this->service()->vincular($this->escolaB, self::CNPJ_A, null, 'teste');
    }

    public function test_gestor_edita_e_vinculado_ve_a_alteracao(): void
    {
        $this->compartilhar();

        $this->putJson('/v2/admin/minha-conta/cnpj', ['razao_social' => 'Empresa Ltda', 'estado' => 'al', 'cep' => '57000-000'], $this->auth($this->adminA, $this->escolaA))
            ->assertOk()
            ->assertJsonPath('cnpj.dados.razao_social', 'Empresa Ltda')
            ->assertJsonPath('cnpj.dados.estado', 'AL');

        $this->getJson('/v2/admin/minha-conta/cnpj', $this->auth($this->adminB, $this->escolaB))
            ->assertOk()
            ->assertJsonPath('cnpj.dados.razao_social', 'Empresa Ltda')
            ->assertJsonPath('cnpj.papel', 'vinculado')
            ->assertJsonPath('cnpj.pode_editar', false)
            ->assertJsonPath('cnpj.compartilhado', true)
            ->assertJsonMissingPath('cnpj.vinculos');

        $this->assertSame(1, DB::table('entidade_juridica_auditoria')->where('acao', 'dados_juridicos_atualizados')->count());
    }

    public function test_vinculado_nao_edita_dados_juridicos_pela_api(): void
    {
        $this->compartilhar();

        $this->putJson('/v2/admin/minha-conta/cnpj', ['razao_social' => 'Invasor'], $this->auth($this->adminB, $this->escolaB))
            ->assertForbidden()
            ->assertJsonPath('code', 'CNPJ_SOMENTE_LEITURA');

        $this->assertSame('Escola A', DB::table('entidades_juridicas')->value('razao_social'));
    }

    public function test_gestor_nao_altera_o_cnpj_pela_api(): void
    {
        $this->putJson('/v2/admin/minha-conta/cnpj', ['cnpj' => self::CNPJ_B, 'razao_social' => 'X'], $this->auth($this->adminA, $this->escolaA))
            ->assertForbidden()
            ->assertJsonPath('code', 'CNPJ_IMUTAVEL');

        $this->assertSame(self::CNPJ_A, DB::table('entidades_juridicas')->value('cnpj'));
    }

    // ------------------------------------------------------------------ super admin

    public function test_transferencia_de_gestor_pelo_super_admin(): void
    {
        $this->compartilhar();
        $sa = $this->superAdmin();

        $this->postJson('/v2/superadmin/cnpj/'.self::CNPJ_A.'/gestor', ['tenant_id' => $this->escolaB, 'justificativa' => 'Venda da unidade A'], $this->auth($sa, null))
            ->assertOk();

        $this->assertSame(VinculoCnpj::GESTOR, $this->vinculo($this->escolaB)->papel);
        $this->assertSame(VinculoCnpj::VINCULADO, $this->vinculo($this->escolaA)->papel);
        $this->assertSame(1, DB::table('tenant_entidade_juridica')->whereNotNull('gestor_ativo')->count());

        // A agora é só leitura
        $this->putJson('/v2/admin/minha-conta/cnpj', ['razao_social' => 'X'], $this->auth($this->adminA, $this->escolaA))->assertForbidden();
    }

    public function test_transferencia_exige_justificativa_e_tenant_vinculado(): void
    {
        $sa = $this->superAdmin();

        $this->postJson('/v2/superadmin/cnpj/'.self::CNPJ_A.'/gestor', ['tenant_id' => $this->escolaB, 'justificativa' => 'curta'], $this->auth($sa, null))
            ->assertStatus(422)->assertJsonPath('code', 'JUSTIFICATIVA_OBRIGATORIA');
        $this->postJson('/v2/superadmin/cnpj/'.self::CNPJ_A.'/gestor', ['tenant_id' => $this->escolaB, 'justificativa' => 'Justificativa válida'], $this->auth($sa, null))
            ->assertStatus(422)->assertJsonPath('code', 'TENANT_NAO_VINCULADO');
    }

    public function test_revogar_excecao_preserva_vinculos_e_bloqueia_novos(): void
    {
        $this->compartilhar();
        $sa = $this->superAdmin();
        $excecaoId = (int) DB::table('cnpj_excecoes')->value('id');

        $this->postJson("/v2/superadmin/cnpj/excecoes/{$excecaoId}/revogar", ['motivo' => 'Unidade encerrada'], $this->auth($sa, null))
            ->assertOk()
            ->assertJsonPath('excecoes.0.vigente', false);

        $this->assertNotNull($this->vinculo($this->escolaB), 'vínculo existente mantido');
        $c = $this->tenant('Escola C');
        $this->expectException(EntidadeJuridicaException::class);
        $this->service()->vincular($c, self::CNPJ_A, null, 'teste');
    }

    public function test_excecao_registra_responsavel_justificativa_e_auditoria(): void
    {
        $sa = $this->superAdmin();

        $this->postJson('/v2/superadmin/cnpj/'.self::CNPJ_A.'/excecoes', ['justificativa' => 'Grupo com duas unidades'], $this->auth($sa, null))
            ->assertCreated()
            ->assertJsonPath('excecoes.0.autorizado_por_nome', 'Super')
            ->assertJsonPath('excecoes.0.justificativa', 'Grupo com duas unidades');

        $this->postJson('/v2/superadmin/cnpj/'.self::CNPJ_A.'/excecoes', ['justificativa' => 'De novo, de novo'], $this->auth($sa, null))
            ->assertStatus(409);
        $this->assertSame($sa, (int) DB::table('entidade_juridica_auditoria')->where('acao', 'excecao_autorizada')->value('usuario_id'));
    }

    public function test_politica_e_rotas_de_cnpj_so_para_super_admin(): void
    {
        $this->putJson('/v2/superadmin/cnpj/politica', ['politica' => 'permitir'], $this->auth($this->adminA, $this->escolaA))->assertForbidden();
        $this->assertSame(PoliticaCnpj::EXCECAO, $this->service()->politica());

        $sa = $this->superAdmin();
        $this->putJson('/v2/superadmin/cnpj/politica', ['politica' => 'invalida'], $this->auth($sa, null))->assertStatus(422);
        $this->putJson('/v2/superadmin/cnpj/politica', ['politica' => 'bloquear'], $this->auth($sa, null))->assertOk()->assertJsonPath('politica', 'bloquear');
        $this->getJson('/v2/superadmin/cnpj?busca=escola', $this->auth($sa, null))->assertOk()->assertJsonPath('cnpjs.0.cnpj', self::CNPJ_A);
    }

    // ------------------------------------------------------------------ listagem

    public function test_troca_de_cnpj_cria_entidade_com_dados_da_academia_e_a_antiga_sai_da_lista(): void
    {
        DB::table('tenants')->where('id', $this->escolaA)->update(['cidade' => 'Arapiraca', 'estado' => 'AL']);
        $this->autorizar(); // exceção no CNPJ antigo, como em produção
        $sa = $this->superAdmin();

        $this->service()->definirCnpjDoTenant($this->escolaA, self::CNPJ_B, $sa, 'superadmin_academia');

        $nova = DB::table('entidades_juridicas')->where('cnpj', self::CNPJ_B)->first();
        $this->assertSame('Escola A', $nova->razao_social, 'não nasce em branco');
        $this->assertSame('Arapiraca', $nova->cidade);

        $this->getJson('/v2/superadmin/cnpj', $this->auth($sa, null))
            ->assertOk()
            ->assertJsonCount(1, 'cnpjs')
            ->assertJsonPath('cnpjs.0.cnpj', self::CNPJ_B)
            ->assertJsonPath('cnpjs.0.academias.0.nome', 'Escola A')
            ->assertJsonPath('cnpjs.0.academias.0.papel', 'gestor');

        $this->getJson('/v2/superadmin/cnpj?sem_academia=1', $this->auth($sa, null))
            ->assertOk()
            ->assertJsonCount(2, 'cnpjs')
            ->assertJsonPath('cnpjs.1.cnpj', self::CNPJ_A)
            ->assertJsonPath('cnpjs.1.academias', []);
    }

    // ------------------------------------------------------------------ remoção / troca

    public function test_saida_do_gestor_deixa_cnpj_sem_gestor_ate_o_super_admin_definir(): void
    {
        $this->compartilhar();

        $this->service()->desvincular($this->escolaA, null, 'teste');

        $this->assertNull(DB::table('tenants')->where('id', $this->escolaA)->value('cnpj'));
        $this->assertNull(DB::table('tenant_entidade_juridica')->whereNotNull('gestor_ativo')->first(), 'ninguém assume sozinho');
        $this->getJson('/v2/admin/minha-conta/cnpj', $this->auth($this->adminB, $this->escolaB))
            ->assertJsonPath('cnpj.pode_editar', false)
            ->assertJsonPath('cnpj.tem_gestor', false);
        $this->assertTrue(DB::table('entidade_juridica_auditoria')->where('acao', 'vinculo_encerrado')->exists());
    }

    // ------------------------------------------------------------------ concorrência (garantias do banco)

    public function test_banco_recusa_dois_gestores_ativos_para_o_mesmo_cnpj(): void
    {
        $this->compartilhar();
        $entidadeId = (int) DB::table('entidades_juridicas')->value('id');

        $this->expectException(QueryException::class);
        DB::table('tenant_entidade_juridica')->where('tenant_ativo', $this->escolaB)->update(['papel' => 'gestor', 'gestor_ativo' => $entidadeId]);
    }

    public function test_banco_recusa_dois_vinculos_ativos_para_o_mesmo_tenant(): void
    {
        $this->expectException(QueryException::class);
        DB::table('tenant_entidade_juridica')->insert([
            'tenant_id' => $this->escolaA, 'entidade_juridica_id' => 1, 'papel' => 'vinculado', 'status' => 'ativo',
            'vinculado_em' => now(), 'tenant_ativo' => $this->escolaA,
        ]);
    }

    public function test_vincular_de_novo_ao_mesmo_cnpj_e_idempotente(): void
    {
        $this->service()->vincular($this->escolaA, self::CNPJ_A, null, 'teste');
        $this->service()->vincular($this->escolaA, '11.222.333/0001-81', null, 'teste');

        $this->assertSame(1, DB::table('tenant_entidade_juridica')->count());
    }

    // ------------------------------------------------------------------ Minha Conta: escola, perfil, senha

    public function test_cada_tenant_edita_so_os_proprios_dados_operacionais(): void
    {
        $this->compartilhar();

        $this->putJson('/v2/admin/minha-conta/escola', [
            'nome' => 'Escola A Centro', 'email' => 'contato@a.com', 'telefone' => '(82) 99999-0000',
            'id' => $this->escolaB, 'tenant_id' => $this->escolaB, 'cnpj' => self::CNPJ_B, 'ativo' => 0,
        ], $this->auth($this->adminA, $this->escolaA))
            ->assertOk()
            ->assertJsonPath('escola.nome', 'Escola A Centro');

        $a = DB::table('tenants')->find($this->escolaA);
        $b = DB::table('tenants')->find($this->escolaB);
        $this->assertSame('82999990000', $a->telefone);
        $this->assertSame(self::CNPJ_A, $a->cnpj, 'CNPJ não muda pela aba da escola');
        $this->assertSame(1, (int) $a->ativo);
        $this->assertSame('Escola B', $b->nome);
    }

    public function test_acesso_cruzado_admin_de_outra_escola_e_recusado(): void
    {
        // Admin B com token de A (ex.: tenant_id adulterado): não tem papel admin em A
        $this->getJson('/v2/admin/minha-conta/escola', $this->auth($this->adminB, $this->escolaA))->assertForbidden();
        $this->putJson('/v2/admin/minha-conta/cnpj', ['razao_social' => 'X'], $this->auth($this->adminB, $this->escolaA))->assertForbidden();

        // Admin de B que é só aluno em A também não passa (admin.auth olha o maior papel em qualquer academia)
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => $this->escolaA, 'usuario_id' => $this->adminB, 'papel_id' => Papel::ALUNO, 'ativo' => 1]);
        $this->getJson('/v2/admin/minha-conta/cnpj', $this->auth($this->adminB, $this->escolaA))->assertForbidden();
    }

    public function test_perfil_atualiza_nome_e_telefone_e_troca_de_email_exige_senha(): void
    {
        $h = $this->auth($this->adminA, $this->escolaA);

        $this->putJson('/v2/admin/minha-conta/perfil', ['nome' => 'Ana', 'telefone' => '82988887777', 'email' => DB::table('usuarios')->where('id', $this->adminA)->value('email')], $h)
            ->assertOk()->assertJsonPath('perfil.nome', 'Ana');

        $this->putJson('/v2/admin/minha-conta/perfil', ['nome' => 'Ana', 'email' => 'nova@teste.com'], $h)
            ->assertStatus(422)->assertJsonPath('code', 'SENHA_ATUAL_INVALIDA');
        $this->putJson('/v2/admin/minha-conta/perfil', ['nome' => 'Ana', 'email' => DB::table('usuarios')->where('id', $this->adminB)->value('email'), 'senha_atual' => 'senha-atual'], $h)
            ->assertStatus(422);
        $this->putJson('/v2/admin/minha-conta/perfil', ['nome' => 'Ana', 'email' => 'Nova@Teste.com', 'senha_atual' => 'senha-atual'], $h)
            ->assertOk()->assertJsonPath('perfil.email', 'nova@teste.com');
        $this->assertSame('nova@teste.com', DB::table('usuarios')->where('id', $this->adminA)->value('email_global'));
    }

    public function test_alterar_senha(): void
    {
        $h = $this->auth($this->adminA, $this->escolaA);

        $this->putJson('/v2/admin/minha-conta/senha', ['senha_atual' => 'errada', 'nova_senha' => 'nova-senha', 'confirmacao_senha' => 'nova-senha'], $h)
            ->assertStatus(422)->assertJsonPath('code', 'SENHA_ATUAL_INVALIDA');
        $this->putJson('/v2/admin/minha-conta/senha', ['senha_atual' => 'senha-atual', 'nova_senha' => 'nova-senha', 'confirmacao_senha' => 'outra'], $h)
            ->assertStatus(422);
        $this->putJson('/v2/admin/minha-conta/senha', ['senha_atual' => 'senha-atual', 'nova_senha' => 'nova-senha', 'confirmacao_senha' => 'nova-senha'], $h)
            ->assertOk();

        $this->assertTrue(password_verify('nova-senha', DB::table('usuarios')->where('id', $this->adminA)->value('senha_hash')));
    }

    public function test_senha_atual_errada_repetida_trava_por_tentativas(): void
    {
        $h = $this->auth($this->adminA, $this->escolaA);
        $body = ['senha_atual' => 'errada', 'nova_senha' => 'nova-senha', 'confirmacao_senha' => 'nova-senha'];
        for ($i = 0; $i < 5; $i++) {
            $this->putJson('/v2/admin/minha-conta/senha', $body, $h)->assertStatus(422);
        }

        $this->putJson('/v2/admin/minha-conta/senha', ['senha_atual' => 'senha-atual'] + $body, $h)->assertStatus(429);
    }
}
