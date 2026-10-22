<?php

namespace Tests\Concerns;

use App\Services\JwtService;
use App\Support\Papel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema mínimo (SQLite) de tenants/usuários/papéis + a migration de CNPJ compartilhado.
 */
trait CnpjCompartilhadoSchema
{
    use RequiresSqlite;

    private int $seq = 0;

    protected function criarSchemaCnpj(): void
    {
        // Nunca no banco do ambiente: o container define DB_CONNECTION=mysql
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        $this->requireSqlite();
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        Schema::create('tenants', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome')->unique('unique_tenant_nome');
            $t->string('slug')->nullable();
            $t->string('email')->nullable();
            $t->string('cnpj', 18)->nullable()->unique('unique_tenant_cnpj');
            $t->string('telefone')->nullable();
            $t->text('whatsapp_links')->nullable();
            foreach (['responsavel_nome', 'responsavel_cpf', 'responsavel_telefone', 'responsavel_email', 'endereco',
                'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado'] as $c) {
                $t->string($c)->nullable();
            }
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('papeis', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
            $t->string('descricao')->nullable();
            $t->integer('nivel')->default(0);
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
            $t->string('email');
            $t->string('email_global')->nullable();
            $t->string('telefone')->nullable();
            $t->string('cpf')->nullable();
            foreach (['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado'] as $c) {
                $t->string($c)->nullable();
            }
            $t->text('foto_base64')->nullable();
            $t->string('foto_caminho')->nullable();
            $t->string('senha_hash')->default('x');
            $t->string('password_reset_token')->nullable();
            $t->dateTime('password_reset_expires_at')->nullable();
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
        });
        Schema::create('formas_pagamento', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
            $t->decimal('percentual_desconto')->default(0);
            $t->boolean('ativo')->default(true);
        });
        Schema::create('tenant_formas_pagamento', function (Blueprint $t) {
            $t->increments('id');
            foreach (['tenant_id', 'forma_pagamento_id', 'ativo', 'aceita_parcelamento', 'parcelas_minimas', 'parcelas_maximas', 'parcelas_sem_juros', 'dias_compensacao'] as $c) {
                $t->integer($c)->default(0);
            }
            foreach (['taxa_percentual', 'taxa_fixa', 'juros_parcelamento', 'valor_minimo'] as $c) {
                $t->decimal($c)->default(0);
            }
        });
        foreach ([1 => 'aluno', 2 => 'professor', 3 => 'admin', 4 => 'super_admin', 5 => 'comercial'] as $id => $nome) {
            DB::table('papeis')->insert(['id' => $id, 'nome' => $nome, 'nivel' => $id * 10]);
        }
        DB::table('tenants')->insert(['id' => 1, 'nome' => 'Sistema', 'slug' => 'sistema', 'email' => 'sistema@teste.com']);
    }

    protected function rodarMigrationCnpj(): void
    {
        (require database_path('migrations/2026_10_22_120000_create_entidades_juridicas_tables.php'))->up();
    }

    protected function tenant(string $nome, ?string $cnpj = null): int
    {
        return (int) DB::table('tenants')->insertGetId(['nome' => $nome, 'slug' => str()->slug($nome), 'email' => str()->slug($nome).'@teste.com', 'cnpj' => $cnpj]);
    }

    protected function usuario(?int $tenantId, int $papelId, string $nome = 'Usuário', string $senha = 'senha-atual'): int
    {
        $n = ++$this->seq;
        $id = (int) DB::table('usuarios')->insertGetId([
            'nome' => $nome, 'email' => "u{$n}@teste.com", 'email_global' => "u{$n}@teste.com",
            'senha_hash' => password_hash($senha, PASSWORD_BCRYPT),
        ]);
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => $tenantId ?? 1, 'usuario_id' => $id, 'papel_id' => $papelId, 'ativo' => 1]);

        return $id;
    }

    /** @return array<string, string> */
    protected function auth(int $usuarioId, ?int $tenantId): array
    {
        $payload = ['user_id' => $usuarioId, 'email' => 'x@teste.com', 'tenant_id' => $tenantId];
        if ($tenantId === null) {
            $payload['is_super_admin'] = true;
        }

        return ['Authorization' => 'Bearer '.app(JwtService::class)->encode($payload)];
    }

    /** id de um motivo de acesso de suporte (seed de motivos_acesso_suporte) */
    protected function motivoAcesso(string $nome = 'Ajuste de plano ou cobrança a pedido da academia'): ?int
    {
        $id = DB::table('motivos_acesso_suporte')->where('nome', $nome)->value('id');

        return $id === null ? null : (int) $id;
    }

    protected function superAdmin(): int
    {
        return $this->usuario(null, Papel::SUPER_ADMIN, 'Super');
    }
}
