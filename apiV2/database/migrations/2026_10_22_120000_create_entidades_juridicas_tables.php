<?php

use App\Support\PoliticaCnpj;
use App\Support\VinculoCnpj;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CNPJ compartilhado entre tenants: os dados jurídicos ficam em `entidades_juridicas` (um registro por CNPJ)
 * e cada tenant aponta para ela por `tenant_entidade_juridica` (papel gestor ou vinculado).
 *
 * `tenants.cnpj` continua existindo como espelho de compatibilidade (API Slim, cobrança Asaas, busca do
 * super admin) e é mantido só pelo EntidadeJuridicaService. O UNIQUE dela sai: a unicidade agora é de
 * `entidades_juridicas.cnpj`, e o compartilhamento segue a política em `configuracoes_sistema`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuracoes_sistema')) {
            Schema::create('configuracoes_sistema', function (Blueprint $t) {
                $t->string('chave', 100)->primary();
                $t->text('valor')->nullable();
                $t->integer('atualizado_por')->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }
        DB::table('configuracoes_sistema')->insertOrIgnore([
            'chave' => PoliticaCnpj::CHAVE,
            'valor' => PoliticaCnpj::PADRAO,
            'updated_at' => now(),
        ]);

        if (! Schema::hasTable('entidades_juridicas')) {
            Schema::create('entidades_juridicas', function (Blueprint $t) {
                $t->increments('id');
                $t->char('cnpj', 14)->unique('uk_entidades_juridicas_cnpj')->comment('Só dígitos');
                $t->string('razao_social', 255)->nullable();
                $t->string('nome_fantasia', 255)->nullable();
                $t->string('inscricao_estadual', 30)->nullable();
                $t->string('inscricao_municipal', 30)->nullable();
                // Endereço fiscal (o operacional de cada escola continua em tenants)
                $t->string('cep', 10)->nullable();
                $t->string('logradouro', 255)->nullable();
                $t->string('numero', 20)->nullable();
                $t->string('complemento', 100)->nullable();
                $t->string('bairro', 100)->nullable();
                $t->string('cidade', 100)->nullable();
                $t->string('estado', 2)->nullable();
                $t->integer('atualizado_por')->nullable();
                $t->timestamps();

                $t->foreign('atualizado_por')->references('id')->on('usuarios')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('tenant_entidade_juridica')) {
            Schema::create('tenant_entidade_juridica', function (Blueprint $t) {
                $t->increments('id');
                $t->integer('tenant_id');
                $t->unsignedInteger('entidade_juridica_id');
                $t->string('papel', 20)->comment('gestor | vinculado');
                $t->string('status', 20)->default(VinculoCnpj::ATIVO)->comment('ativo | encerrado');
                $t->dateTime('vinculado_em');
                $t->dateTime('encerrado_em')->nullable();
                // Mantidos pelo EntidadeJuridicaRepository (NULL quando não se aplica; NULL não conflita no UNIQUE):
                // = tenant_id enquanto o vínculo está ativo → um CNPJ ativo por tenant
                $t->integer('tenant_ativo')->nullable();
                // = entidade_juridica_id enquanto ativo e gestor → um gestor cadastral ativo por CNPJ
                $t->unsignedInteger('gestor_ativo')->nullable();
                $t->timestamps();

                $t->unique('tenant_ativo', 'uk_tej_tenant_ativo');
                $t->unique('gestor_ativo', 'uk_tej_gestor_ativo');
                $t->index(['entidade_juridica_id', 'status'], 'idx_tej_entidade_status');
                $t->index(['tenant_id', 'status'], 'idx_tej_tenant_status');

                $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $t->foreign('entidade_juridica_id')->references('id')->on('entidades_juridicas')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('cnpj_excecoes')) {
            Schema::create('cnpj_excecoes', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('entidade_juridica_id');
                $t->text('justificativa');
                $t->integer('autorizado_por')->nullable();
                $t->dateTime('autorizado_em');
                $t->integer('revogado_por')->nullable();
                $t->dateTime('revogado_em')->nullable()->comment('NULL = exceção vigente');
                $t->text('motivo_revogacao')->nullable();
                $t->timestamps();

                $t->index(['entidade_juridica_id', 'revogado_em'], 'idx_cnpj_exc_vigente');
                $t->foreign('entidade_juridica_id')->references('id')->on('entidades_juridicas')->cascadeOnDelete();
                $t->foreign('autorizado_por')->references('id')->on('usuarios')->nullOnDelete();
                $t->foreign('revogado_por')->references('id')->on('usuarios')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('entidade_juridica_auditoria')) {
            Schema::create('entidade_juridica_auditoria', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('entidade_juridica_id')->nullable();
                $t->char('cnpj', 14)->nullable();
                $t->integer('tenant_id')->nullable()->comment('Tenant afetado ou de onde partiu a ação');
                $t->integer('usuario_id')->nullable()->comment('NULL = sistema');
                $t->string('acao', 40);
                $t->json('dados_anteriores')->nullable();
                $t->json('dados_novos')->nullable();
                $t->text('justificativa')->nullable();
                $t->string('ip', 45)->nullable();
                $t->timestamp('created_at')->useCurrent();

                $t->index(['entidade_juridica_id', 'created_at'], 'idx_ej_aud_entidade');
                $t->index(['tenant_id', 'created_at'], 'idx_ej_aud_tenant');
                $t->foreign('entidade_juridica_id')->references('id')->on('entidades_juridicas')->nullOnDelete();
            });
        }

        $this->migrarCnpjsExistentes();

        if (Schema::hasIndex('tenants', 'unique_tenant_cnpj')) {
            Schema::table('tenants', fn (Blueprint $t) => $t->dropUnique('unique_tenant_cnpj'));
        }
    }

    /**
     * Cada tenant com CNPJ de 14 dígitos ganha uma entidade (ou entra na já criada para o mesmo CNPJ).
     * O primeiro tenant de cada CNPJ vira gestor. Razão social e endereço fiscal partem do cadastro atual
     * da academia, para o gestor revisar em Minha Conta → Dados do CNPJ.
     */
    private function migrarCnpjsExistentes(): void
    {
        $agora = now();
        $tenants = DB::table('tenants')->whereNotNull('cnpj')->where('cnpj', '<>', '')->orderBy('id')->get();

        foreach ($tenants as $tenant) {
            $cnpj = preg_replace('/\D/', '', (string) $tenant->cnpj);
            if (strlen($cnpj) !== 14 || DB::table('tenant_entidade_juridica')->where('tenant_ativo', $tenant->id)->exists()) {
                continue;
            }

            $entidadeId = DB::table('entidades_juridicas')->where('cnpj', $cnpj)->value('id');
            if (! $entidadeId) {
                $entidadeId = DB::table('entidades_juridicas')->insertGetId([
                    'cnpj' => $cnpj,
                    'razao_social' => $tenant->nome,
                    'cep' => $tenant->cep ?? null,
                    'logradouro' => $tenant->logradouro ?? null,
                    'numero' => $tenant->numero ?? null,
                    'complemento' => $tenant->complemento ?? null,
                    'bairro' => $tenant->bairro ?? null,
                    'cidade' => $tenant->cidade ?? null,
                    'estado' => $tenant->estado ?? null,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }

            $gestor = ! DB::table('tenant_entidade_juridica')->where('gestor_ativo', $entidadeId)->exists();
            DB::table('tenant_entidade_juridica')->insert([
                'tenant_id' => $tenant->id,
                'entidade_juridica_id' => $entidadeId,
                'papel' => $gestor ? VinculoCnpj::GESTOR : VinculoCnpj::VINCULADO,
                'status' => VinculoCnpj::ATIVO,
                'vinculado_em' => $tenant->created_at ?? $agora,
                'tenant_ativo' => $tenant->id,
                'gestor_ativo' => $gestor ? $entidadeId : null,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
            // Normaliza o espelho (havia CNPJ com máscara em cadastros antigos)
            DB::table('tenants')->where('id', $tenant->id)->update(['cnpj' => $cnpj]);
            DB::table('entidade_juridica_auditoria')->insert([
                'entidade_juridica_id' => $entidadeId,
                'cnpj' => $cnpj,
                'tenant_id' => $tenant->id,
                'acao' => 'migracao',
                'dados_novos' => json_encode(['papel' => $gestor ? VinculoCnpj::GESTOR : VinculoCnpj::VINCULADO]),
                'created_at' => $agora,
            ]);
        }
    }

    public function down(): void
    {
        // O UNIQUE só volta se nenhum CNPJ estiver compartilhado; senão o down falharia no meio
        $duplicado = DB::table('tenants')->whereNotNull('cnpj')->where('cnpj', '<>', '')
            ->select('cnpj')->groupBy('cnpj')->havingRaw('COUNT(*) > 1')->exists();
        if (! $duplicado && ! Schema::hasIndex('tenants', 'unique_tenant_cnpj')) {
            Schema::table('tenants', fn (Blueprint $t) => $t->unique('cnpj', 'unique_tenant_cnpj'));
        }

        Schema::dropIfExists('entidade_juridica_auditoria');
        Schema::dropIfExists('cnpj_excecoes');
        Schema::dropIfExists('tenant_entidade_juridica');
        Schema::dropIfExists('entidades_juridicas');
        DB::table('configuracoes_sistema')->where('chave', PoliticaCnpj::CHAVE)->delete();
        if (DB::table('configuracoes_sistema')->count() === 0) {
            Schema::dropIfExists('configuracoes_sistema');
        }
    }
};
