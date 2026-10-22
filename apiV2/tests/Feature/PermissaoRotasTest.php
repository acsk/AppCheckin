<?php

namespace Tests\Feature;

use App\Support\Papel;
use App\Support\Permissao as P;
use App\Support\PermissaoRotas;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CnpjCompartilhadoSchema;
use Tests\TestCase;

/**
 * Fase 3: cada rota do painel exige a permissão do mapa (PermissaoRotas), na academia do token.
 * Rotas de teste com os mesmos caminhos das reais passam pelos mesmos middlewares.
 */
class PermissaoRotasTest extends TestCase
{
    use CnpjCompartilhadoSchema;

    private int $escola;

    private int $outra;

    /** @var array<string, int> */
    private array $u = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaCnpj();
        (require database_path('migrations/2026_10_22_160000_create_acessos_suporte_tables.php'))->up();
        (require database_path('migrations/2026_10_22_170000_create_tenant_usuario_permissoes.php'))->up();
        $this->escola = $this->tenant('Escola A');
        $this->outra = $this->tenant('Escola B');
        $this->u['principal'] = $this->usuario($this->escola, Papel::ADMIN, 'Dono');
        (require database_path('migrations/2026_10_22_190000_estrutura_permissoes.php'))->up();
        foreach (['admin' => Papel::ADMIN, 'financeiro' => Papel::FINANCEIRO, 'comercial' => Papel::COMERCIAL, 'professor' => Papel::PROFESSOR, 'aluno' => Papel::ALUNO] as $nome => $papel) {
            $this->u[$nome] = $this->usuario($this->escola, $papel, ucfirst($nome));
        }
        // Admin em outra academia e só aluno nesta: antes passava como admin aqui
        $this->u['admin_de_outra'] = $this->usuario($this->outra, Papel::ADMIN, 'Admin B');
        \Illuminate\Support\Facades\DB::table('tenant_usuario_papel')->insert(['tenant_id' => $this->escola, 'usuario_id' => $this->u['admin_de_outra'], 'papel_id' => Papel::ALUNO, 'ativo' => 1]);

        Route::prefix('v2/admin')->middleware(['jwt.auth', 'admin.auth', 'permissao.rota'])->group(function () {
            Route::get('/alunos/zz/teste/permissao', fn () => 'ok');
            Route::post('/alunos/zz/teste/permissao', fn () => 'ok');
            Route::get('/pagamentos-plano/zz/teste/permissao', fn () => 'ok');
            Route::post('/contas-receber/zz/teste/permissao', fn () => 'ok');
            Route::get('/meu-contrato/zz/teste/permissao', fn () => 'ok');
            Route::get('/turmas/zz/teste/permissao', fn () => 'ok');
            Route::put('/parametros/zz/teste/permissao', fn () => 'ok');
            Route::get('/rota-sem-regra-teste', fn () => 'ok');
        });
    }

    private function codigo(string $quem, string $metodo, string $caminho): int
    {
        return $this->json($metodo, '/v2/admin/'.$caminho, [], $this->auth($this->u[$quem], $this->escola))->getStatusCode();
    }

    public function test_mapa_de_rotas(): void
    {
        $this->assertSame(P::ALUNOS_VER, PermissaoRotas::exigida('GET', 'v2/admin/alunos/10'));
        $this->assertSame(P::ALUNOS_EDITAR, PermissaoRotas::exigida('PUT', 'v2/admin/alunos/10'));
        $this->assertSame(P::FINANCEIRO_AVANCADO, PermissaoRotas::exigida('DELETE', 'v2/admin/matriculas/10'));
        $this->assertSame(P::FINANCEIRO_BAIXAR, PermissaoRotas::exigida('POST', 'v2/admin/matriculas/10/pagamentos/3/confirmar'));
        $this->assertSame(P::MATRICULAS_GERENCIAR, PermissaoRotas::exigida('POST', 'v2/admin/matriculas/10/cancelar'));
        $this->assertSame(P::MATRICULAS_VER, PermissaoRotas::exigida('GET', 'v2/admin/planos/2/ciclos'));
        $this->assertSame(P::CONFIGURACOES_EDITAR, PermissaoRotas::exigida('POST', 'v2/admin/planos'));
        $this->assertSame(P::CONTA_GERENCIAR, PermissaoRotas::exigida('POST', 'v2/admin/meu-contrato/trocar-plano'));
        $this->assertSame(PermissaoRotas::LIVRE, PermissaoRotas::exigida('PUT', 'v2/admin/minha-conta/senha'));
        $this->assertSame(P::ALUNOS_EDITAR, PermissaoRotas::exigida('POST', 'v2/tenant/usuarios'));
        $this->assertNull(PermissaoRotas::exigida('GET', 'v2/admin/rota-nova-qualquer'));
    }

    public function test_cada_papel_entra_so_no_que_pode(): void
    {
        $esperado = [
            // [método, caminho] => quem passa
            'GET alunos/zz/teste/permissao' => ['principal', 'admin', 'financeiro'],
            'POST alunos/zz/teste/permissao' => ['principal', 'admin'],
            'GET pagamentos-plano/zz/teste/permissao' => ['principal', 'admin', 'financeiro'],
            'POST contas-receber/zz/teste/permissao' => ['principal', 'admin', 'financeiro'],
            'GET meu-contrato/zz/teste/permissao' => ['principal'],
            'GET turmas/zz/teste/permissao' => ['principal', 'admin'],
            'PUT parametros/zz/teste/permissao' => ['principal', 'admin'],
            'GET rota-sem-regra-teste' => ['principal', 'admin'],
        ];
        foreach ($esperado as $rota => $permitidos) {
            [$metodo, $caminho] = explode(' ', $rota);
            foreach (array_keys($this->u) as $quem) {
                $status = $this->codigo($quem, $metodo, $caminho);
                $this->assertSame(in_array($quem, $permitidos, true) ? 200 : 403, $status, "{$quem} em {$rota}");
            }
        }
    }

    public function test_permissao_concedida_abre_a_rota_e_retirada_fecha(): void
    {
        \Illuminate\Support\Facades\DB::table('tenant_usuario_permissoes')->insert([
            ['tenant_id' => $this->escola, 'usuario_id' => $this->u['comercial'], 'permissao' => P::ALUNOS_VER, 'efeito' => 'conceder'],
            ['tenant_id' => $this->escola, 'usuario_id' => $this->u['admin'], 'permissao' => P::AULAS_GERENCIAR, 'efeito' => 'retirar'],
        ]);

        $this->assertSame(200, $this->codigo('comercial', 'GET', 'alunos/zz/teste/permissao'));
        $this->assertSame(403, $this->codigo('admin', 'GET', 'turmas/zz/teste/permissao'));
    }

    public function test_perfil_e_senha_ficam_livres_para_a_equipe(): void
    {
        $this->getJson('/v2/admin/minha-conta/permissoes', $this->auth($this->u['financeiro'], $this->escola))
            ->assertOk()->assertJsonPath('papeis', [Papel::FINANCEIRO]);
        $this->getJson('/v2/admin/minha-conta/permissoes', $this->auth($this->u['aluno'], $this->escola))->assertForbidden();
    }
}
