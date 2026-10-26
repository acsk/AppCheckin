<?php

namespace Tests\Feature;

use App\Repositories\MobilePerfilRepository;
use App\Services\Admin\AdminTenantService;
use App\Support\TenantInstagram;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RequiresSqlite;
use Tests\TestCase;

class TenantInstagramTest extends TestCase
{
    use RequiresSqlite;

    private const URL = 'https://www.instagram.com/ciadanatacao?utm_source=qr&cplk=bzBkampodHQxdXZ1';

    private const WHATSAPP = [['nome' => 'Avisos', 'url' => 'https://chat.whatsapp.com/Grupo123']];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireSqlite();
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->string('slug');
            $t->string('email')->nullable();
            $t->string('telefone')->nullable();
            $t->text('whatsapp_links')->nullable();
            $t->boolean('ativo')->default(true);
        });
        Schema::create('tenant_usuario_papel', function (Blueprint $t) {
            $t->integer('tenant_id');
            $t->integer('usuario_id');
            $t->boolean('ativo')->default(true);
        });
        DB::table('tenants')->insert([
            ['id' => 2, 'nome' => 'CIA DA NATAÇÃO', 'slug' => 'cia-da-nataCAo', 'whatsapp_links' => json_encode(self::WHATSAPP)],
            ['id' => 3, 'nome' => 'Outra academia', 'slug' => 'outra', 'whatsapp_links' => null],
        ]);
        (require database_path('migrations/2026_10_26_100000_instagram_academia.php'))->up();
    }

    public function test_migration_configura_apenas_cia_e_preserva_whatsapp(): void
    {
        $c = app(AdminTenantService::class)->obterWhatsappLinks(2);
        $this->assertSame(self::URL, $c['body']['data']['instagram_url']);
        $this->assertSame(self::WHATSAPP, $c['body']['data']['whatsapp_links']);
        $this->assertNull(DB::table('tenants')->where('id', 3)->value('instagram_url'));
    }

    public function test_salvar_e_remover_instagram_preserva_whatsapp_e_outra_academia(): void
    {
        $service = app(AdminTenantService::class);
        $url = 'https://www.instagram.com/outro.perfil/';
        $r = $service->salvarWhatsappLinks(3, ['instagram_url' => $url]);
        $this->assertSame(200, $r['status']);
        $this->assertSame($url, $service->obterWhatsappLinks(3)['body']['data']['instagram_url']);
        $this->assertSame(self::URL, $service->obterWhatsappLinks(2)['body']['data']['instagram_url']);
        $this->assertSame(200, $service->salvarWhatsappLinks(2, ['instagram_url' => null])['status']);
        $c = $service->obterWhatsappLinks(2)['body']['data'];
        $this->assertNull($c['instagram_url']);
        $this->assertSame(self::WHATSAPP, $c['whatsapp_links']);
    }

    public function test_cliente_antigo_preserva_instagram_ao_salvar_whatsapp(): void
    {
        $r = app(AdminTenantService::class)->salvarWhatsappLinks(2, ['whatsapp_links' => []]);
        $this->assertSame(200, $r['status']);
        $this->assertSame(self::URL, $r['body']['data']['instagram_url']);
        $this->assertSame([], $r['body']['data']['whatsapp_links']);
    }

    public function test_rejeita_urls_externas_e_de_conteudo_sem_alterar_cadastro(): void
    {
        foreach (['javascript:alert(1)', 'http://instagram.com/perfil', 'https://instagram.com.evil.test/perfil',
            'https://instagram.com@evil.test/perfil', 'https://evil.test@instagram.com/perfil',
            'https://www.instagram.com/p/ABC/', 'https://www.instagram.com/', [], str_repeat('x', 2049)] as $url) {
            $this->assertNotNull(TenantInstagram::validar($url));
            $this->assertSame(422, app(AdminTenantService::class)->salvarWhatsappLinks(2, ['instagram_url' => $url])['status']);
        }
        $this->assertSame(self::URL, DB::table('tenants')->where('id', 2)->value('instagram_url'));
        $this->assertNull(TenantInstagram::validar(self::URL));
    }

    public function test_perfil_mobile_entrega_contatos_somente_das_academias_do_usuario(): void
    {
        DB::table('tenant_usuario_papel')->insert([
            ['tenant_id' => 2, 'usuario_id' => 10], ['tenant_id' => 3, 'usuario_id' => 11],
        ]);
        $repo = app(MobilePerfilRepository::class);
        $this->assertSame(self::URL, $repo->listarTenantsAtivosDoUsuario(10)[0]['instagram_url']);
        $this->assertNull($repo->listarTenantsAtivosDoUsuario(11)[0]['instagram_url']);
        DB::table('tenant_usuario_papel')->where('usuario_id', 10)->update(['ativo' => false]);
        $this->assertSame([], $repo->listarTenantsAtivosDoUsuario(10));
    }
}
