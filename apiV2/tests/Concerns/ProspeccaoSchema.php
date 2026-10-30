<?php

namespace Tests\Concerns;

use App\Services\JwtService;
use App\Support\AcademyDateTime;
use App\Support\Papel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema mínimo (SQLite) das tabelas existentes que o módulo Prospecção lê, mais as migrations do módulo.
 */
trait ProspeccaoSchema
{
    use RequiresSqlite;

    private int $seq = 0;

    protected function criarSchemaProspeccao(): void
    {
        // Nunca no banco do ambiente: o container define DB_CONNECTION=mysql
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        $this->requireSqlite();
        config(['appcheckin.jwt_secret' => 'test-secret-key-with-enough-length-for-hs256-algorithm']);

        Schema::create('tenants', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nome');
            $t->string('slug')->nullable();
            $t->string('email')->nullable();
            $t->string('telefone')->nullable();
            $t->text('whatsapp_links')->nullable();
            $t->string('instagram_url', 2048)->nullable();
            $t->boolean('ativo')->default(true);
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
            $t->string('cep')->nullable();
            $t->string('logradouro')->nullable();
            $t->string('numero')->nullable();
            $t->string('complemento')->nullable();
            $t->string('bairro')->nullable();
            $t->string('cidade')->nullable();
            $t->string('estado')->nullable();
            $t->text('foto_base64')->nullable();
            $t->string('senha_hash')->default('x');
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });
        Schema::create('alunos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('usuario_id');
            $t->string('nome');
            $t->string('telefone')->nullable();
            $t->string('whatsapp')->nullable();
            $t->string('cpf')->nullable();
            $t->string('cep')->nullable();
            $t->date('data_nascimento')->nullable();
            $t->string('foto_caminho')->nullable();
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
        Schema::create('modalidades', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->string('nome');
            $t->string('cor')->nullable();
        });
        Schema::create('planos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('modalidade_id');
            $t->string('nome');
            $t->decimal('valor', 10, 2)->default(100);
            $t->integer('duracao_dias')->default(30);
            $t->integer('checkins_semanais')->default(3);
        });
        Schema::create('plano_ciclos', function (Blueprint $t) {
            $t->increments('id');
            $t->decimal('valor_mensal_equivalente', 10, 2)->nullable();
        });
        Schema::create('status_matricula', function (Blueprint $t) {
            $t->increments('id');
            $t->string('codigo');
            $t->string('nome');
        });
        Schema::create('matriculas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('aluno_id')->nullable();
            $t->integer('plano_id');
            $t->integer('plano_ciclo_id')->nullable();
            $t->string('tipo_cobranca')->default('recorrente');
            $t->date('data_matricula');
            $t->date('data_inicio');
            $t->date('data_vencimento');
            $t->date('proxima_data_vencimento')->nullable();
            $t->decimal('valor', 10, 2)->default(100);
            $t->integer('status_id');
            $t->date('data_cancelamento')->nullable();
            $t->text('motivo_cancelamento')->nullable();
            $t->string('motivo_cancelamento_codigo', 30)->nullable();
            $t->timestamps();
        });
        Schema::create('checkins', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('aluno_id');
            $t->dateTime('data_checkin');
        });
        Schema::create('pagamentos_plano', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('matricula_id');
            $t->decimal('valor', 10, 2);
            $t->date('data_vencimento');
            $t->date('data_pagamento')->nullable();
            $t->integer('status_pagamento_id');
        });
        Schema::create('tipos_parametro', function (Blueprint $t) {
            $t->increments('id');
            $t->string('codigo');
            $t->string('nome');
            $t->text('descricao')->nullable();
            $t->string('icone')->nullable();
            $t->integer('ordem')->default(0);
            $t->boolean('ativo')->default(true);
        });
        Schema::create('parametros', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tipo_parametro_id');
            $t->string('codigo');
            $t->string('nome');
            $t->text('descricao')->nullable();
            $t->string('tipo_valor');
            $t->text('valor_padrao')->nullable();
            $t->integer('ordem')->default(0);
            $t->boolean('visivel_admin')->default(true);
            $t->boolean('visivel_tenant')->default(true);
            $t->boolean('ativo')->default(true);
        });
        Schema::create('parametros_tenant', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('tenant_id');
            $t->integer('parametro_id');
            $t->text('valor')->nullable();
            $t->boolean('ativo')->default(true);
            $t->integer('atualizado_por')->nullable();
            $t->timestamps();
        });

        foreach (['ativa', 'vencida', 'cancelada', 'finalizada', 'pendente', 'bloqueado'] as $i => $codigo) {
            DB::table('status_matricula')->insert(['id' => $i + 1, 'codigo' => $codigo, 'nome' => ucfirst($codigo)]);
        }
        foreach ([1 => 'aluno', 2 => 'professor', 3 => 'admin', 4 => 'super_admin'] as $id => $nome) {
            DB::table('papeis')->insert(['id' => $id, 'nome' => $nome, 'nivel' => $id * 10]);
        }
        foreach (['2026_10_21_120000_add_papel_comercial', '2026_10_21_120100_create_prospeccao_tables', '2026_10_21_120200_add_parametro_score_prospeccao'] as $m) {
            (require database_path('migrations/'.$m.'.php'))->up();
        }
    }

    protected function tenant(string $nome = 'Academia'): int
    {
        return (int) DB::table('tenants')->insertGetId(['nome' => $nome]);
    }

    protected function usuario(int $tenantId, int $papelId, string $nome = 'Usuário'): int
    {
        $id = (int) DB::table('usuarios')->insertGetId(['nome' => $nome, 'email' => 'u'.(++$this->seq).'@teste.com']);
        DB::table('tenant_usuario_papel')->insert(['tenant_id' => $tenantId, 'usuario_id' => $id, 'papel_id' => $papelId, 'ativo' => 1]);

        return $id;
    }

    protected function aluno(int $tenantId, string $nome = 'MARIA OLIVEIRA', string $telefone = '82999990000'): int
    {
        $usuarioId = $this->usuario($tenantId, Papel::ALUNO, $nome);

        return (int) DB::table('alunos')->insertGetId(['usuario_id' => $usuarioId, 'nome' => $nome, 'telefone' => $telefone]);
    }

    protected function plano(int $tenantId, int $duracao = 30, string $nome = 'Mensal'): int
    {
        $mod = (int) DB::table('modalidades')->insertGetId(['tenant_id' => $tenantId, 'nome' => 'Natação']);

        return (int) DB::table('planos')->insertGetId(['tenant_id' => $tenantId, 'modalidade_id' => $mod, 'nome' => $nome, 'duracao_dias' => $duracao]);
    }

    /** @param array<string, mixed> $extra */
    protected function matricula(int $tenantId, int $alunoId, int $planoId, string $status, ?string $cancelamento = null, array $extra = []): int
    {
        $statusId = (int) DB::table('status_matricula')->where('codigo', $status)->value('id');
        $inicio = $extra['data_inicio'] ?? $this->dia(-200);

        return (int) DB::table('matriculas')->insertGetId($extra + [
            'tenant_id' => $tenantId, 'aluno_id' => $alunoId, 'plano_id' => $planoId,
            'data_matricula' => $inicio, 'data_inicio' => $inicio, 'data_vencimento' => $inicio,
            'status_id' => $statusId, 'data_cancelamento' => $cancelamento,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function dia(int $offset): string
    {
        return AcademyDateTime::todayStart()->modify(($offset >= 0 ? '+' : '').$offset.' days')->format('Y-m-d');
    }

    protected function token(int $usuarioId, int $tenantId): string
    {
        return app(JwtService::class)->encode(['user_id' => $usuarioId, 'email' => 'x@teste.com', 'tenant_id' => $tenantId]);
    }

    /** @return array<string, string> */
    protected function auth(int $usuarioId, int $tenantId): array
    {
        return ['Authorization' => 'Bearer '.$this->token($usuarioId, $tenantId)];
    }
}
